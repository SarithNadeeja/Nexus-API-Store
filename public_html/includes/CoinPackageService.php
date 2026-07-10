<?php

declare(strict_types=1);

final class CoinPackageService
{
    private const DEFAULT_PACKAGES = [
        ['name' => 'Starter Pack', 'coin_amount' => 100, 'price_usd' => 1.00, 'tone' => 'package-blue', 'sort_order' => 1],
        ['name' => 'Growth Pack', 'coin_amount' => 250, 'price_usd' => 2.00, 'tone' => 'package-purple', 'sort_order' => 2],
        ['name' => 'Pro Pack', 'coin_amount' => 500, 'price_usd' => 4.00, 'tone' => 'package-orange', 'sort_order' => 3],
        ['name' => 'Enterprise Pack', 'coin_amount' => 1000, 'price_usd' => 7.00, 'tone' => 'package-green', 'sort_order' => 4],
    ];

    public static function ensureSchema(PDO $pdo): void
    {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'pgsql') {
            return;
        }

        try {
            self::ensurePackagesSchema($pdo);
            self::ensureSettingsSchemaInternal($pdo);
        } catch (Throwable $e) {
            error_log('CoinPackageService::ensureSchema failed: ' . $e->getMessage());
        }
    }

    private static function ensurePackagesSchema(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS coin_packages (
                id BIGSERIAL PRIMARY KEY,
                name VARCHAR(120) NOT NULL,
                coin_amount INTEGER NOT NULL,
                price_usd NUMERIC(10,2) NOT NULL,
                tone VARCHAR(40) NOT NULL DEFAULT \'package-blue\',
                sort_order INTEGER NOT NULL DEFAULT 0,
                is_active BOOLEAN NOT NULL DEFAULT TRUE,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            )'
        );

        $count = (int) $pdo->query('SELECT COUNT(*) FROM coin_packages')->fetchColumn();
        if ($count === 0) {
            $insert = $pdo->prepare(
                'INSERT INTO coin_packages (name, coin_amount, price_usd, tone, sort_order, is_active)
                 VALUES (?, ?, ?, ?, ?, TRUE)'
            );
            foreach (self::DEFAULT_PACKAGES as $package) {
                $insert->execute([
                    $package['name'],
                    $package['coin_amount'],
                    $package['price_usd'],
                    $package['tone'],
                    $package['sort_order'],
                ]);
            }
        }
    }

    private static function ensureSettingsSchemaInternal(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS coin_settings (
                id INTEGER PRIMARY KEY DEFAULT 1 CHECK (id = 1),
                custom_recharge_enabled BOOLEAN NOT NULL DEFAULT TRUE,
                custom_coin_price_usd NUMERIC(10,4) NOT NULL DEFAULT 0.01,
                custom_coin_min INTEGER NOT NULL DEFAULT 50,
                custom_coin_max INTEGER NOT NULL DEFAULT 100000,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            )'
        );

        self::ensureColumn($pdo, 'coin_settings', 'whatsapp_number', "VARCHAR(20) NOT NULL DEFAULT ''");

        $settingsCount = (int) $pdo->query('SELECT COUNT(*) FROM coin_settings')->fetchColumn();
        if ($settingsCount === 0) {
            $pdo->exec(
                'INSERT INTO coin_settings (id, custom_recharge_enabled, custom_coin_price_usd, custom_coin_min, custom_coin_max)
                 VALUES (1, TRUE, 0.01, 50, 100000)'
            );
        }
    }

    private static function ensureColumn(PDO $pdo, string $table, string $column, string $definition): void
    {
        try {
            $pdo->exec("ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS {$column} {$definition}");
            return;
        } catch (PDOException) {
            // Fall back for hosts that do not support IF NOT EXISTS on ADD COLUMN.
        }

        $stmt = $pdo->prepare(
            'SELECT 1 FROM information_schema.columns
             WHERE table_schema = current_schema() AND table_name = ? AND column_name = ?'
        );
        $stmt->execute([$table, $column]);
        if (!$stmt->fetchColumn()) {
            $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
        }
    }

    public static function ensureSettingsSchema(PDO $pdo): void
    {
        self::ensureSchema($pdo);
    }

    public static function getCustomSettings(PDO $pdo): array
    {
        self::ensureSettingsSchema($pdo);
        $row = $pdo->query('SELECT * FROM coin_settings WHERE id = 1')->fetch();
        if (!$row) {
            return [
                'custom_recharge_enabled' => true,
                'custom_coin_price_usd' => 0.01,
                'custom_coin_min' => 50,
                'custom_coin_max' => 100000,
                'whatsapp_number' => '',
            ];
        }

        return $row;
    }

    public static function saveCustomSettings(PDO $pdo, array $data): void
    {
        self::ensureSettingsSchema($pdo);

        $enabled = isset($data['custom_recharge_enabled']) && (string) $data['custom_recharge_enabled'] !== '' && (string) $data['custom_recharge_enabled'] !== '0';
        $pricePerCoin = round((float) ($data['custom_coin_price_usd'] ?? 0), 4);
        $minCoins = (int) ($data['custom_coin_min'] ?? 0);
        $maxCoins = (int) ($data['custom_coin_max'] ?? 0);

        if ($pricePerCoin <= 0 || $pricePerCoin > 1000) {
            throw new InvalidArgumentException('Custom price per coin must be greater than zero.');
        }
        if ($minCoins < 1) {
            throw new InvalidArgumentException('Minimum custom coins must be at least 1.');
        }
        if ($maxCoins < $minCoins) {
            throw new InvalidArgumentException('Maximum custom coins must be greater than minimum.');
        }

        $pdo->prepare(
            'INSERT INTO coin_settings (id, custom_recharge_enabled, custom_coin_price_usd, custom_coin_min, custom_coin_max)
             VALUES (1, ?, ?, ?, ?)
             ON CONFLICT (id) DO UPDATE SET
                custom_recharge_enabled = EXCLUDED.custom_recharge_enabled,
                custom_coin_price_usd = EXCLUDED.custom_coin_price_usd,
                custom_coin_min = EXCLUDED.custom_coin_min,
                custom_coin_max = EXCLUDED.custom_coin_max,
                updated_at = CURRENT_TIMESTAMP'
        )->execute([$enabled, $pricePerCoin, $minCoins, $maxCoins]);
    }

    public static function getContactSettings(PDO $pdo): array
    {
        $row = self::getCustomSettings($pdo);

        return [
            'whatsappNumber' => (string) ($row['whatsapp_number'] ?? ''),
        ];
    }

    public static function saveContactSettings(PDO $pdo, array $data): void
    {
        self::ensureSettingsSchema($pdo);

        $whatsappNumber = self::normalizeWhatsAppNumber((string) ($data['whatsapp_number'] ?? ''));
        if ($whatsappNumber === '') {
            throw new InvalidArgumentException('WhatsApp number is required.');
        }
        if (strlen($whatsappNumber) < 8 || strlen($whatsappNumber) > 15) {
            throw new InvalidArgumentException('Enter a valid WhatsApp number with country code.');
        }

        $existing = $pdo->query('SELECT id FROM coin_settings WHERE id = 1')->fetch();
        if ($existing) {
            $pdo->prepare(
                'UPDATE coin_settings
                 SET whatsapp_number = ?, updated_at = CURRENT_TIMESTAMP
                 WHERE id = 1'
            )->execute([$whatsappNumber]);

            return;
        }

        $pdo->prepare(
            'INSERT INTO coin_settings (id, custom_recharge_enabled, custom_coin_price_usd, custom_coin_min, custom_coin_max, whatsapp_number)
             VALUES (1, TRUE, 0.01, 50, 100000, ?)'
        )->execute([$whatsappNumber]);
    }

    public static function normalizeWhatsAppNumber(string $number): string
    {
        return preg_replace('/\D+/', '', $number) ?? '';
    }

    public static function formatCustomSettings(array $row): array
    {
        return [
            'enabled' => db_bool($row['custom_recharge_enabled'] ?? false),
            'pricePerCoin' => (float) ($row['custom_coin_price_usd'] ?? 0.01),
            'minCoins' => (int) ($row['custom_coin_min'] ?? 50),
            'maxCoins' => (int) ($row['custom_coin_max'] ?? 100000),
        ];
    }

    public static function calculateCustomPrice(array $settings, int $coins): float
    {
        return round($coins * (float) $settings['custom_coin_price_usd'], 2);
    }

    public static function getPublicCatalog(PDO $pdo): array
    {
        $settings = self::getCustomSettings($pdo);

        return [
            'packages' => self::listActive($pdo),
            'customRecharge' => self::formatCustomSettings($settings),
            'whatsappNumber' => self::normalizeWhatsAppNumber((string) ($settings['whatsapp_number'] ?? '')),
        ];
    }

    public static function listActive(PDO $pdo): array
    {
        self::ensureSchema($pdo);
        $stmt = $pdo->query(
            'SELECT * FROM coin_packages WHERE is_active = TRUE ORDER BY sort_order ASC, coin_amount ASC'
        );

        return array_map([self::class, 'formatPublic'], $stmt->fetchAll());
    }

    public static function listAll(PDO $pdo): array
    {
        self::ensureSchema($pdo);
        $stmt = $pdo->query(
            'SELECT * FROM coin_packages ORDER BY sort_order ASC, coin_amount ASC'
        );

        return $stmt->fetchAll();
    }

    public static function findActiveById(PDO $pdo, int $id): ?array
    {
        self::ensureSchema($pdo);
        $stmt = $pdo->prepare('SELECT * FROM coin_packages WHERE id = ? AND is_active = TRUE');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function findActiveByCoins(PDO $pdo, int $coins): ?array
    {
        self::ensureSchema($pdo);
        $stmt = $pdo->prepare('SELECT * FROM coin_packages WHERE coin_amount = ? AND is_active = TRUE');
        $stmt->execute([$coins]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function save(PDO $pdo, array $data): void
    {
        self::ensureSchema($pdo);

        $name = trim($data['name'] ?? '');
        $coinAmount = (int) ($data['coin_amount'] ?? 0);
        $priceUsd = round((float) ($data['price_usd'] ?? 0), 2);
        $tone = trim($data['tone'] ?? 'package-blue');
        $sortOrder = (int) ($data['sort_order'] ?? 0);
        $isActive = isset($data['is_active']) && (string) $data['is_active'] !== '' && (string) $data['is_active'] !== '0';
        $id = isset($data['id']) && $data['id'] !== '' ? (int) $data['id'] : null;

        if ($name === '') {
            throw new InvalidArgumentException('Package name is required.');
        }
        if ($coinAmount < 1 || $coinAmount > 1000000) {
            throw new InvalidArgumentException('Coin amount must be between 1 and 1,000,000.');
        }
        if ($priceUsd <= 0 || $priceUsd > 100000) {
            throw new InvalidArgumentException('Price must be greater than zero.');
        }

        $allowedTones = ['package-blue', 'package-purple', 'package-orange', 'package-green'];
        if (!in_array($tone, $allowedTones, true)) {
            $tone = 'package-blue';
        }

        $duplicate = $pdo->prepare(
            'SELECT id FROM coin_packages WHERE coin_amount = ? AND id <> COALESCE(?, 0)'
        );
        $duplicate->execute([$coinAmount, $id]);
        if ($duplicate->fetch()) {
            throw new InvalidArgumentException('A package with this coin amount already exists.');
        }

        if ($id) {
            $pdo->prepare(
                'UPDATE coin_packages
                 SET name = ?, coin_amount = ?, price_usd = ?, tone = ?, sort_order = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP
                 WHERE id = ?'
            )->execute([$name, $coinAmount, $priceUsd, $tone, $sortOrder, $isActive, $id]);

            return;
        }

        $pdo->prepare(
            'INSERT INTO coin_packages (name, coin_amount, price_usd, tone, sort_order, is_active)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$name, $coinAmount, $priceUsd, $tone, $sortOrder, $isActive]);
    }

    public static function delete(PDO $pdo, int $id): void
    {
        self::ensureSchema($pdo);
        $pdo->prepare('DELETE FROM coin_packages WHERE id = ?')->execute([$id]);
    }

    public static function formatPublic(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'coins' => (int) $row['coin_amount'],
            'price' => (float) $row['price_usd'],
            'tone' => $row['tone'],
        ];
    }
}
