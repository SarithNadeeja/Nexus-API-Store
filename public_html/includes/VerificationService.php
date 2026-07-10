<?php

declare(strict_types=1);

final class VerificationService
{
    public static function createAndSend(PDO $pdo, array $config, array $user): bool
    {
        $token = uuid_v4();
        $expires = (new DateTimeImmutable('+24 hours'))->format('Y-m-d H:i:s');

        $stmt = $pdo->prepare('SELECT id FROM email_verification_tokens WHERE user_id = ?');
        $stmt->execute([$user['id']]);
        $existing = $stmt->fetch();

        if ($existing) {
            $update = $pdo->prepare('UPDATE email_verification_tokens SET token = ?, expires_at = ?, verified_at = NULL WHERE user_id = ?');
            $update->execute([$token, $expires, $user['id']]);
        } else {
            $insert = $pdo->prepare('INSERT INTO email_verification_tokens (user_id, token, expires_at) VALUES (?, ?, ?)');
            $insert->execute([$user['id'], $token, $expires]);
        }

        $verifyUrl = base_url($config) . '/verify-email.html?token=' . urlencode($token);
        $body = "Hello {$user['full_name']},\n\n"
            . "Thanks for creating your Nexus API Store account.\n\n"
            . "Please verify your email address by opening this link:\n"
            . $verifyUrl . "\n\n"
            . "This link expires in 24 hours.\n\n"
            . "If you did not create this account, you can ignore this email.";

        return Mailer::send($config, $user['email'], 'Verify your Nexus API Store account', $body);
    }

    public static function resend(PDO $pdo, array $config, array $user): void
    {
        if (db_bool($user['email_verified'])) {
            throw new InvalidArgumentException('This email address is already verified.');
        }
        if (!self::createAndSend($pdo, $config, $user)) {
            $detail = Mailer::getLastError();
            throw new RuntimeException(
                'Unable to send verification email. ' . ($detail ?: 'Please confirm Gmail SMTP settings and try again.')
            );
        }
    }

    public static function verifyToken(PDO $pdo, string $rawToken): array
    {
        $stmt = $pdo->prepare('SELECT t.*, u.* FROM email_verification_tokens t JOIN app_users u ON u.id = t.user_id WHERE t.token = ?');
        $stmt->execute([$rawToken]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new InvalidArgumentException('Invalid verification link.');
        }
        if ($row['verified_at'] !== null) {
            throw new InvalidArgumentException('This email has already been verified.');
        }
        if (strtotime($row['expires_at']) < time()) {
            throw new InvalidArgumentException('This verification link has expired.');
        }

        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE app_users SET email_verified = TRUE, coin_balance = coin_balance + 100 WHERE id = ?')
                ->execute([$row['user_id']]);
            $pdo->prepare('UPDATE email_verification_tokens SET verified_at = NOW() WHERE id = ?')
                ->execute([$row['id']]);
            $pdo->prepare('INSERT INTO coin_transactions (user_id, transaction_type, coin_amount, description) VALUES (?, ?, ?, ?)')
                ->execute([$row['user_id'], 'WELCOME_BONUS', 100, 'Welcome bonus after email verification']);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        $stmt = $pdo->prepare('SELECT * FROM app_users WHERE id = ?');
        $stmt->execute([$row['user_id']]);
        return $stmt->fetch();
    }
}
