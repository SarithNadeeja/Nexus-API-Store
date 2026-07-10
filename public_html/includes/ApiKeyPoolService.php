<?php

declare(strict_types=1);

final class ApiKeyPoolService
{
    public const POOL_MARKER = '__pool__';

    public static function ensureSchema(PDO $pdo): void
    {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'pgsql') {
            return;
        }

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS api_key_inventory (
                id BIGSERIAL PRIMARY KEY,
                api_listing_id BIGINT NOT NULL,
                key_link VARCHAR(500) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT \'AVAILABLE\',
                assigned_user_id BIGINT NULL,
                api_purchase_id BIGINT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                assigned_at TIMESTAMP NULL,
                CONSTRAINT fk_api_key_inventory_listing FOREIGN KEY (api_listing_id) REFERENCES api_listings(id) ON DELETE CASCADE,
                CONSTRAINT fk_api_key_inventory_user FOREIGN KEY (assigned_user_id) REFERENCES app_users(id),
                CONSTRAINT uk_api_key_inventory_link UNIQUE (key_link)
            )'
        );

        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_api_key_inventory_listing_status ON api_key_inventory (api_listing_id, status)');

        try {
            $pdo->exec('ALTER TABLE api_purchases ALTER COLUMN purchased_key_snapshot TYPE VARCHAR(500)');
        } catch (Throwable $e) {
            // Column may already be wide enough.
        }

        self::migrateLegacyListingKeys($pdo);
    }

    private static function migrateLegacyListingKeys(PDO $pdo): void
    {
        $stmt = $pdo->query(
            "SELECT a.id, a.api_key_value
             FROM api_listings a
             WHERE a.api_key_value IS NOT NULL
               AND a.api_key_value <> ''
               AND a.api_key_value <> '" . self::POOL_MARKER . "'"
        );

        $check = $pdo->prepare('SELECT id FROM api_key_inventory WHERE api_listing_id = ? LIMIT 1');
        $insert = $pdo->prepare(
            'INSERT INTO api_key_inventory (api_listing_id, key_link, status, assigned_user_id, assigned_at)
             VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)'
        );

        foreach ($stmt->fetchAll() as $row) {
            $listingId = (int) $row['id'];
            $check->execute([$listingId]);
            if ($check->fetch()) {
                continue;
            }

            $purchase = $pdo->prepare(
                'SELECT user_id, id FROM api_purchases WHERE api_listing_id = ? ORDER BY id ASC LIMIT 1'
            );
            $purchase->execute([$listingId]);
            $purchaseRow = $purchase->fetch();

            if ($purchaseRow) {
                $insert->execute([
                    $listingId,
                    $row['api_key_value'],
                    'ASSIGNED',
                    (int) $purchaseRow['user_id'],
                ]);
                $pdo->prepare('UPDATE api_key_inventory SET api_purchase_id = ? WHERE api_listing_id = ? AND key_link = ?')
                    ->execute([(int) $purchaseRow['id'], $listingId, $row['api_key_value']]);
            } else {
                $insert->execute([$listingId, $row['api_key_value'], 'AVAILABLE', null]);
            }

            $pdo->prepare('UPDATE api_listings SET api_key_value = ? WHERE id = ?')
                ->execute([self::POOL_MARKER, $listingId]);
        }
    }

    public static function parseBulkLinks(string $raw): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];
        $links = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (!preg_match('#^https?://#i', $line)) {
                throw new InvalidArgumentException('Each API key link must start with http:// or https://');
            }
            if (strlen($line) > 500) {
                throw new InvalidArgumentException('API key links must be 500 characters or fewer.');
            }
            $links[] = $line;
        }

        $unique = array_values(array_unique($links));
        if (!$unique) {
            throw new InvalidArgumentException('Add at least one API key link (one per line).');
        }

        return $unique;
    }

    public static function addKeys(PDO $pdo, int $listingId, array $links): int
    {
        self::ensureSchema($pdo);

        $insert = $pdo->prepare(
            'INSERT INTO api_key_inventory (api_listing_id, key_link, status)
             VALUES (?, ?, \'AVAILABLE\')
             ON CONFLICT (key_link) DO NOTHING'
        );

        $added = 0;
        foreach ($links as $link) {
            $insert->execute([$listingId, $link]);
            $added += $insert->rowCount();
        }

        return $added;
    }

    public static function countsForListing(PDO $pdo, int $listingId): array
    {
        self::ensureSchema($pdo);
        $stmt = $pdo->prepare(
            "SELECT
                COUNT(*)::int AS total_keys,
                COUNT(*) FILTER (WHERE status = 'AVAILABLE')::int AS available_keys,
                COUNT(*) FILTER (WHERE status = 'ASSIGNED')::int AS assigned_keys
             FROM api_key_inventory
             WHERE api_listing_id = ?"
        );
        $stmt->execute([$listingId]);
        $row = $stmt->fetch() ?: ['total_keys' => 0, 'available_keys' => 0, 'assigned_keys' => 0];

        return [
            'total' => (int) $row['total_keys'],
            'available' => (int) $row['available_keys'],
            'assigned' => (int) $row['assigned_keys'],
        ];
    }

    public static function assignKeyForPurchase(PDO $pdo, int $listingId, int $userId): ?array
    {
        self::ensureSchema($pdo);

        $stmt = $pdo->prepare(
            "SELECT id, key_link
             FROM api_key_inventory
             WHERE api_listing_id = ? AND status = 'AVAILABLE'
             ORDER BY id ASC
             LIMIT 1
             FOR UPDATE SKIP LOCKED"
        );
        $stmt->execute([$listingId]);
        $key = $stmt->fetch();
        if (!$key) {
            return null;
        }

        $pdo->prepare(
            "UPDATE api_key_inventory
             SET status = 'ASSIGNED', assigned_user_id = ?, assigned_at = CURRENT_TIMESTAMP
             WHERE id = ?"
        )->execute([$userId, (int) $key['id']]);

        return $key;
    }

    public static function attachPurchaseToKey(PDO $pdo, int $inventoryId, int $purchaseId): void
    {
        $pdo->prepare('UPDATE api_key_inventory SET api_purchase_id = ? WHERE id = ?')
            ->execute([$purchaseId, $inventoryId]);
    }

    public static function deleteByListing(PDO $pdo, int $listingId): void
    {
        self::ensureSchema($pdo);
        $pdo->prepare('DELETE FROM api_key_inventory WHERE api_listing_id = ?')->execute([$listingId]);
    }
}
