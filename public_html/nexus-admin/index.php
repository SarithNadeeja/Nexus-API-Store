<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$section = $_GET['section'] ?? 'dashboard';

AdminService::ensureDefaultAdmin($pdo);
$admin = Auth::requireAdmin($pdo);
$success = flash('success');
$error = flash('error');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $redirectUrl = '/nexus-admin/index.php?section=' . urlencode($_POST['return_section'] ?? $section);
    try {
        switch ($action) {
            case 'save_category':
                AdminService::saveCategory($pdo, $_POST);
                break;
            case 'delete_category':
                AdminService::deleteCategory($pdo, (int) ($_POST['id'] ?? 0));
                break;
            case 'save_api':
                $saveResult = AdminService::saveApi($pdo, $_POST);
                flash('success', $saveResult['message'] ?? 'Changes saved successfully.');
                $redirectUrl = '/nexus-admin/index.php?section=apis' . (!empty($saveResult['listingId']) ? '&edit=' . (int) $saveResult['listingId'] : '');
                break;
            case 'delete_api':
                AdminService::deleteApi($pdo, (int) ($_POST['id'] ?? 0));
                break;
            case 'create_admin':
                AdminService::createAdmin($pdo, $_POST['username'] ?? '', $_POST['password'] ?? '');
                break;
            case 'update_admin_password':
                AdminService::updateAdminPassword($pdo, (int) ($_POST['userId'] ?? 0), $_POST['newPassword'] ?? '');
                break;
            case 'delete_admin':
                AdminService::deleteAdmin($pdo, (int) ($_POST['id'] ?? 0), $admin['username']);
                break;
            case 'adjust_coins':
                AdminService::adjustUserCoins(
                    $pdo,
                    (int) ($_POST['userId'] ?? 0),
                    $_POST['operation'] ?? 'ADD',
                    (int) ($_POST['coinAmount'] ?? 0),
                    trim($_POST['note'] ?? '')
                );
                break;
            case 'save_coin_package':
                CoinPackageService::save($pdo, $_POST);
                break;
            case 'delete_coin_package':
                CoinPackageService::delete($pdo, (int) ($_POST['id'] ?? 0));
                break;
            case 'save_coin_settings':
                CoinPackageService::saveCustomSettings($pdo, $_POST);
                break;
            case 'save_contact_settings':
                CoinPackageService::saveContactSettings($pdo, $_POST);
                break;
            default:
                throw new InvalidArgumentException('Unknown action.');
        }
        if ($action !== 'save_api') {
            flash('success', 'Changes saved successfully.');
        }
    } catch (InvalidArgumentException $e) {
        flash('error', $e->getMessage());
    }
    redirect($redirectUrl);
}

$counts = AdminService::counts($pdo);
$dashboard = null;
if ($section === 'dashboard') {
    try {
        require_once dirname(__DIR__) . '/includes/DashboardService.php';
        $dashboard = DashboardService::getData($pdo);
    } catch (Throwable $e) {
        $error = $error ?: 'Dashboard failed to load: ' . $e->getMessage();
    }
}
$categories = $pdo->query('SELECT * FROM categories ORDER BY name')->fetchAll();
$apis = $pdo->query('SELECT a.*, c.name AS category_name FROM api_listings a JOIN categories c ON c.id = a.category_id ORDER BY a.id DESC')->fetchAll();
$editApiId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$editApi = null;
$editApiKeyCounts = ['hasCode' => false, 'sold' => 0];

if ($section === 'apis') {
    KeyPool::ensureSchema($pdo);
    foreach ($apis as &$apiRow) {
        $counts = KeyPool::statsForListing($pdo, (int) $apiRow['id']);
        $apiRow['has_code'] = $counts['hasCode'];
        $apiRow['key_sold'] = $counts['sold'];
        if ($editApiId > 0 && (int) $apiRow['id'] === $editApiId) {
            $editApi = $apiRow;
            $editApiKeyCounts = $counts;
        }
    }
    unset($apiRow);
}
$users = $pdo->query('SELECT * FROM app_users ORDER BY created_at DESC')->fetchAll();
$coinPackages = [];
$coinSettings = CoinPackageService::getCustomSettings($pdo);
$contactSettings = CoinPackageService::getContactSettings($pdo);
$coinSchemaError = null;

if (in_array($section, ['coin-packages', 'settings'], true)) {
    try {
        CoinPackageService::ensureSchema($pdo);
        if (!CoinPackageService::tablesReady($pdo)) {
            throw new RuntimeException('Coin tables are missing. Open /migrate-coins.php once to create them.');
        }
        $coinPackages = CoinPackageService::listAll($pdo);
        $coinSettings = CoinPackageService::getCustomSettings($pdo);
        $contactSettings = CoinPackageService::getContactSettings($pdo);
    } catch (Throwable $e) {
        $coinSchemaError = $e->getMessage();
    }
}

$admins = $pdo->query('SELECT * FROM admin_users ORDER BY id ASC')->fetchAll();
$username = Auth::adminUsername();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel | Nexus API Store</title>
    <link rel="stylesheet" href="/nexus-admin/admin.css">
    <?php if ($section === 'dashboard'): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" defer></script>
    <script src="/nexus-admin/dashboard.js" defer></script>
    <?php endif; ?>
    <?php if ($section === 'categories'): ?>
    <script src="/nexus-admin/categories.js" defer></script>
    <?php endif; ?>
    <?php if ($section === 'apis'): ?>
    <script src="/nexus-admin/apis.js" defer></script>
    <?php endif; ?>
    <?php if ($section === 'users'): ?>
    <script src="/nexus-admin/wallets.js" defer></script>
    <?php endif; ?>
    <?php if ($section === 'admins'): ?>
    <script src="/nexus-admin/admins.js" defer></script>
    <?php endif; ?>
    <?php if ($section === 'coin-packages'): ?>
    <script src="/assets/js/currency.js"></script>
    <script src="/nexus-admin/coins.js" defer></script>
    <?php endif; ?>
    <?php if ($section !== 'dashboard'): ?>
    <script src="/nexus-admin/admin-shell.js" defer></script>
    <?php endif; ?>
</head>
<body>
<div class="admin-layout">
    <aside class="sidebar">
        <div class="sidebar-top">
            <div class="brand-mark">N</div>
            <div>
                <h1>Nexus API Store</h1>
                <p>Admin Workspace</p>
            </div>
        </div>

        <nav class="sidebar-nav">
            <div class="sidebar-group-label">Main Menu</div>
            <a href="?section=dashboard" class="nav-link <?= $section === 'dashboard' ? 'active' : '' ?>"><span class="nav-icon">⌂</span><span>Dashboard</span></a>
            <a href="?section=categories" class="nav-link <?= $section === 'categories' ? 'active' : '' ?>"><span class="nav-icon">▦</span><span>Categories</span></a>
            <a href="?section=apis" class="nav-link <?= $section === 'apis' ? 'active' : '' ?>"><span class="nav-icon">🔑</span><span>API Keys</span></a>
            <a href="?section=users" class="nav-link <?= $section === 'users' ? 'active' : '' ?>"><span class="nav-icon">👛</span><span>User Wallets</span></a>

            <div class="sidebar-group-label">Administration</div>
            <a href="?section=coin-packages" class="nav-link <?= $section === 'coin-packages' ? 'active' : '' ?>"><span class="nav-icon">◎</span><span>Coin Packages</span></a>
            <a href="?section=admins" class="nav-link <?= $section === 'admins' ? 'active' : '' ?>"><span class="nav-icon">👤</span><span>Admin Accounts</span></a>
            <a href="?section=dashboard" class="nav-link"><span class="nav-icon">📋</span><span>Activity Logs</span></a>
            <a href="?section=settings" class="nav-link <?= $section === 'settings' ? 'active' : '' ?>"><span class="nav-icon">⚙</span><span>System Settings</span></a>
        </nav>

        <div class="sidebar-footer">
            <div class="help-card">
                <strong>Need Help?</strong>
                <p>View documentation and platform guides.</p>
                <a class="secondary-btn full-width" href="/about-us.html" target="_blank" rel="noreferrer">View Documentation</a>
            </div>
            <form action="/nexus-admin/logout.php" method="post">
                <button class="sidebar-signout" type="submit">Sign out</button>
            </form>
        </div>
    </aside>
    <main class="content-shell">
        <?php if (!in_array($section, ['dashboard', 'categories', 'apis', 'users', 'admins', 'coin-packages', 'settings'], true)): ?>
        <header class="topbar">
            <div><div class="eyebrow">Admin Panel</div><h2>Manage your API selling platform</h2></div>
        </header>
        <?php endif; ?>
        <?php if ($success): ?><div class="alert success"><?= h($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert error"><?= h($error) ?></div><?php endif; ?>
        <?php if (!empty($coinSchemaError) && in_array($section, ['coin-packages', 'settings'], true)): ?>
            <div class="alert error">
                Coin wallet tables need setup: <?= h($coinSchemaError) ?>
                <a href="/migrate-coins.php" target="_blank" rel="noreferrer">Run coin migration</a>
            </div>
        <?php endif; ?>

        <?php if ($section === 'dashboard' && $dashboard): ?>
        <section id="dashboard-root" class="dashboard-page section-page">
            <header class="dashboard-header section-page-header">
                <div>
                    <h2>Dashboard</h2>
                    <p>Welcome back, <?= h($username) ?>! Here's what's happening with your API platform.</p>
                    <span class="dashboard-updated" id="dashboard-updated">Live data enabled</span>
                </div>
                <div class="dashboard-header-actions section-page-actions">
                    <label class="dashboard-search">
                        <span>⌕</span>
                        <input type="search" id="dashboard-search" placeholder="Search anything..." autocomplete="off">
                        <kbd>Ctrl + K</kbd>
                    </label>
                    <button class="icon-btn" type="button" title="Notifications">
                        🔔
                        <span class="notification-dot" id="notification-count" hidden>0</span>
                    </button>
                    <div class="profile-chip">
                        <div class="profile-avatar"><?= h(strtoupper(substr($username ?? 'A', 0, 1))) ?></div>
                        <div>
                            <strong><?= h($username) ?></strong>
                            <span>Super Admin</span>
                        </div>
                        <span class="profile-chevron">▾</span>
                    </div>
                </div>
            </header>

            <div class="dashboard-stats-grid" id="dashboard-stats">
                <article class="dash-stat-card accent-purple">
                    <div class="dash-stat-icon tone-purple">▦</div>
                    <div class="dash-stat-body">
                        <div class="dash-stat-label">Categories</div>
                        <div class="dash-stat-value"><?= (int) $dashboard['counts']['categories'] ?></div>
                        <div class="dash-stat-sub">Total categories</div>
                    </div>
                </article>
                <article class="dash-stat-card accent-green">
                    <div class="dash-stat-icon tone-green">{ }</div>
                    <div class="dash-stat-body">
                        <div class="dash-stat-label">API Listings</div>
                        <div class="dash-stat-value"><?= (int) $dashboard['counts']['apis'] ?></div>
                        <div class="dash-stat-sub">Total API listings</div>
                    </div>
                </article>
                <article class="dash-stat-card accent-blue">
                    <div class="dash-stat-icon tone-blue">👥</div>
                    <div class="dash-stat-body">
                        <div class="dash-stat-label">Users</div>
                        <div class="dash-stat-value"><?= (int) $dashboard['counts']['users'] ?></div>
                        <div class="dash-stat-sub">Total registered users</div>
                    </div>
                </article>
                <article class="dash-stat-card accent-orange">
                    <div class="dash-stat-icon tone-orange">🛡</div>
                    <div class="dash-stat-body">
                        <div class="dash-stat-label">Admins</div>
                        <div class="dash-stat-value"><?= (int) $dashboard['counts']['admins'] ?></div>
                        <div class="dash-stat-sub">Total admin accounts</div>
                    </div>
                </article>
            </div>

            <div class="dashboard-main-grid">
                <article class="dashboard-panel chart-panel">
                    <div class="panel-head">
                        <div>
                            <h3>Platform Overview</h3>
                            <p>Track platform growth and API usage</p>
                        </div>
                        <label class="chart-period-select">
                            <select id="chart-period" aria-label="Chart period">
                                <option value="7" selected>Last 7 days</option>
                                <option value="14">Last 14 days</option>
                                <option value="30">Last 30 days</option>
                            </select>
                        </label>
                    </div>
                    <div class="chart-wrap">
                        <canvas id="overview-chart"></canvas>
                    </div>
                </article>

                <article class="dashboard-panel">
                    <div class="panel-head">
                        <h3>Recent Activity</h3>
                        <a href="?section=users" class="panel-link">View all</a>
                    </div>
                    <div id="recent-activity" class="activity-list">
                        <?php foreach ($dashboard['recentActivity'] as $item): ?>
                        <div class="activity-item">
                            <div class="activity-icon"><?= $item['type'] === 'user' ? '👤' : ($item['type'] === 'key' ? '🔑' : ($item['type'] === 'api' ? '{ }' : '🛡')) ?></div>
                            <div class="activity-copy">
                                <strong><?= h($item['title']) ?></strong>
                                <span><?= h($item['detail']) ?></span>
                            </div>
                            <time><?= h($item['timeAgo']) ?></time>
                        </div>
                        <?php endforeach; ?>
                        <?php if (!$dashboard['recentActivity']): ?><p class="empty-copy">No recent activity yet.</p><?php endif; ?>
                    </div>
                </article>
            </div>

            <div class="dashboard-bottom-grid">
                <article class="dashboard-panel">
                    <div class="panel-head"><h3>Top Categories</h3></div>
                    <div id="top-categories">
                        <?php if ($dashboard['topCategories']): ?>
                            <?php foreach ($dashboard['topCategories'] as $item): ?>
                            <div class="rank-row">
                                <span><?= h($item['name']) ?></span>
                                <strong><?= (int) $item['api_count'] ?> APIs</strong>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-icon">📁</div>
                                <p>No categories yet</p>
                                <span>Create your first category to get started.</span>
                                <a class="primary-btn" href="?section=categories">Create Category</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </article>

                <article class="dashboard-panel">
                    <div class="panel-head"><h3>Top APIs</h3></div>
                    <div id="top-apis">
                        <?php if ($dashboard['topApis']): ?>
                            <?php foreach ($dashboard['topApis'] as $item): ?>
                            <div class="rank-row">
                                <span><?= h($item['name']) ?></span>
                                <strong><?= (int) $item['purchase_count'] ?> purchases</strong>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-icon">{ }</div>
                                <p>No API listings yet</p>
                                <span>Add your first API listing to get started.</span>
                                <a class="primary-btn" href="?section=apis">Add API Listing</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </article>

                <article class="dashboard-panel system-panel">
                    <div class="panel-head"><h3>System Status</h3></div>
                    <div class="system-list">
                        <div class="system-row"><span>Server Status</span><em id="system-server" class="status-badge status-good"><?= h($dashboard['system']['server']) ?></em></div>
                        <div class="system-row"><span>Database</span><em id="system-database" class="status-badge status-good"><?= h($dashboard['system']['database']) ?></em></div>
                        <div class="system-row"><span>Storage</span><em id="system-storage" class="status-badge status-info"><?= h($dashboard['system']['storageLabel']) ?></em></div>
                        <div class="system-row"><span>API Response Time</span><em id="system-api" class="status-badge status-info"><?= (int) $dashboard['system']['apiResponseMs'] ?>ms</em></div>
                    </div>
                </article>
            </div>

            <footer class="dashboard-footer">
                <span>© <?= date('Y') ?> Nexus API Store. All rights reserved.</span>
                <div class="dashboard-footer-links">
                    <span>Version 1.0.0</span>
                    <a href="/index.html#security">Privacy Policy</a>
                    <a href="/index.html#faq">Terms of Service</a>
                </div>
            </footer>
        </section>
        <?php endif; ?>

        <?php if ($section === 'categories'): ?>
        <section id="categories-page" class="section-page">
            <header class="section-page-header">
                <div>
                    <div class="breadcrumbs"><a href="?section=dashboard">Dashboard</a> <span>›</span> <span>Categories</span></div>
                    <h2>Categories</h2>
                    <p>Organize your APIs with categories to help users discover your services easily.</p>
                </div>
                <div class="section-page-actions">
                    <label class="dashboard-search">
                        <span>⌕</span>
                        <input type="search" id="admin-shell-search" placeholder="Search anything..." autocomplete="off">
                        <kbd>Ctrl + K</kbd>
                    </label>
                    <button class="icon-btn" type="button" title="Notifications">🔔</button>
                    <div class="profile-chip">
                        <div class="profile-avatar"><?= h(strtoupper(substr($username ?? 'A', 0, 1))) ?></div>
                        <div>
                            <strong><?= h($username) ?></strong>
                            <span>Super Admin</span>
                        </div>
                        <span class="profile-chevron">▾</span>
                    </div>
                </div>
            </header>

            <article class="section-panel category-form-panel">
                <div class="panel-title-row">
                    <div class="panel-title-icon tone-blue">▦</div>
                    <div>
                        <h3 id="category-form-title">Add / Update Category</h3>
                        <p>Create or edit a marketplace category.</p>
                    </div>
                </div>

                <div class="category-form-layout">
                    <form method="post" class="category-form" id="category-form">
                        <input type="hidden" name="action" value="save_category">
                        <input type="hidden" name="return_section" value="categories">
                        <input type="hidden" name="id" id="category-id">

                        <label class="field-label">
                            <span>Category Name <em>*</em></span>
                            <input type="text" name="name" id="category-name" placeholder="Enter category name" required>
                        </label>

                        <label class="field-label">
                            <span>Description</span>
                            <textarea name="description" id="category-description" rows="5" placeholder="Enter category description (optional)"></textarea>
                        </label>

                        <div class="form-actions-row">
                            <button class="primary-btn" type="submit">💾 Save Category</button>
                            <button class="secondary-btn" type="button" id="category-reset-btn">✕ Reset</button>
                        </div>
                    </form>

                    <aside class="category-preview-card">
                        <div class="preview-label">Category Preview</div>
                        <div class="preview-icon-wrap">
                            <div class="preview-icon">📁</div>
                        </div>
                        <h4 id="category-preview-name">Category Name</h4>
                        <p id="category-preview-description">Category description preview</p>
                        <span class="preview-badge">New</span>
                    </aside>
                </div>
            </article>

            <article class="section-panel">
                <div class="table-toolbar">
                    <div class="panel-title-row compact">
                        <div class="panel-title-icon tone-purple">☰</div>
                        <h3>Categories List</h3>
                    </div>
                    <div class="table-toolbar-actions">
                        <label class="table-search">
                            <span>⌕</span>
                            <input type="search" id="category-table-search" placeholder="Search categories...">
                        </label>
                        <button class="primary-btn" type="button" id="category-add-btn">+ Add Category</button>
                    </div>
                </div>

                <div class="table-card flush">
                    <table class="categories-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="category-table-body">
                        <?php foreach ($categories as $index => $cat): ?>
                            <?php $visual = category_visual($index); ?>
                            <tr>
                                <td><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></td>
                                <td>
                                    <div class="category-name-cell">
                                        <span class="category-row-icon <?= h($visual['tone']) ?>"><?= $visual['icon'] ?></span>
                                        <strong><?= h($cat['name']) ?></strong>
                                    </div>
                                </td>
                                <td><?= h($cat['description'] ?: '—') ?></td>
                                <td><?= h(format_admin_date($cat['created_at'] ?? null)) ?></td>
                                <td>
                                    <div class="table-action-buttons">
                                        <button
                                            type="button"
                                            class="icon-action edit"
                                            data-edit-category
                                            data-id="<?= (int) $cat['id'] ?>"
                                            data-name="<?= h($cat['name']) ?>"
                                            data-description="<?= h($cat['description'] ?? '') ?>"
                                            title="Edit"
                                        >✎</button>
                                        <form method="post" class="inline-form" onsubmit="return confirm('Delete this category?')">
                                            <input type="hidden" name="action" value="delete_category">
                                            <input type="hidden" name="return_section" value="categories">
                                            <input type="hidden" name="id" value="<?= (int) $cat['id'] ?>">
                                            <button class="icon-action delete" type="submit" title="Delete">🗑</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$categories): ?>
                            <tr>
                                <td colspan="5" class="empty-copy">No categories yet. Create your first category above.</td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="table-footer">
                    <div class="pagination" id="category-pagination"></div>
                    <label class="per-page">
                        <select id="category-per-page">
                            <option value="5">5 per page</option>
                            <option value="10" selected>10 per page</option>
                            <option value="20">20 per page</option>
                        </select>
                    </label>
                </div>
            </article>
        </section>
        <?php endif; ?>

        <?php if ($section === 'apis'): ?>
        <section class="section-block">
            <div class="card">
                <div class="panel-title-row" style="margin-bottom:1rem;">
                    <div>
                        <h4 id="api-form-title"><?= $editApi ? 'Update API Listing' : 'Add API Listing' ?></h4>
                        <p class="muted" style="margin:0.35rem 0 0;">Upload one shared code snippet per listing. Every customer receives the same code after purchase.</p>
                    </div>
                    <?php if ($editApi): ?>
                        <a class="btn btn-secondary btn-sm" href="?section=apis">Add New Listing</a>
                    <?php endif; ?>
                </div>
                <form method="post" class="form-grid" id="api-form">
                    <input type="hidden" name="action" value="save_api">
                    <input type="hidden" name="return_section" value="apis">
                    <input type="hidden" name="id" id="api-id" value="<?= $editApi ? (int) $editApi['id'] : '' ?>">
                    <label>Name<input name="name" id="api-name" required value="<?= $editApi ? h($editApi['name']) : '' ?>"></label>
                    <label>Status
                        <select name="status" id="api-status" required>
                            <?php foreach (['ACTIVE', 'INACTIVE'] as $statusOption): ?>
                                <option value="<?= $statusOption ?>" <?= ($editApi['status'] ?? 'ACTIVE') === $statusOption ? 'selected' : '' ?>><?= $statusOption ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Category
                        <select name="category_id" id="api-category" required>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= (int) $cat['id'] ?>" <?= $editApi && (int) $editApi['category_id'] === (int) $cat['id'] ? 'selected' : '' ?>><?= h($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Price (coins)<input type="number" name="price_coins" id="api-price" min="1" value="<?= $editApi ? (int) $editApi['price_coins'] : 50 ?>" required></label>
                    <label>Expiration (months)<input type="number" name="expiration_months" id="api-expiration-months" min="1" max="120" value="<?= $editApi ? (int) ($editApi['expiration_months'] ?? 1) : 1 ?>" required></label>
                    <span class="field-hint full-span">Each sold API key will expire this many months after a customer purchases it.</span>
                    <label class="full-span">Description<textarea name="description" id="api-description" rows="3"><?= $editApi ? h($editApi['description'] ?? '') : '' ?></textarea></label>
                    <label class="full-span">
                        Code Snippet
                        <textarea name="code_snippet" id="api-code-snippet" rows="16" placeholder="const API_KEY = 'sk_live_abc123';&#10;curl -H &quot;Authorization: Bearer token_here&quot; https://api.example.com/v1/resource"><?= $editApi ? h(KeyPool::getCode($editApi)) : '' ?></textarea>
                        <span class="field-hint">Paste the full code exactly as customers should receive it. The entire textarea is saved as one snippet — nothing is split by line or <code>---</code>.</span>
                    </label>
                    <?php if ($editApi): ?>
                        <div class="full-span key-pool-stats">
                            <strong>Listing:</strong>
                            <span class="pill <?= $editApiKeyCounts['hasCode'] ? 'pill-emerald' : 'pill-amber' ?>"><?= $editApiKeyCounts['hasCode'] ? 'Code configured' : 'No code yet' ?></span>
                            <span class="pill pill-cyan"><?= (int) $editApiKeyCounts['sold'] ?> purchase<?= (int) $editApiKeyCounts['sold'] === 1 ? '' : 's' ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="full-span form-actions">
                        <button class="primary-btn" type="submit"><?= $editApi ? 'Save Changes' : 'Create Listing' ?></button>
                        <button class="btn btn-secondary" type="button" id="api-reset-btn">Clear Form</button>
                    </div>
                </form>
            </div>
            <div class="table-card">
                <div class="panel-title-row" style="margin-bottom:1rem;">
                    <div>
                        <h4>API Listings</h4>
                        <p class="muted" style="margin:0.35rem 0 0;">Each row is one product. Customers receive the same shared code snippet after purchase.</p>
                    </div>
                    <label class="dashboard-search">
                        <span>⌕</span>
                        <input type="search" id="api-table-search" placeholder="Search listings..." autocomplete="off">
                    </label>
                </div>
                <table>
                    <thead><tr><th>Name</th><th>Category</th><th>Price</th><th>Expires In</th><th>Code</th><th>Purchases</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody id="api-table-body">
                    <?php foreach ($apis as $api): ?>
                        <tr data-row>
                            <td><?= h($api['name']) ?></td>
                            <td><?= h($api['category_name']) ?></td>
                            <td><?= (int) $api['price_coins'] ?> coins</td>
                            <td><?= (int) ($api['expiration_months'] ?? 1) ?> mo</td>
                            <td><span class="pill <?= !empty($api['has_code']) ? 'pill-emerald' : 'pill-amber' ?>"><?= !empty($api['has_code']) ? 'Yes' : 'No' ?></span></td>
                            <td><?= (int) ($api['key_sold'] ?? 0) ?></td>
                            <td><?= h($api['status']) ?></td>
                            <td>
                                <div class="table-actions">
                                    <button
                                        class="icon-action"
                                        type="button"
                                        title="Edit"
                                        data-edit-api
                                        data-id="<?= (int) $api['id'] ?>"
                                        data-name="<?= h($api['name']) ?>"
                                        data-status="<?= h($api['status']) ?>"
                                        data-category-id="<?= (int) $api['category_id'] ?>"
                                        data-price="<?= (int) $api['price_coins'] ?>"
                                        data-expiration-months="<?= (int) ($api['expiration_months'] ?? 1) ?>"
                                        data-description="<?= h($api['description'] ?? '') ?>"
                                        data-has-code="<?= !empty($api['has_code']) ? '1' : '0' ?>"
                                        data-purchases="<?= (int) ($api['key_sold'] ?? 0) ?>"
                                    >✎</button>
                                    <form method="post" class="inline-form" onsubmit="return confirm('Delete this API listing?')">
                                        <input type="hidden" name="action" value="delete_api">
                                        <input type="hidden" name="return_section" value="apis">
                                        <input type="hidden" name="id" value="<?= (int) $api['id'] ?>">
                                        <button class="icon-action delete" type="submit" title="Delete">🗑</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$apis): ?>
                        <tr>
                            <td colspan="8" class="empty-copy">No API listings yet. Create one above and paste the shared code snippet.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
                <div class="table-footer">
                    <div class="pagination" id="api-pagination"></div>
                    <label class="per-page">
                        <select id="api-per-page">
                            <option value="5">5 per page</option>
                            <option value="10" selected>10 per page</option>
                            <option value="20">20 per page</option>
                        </select>
                    </label>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($section === 'users'): ?>
        <?php
            $unverifiedUsers = 0;
            foreach ($users as $u) {
                if (!db_bool($u['email_verified'])) {
                    $unverifiedUsers++;
                }
            }
        ?>
        <section id="wallets-page" class="section-page">
            <header class="section-page-header">
                <div>
                    <div class="breadcrumbs"><a href="?section=dashboard">Dashboard</a> <span>›</span> <span>User Wallets</span></div>
                    <h2>User Wallets</h2>
                    <p>Manage and adjust user wallet coins securely.</p>
                </div>
                <div class="section-page-actions">
                    <label class="dashboard-search">
                        <span>⌕</span>
                        <input type="search" id="admin-shell-search" placeholder="Search anything..." autocomplete="off">
                        <kbd>Ctrl + K</kbd>
                    </label>
                    <button class="icon-btn" type="button" title="Notifications">
                        🔔
                        <?php if ($unverifiedUsers > 0): ?><span class="notification-dot"><?= $unverifiedUsers ?></span><?php endif; ?>
                    </button>
                    <div class="profile-chip">
                        <div class="profile-avatar"><?= h(strtoupper(substr($username ?? 'A', 0, 1))) ?></div>
                        <div>
                            <strong><?= h($username) ?></strong>
                            <span>Super Admin</span>
                        </div>
                        <span class="profile-chevron">▾</span>
                    </div>
                </div>
            </header>

            <div class="wallets-layout">
                <article class="section-panel wallet-form-panel">
                    <div class="panel-title-row">
                        <div class="panel-title-icon tone-blue">👛</div>
                        <div>
                            <h3>Adjust User Coins</h3>
                            <p>Credit or debit coins from a user wallet.</p>
                        </div>
                    </div>

                    <form method="post" class="wallet-form" id="wallet-adjust-form">
                        <input type="hidden" name="action" value="adjust_coins">
                        <input type="hidden" name="return_section" value="users">

                        <label class="field-label">
                            <span>User <em>*</em></span>
                            <div class="user-combobox" id="wallet-user-combobox">
                                <input type="hidden" name="userId" id="wallet-user-id" value="">
                                <div class="user-combobox-control">
                                    <span class="user-combobox-icon">⌕</span>
                                    <input
                                        type="text"
                                        id="wallet-user-search"
                                        placeholder="Type name or email to search users..."
                                        autocomplete="off"
                                        <?= !$users ? 'disabled' : '' ?>
                                    >
                                    <button class="user-combobox-clear" type="button" id="wallet-user-clear" hidden aria-label="Clear selected user">×</button>
                                </div>
                                <ul class="user-combobox-list" id="wallet-user-list" hidden></ul>
                            </div>
                            <script type="application/json" id="wallet-users-data"><?= json_encode(array_map(static function (array $user): array {
                                return [
                                    'id' => (int) $user['id'],
                                    'name' => $user['full_name'],
                                    'email' => $user['email'],
                                    'balance' => (int) $user['coin_balance'],
                                ];
                            }, $users), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
                        </label>

                        <label class="field-label">
                            <span>Operation <em>*</em></span>
                            <select name="operation" required>
                                <option value="ADD">Add coins</option>
                                <option value="REMOVE">Remove coins</option>
                            </select>
                        </label>

                        <label class="field-label">
                            <span>Amount <em>*</em></span>
                            <div class="input-with-icon">
                                <span class="input-icon">◎</span>
                                <input type="number" name="coinAmount" min="1" placeholder="Enter amount" required>
                            </div>
                        </label>

                        <label class="field-label">
                            <span>Note</span>
                            <textarea name="note" rows="4" placeholder="Write a note for this adjustment (optional)"></textarea>
                        </label>

                        <button class="primary-btn full-width" type="submit" <?= !$users ? 'disabled' : '' ?>>✈ Apply Adjustment</button>
                    </form>

                    <div class="info-box">
                        <span class="info-box-icon">ℹ</span>
                        <p><strong>Important:</strong> All wallet adjustments are recorded in the activity logs for transparency and security.</p>
                    </div>
                </article>

                <article class="section-panel wallet-table-panel">
                    <div class="table-toolbar">
                        <div class="panel-title-row compact">
                            <div class="panel-title-icon tone-purple">☰</div>
                            <h3>Users Wallet Overview</h3>
                        </div>
                        <div class="table-toolbar-actions">
                            <label class="table-search">
                                <span>⌕</span>
                                <input type="search" id="wallet-table-search" placeholder="Search users...">
                            </label>
                            <button class="secondary-btn" type="button" id="wallet-export-btn">⬇ Export</button>
                        </div>
                    </div>

                    <div class="table-card flush">
                        <table class="wallets-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Balance</th>
                                    <th>Verified</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="wallet-table-body">
                            <?php foreach ($users as $index => $user): ?>
                                <?php $tone = user_avatar_tone($index); $verified = db_bool($user['email_verified']); ?>
                                <tr
                                    data-user-row
                                    data-user-id="<?= (int) $user['id'] ?>"
                                    data-name="<?= h($user['full_name']) ?>"
                                    data-email="<?= h($user['email']) ?>"
                                    data-balance="<?= (int) $user['coin_balance'] ?>"
                                    data-verified="<?= $verified ? 'Yes' : 'No' ?>"
                                >
                                    <td>
                                        <div class="user-name-cell">
                                            <span class="user-avatar <?= h($tone) ?>"><?= h(strtoupper(substr($user['full_name'], 0, 1))) ?></span>
                                            <strong><?= h($user['full_name']) ?></strong>
                                        </div>
                                    </td>
                                    <td><?= h($user['email']) ?></td>
                                    <td><span class="coin-balance">◎ <?= number_format((int) $user['coin_balance']) ?></span></td>
                                    <td>
                                        <span class="status-badge <?= $verified ? 'status-good' : 'status-bad' ?>">
                                            <?= $verified ? 'Yes' : 'No' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="wallet-actions">
                                            <button class="icon-action menu" type="button" data-wallet-menu-toggle title="Actions">⋮</button>
                                            <div class="wallet-menu">
                                                <button
                                                    type="button"
                                                    data-select-wallet-user
                                                    data-user-id="<?= (int) $user['id'] ?>"
                                                    data-user-name="<?= h($user['full_name']) ?>"
                                                    data-user-email="<?= h($user['email']) ?>"
                                                >Adjust wallet</button>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$users): ?>
                                <tr>
                                    <td colspan="5" class="empty-copy">No registered users yet.</td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="table-footer wallet-table-footer">
                        <div class="wallet-summary" id="wallet-table-summary">Showing 0 users</div>
                        <div class="pagination" id="wallet-pagination"></div>
                        <label class="per-page">
                            <select id="wallet-per-page">
                                <option value="5">5 per page</option>
                                <option value="10" selected>10 per page</option>
                                <option value="20">20 per page</option>
                            </select>
                        </label>
                    </div>
                </article>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($section === 'coin-packages'): ?>
        <section id="coin-packages-page" class="section-page">
            <header class="section-page-header">
                <div>
                    <div class="breadcrumbs"><a href="?section=dashboard">Dashboard</a> <span>›</span> <span>Coin Packages</span></div>
                    <h2>Coin Packages</h2>
                    <p>Manage recharge packages and update coin prices shown on the customer wallet.</p>
                </div>
                <div class="section-page-actions">
                    <label class="dashboard-search">
                        <span>⌕</span>
                        <input type="search" id="admin-shell-search" placeholder="Search anything..." autocomplete="off">
                        <kbd>Ctrl + K</kbd>
                    </label>
                    <button class="icon-btn" type="button" title="Notifications">🔔</button>
                    <div class="profile-chip">
                        <div class="profile-avatar"><?= h(strtoupper(substr($username ?? 'A', 0, 1))) ?></div>
                        <div>
                            <strong><?= h($username) ?></strong>
                            <span>Super Admin</span>
                        </div>
                        <span class="profile-chevron">▾</span>
                    </div>
                </div>
            </header>

            <article class="section-panel">
                <div class="panel-title-row">
                    <div class="panel-title-icon tone-green">⚙</div>
                    <div>
                        <h3>Custom Coin Recharge</h3>
                        <p>Let customers enter a custom coin amount on the wallet dashboard.</p>
                    </div>
                </div>

                <form method="post" class="category-form coin-settings-form">
                    <input type="hidden" name="action" value="save_coin_settings">
                    <input type="hidden" name="return_section" value="coin-packages">

                    <label class="field-label checkbox-field">
                        <input type="checkbox" name="custom_recharge_enabled" value="1" <?= db_bool($coinSettings['custom_recharge_enabled']) ? 'checked' : '' ?>>
                        <span>Enable custom coin recharge on customer dashboard</span>
                    </label>

                    <div class="coin-package-fields-row three-up">
                        <label class="field-label">
                            <span>Price per coin (LKR) <em>*</em></span>
                            <input type="number" name="custom_coin_price_usd" min="0.01" step="0.01" value="<?= h(number_format((float) $coinSettings['custom_coin_price_usd'], 2, '.', '')) ?>" required>
                        </label>
                        <label class="field-label">
                            <span>Minimum coins <em>*</em></span>
                            <input type="number" name="custom_coin_min" min="1" value="<?= (int) $coinSettings['custom_coin_min'] ?>" required>
                        </label>
                        <label class="field-label">
                            <span>Maximum coins <em>*</em></span>
                            <input type="number" name="custom_coin_max" min="1" value="<?= (int) $coinSettings['custom_coin_max'] ?>" required>
                        </label>
                    </div>

                    <button class="primary-btn" type="submit">💾 Save Custom Settings</button>
                </form>
            </article>

            <article class="section-panel">
                <div class="panel-title-row">
                    <div class="panel-title-icon tone-blue">◎</div>
                    <div>
                        <h3 id="coin-package-form-title">Add / Update Coin Package</h3>
                        <p>Set coin amount, LKR price, and display style for wallet recharge.</p>
                    </div>
                </div>

                <div class="category-form-layout">
                    <form method="post" class="category-form" id="coin-package-form">
                        <input type="hidden" name="action" value="save_coin_package">
                        <input type="hidden" name="return_section" value="coin-packages">
                        <input type="hidden" name="id" id="coin-package-id">

                        <label class="field-label">
                            <span>Package Name <em>*</em></span>
                            <input type="text" name="name" id="coin-package-name" placeholder="e.g. Starter Pack" required>
                        </label>

                        <div class="coin-package-fields-row">
                            <label class="field-label">
                                <span>Coins <em>*</em></span>
                                <input type="number" name="coin_amount" id="coin-package-amount" min="1" placeholder="100" required>
                            </label>
                            <label class="field-label">
                                <span>Price (LKR) <em>*</em></span>
                                <input type="number" name="price_usd" id="coin-package-price" min="1" step="1" placeholder="300" required>
                            </label>
                        </div>

                        <div class="coin-package-fields-row">
                            <label class="field-label">
                                <span>Card Color</span>
                                <select name="tone" id="coin-package-tone">
                                    <option value="package-blue">Blue</option>
                                    <option value="package-purple">Purple</option>
                                    <option value="package-orange">Orange</option>
                                    <option value="package-green">Green</option>
                                </select>
                            </label>
                            <label class="field-label">
                                <span>Sort Order</span>
                                <input type="number" name="sort_order" id="coin-package-sort" min="0" value="0">
                            </label>
                        </div>

                        <label class="field-label checkbox-field">
                            <input type="checkbox" name="is_active" id="coin-package-active" value="1" checked>
                            <span>Active (visible to customers)</span>
                        </label>

                        <div class="form-actions-row">
                            <button class="primary-btn" type="submit">💾 Save Package</button>
                            <button class="secondary-btn" type="button" id="coin-package-reset-btn">✕ Reset</button>
                        </div>
                    </form>

                    <aside class="coin-package-preview-card">
                        <div class="preview-label">Customer Preview</div>
                        <button class="wallet-package-card preview-package" id="coin-package-preview" type="button">
                            <span class="wallet-package-icon">◎</span>
                            <strong id="coin-package-preview-coins">100 Coins</strong>
                            <span class="wallet-package-price" id="coin-package-preview-price">LKR 300.00</span>
                        </button>
                        <p id="coin-package-preview-name">Starter Pack</p>
                    </aside>
                </div>
            </article>

            <article class="section-panel">
                <div class="table-toolbar">
                    <div class="panel-title-row compact">
                        <div class="panel-title-icon tone-purple">☰</div>
                        <h3>Coin Packages List</h3>
                    </div>
                    <div class="table-toolbar-actions">
                        <label class="table-search">
                            <span>⌕</span>
                            <input type="search" id="coin-package-table-search" placeholder="Search packages...">
                        </label>
                        <button class="primary-btn" type="button" id="coin-package-add-btn">+ Add Package</button>
                    </div>
                </div>

                <div class="table-card flush">
                    <table class="categories-table coin-packages-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Package</th>
                                <th>Coins</th>
                                <th>Price</th>
                                <th>Status</th>
                                <th>Order</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="coin-package-table-body">
                        <?php foreach ($coinPackages as $index => $package): ?>
                            <?php $active = db_bool($package['is_active']); ?>
                            <tr
                                data-package-row
                                data-id="<?= (int) $package['id'] ?>"
                                data-name="<?= h($package['name']) ?>"
                                data-coin-amount="<?= (int) $package['coin_amount'] ?>"
                                data-price-usd="<?= h((string) $package['price_usd']) ?>"
                                data-tone="<?= h($package['tone']) ?>"
                                data-sort-order="<?= (int) $package['sort_order'] ?>"
                                data-is-active="<?= $active ? '1' : '0' ?>"
                            >
                                <td><?= (int) $package['id'] ?></td>
                                <td>
                                    <div class="coin-package-name-cell">
                                        <span class="wallet-package-icon mini <?= h($package['tone']) ?>">◎</span>
                                        <strong><?= h($package['name']) ?></strong>
                                    </div>
                                </td>
                                <td><?= number_format((int) $package['coin_amount']) ?> coins</td>
                                <td><?= format_lkr($package['price_usd']) ?></td>
                                <td>
                                    <span class="status-badge <?= $active ? 'status-good' : 'status-warn' ?>">
                                        <?= $active ? 'Active' : 'Hidden' ?>
                                    </span>
                                </td>
                                <td><?= (int) $package['sort_order'] ?></td>
                                <td>
                                    <div class="table-action-buttons">
                                        <button class="icon-action edit" type="button" data-edit-coin-package data-id="<?= (int) $package['id'] ?>" title="Edit">✎</button>
                                        <form method="post" class="inline-form" onsubmit="return confirm('Delete this coin package?')">
                                            <input type="hidden" name="action" value="delete_coin_package">
                                            <input type="hidden" name="return_section" value="coin-packages">
                                            <input type="hidden" name="id" value="<?= (int) $package['id'] ?>">
                                            <button class="icon-action delete" type="submit" title="Delete">🗑</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$coinPackages): ?>
                            <tr><td colspan="7" class="empty-copy">No coin packages yet.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="table-footer">
                    <div class="wallet-summary" id="coin-package-table-summary">Showing 0 packages</div>
                    <div class="pagination" id="coin-package-pagination"></div>
                    <label class="per-page">
                        <select id="coin-package-per-page">
                            <option value="5">5 per page</option>
                            <option value="10" selected>10 per page</option>
                            <option value="20">20 per page</option>
                        </select>
                    </label>
                </div>
            </article>
        </section>
        <?php endif; ?>

        <?php if ($section === 'settings'): ?>
        <section id="settings-page" class="section-page">
            <header class="section-page-header">
                <div>
                    <div class="breadcrumbs"><a href="?section=dashboard">Dashboard</a> <span>›</span> <span>System Settings</span></div>
                    <h2>System Settings</h2>
                    <p>Manage platform contact details used for customer recharge requests.</p>
                </div>
                <div class="section-page-actions">
                    <label class="dashboard-search">
                        <span>⌕</span>
                        <input type="search" id="admin-shell-search" placeholder="Search anything..." autocomplete="off">
                        <kbd>Ctrl + K</kbd>
                    </label>
                    <button class="icon-btn" type="button" title="Notifications">🔔</button>
                    <div class="profile-chip">
                        <div class="profile-avatar"><?= h(strtoupper(substr($username ?? 'A', 0, 1))) ?></div>
                        <div>
                            <strong><?= h($username) ?></strong>
                            <span>Super Admin</span>
                        </div>
                        <span class="profile-chevron">▾</span>
                    </div>
                </div>
            </header>

            <article class="section-panel">
                <div class="panel-title-row">
                    <div class="panel-title-icon tone-green">📱</div>
                    <div>
                        <h3>Contact Details</h3>
                        <p>Update the WhatsApp number customers use when requesting coin recharges from the dashboard.</p>
                    </div>
                </div>

                <form method="post" class="category-form contact-settings-form">
                    <input type="hidden" name="action" value="save_contact_settings">
                    <input type="hidden" name="return_section" value="settings">

                    <label class="field-label">
                        <span>WhatsApp Number <em>*</em></span>
                        <input
                            type="text"
                            name="whatsapp_number"
                            value="<?= h($contactSettings['whatsappNumber']) ?>"
                            placeholder="e.g. 94771234567"
                            required
                            autocomplete="tel"
                        >
                    </label>
                    <p class="form-hint">Country code + number only, no + or spaces. Example: 94771234567 for Sri Lanka.</p>

                    <?php if ($contactSettings['whatsappNumber'] !== ''): ?>
                        <div class="contact-preview-card">
                            <strong>Current recharge link</strong>
                            <p>Customers will message this number when they click “Request via WhatsApp” on the dashboard.</p>
                            <code>+<?= h($contactSettings['whatsappNumber']) ?></code>
                        </div>
                    <?php endif; ?>

                    <button class="primary-btn" type="submit">💾 Save Contact Details</button>
                </form>
            </article>
        </section>
        <?php endif; ?>

        <?php if ($section === 'admins'): ?>
        <section id="admins-page" class="section-page">
            <header class="section-page-header">
                <div>
                    <div class="breadcrumbs"><a href="?section=dashboard">Dashboard</a> <span>›</span> <span>Admin Accounts</span></div>
                    <h2>Admin Accounts</h2>
                    <p>Create new admin accounts and manage existing administrators.</p>
                </div>
                <div class="section-page-actions">
                    <label class="dashboard-search">
                        <span>⌕</span>
                        <input type="search" id="admin-shell-search" placeholder="Search anything..." autocomplete="off">
                        <kbd>Ctrl + K</kbd>
                    </label>
                    <button class="icon-btn" type="button" title="Notifications">
                        🔔
                        <?php
                            $pendingAdmins = 0;
                            foreach ($admins as $row) {
                                if (AdminService::mustChangeCredentials($row)) {
                                    $pendingAdmins++;
                                }
                            }
                        ?>
                        <?php if ($pendingAdmins > 0): ?><span class="notification-dot"><?= $pendingAdmins ?></span><?php endif; ?>
                    </button>
                    <div class="profile-chip">
                        <div class="profile-avatar"><?= h(strtoupper(substr($username ?? 'A', 0, 1))) ?></div>
                        <div>
                            <strong><?= h($username) ?></strong>
                            <span>Super Admin</span>
                        </div>
                        <span class="profile-chevron">▾</span>
                    </div>
                </div>
            </header>

            <div class="admins-forms-grid">
                <article class="section-panel admin-create-panel">
                    <div class="panel-title-row">
                        <div class="panel-title-icon tone-blue">👤</div>
                        <div>
                            <h3>Create New Admin</h3>
                            <p>Add a trusted administrator to the workspace.</p>
                        </div>
                    </div>

                    <form method="post" class="admin-form" id="create-admin-form">
                        <input type="hidden" name="action" value="create_admin">
                        <input type="hidden" name="return_section" value="admins">

                        <label class="field-label">
                            <span>Username <em>*</em></span>
                            <input type="text" name="username" placeholder="Enter username" required autocomplete="off">
                        </label>

                        <label class="field-label">
                            <span>Password <em>*</em></span>
                            <div class="password-field">
                                <input type="password" name="password" id="create-admin-password" minlength="8" placeholder="Enter password" required autocomplete="new-password">
                                <button class="password-toggle" type="button" data-password-target="create-admin-password" aria-label="Show password">👁</button>
                            </div>
                        </label>

                        <p class="form-hint">The new admin will receive default permissions.</p>

                        <button class="primary-btn full-width" type="submit">👤 Create Admin</button>
                    </form>
                </article>

                <article class="section-panel admin-password-panel">
                    <div class="panel-title-row">
                        <div class="panel-title-icon tone-purple">🔑</div>
                        <div>
                            <h3>Update Admin Password</h3>
                            <p>Reset credentials for an existing administrator.</p>
                        </div>
                    </div>

                    <form method="post" class="admin-form" id="update-admin-password-form">
                        <input type="hidden" name="action" value="update_admin_password">
                        <input type="hidden" name="return_section" value="admins">

                        <label class="field-label">
                            <span>Admin <em>*</em></span>
                            <select name="userId" id="admin-password-user-id" required>
                                <option value="">Select admin</option>
                                <?php foreach ($admins as $row): ?>
                                    <option value="<?= (int) $row['id'] ?>"><?= h($row['username']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label class="field-label">
                            <span>New Password <em>*</em></span>
                            <div class="password-field">
                                <input type="password" name="newPassword" id="update-admin-password" minlength="8" placeholder="Enter new password" required autocomplete="new-password">
                                <button class="password-toggle" type="button" data-password-target="update-admin-password" aria-label="Show password">👁</button>
                            </div>
                        </label>

                        <p class="form-hint">Choose a strong password to keep the account secure.</p>

                        <button class="primary-btn tone-purple full-width" type="submit" <?= !$admins ? 'disabled' : '' ?>>🔒 Update Password</button>
                    </form>
                </article>
            </div>

            <article class="section-panel admins-table-panel">
                <div class="table-toolbar">
                    <div class="panel-title-row compact">
                        <div class="panel-title-icon tone-blue">☰</div>
                        <h3>Administrator Accounts</h3>
                    </div>
                    <div class="table-toolbar-actions">
                        <label class="table-search">
                            <span>⌕</span>
                            <input type="search" id="admins-table-search" placeholder="Search admins...">
                        </label>
                        <div class="filter-dropdown">
                            <button class="secondary-btn" type="button" id="admins-filter-toggle">⚙ Filter</button>
                            <div class="filter-menu" id="admins-filter-menu">
                                <button type="button" data-admin-filter="all" class="active">All admins</button>
                                <button type="button" data-admin-filter="active">Active only</button>
                                <button type="button" data-admin-filter="pending">Setup required</button>
                            </div>
                        </div>
                        <button class="secondary-btn" type="button" id="admins-export-btn">⬇ Export</button>
                    </div>
                </div>

                <div class="table-card flush">
                    <table class="admins-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Username</th>
                                <th>Created At</th>
                                <th>Last Login</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="admins-table-body">
                        <?php foreach ($admins as $index => $row): ?>
                            <?php
                                $pendingSetup = AdminService::mustChangeCredentials($row);
                                $status = $pendingSetup ? 'pending' : 'active';
                                $tone = user_avatar_tone($index);
                            ?>
                            <tr
                                data-admin-row
                                data-admin-id="<?= (int) $row['id'] ?>"
                                data-username="<?= h($row['username']) ?>"
                                data-created="<?= h($row['created_at'] ?? '') ?>"
                                data-last-login="<?= h($row['last_login'] ?? '') ?>"
                                data-status="<?= h($status) ?>"
                            >
                                <td><?= (int) $row['id'] ?></td>
                                <td>
                                    <div class="admin-username-cell">
                                        <span class="user-avatar <?= h($tone) ?>"><?= h(strtoupper(substr($row['username'], 0, 1))) ?></span>
                                        <div>
                                            <strong><?= h($row['username']) ?></strong>
                                            <span class="role-badge">Super Admin</span>
                                        </div>
                                    </div>
                                </td>
                                <td><?= h(format_admin_datetime($row['created_at'] ?? null)) ?></td>
                                <td><?= h(format_admin_datetime($row['last_login'] ?? null)) ?></td>
                                <td>
                                    <span class="status-badge <?= $pendingSetup ? 'status-warn' : 'status-good' ?>">
                                        <?= $pendingSetup ? 'Setup Required' : 'Active' ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="wallet-actions">
                                        <button class="icon-action menu" type="button" data-admin-menu-toggle title="Actions">⋮</button>
                                        <div class="wallet-menu admin-menu">
                                            <button type="button" data-select-admin-password data-admin-id="<?= (int) $row['id'] ?>">Update password</button>
                                            <?php if ($row['username'] !== $username): ?>
                                                <form method="post" onsubmit="return confirm('Delete this admin account?')">
                                                    <input type="hidden" name="action" value="delete_admin">
                                                    <input type="hidden" name="return_section" value="admins">
                                                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                                    <button type="submit">Delete admin</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$admins): ?>
                            <tr>
                                <td colspan="6" class="empty-copy">No admin accounts found.</td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="table-footer wallet-table-footer">
                    <div class="wallet-summary" id="admins-table-summary">Showing 0 admins</div>
                    <div class="pagination" id="admins-pagination"></div>
                    <label class="per-page">
                        <select id="admins-per-page">
                            <option value="5">5 per page</option>
                            <option value="10" selected>10 per page</option>
                            <option value="20">20 per page</option>
                        </select>
                    </label>
                </div>
            </article>

            <div class="security-banner">
                <div class="security-banner-copy">
                    <span class="security-banner-icon">🛡</span>
                    <div>
                        <strong>Security Best Practices</strong>
                        <p>Use strong passwords and limit admin access to trusted users only. All admin actions are logged in the activity logs for security.</p>
                    </div>
                </div>
                <a class="secondary-btn" href="?section=dashboard">View Activity Logs</a>
            </div>
        </section>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
