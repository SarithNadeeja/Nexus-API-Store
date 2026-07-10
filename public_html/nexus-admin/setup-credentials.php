<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
AdminService::ensureDefaultAdmin($pdo);

$admin = Auth::requireAdmin($pdo, true);
if (!AdminService::mustChangeCredentials($admin)) {
    redirect('/nexus-admin/index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        AdminService::completeCredentialSetup(
            $pdo,
            (int) $admin['id'],
            $_POST['current_password'] ?? '',
            $_POST['new_username'] ?? '',
            $_POST['new_password'] ?? '',
            $_POST['confirm_password'] ?? ''
        );
        flash('success', 'Admin credentials updated successfully.');
        redirect('/nexus-admin/index.php');
    } catch (InvalidArgumentException $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set Up Admin Account | Nexus API Store</title>
    <link rel="stylesheet" href="/nexus-admin/admin.css">
</head>
<body class="login-body">
<div class="login-shell">
    <div class="login-card">
        <div class="login-badge">First-Time Setup</div>
        <h1>Secure Your Admin Account</h1>
        <p>Before using the admin panel, choose a new username and password for this account.</p>

        <?php if ($error !== ''): ?><div class="alert error"><?= h($error) ?></div><?php endif; ?>

        <form method="post" class="stack-lg">
            <label>
                <span>Current password</span>
                <input type="password" name="current_password" required autocomplete="current-password">
            </label>
            <label>
                <span>New username</span>
                <input type="text" name="new_username" value="<?= h($admin['username']) ?>" required autocomplete="username">
            </label>
            <label>
                <span>New password</span>
                <input type="password" name="new_password" minlength="8" required autocomplete="new-password">
            </label>
            <label>
                <span>Confirm new password</span>
                <input type="password" name="confirm_password" minlength="8" required autocomplete="new-password">
            </label>
            <button type="submit" class="primary-btn">Save and Continue</button>
        </form>
    </div>
</div>
</body>
</html>
