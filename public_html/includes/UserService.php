<?php

declare(strict_types=1);

final class UserService
{
    public static function register(PDO $pdo, array $config, array $data): array
    {
        $fullName = trim($data['fullName'] ?? $data['full_name'] ?? '');
        $email = sanitize_email($data['email'] ?? '');
        $password = $data['password'] ?? '';

        if ($fullName === '' || $email === '' || $password === '') {
            throw new InvalidArgumentException('Full name, email, and password are required.');
        }
        if (strlen($password) < 8 || strlen($password) > 100) {
            throw new InvalidArgumentException('Password must be between 8 and 100 characters.');
        }

        $exists = $pdo->prepare('SELECT id FROM app_users WHERE LOWER(email) = ?');
        $exists->execute([$email]);
        if ($exists->fetch()) {
            throw new InvalidArgumentException('An account with this email already exists.');
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $insert = $pdo->prepare('INSERT INTO app_users (full_name, email, password_hash, coin_balance, email_verified) VALUES (?, ?, ?, 0, FALSE)');
        $insert->execute([$fullName, $email, $hash]);

        $userId = Database::lastInsertId($pdo, 'app_users');
        Auth::clearAppUser();

        $stmt = $pdo->prepare('SELECT * FROM app_users WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        $emailSent = VerificationService::createAndSend($pdo, $config, $user);
        $message = $emailSent
            ? 'Account created. Please verify your email address to complete registration.'
            : 'Account created, but the verification email could not be sent right now. Use Resend verification email and check spam.';

        return [
            'pendingVerification' => true,
            'email' => $user['email'],
            'message' => $message,
            'emailSent' => $emailSent,
        ];
    }

    public static function login(PDO $pdo, array $data): array
    {
        $email = sanitize_email($data['email'] ?? '');
        $password = $data['password'] ?? '';

        $stmt = $pdo->prepare('SELECT * FROM app_users WHERE LOWER(email) = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            throw new InvalidArgumentException('Invalid email or password.');
        }
        if (!db_bool($user['email_verified'])) {
            throw new InvalidArgumentException('Please verify your email address before signing in. Check your inbox for the verification link.');
        }

        Auth::setAppUserId((int) $user['id']);
        return self::buildSession($pdo, $user);
    }

    public static function logout(): void
    {
        Auth::clearAppUser();
    }

    public static function getOptionalSession(PDO $pdo): array
    {
        $userId = Auth::appUserId();
        if ($userId === null) {
            return self::guestSession();
        }

        $stmt = $pdo->prepare('SELECT * FROM app_users WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user || !db_bool($user['email_verified'])) {
            Auth::clearAppUser();
            return self::guestSession();
        }

        return self::buildSession($pdo, $user);
    }

    public static function verifyEmail(PDO $pdo, string $token): array
    {
        VerificationService::verifyToken($pdo, $token);
        Auth::clearAppUser();
        return [
            'success' => true,
            'message' => 'Email verified successfully. You can now sign in.',
        ];
    }

    public static function resendVerification(PDO $pdo, array $config, string $email): void
    {
        $stmt = $pdo->prepare('SELECT * FROM app_users WHERE LOWER(email) = ?');
        $stmt->execute([sanitize_email($email)]);
        $user = $stmt->fetch();
        if (!$user) {
            throw new InvalidArgumentException('No account found for this email.');
        }
        VerificationService::resend($pdo, $config, $user);
    }

    public static function getPublicApis(PDO $pdo): array
    {
        $userId = Auth::appUserId();
        $stmt = $pdo->query(
            'SELECT a.id, a.name, a.description, a.endpoint_url, a.status, a.price_coins, c.name AS category
             FROM api_listings a
             JOIN categories c ON c.id = a.category_id
             ORDER BY a.id ASC'
        );
        $rows = $stmt->fetchAll();
        $result = [];

        foreach ($rows as $row) {
            $purchased = false;
            if ($userId !== null) {
                $check = $pdo->prepare('SELECT id FROM api_purchases WHERE user_id = ? AND api_listing_id = ?');
                $check->execute([$userId, $row['id']]);
                $purchased = (bool) $check->fetch();
            }
            $result[] = [
                'id' => (int) $row['id'],
                'name' => $row['name'],
                'description' => $row['description'],
                'category' => $row['category'],
                'status' => $row['status'],
                'endpointUrl' => $row['endpoint_url'],
                'priceCoins' => (int) $row['price_coins'],
                'purchased' => $purchased,
            ];
        }

        return $result;
    }

    public static function getPublicCategories(PDO $pdo): array
    {
        $stmt = $pdo->query(
            'SELECT c.id, c.name, c.description, COUNT(a.id)::int AS api_count
             FROM categories c
             LEFT JOIN api_listings a ON a.category_id = c.id
             GROUP BY c.id, c.name, c.description
             ORDER BY c.name ASC'
        );

        $result = [];
        $index = 0;
        foreach ($stmt->fetchAll() as $row) {
            $visual = homepage_category_visual($index);
            $result[] = [
                'id' => (int) $row['id'],
                'name' => $row['name'],
                'description' => (string) ($row['description'] ?? ''),
                'apiCount' => (int) $row['api_count'],
                'icon' => $visual['icon'],
                'iconClass' => $visual['iconClass'],
            ];
            $index++;
        }

        return $result;
    }

    public static function recharge(PDO $pdo, array $user, int $coins, ?int $packageId = null, bool $custom = false): array
    {
        if ($custom) {
            $settings = CoinPackageService::getCustomSettings($pdo);
            if (!db_bool($settings['custom_recharge_enabled'])) {
                throw new InvalidArgumentException('Custom coin recharge is not available right now.');
            }

            $minCoins = (int) $settings['custom_coin_min'];
            $maxCoins = (int) $settings['custom_coin_max'];
            if ($coins < $minCoins || $coins > $maxCoins) {
                throw new InvalidArgumentException("Custom amount must be between {$minCoins} and {$maxCoins} coins.");
            }

            $price = CoinPackageService::calculateCustomPrice($settings, $coins);

            $pdo->prepare('UPDATE app_users SET coin_balance = coin_balance + ? WHERE id = ?')
                ->execute([$coins, $user['id']]);
            self::addTransaction(
                $pdo,
                (int) $user['id'],
                'RECHARGE',
                $coins,
                $coins . ' custom coins added to your wallet (' . format_lkr($price) . ')'
            );

            $stmt = $pdo->prepare('SELECT * FROM app_users WHERE id = ?');
            $stmt->execute([$user['id']]);
            return self::buildSession($pdo, $stmt->fetch());
        }

        if ($packageId === null || $packageId <= 0) {
            throw new InvalidArgumentException('Please select a valid coin package.');
        }

        $package = CoinPackageService::findActiveById($pdo, $packageId);
        if (!$package) {
            throw new InvalidArgumentException('This coin package is not available.');
        }

        $coins = (int) $package['coin_amount'];

        $pdo->prepare('UPDATE app_users SET coin_balance = coin_balance + ? WHERE id = ?')
            ->execute([$coins, $user['id']]);
        self::addTransaction(
            $pdo,
            (int) $user['id'],
            'RECHARGE',
            $coins,
            $coins . ' coins added to your wallet (' . $package['name'] . ')'
        );

        $stmt = $pdo->prepare('SELECT * FROM app_users WHERE id = ?');
        $stmt->execute([$user['id']]);
        return self::buildSession($pdo, $stmt->fetch());
    }

    public static function purchase(PDO $pdo, array $user, int $apiId): array
    {
        $stmt = $pdo->prepare('SELECT * FROM api_listings WHERE id = ?');
        $stmt->execute([$apiId]);
        $api = $stmt->fetch();
        if (!$api) {
            throw new InvalidArgumentException('API listing not found.');
        }

        $check = $pdo->prepare('SELECT id FROM api_purchases WHERE user_id = ? AND api_listing_id = ?');
        $check->execute([$user['id'], $apiId]);
        if ($check->fetch()) {
            throw new InvalidArgumentException('You already purchased this API.');
        }

        $price = (int) $api['price_coins'];
        if ((int) $user['coin_balance'] < $price) {
            throw new InvalidArgumentException('Not enough coins for this purchase.');
        }

        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE app_users SET coin_balance = coin_balance - ? WHERE id = ?')
                ->execute([$price, $user['id']]);
            $pdo->prepare(
                'INSERT INTO api_purchases (user_id, api_listing_id, coins_spent, purchased_key_snapshot, access_link_snapshot)
                 VALUES (?, ?, ?, ?, ?)'
            )->execute([$user['id'], $apiId, $price, $api['api_key_value'], $api['access_link']]);
            self::addTransaction($pdo, (int) $user['id'], 'PURCHASE', -$price, 'Purchased ' . $api['name']);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        $purchaseId = Database::lastInsertId($pdo, 'api_purchases');
        return [
            'purchaseId' => $purchaseId,
            'apiName' => $api['name'],
            'coinsSpent' => $price,
            'purchasedKey' => $api['api_key_value'],
            'accessLink' => $api['access_link'],
            'purchasedAt' => date('c'),
        ];
    }

    public static function findOrCreateGoogleUser(PDO $pdo, string $email, string $fullName): array
    {
        $email = sanitize_email($email);
        $stmt = $pdo->prepare('SELECT * FROM app_users WHERE LOWER(email) = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            if (!db_bool($user['email_verified'])) {
                $pdo->prepare('UPDATE app_users SET email_verified = TRUE WHERE id = ?')->execute([$user['id']]);
            }
            if ($fullName !== '' && $fullName !== $user['full_name']) {
                $pdo->prepare('UPDATE app_users SET full_name = ? WHERE id = ?')->execute([$fullName, $user['id']]);
            }
            $stmt->execute([$email]);
            return $stmt->fetch();
        }

        $hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);
        $pdo->prepare('INSERT INTO app_users (full_name, email, password_hash, coin_balance, email_verified) VALUES (?, ?, ?, 100, TRUE)')
            ->execute([$fullName ?: 'Google User', $email, $hash]);
        $userId = Database::lastInsertId($pdo, 'app_users');
        self::addTransaction($pdo, $userId, 'WELCOME_BONUS', 100, 'Welcome bonus after Google sign-in');

        $stmt = $pdo->prepare('SELECT * FROM app_users WHERE id = ?');
        $stmt->execute([$userId]);
        return $stmt->fetch();
    }

    private static function buildSession(PDO $pdo, array $user): array
    {
        $purchasesStmt = $pdo->prepare(
            'SELECT p.id, p.coins_spent, p.purchased_key_snapshot, p.access_link_snapshot, p.created_at, a.name AS api_name
             FROM api_purchases p
             JOIN api_listings a ON a.id = p.api_listing_id
             WHERE p.user_id = ?
             ORDER BY p.created_at DESC'
        );
        $purchasesStmt->execute([$user['id']]);
        $purchases = [];
        foreach ($purchasesStmt->fetchAll() as $p) {
            $purchases[] = [
                'purchaseId' => (int) $p['id'],
                'apiName' => $p['api_name'],
                'coinsSpent' => (int) $p['coins_spent'],
                'purchasedKey' => $p['purchased_key_snapshot'],
                'accessLink' => $p['access_link_snapshot'],
                'purchasedAt' => date('c', strtotime($p['created_at'])),
            ];
        }

        $txStmt = $pdo->prepare(
            'SELECT transaction_type, coin_amount, description, created_at
             FROM coin_transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 10'
        );
        $txStmt->execute([$user['id']]);
        $transactions = [];
        foreach ($txStmt->fetchAll() as $tx) {
            $transactions[] = [
                'transactionType' => $tx['transaction_type'],
                'coinAmount' => (int) $tx['coin_amount'],
                'description' => $tx['description'],
                'createdAt' => date('c', strtotime($tx['created_at'])),
            ];
        }

        return [
            'authenticated' => true,
            'userId' => (int) $user['id'],
            'emailVerified' => db_bool($user['email_verified']),
            'fullName' => $user['full_name'],
            'email' => $user['email'],
            'coinBalance' => (int) $user['coin_balance'],
            'purchases' => $purchases,
            'transactions' => $transactions,
        ];
    }

    private static function guestSession(): array
    {
        return [
            'authenticated' => false,
            'emailVerified' => false,
            'fullName' => null,
            'email' => null,
            'coinBalance' => 0,
            'purchases' => [],
            'transactions' => [],
        ];
    }

    private static function addTransaction(PDO $pdo, int $userId, string $type, int $amount, string $description): void
    {
        $pdo->prepare('INSERT INTO coin_transactions (user_id, transaction_type, coin_amount, description) VALUES (?, ?, ?, ?)')
            ->execute([$userId, $type, $amount, $description]);
    }
}
