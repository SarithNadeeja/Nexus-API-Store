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

        if ($pdo->inTransaction()) {
            return;
        }

        self::safeExec($pdo, 'ALTER TABLE api_listings ADD COLUMN IF NOT EXISTS code_snippet TEXT');
        self::safeExec($pdo, 'ALTER TABLE api_listings ALTER COLUMN api_key_value TYPE TEXT');
        self::safeExec($pdo, 'ALTER TABLE api_listings ADD COLUMN IF NOT EXISTS expiration_months INTEGER NOT NULL DEFAULT 1');
        self::safeExec($pdo, 'ALTER TABLE api_purchases ALTER COLUMN purchased_key_snapshot TYPE TEXT');
        self::safeExec($pdo, 'ALTER TABLE api_purchases ALTER COLUMN access_link_snapshot TYPE TEXT');
        self::safeExec($pdo, 'ALTER TABLE api_purchases ADD COLUMN IF NOT EXISTS expires_at TIMESTAMP NULL');
        self::safeExec($pdo, 'ALTER TABLE api_purchases DROP CONSTRAINT IF EXISTS uk_api_purchases_user_api');

        self::migrateLegacyCodes($pdo);
    }

    private static function safeExec(PDO $pdo, string $sql): void
    {
        try {
            $pdo->exec($sql);
        } catch (Throwable $e) {
            error_log('KeyPool migration skipped: ' . $e->getMessage());
        }
    }

    private static function migrateLegacyCodes(PDO $pdo): void
    {
        try {
            $pdo->exec(
                "UPDATE api_listings
                 SET code_snippet = api_key_value
                 WHERE (code_snippet IS NULL OR BTRIM(code_snippet) = '')
                   AND api_key_value IS NOT NULL
                   AND BTRIM(api_key_value) <> ''
                   AND api_key_value <> '" . self::POOL_MARKER . "'"
            );

            $stmt = $pdo->query(
                "SELECT DISTINCT a.id
                 FROM api_listings a
                 JOIN api_key_inventory k ON k.api_listing_id = a.id
                 WHERE a.code_snippet IS NULL OR BTRIM(a.code_snippet) = ''"
            );

            foreach ($stmt->fetchAll() as $row) {
                $listingId = (int) $row['id'];
                $keyStmt = $pdo->prepare(
                    'SELECT key_link FROM api_key_inventory WHERE api_listing_id = ? ORDER BY id ASC LIMIT 1'
                );
                $keyStmt->execute([$listingId]);
                $snippet = trim((string) $keyStmt->fetchColumn());
                if ($snippet !== '') {
                    self::saveListingCode($pdo, $listingId, $snippet);
                }
            }
        } catch (Throwable $e) {
            error_log('KeyPool legacy code migration failed: ' . $e->getMessage());
        }
    }

    public static function normalizeCode(string $raw): string
    {
        return rtrim($raw, "\r\n");
    }

    public static function getCode(array $listing): string
    {
        $snippet = trim((string) ($listing['code_snippet'] ?? ''));
        if ($snippet !== '' && $snippet !== self::POOL_MARKER) {
            return $snippet;
        }

        $legacy = trim((string) ($listing['api_key_value'] ?? ''));
        if ($legacy !== '' && $legacy !== self::POOL_MARKER) {
            return $legacy;
        }

        return '';
    }

    public static function hasCode(array $listing): bool
    {
        return self::getCode($listing) !== '';
    }

    public static function saveListingCode(PDO $pdo, int $listingId, string $code): void
    {
        $code = self::normalizeCode($code);
        $pdo->prepare(
            'UPDATE api_listings SET code_snippet = ?, api_key_value = ?, endpoint_url = ?, access_link = ? WHERE id = ?'
        )->execute([$code, $code, '', '', $listingId]);
    }

    public static function statsForListing(PDO $pdo, int $listingId): array
    {
        $listingStmt = $pdo->prepare('SELECT code_snippet, api_key_value, status FROM api_listings WHERE id = ?');
        $listingStmt->execute([$listingId]);
        $listing = $listingStmt->fetch() ?: [];

        $soldStmt = $pdo->prepare('SELECT COUNT(*)::int FROM api_purchases WHERE api_listing_id = ?');
        $soldStmt->execute([$listingId]);
        $sold = (int) $soldStmt->fetchColumn();

        $hasCode = self::hasCode($listing);

        return [
            'hasCode' => $hasCode,
            'available' => $hasCode ? 1 : 0,
            'sold' => $sold,
            'total' => $hasCode ? 1 : 0,
            'assigned' => $sold,
        ];
    }

    /** @deprecated Use statsForListing() */
    public static function countsForListing(PDO $pdo, int $listingId): array
    {
        return self::statsForListing($pdo, $listingId);
    }
}

if (!class_exists('ApiKeyPoolService', false)) {
    class_alias(KeyPool::class, 'ApiKeyPoolService');
}
