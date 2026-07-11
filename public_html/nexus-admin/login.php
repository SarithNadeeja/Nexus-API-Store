<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
AdminService::ensureDefaultAdmin($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $admin = AdminService::login($pdo, $_POST['username'] ?? '', $_POST['password'] ?? '');
    if (!$admin) {
        redirect('/nexus-admin/login.php?error=1');
    }
    if (AdminService::mustChangeCredentials($admin)) {
        redirect('/nexus-admin/setup-credentials.php');
    }
    redirect('/nexus-admin/index.php');
}

if (Auth::adminUserId() !== null) {
    $admin = Auth::requireAdmin($pdo, true);
    if (AdminService::mustChangeCredentials($admin)) {
        redirect('/nexus-admin/setup-credentials.php');
    }
    redirect('/nexus-admin/index.php');
}

$error = ($_GET['error'] ?? '') === '1';
$logout = isset($_GET['logout']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | Nexus API Store</title>
    <link rel="stylesheet" href="/nexus-admin/admin.css">
</head>
<body class="login-body">
<div class="login-shell">
    <div class="login-card">
        <div class="login-badge">Admin Panel</div>
        <h1>Nexus API Store</h1>
        <p>Sign in to manage categories, API listings, code snippets, and admin accounts.</p>

        <?php if ($logout): ?><div class="alert success">You have been signed out.</div><?php endif; ?>
        <?php if ($error): ?><div class="alert error">Invalid username or password.</div><?php endif; ?>

        <form method="post" class="stack-lg">
            <label>
                <span>Username</span>
                <input type="text" name="username" placeholder="Enter your username" required autocomplete="username">
            </label>
            <label>
                <span>Password</span>
                <input type="password" name="password" placeholder="Enter your password" required autocomplete="current-password">
            </label>
            <button type="submit" class="primary-btn">Login to Admin</button>
        </form>
    </div>
</div>
</body>
</html>
