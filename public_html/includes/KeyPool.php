<?php

declare(strict_types=1);

final class KeyPool
{
    public const POOL_MARKER = '__pool__';

    public static function ensureSchema(PDO $pdo): void
    {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'pgsql') {
            return;
        }

        self::safeExec($pdo, "CREATE TABLE IF NOT EXISTS api_key_inventory (
            id BIGSERIAL PRIMARY KEY,
            api_listing_id BIGINT NOT NULL,
            key_link TEXT NOT NULL,
            key_hash CHAR(64),
            status VARCHAR(20) NOT NULL DEFAULT 'AVAILABLE',
            assigned_user_id BIGINT NULL,
            api_purchase_id BIGINT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            assigned_at TIMESTAMP NULL,
            CONSTRAINT fk_api_key_inventory_listing FOREIGN KEY (api_listing_id) REFERENCES api_listings(id) ON DELETE CASCADE,
            CONSTRAINT fk_api_key_inventory_user FOREIGN KEY (assigned_user_id) REFERENCES app_users(id)
        )");
        self::safeExec($pdo, 'CREATE INDEX IF NOT EXISTS idx_api_key_inventory_listing_status ON api_key_inventory (api_listing_id, status)');
        self::safeExec($pdo, 'ALTER TABLE api_key_inventory ADD COLUMN IF NOT EXISTS key_hash CHAR(64)');
        self::safeExec($pdo, "UPDATE api_key_inventory SET key_hash = encode(digest(key_link, 'sha256'), 'hex') WHERE key_hash IS NULL OR key_hash = ''");
        self::safeExec($pdo, 'ALTER TABLE api_key_inventory DROP CONSTRAINT IF EXISTS uk_api_key_inventory_link');
        self::safeExec($pdo, 'DROP INDEX IF EXISTS uk_api_key_inventory_listing_key_hash');
        self::safeExec($pdo, 'CREATE UNIQUE INDEX IF NOT EXISTS uk_api_key_inventory_listing_key_hash ON api_key_inventory (api_listing_id, key_hash)');
        self::safeExec($pdo, 'ALTER TABLE api_purchases ALTER COLUMN purchased_key_snapshot TYPE TEXT');
        self::safeExec($pdo, 'ALTER TABLE api_purchases ALTER COLUMN access_link_snapshot TYPE TEXT');
        self::safeExec($pdo, 'ALTER TABLE api_key_inventory ALTER COLUMN key_link TYPE TEXT');
        self::safeExec($pdo, 'ALTER TABLE api_listings ADD COLUMN IF NOT EXISTS expiration_months INTEGER NOT NULL DEFAULT 1');
        self::safeExec($pdo, 'ALTER TABLE api_purchases ADD COLUMN IF NOT EXISTS expires_at TIMESTAMP NULL');
        self::safeExec($pdo, 'ALTER TABLE api_purchases DROP CONSTRAINT IF EXISTS uk_api_purchases_user_api');
        self::safeExec($pdo, "UPDATE api_purchases p
            SET expires_at = p.created_at + (COALESCE(a.expiration_months, 1) || ' months')::interval
            FROM api_listings a
            WHERE p.api_listing_id = a.id AND p.expires_at IS NULL");
        self::safeExec($pdo, "DELETE FROM api_key_inventory WHERE status = 'ASSIGNED'");

        try {
            self::migrateLegacyListingKeys($pdo);
        } catch (Throwable $e) {
            error_log('KeyPool legacy migration failed: ' . $e->getMessage());
        }
    }

    private static function safeExec(PDO $pdo, string $sql): void
    {
        try {
            $pdo->exec($sql);
        } catch (Throwable $e) {
            error_log('KeyPool migration skipped: ' . $e->getMessage());
        }
    }

    private static function migrateLegacyListingKeys(PDO $pdo): void
    {
        $marker = self::POOL_MARKER;
        $sql = "SELECT a.id, a.api_key_value
            FROM api_listings a
            WHERE a.api_key_value IS NOT NULL
              AND a.api_key_value <> ''
              AND a.api_key_value <> '" . $marker . "'";
        $stmt = $pdo->query($sql);

        $check = $pdo->prepare('SELECT id FROM api_key_inventory WHERE api_listing_id = ? LIMIT 1');
        $insert = $pdo->prepare("INSERT INTO api_key_inventory (api_listing_id, key_link, key_hash, status) VALUES (?, ?, ?, 'AVAILABLE') ON CONFLICT (api_listing_id, key_hash) DO NOTHING");

        foreach ($stmt->fetchAll() as $row) {
            $listingId = (int) $row['id'];
            $check->execute([$listingId]);
            if ($check->fetch()) {
                continue;
            }

            $purchase = $pdo->prepare('SELECT user_id, id FROM api_purchases WHERE api_listing_id = ? ORDER BY id ASC LIMIT 1');
            $purchase->execute([$listingId]);
            if ($purchase->fetch()) {
                $pdo->prepare('UPDATE api_listings SET api_key_value = ? WHERE id = ?')->execute([$marker, $listingId]);
                continue;
            }

            $snippet = (string) $row['api_key_value'];
            $insert->execute([$listingId, $snippet, self::snippetHash($snippet)]);
            $pdo->prepare('UPDATE api_listings SET api_key_value = ? WHERE id = ?')->execute([$marker, $listingId]);
        }
    }

    public static function parseBulkSnippets(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            throw new InvalidArgumentException('Add at least one code snippet.');
        }

        $delimiter = "\n---\n";
        $chunks = str_contains($raw, $delimiter)
            ? explode($delimiter, $raw)
            : preg_split('/\r\n|\r|\n/', $raw);

        $snippets = [];
        foreach ($chunks as $chunk) {
            $snippet = trim((string) $chunk);
            if ($snippet === '' || str_starts_with($snippet, '#')) {
                continue;
            }
            $snippets[] = $snippet;
        }

        $unique = array_values(array_unique($snippets));
        if ($unique === []) {
            throw new InvalidArgumentException('Add at least one code snippet.');
        }

        return $unique;
    }

    public static function parseBulkLinks(string $raw): array
    {
        return self::parseBulkSnippets($raw);
    }

    public static function addKeys(PDO $pdo, int $listingId, array $links): int
    {
        self::ensureSchema($pdo);
        $insert = $pdo->prepare(
            "INSERT INTO api_key_inventory (api_listing_id, key_link, key_hash, status)
             VALUES (?, ?, ?, 'AVAILABLE')
             ON CONFLICT (api_listing_id, key_hash) DO NOTHING"
        );
        $added = 0;
        foreach ($links as $link) {
            $insert->execute([$listingId, $link, self::snippetHash((string) $link)]);
            $added += $insert->rowCount();
        }
        return $added;
    }

    private static function snippetHash(string $snippet): string
    {
        return hash('sha256', $snippet);
    }

    public static function countsForListing(PDO $pdo, int $listingId): array
    {
        self::ensureSchema($pdo);
        $stmt = $pdo->prepare('SELECT
            (SELECT COUNT(*)::int FROM api_key_inventory WHERE api_listing_id = ?) AS available_keys,
            (SELECT COUNT(*)::int FROM api_purchases WHERE api_listing_id = ?) AS sold_keys');
        $stmt->execute([$listingId, $listingId]);
        $row = $stmt->fetch() ?: ['available_keys' => 0, 'sold_keys' => 0];
        $available = (int) $row['available_keys'];
        $sold = (int) $row['sold_keys'];
        return [
            'total' => $available + $sold,
            'available' => $available,
            'assigned' => $sold,
            'sold' => $sold,
        ];
    }

    public static function claimKeyForPurchase(PDO $pdo, int $listingId): ?array
    {
        self::ensureSchema($pdo);
        $stmt = $pdo->prepare('SELECT id, key_link FROM api_key_inventory WHERE api_listing_id = ? ORDER BY id ASC LIMIT 1 FOR UPDATE SKIP LOCKED');
        $stmt->execute([$listingId]);
        $key = $stmt->fetch();
        if (!$key) {
            return null;
        }
        $pdo->prepare('DELETE FROM api_key_inventory WHERE id = ?')->execute([(int) $key['id']]);
        return $key;
    }

    public static function deleteByListing(PDO $pdo, int $listingId): void
    {
        self::ensureSchema($pdo);
        $pdo->prepare('DELETE FROM api_key_inventory WHERE api_listing_id = ?')->execute([$listingId]);
    }
}

if (!class_exists('ApiKeyPoolService', false)) {
    class_alias(KeyPool::class, 'ApiKeyPoolService');
}
