<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
AdminService::ensureDefaultAdmin($pdo);
$admin = Auth::requireAdmin($pdo);
$section = $_GET['section'] ?? 'dashboard';
$success = flash('success');
$error = flash('error');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        match ($action) {
            'save_category' => AdminService::saveCategory($pdo, $_POST),
            'delete_category' => AdminService::deleteCategory($pdo, (int) ($_POST['id'] ?? 0)),
            'save_api' => AdminService::saveApi($pdo, $_POST),
            'delete_api' => AdminService::deleteApi($pdo, (int) ($_POST['id'] ?? 0)),
            'create_admin' => AdminService::createAdmin($pdo, $_POST['username'] ?? '', $_POST['password'] ?? ''),
            'update_admin_password' => AdminService::updateAdminPassword($pdo, (int) ($_POST['userId'] ?? 0), $_POST['newPassword'] ?? ''),
            'delete_admin' => AdminService::deleteAdmin($pdo, (int) ($_POST['id'] ?? 0), $admin['username']),
            'adjust_coins' => AdminService::adjustUserCoins(
                $pdo,
                (int) ($_POST['userId'] ?? 0),
                $_POST['operation'] ?? 'ADD',
                (int) ($_POST['coinAmount'] ?? 0),
                trim($_POST['note'] ?? '')
            ),
            default => throw new InvalidArgumentException('Unknown action.'),
        };
        flash('success', 'Changes saved successfully.');
    } catch (InvalidArgumentException $e) {
        flash('error', $e->getMessage());
    }
    redirect('/nexus-admin/index.php?section=' . urlencode($_POST['return_section'] ?? $section));
}

$counts = AdminService::counts($pdo);
$categories = $pdo->query('SELECT * FROM categories ORDER BY name')->fetchAll();
$apis = $pdo->query('SELECT a.*, c.name AS category_name FROM api_listings a JOIN categories c ON c.id = a.category_id ORDER BY a.id DESC')->fetchAll();
$users = $pdo->query('SELECT * FROM app_users ORDER BY created_at DESC')->fetchAll();
$admins = $pdo->query('SELECT * FROM admin_users ORDER BY username')->fetchAll();
$username = Auth::adminUsername();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel | Nexus API Store</title>
    <link rel="stylesheet" href="/nexus-admin/admin.css">
</head>
<body>
<div class="admin-layout">
    <aside class="sidebar">
        <div>
            <div class="brand-mark">N</div>
            <h1>Nexus API Store</h1>
            <p>Admin workspace</p>
        </div>
        <nav class="sidebar-nav">
            <?php foreach (['dashboard'=>'Dashboard','categories'=>'Categories','apis'=>'API Keys','users'=>'User Wallets','admins'=>'Admin Accounts'] as $key=>$label): ?>
                <a href="?section=<?= h($key) ?>" class="<?= $section === $key ? 'active' : '' ?>"><?= h($label) ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-footer">
            <div class="small-label">Signed in as</div>
            <strong><?= h($username) ?></strong>
            <form action="/nexus-admin/logout.php" method="post"><button class="secondary-btn full-width" type="submit">Logout</button></form>
        </div>
    </aside>
    <main class="content-shell">
        <header class="topbar">
            <div><div class="eyebrow">Admin Panel</div><h2>Manage your API selling platform</h2></div>
        </header>
        <?php if ($success): ?><div class="alert success"><?= h($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert error"><?= h($error) ?></div><?php endif; ?>

        <?php if ($section === 'dashboard'): ?>
        <section class="stats-grid four-up">
            <div class="stat-card"><span class="small-label">Categories</span><strong><?= $counts['categories'] ?></strong></div>
            <div class="stat-card"><span class="small-label">API Listings</span><strong><?= $counts['apis'] ?></strong></div>
            <div class="stat-card"><span class="small-label">Users</span><strong><?= $counts['users'] ?></strong></div>
            <div class="stat-card"><span class="small-label">Admins</span><strong><?= $counts['admins'] ?></strong></div>
        </section>
        <?php endif; ?>

        <?php if ($section === 'categories'): ?>
        <section class="section-block">
            <div class="card">
                <h4>Add / Update Category</h4>
                <form method="post" class="form-grid">
                    <input type="hidden" name="action" value="save_category">
                    <input type="hidden" name="return_section" value="categories">
                    <input type="hidden" name="id" id="category-id">
                    <label class="full-span">Name<input name="name" required></label>
                    <label class="full-span">Description<textarea name="description" rows="3"></textarea></label>
                    <div class="full-span"><button class="primary-btn" type="submit">Save Category</button></div>
                </form>
            </div>
            <div class="table-card">
                <table>
                    <thead><tr><th>Name</th><th>Description</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($categories as $cat): ?>
                        <tr>
                            <td><?= h($cat['name']) ?></td>
                            <td><?= h($cat['description']) ?></td>
                            <td class="inline-actions">
                                <form method="post" class="inline-form" onsubmit="return confirm('Delete this category?')">
                                    <input type="hidden" name="action" value="delete_category">
                                    <input type="hidden" name="return_section" value="categories">
                                    <input type="hidden" name="id" value="<?= (int) $cat['id'] ?>">
                                    <button class="danger-btn" type="submit">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($section === 'apis'): ?>
        <section class="section-block">
            <div class="card">
                <h4>Add / Update API Listing</h4>
                <form method="post" class="form-grid">
                    <input type="hidden" name="action" value="save_api">
                    <input type="hidden" name="return_section" value="apis">
                    <input type="hidden" name="id" id="api-id">
                    <label>Name<input name="name" required></label>
                    <label>Status<input name="status" value="ACTIVE" required></label>
                    <label>Category
                        <select name="category_id" required>
                            <?php foreach ($categories as $cat): ?><option value="<?= (int) $cat['id'] ?>"><?= h($cat['name']) ?></option><?php endforeach; ?>
                        </select>
                    </label>
                    <label>Price (coins)<input type="number" name="price_coins" min="1" value="50" required></label>
                    <label class="full-span">API Key<input name="api_key_value" required></label>
                    <label class="full-span">Endpoint URL<input name="endpoint_url" required></label>
                    <label class="full-span">Access Link<input name="access_link" required></label>
                    <label class="full-span">Description<textarea name="description" rows="3"></textarea></label>
                    <div class="full-span"><button class="primary-btn" type="submit">Save API</button></div>
                </form>
            </div>
            <div class="table-card">
                <table>
                    <thead><tr><th>Name</th><th>Category</th><th>Price</th><th>Key</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($apis as $api): ?>
                        <tr>
                            <td><?= h($api['name']) ?></td>
                            <td><?= h($api['category_name']) ?></td>
                            <td><?= (int) $api['price_coins'] ?> coins</td>
                            <td><code><?= h($api['api_key_value']) ?></code></td>
                            <td>
                                <form method="post" class="inline-form" onsubmit="return confirm('Delete this API?')">
                                    <input type="hidden" name="action" value="delete_api">
                                    <input type="hidden" name="return_section" value="apis">
                                    <input type="hidden" name="id" value="<?= (int) $api['id'] ?>">
                                    <button class="danger-btn" type="submit">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($section === 'users'): ?>
        <section class="section-block split-grid user-wallet-grid">
            <div class="card">
                <h4>Adjust User Coins</h4>
                <form method="post" class="stack-md">
                    <input type="hidden" name="action" value="adjust_coins">
                    <input type="hidden" name="return_section" value="users">
                    <label>User
                        <select name="userId" required>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= (int) $user['id'] ?>"><?= h($user['full_name']) ?> (<?= h($user['email']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Operation
                        <select name="operation"><option value="ADD">Add coins</option><option value="REMOVE">Remove coins</option></select>
                    </label>
                    <label>Amount<input type="number" name="coinAmount" min="1" required></label>
                    <label>Note<input name="note" placeholder="Manual admin wallet adjustment"></label>
                    <button class="primary-btn" type="submit">Apply Adjustment</button>
                </form>
            </div>
            <div class="table-card">
                <table>
                    <thead><tr><th>Name</th><th>Email</th><th>Balance</th><th>Verified</th></tr></thead>
                    <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= h($user['full_name']) ?></td>
                            <td><?= h($user['email']) ?></td>
                            <td><?= (int) $user['coin_balance'] ?></td>
                            <td><?= (int) $user['email_verified'] ? 'Yes' : 'No' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($section === 'admins'): ?>
        <section class="section-block split-grid">
            <div class="card">
                <h4>Create Admin</h4>
                <form method="post" class="stack-md">
                    <input type="hidden" name="action" value="create_admin">
                    <input type="hidden" name="return_section" value="admins">
                    <label>Username<input name="username" required></label>
                    <label>Password<input type="password" name="password" minlength="8" required></label>
                    <button class="primary-btn" type="submit">Create Admin</button>
                </form>
            </div>
            <div class="card">
                <h4>Update Admin Password</h4>
                <form method="post" class="stack-md">
                    <input type="hidden" name="action" value="update_admin_password">
                    <input type="hidden" name="return_section" value="admins">
                    <label>Admin
                        <select name="userId" required>
                            <?php foreach ($admins as $row): ?><option value="<?= (int) $row['id'] ?>"><?= h($row['username']) ?></option><?php endforeach; ?>
                        </select>
                    </label>
                    <label>New Password<input type="password" name="newPassword" minlength="8" required></label>
                    <button class="secondary-btn" type="submit">Update Password</button>
                </form>
            </div>
            <div class="table-card full-span">
                <table>
                    <thead><tr><th>Username</th><th>Created</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($admins as $row): ?>
                        <tr>
                            <td><?= h($row['username']) ?></td>
                            <td><?= h($row['created_at']) ?></td>
                            <td>
                                <form method="post" class="inline-form" onsubmit="return confirm('Delete this admin?')">
                                    <input type="hidden" name="action" value="delete_admin">
                                    <input type="hidden" name="return_section" value="admins">
                                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                    <button class="danger-btn" type="submit">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
