<?php

declare(strict_types=1);

final class AdminService
{
    public static function ensureDefaultAdmin(PDO $pdo): void
    {
        self::ensureAdminSchema($pdo);

        $count = (int) $pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
        if ($count > 0) {
            return;
        }

        $hash = password_hash('Admin@123', PASSWORD_BCRYPT);
        $pdo->prepare(
            'INSERT INTO admin_users (username, password_hash, must_change_credentials) VALUES (?, ?, TRUE)'
        )->execute(['admin', $hash]);
    }

    public static function ensureAdminSchema(PDO $pdo): void
    {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'pgsql') {
            return;
        }

        try {
            self::ensureAdminColumn($pdo, 'must_change_credentials', 'BOOLEAN NOT NULL DEFAULT FALSE', true);
            self::ensureAdminColumn($pdo, 'last_login', 'TIMESTAMP NULL', false);
        } catch (Throwable $e) {
            error_log('AdminService::ensureAdminSchema failed: ' . $e->getMessage());
        }
    }

    private static function ensureAdminColumn(PDO $pdo, string $column, string $definition, bool $seedDefaultAdminFlag): void
    {
        try {
            $pdo->exec("ALTER TABLE admin_users ADD COLUMN IF NOT EXISTS {$column} {$definition}");
        } catch (PDOException) {
            $columnCheck = $pdo->prepare(
                'SELECT 1 FROM information_schema.columns
                 WHERE table_schema = current_schema() AND table_name = \'admin_users\' AND column_name = ?'
            );
            $columnCheck->execute([$column]);
            if (!$columnCheck->fetchColumn()) {
                $pdo->exec("ALTER TABLE admin_users ADD COLUMN {$column} {$definition}");
            }
        }

        if (!$seedDefaultAdminFlag || $column !== 'must_change_credentials') {
            return;
        }

        $admins = $pdo->query('SELECT id, username, password_hash FROM admin_users')->fetchAll();
        $update = $pdo->prepare('UPDATE admin_users SET must_change_credentials = TRUE WHERE id = ?');
        foreach ($admins as $admin) {
            if (
                strtolower((string) $admin['username']) === 'admin'
                && password_verify('Admin@123', (string) $admin['password_hash'])
            ) {
                $update->execute([(int) $admin['id']]);
            }
        }
    }

    public static function mustChangeCredentials(array $admin): bool
    {
        return db_bool($admin['must_change_credentials'] ?? false);
    }

    public static function login(PDO $pdo, string $username, string $password): ?array
    {
        self::ensureAdminSchema($pdo);

        $stmt = $pdo->prepare('SELECT * FROM admin_users WHERE username = ?');
        $stmt->execute([trim($username)]);
        $admin = $stmt->fetch();
        if (!$admin || !password_verify($password, $admin['password_hash'])) {
            return null;
        }
        $pdo->prepare('UPDATE admin_users SET last_login = CURRENT_TIMESTAMP WHERE id = ?')->execute([(int) $admin['id']]);
        Auth::setAdmin((int) $admin['id'], $admin['username']);
        return $admin;
    }

    public static function completeCredentialSetup(
        PDO $pdo,
        int $adminId,
        string $currentPassword,
        string $newUsername,
        string $newPassword,
        string $confirmPassword
    ): array {
        self::ensureAdminSchema($pdo);

        $stmt = $pdo->prepare('SELECT * FROM admin_users WHERE id = ?');
        $stmt->execute([$adminId]);
        $admin = $stmt->fetch();
        if (!$admin) {
            throw new InvalidArgumentException('Admin account not found.');
        }
        if (!password_verify($currentPassword, $admin['password_hash'])) {
            throw new InvalidArgumentException('Current password is incorrect.');
        }

        $newUsername = trim($newUsername);
        if ($newUsername === '') {
            throw new InvalidArgumentException('New username is required.');
        }
        if (strlen($newPassword) < 8) {
            throw new InvalidArgumentException('New password must be at least 8 characters.');
        }
        if ($newPassword !== $confirmPassword) {
            throw new InvalidArgumentException('New passwords do not match.');
        }
        if ($newPassword === 'Admin@123') {
            throw new InvalidArgumentException('Choose a stronger password than the default setup password.');
        }
        if ($newPassword === $currentPassword) {
            throw new InvalidArgumentException('Choose a different password from your current one.');
        }
        if (password_verify($newPassword, $admin['password_hash'])) {
            throw new InvalidArgumentException('Choose a different password from your current one.');
        }

        $check = $pdo->prepare('SELECT id FROM admin_users WHERE LOWER(username) = LOWER(?) AND id <> ?');
        $check->execute([$newUsername, $adminId]);
        if ($check->fetch()) {
            throw new InvalidArgumentException('That username is already in use.');
        }

        $pdo->prepare(
            'UPDATE admin_users SET username = ?, password_hash = ?, must_change_credentials = FALSE WHERE id = ?'
        )->execute([
            $newUsername,
            password_hash($newPassword, PASSWORD_BCRYPT),
            $adminId,
        ]);

        Auth::setAdmin($adminId, $newUsername);

        $stmt->execute([$adminId]);
        return $stmt->fetch();
    }

    public static function counts(PDO $pdo): array
    {
        return [
            'categories' => (int) $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn(),
            'apis' => (int) $pdo->query('SELECT COUNT(*) FROM api_listings')->fetchColumn(),
            'users' => (int) $pdo->query('SELECT COUNT(*) FROM app_users')->fetchColumn(),
            'admins' => (int) $pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn(),
        ];
    }

    public static function saveCategory(PDO $pdo, array $data): void
    {
        $name = trim($data['name'] ?? '');
        if ($name === '') {
            throw new InvalidArgumentException('Category name is required.');
        }
        $description = trim($data['description'] ?? '');
        $id = isset($data['id']) && $data['id'] !== '' ? (int) $data['id'] : null;

        if ($id) {
            $pdo->prepare('UPDATE categories SET name = ?, description = ? WHERE id = ?')
                ->execute([$name, $description, $id]);
            return;
        }

        $check = $pdo->prepare('SELECT id FROM categories WHERE LOWER(name) = LOWER(?)');
        $check->execute([$name]);
        if ($check->fetch()) {
            throw new InvalidArgumentException('A category with this name already exists.');
        }
        $pdo->prepare('INSERT INTO categories (name, description) VALUES (?, ?)')
            ->execute([$name, $description]);
    }

    public static function deleteCategory(PDO $pdo, int $id): void
    {
        $check = $pdo->prepare('SELECT COUNT(*) FROM api_listings WHERE category_id = ?');
        $check->execute([$id]);
        if ((int) $check->fetchColumn() > 0) {
            throw new InvalidArgumentException('Cannot delete a category that still has API listings.');
        }
        $pdo->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
    }

    public static function saveApi(PDO $pdo, array $data): array
    {
        KeyPool::ensureSchema($pdo);

        $required = ['name', 'status', 'category_id', 'price_coins', 'expiration_months'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || trim((string) $data[$field]) === '') {
                throw new InvalidArgumentException(ucfirst(str_replace('_', ' ', $field)) . ' is required.');
            }
        }

        $id = isset($data['id']) && $data['id'] !== '' ? (int) $data['id'] : null;
        if ($id) {
            $stmt = $pdo->prepare('SELECT * FROM api_listings WHERE id = ?');
            $stmt->execute([$id]);
            $existing = $stmt->fetch() ?: null;
            if (!$existing) {
                throw new InvalidArgumentException('API listing not found.');
            }
        }

        $expirationMonths = (int) $data['expiration_months'];
        if ($expirationMonths < 1 || $expirationMonths > 120) {
            throw new InvalidArgumentException('Expiration time must be between 1 and 120 months.');
        }

        $bulkRaw = trim((string) ($data['bulk_key_links'] ?? ''));
        $bulkSnippets = $bulkRaw !== '' ? KeyPool::parseBulkSnippets($bulkRaw) : [];

        if (!$id && !$bulkSnippets) {
            throw new InvalidArgumentException('Add at least one code snippet.');
        }

        $placeholderLink = '';
        $payload = [
            trim((string) $data['name']),
            trim((string) ($data['description'] ?? '')),
            $placeholderLink,
            $placeholderLink,
            KeyPool::POOL_MARKER,
            trim((string) $data['status']),
            (int) $data['price_coins'],
            (int) $data['category_id'],
            $expirationMonths,
        ];

        if ($id) {
            $payload[] = $id;
            $pdo->prepare(
                'UPDATE api_listings SET name=?, description=?, endpoint_url=?, access_link=?, api_key_value=?, status=?, price_coins=?, category_id=?, expiration_months=? WHERE id=?'
            )->execute($payload);

            $keysAdded = $bulkSnippets ? KeyPool::addKeys($pdo, $id, $bulkSnippets) : 0;
            $counts = KeyPool::countsForListing($pdo, $id);
            if ($counts['total'] === 0) {
                throw new InvalidArgumentException('This listing has no code snippets. Paste snippets in the bulk field.');
            }

            $message = 'API listing updated.';
            if ($keysAdded > 0) {
                $skipped = count($bulkSnippets) - $keysAdded;
                $message = "Added {$keysAdded} new code snippet" . ($keysAdded === 1 ? '' : 's') . '.';
                if ($skipped > 0) {
                    $message .= " {$skipped} duplicate snippet" . ($skipped === 1 ? ' was' : 's were') . ' skipped.';
                }
            }

            return ['listingId' => $id, 'keysAdded' => $keysAdded, 'message' => $message];
        }

        $pdo->prepare(
            'INSERT INTO api_listings (name, description, endpoint_url, access_link, api_key_value, status, price_coins, category_id, expiration_months)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute($payload);

        $listingId = Database::lastInsertId($pdo, 'api_listings');
        $keysAdded = KeyPool::addKeys($pdo, $listingId, $bulkSnippets);

        return [
            'listingId' => $listingId,
            'keysAdded' => $keysAdded,
            'message' => "API listing created with {$keysAdded} code snippet" . ($keysAdded === 1 ? '' : 's') . '.',
        ];
    }

    public static function deleteApi(PDO $pdo, int $id): void
    {
        $pdo->prepare('DELETE FROM api_listings WHERE id = ?')->execute([$id]);
    }

    public static function createAdmin(PDO $pdo, string $username, string $password): void
    {
        self::ensureAdminSchema($pdo);

        if (strlen($password) < 8) {
            throw new InvalidArgumentException('Password must be at least 8 characters.');
        }
        $check = $pdo->prepare('SELECT id FROM admin_users WHERE LOWER(username) = LOWER(?)');
        $check->execute([trim($username)]);
        if ($check->fetch()) {
            throw new InvalidArgumentException('An admin with this username already exists.');
        }
        $pdo->prepare(
            'INSERT INTO admin_users (username, password_hash, must_change_credentials) VALUES (?, ?, FALSE)'
        )->execute([trim($username), password_hash($password, PASSWORD_BCRYPT)]);
    }

    public static function updateAdminPassword(PDO $pdo, int $userId, string $password): void
    {
        if (strlen($password) < 8) {
            throw new InvalidArgumentException('Password must be at least 8 characters.');
        }
        $pdo->prepare('UPDATE admin_users SET password_hash = ?, must_change_credentials = FALSE WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_BCRYPT), $userId]);
    }

    public static function deleteAdmin(PDO $pdo, int $id, string $currentUsername): void
    {
        $stmt = $pdo->prepare('SELECT username FROM admin_users WHERE id = ?');
        $stmt->execute([$id]);
        $admin = $stmt->fetch();
        if (!$admin) {
            throw new InvalidArgumentException('Admin user not found.');
        }
        if ($admin['username'] === $currentUsername) {
            throw new InvalidArgumentException('You cannot delete the currently logged-in admin.');
        }
        if ((int) $pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn() <= 1) {
            throw new InvalidArgumentException('At least one admin account must remain.');
        }
        $pdo->prepare('DELETE FROM admin_users WHERE id = ?')->execute([$id]);
    }

    public static function adjustUserCoins(PDO $pdo, int $userId, string $operation, int $amount, string $note): void
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Coin amount must be greater than zero.');
        }
        $stmt = $pdo->prepare('SELECT * FROM app_users WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        if (!$user) {
            throw new InvalidArgumentException('User not found.');
        }

        $description = $note !== '' ? $note : 'Manual admin wallet adjustment';
        if (strtoupper($operation) === 'REMOVE') {
            if ((int) $user['coin_balance'] < $amount) {
                throw new InvalidArgumentException('Cannot remove more coins than the user currently has.');
            }
            $pdo->prepare('UPDATE app_users SET coin_balance = coin_balance - ? WHERE id = ?')->execute([$amount, $userId]);
            $pdo->prepare('INSERT INTO coin_transactions (user_id, transaction_type, coin_amount, description) VALUES (?, ?, ?, ?)')
                ->execute([$userId, 'ADMIN_DEBIT', -$amount, $description]);
            return;
        }

        $pdo->prepare('UPDATE app_users SET coin_balance = coin_balance + ? WHERE id = ?')->execute([$amount, $userId]);
        $pdo->prepare('INSERT INTO coin_transactions (user_id, transaction_type, coin_amount, description) VALUES (?, ?, ?, ?)')
            ->execute([$userId, 'ADMIN_CREDIT', $amount, $description]);
    }
}
