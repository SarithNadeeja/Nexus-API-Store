<?php

declare(strict_types=1);

final class Auth
{
    private const APP_USER_KEY = 'app_user_id';
    private const ADMIN_USER_KEY = 'admin_user_id';
    private const ADMIN_USERNAME_KEY = 'admin_username';

    public static function appUserId(): ?int
    {
        return isset($_SESSION[self::APP_USER_KEY]) ? (int) $_SESSION[self::APP_USER_KEY] : null;
    }

    public static function setAppUserId(int $id): void
    {
        $_SESSION[self::APP_USER_KEY] = $id;
    }

    public static function clearAppUser(): void
    {
        unset($_SESSION[self::APP_USER_KEY]);
    }

    public static function adminUserId(): ?int
    {
        return isset($_SESSION[self::ADMIN_USER_KEY]) ? (int) $_SESSION[self::ADMIN_USER_KEY] : null;
    }

    public static function adminUsername(): ?string
    {
        return $_SESSION[self::ADMIN_USERNAME_KEY] ?? null;
    }

    public static function setAdmin(int $id, string $username): void
    {
        $_SESSION[self::ADMIN_USER_KEY] = $id;
        $_SESSION[self::ADMIN_USERNAME_KEY] = $username;
    }

    public static function clearAdmin(): void
    {
        unset($_SESSION[self::ADMIN_USER_KEY], $_SESSION[self::ADMIN_USERNAME_KEY]);
    }

    public static function requireAppUser(PDO $pdo): array
    {
        $id = self::appUserId();
        if ($id === null) {
            json_error('Please sign in to continue.', 401);
        }
        $stmt = $pdo->prepare('SELECT * FROM app_users WHERE id = ?');
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        if (!$user) {
            self::clearAppUser();
            json_error('User session not found.', 401);
        }
        return $user;
    }

    public static function requireVerifiedAppUser(PDO $pdo): array
    {
        $user = self::requireAppUser($pdo);
        if (!(int) $user['email_verified']) {
            json_error('Please verify your email address before using coins or buying API keys.');
        }
        return $user;
    }

    public static function requireAdmin(PDO $pdo): array
    {
        $id = self::adminUserId();
        if ($id === null) {
            redirect('/nexus-admin/login.php');
        }
        $stmt = $pdo->prepare('SELECT * FROM admin_users WHERE id = ?');
        $stmt->execute([$id]);
        $admin = $stmt->fetch();
        if (!$admin) {
            self::clearAdmin();
            redirect('/nexus-admin/login.php');
        }
        return $admin;
    }
}
