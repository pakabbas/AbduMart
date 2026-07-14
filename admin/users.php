<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

$adminSection = 'users';
$tab = $_GET['tab'] ?? 'admins';
if (!in_array($tab, ['admins', 'customers'], true)) {
    $tab = 'admins';
}

$search = trim($_GET['q'] ?? '');
$currentAdminId = (int) current_user()['id'];

function build_customer_query(string $search): array
{
    $sql = "SELECT u.id, u.email, u.first_name, u.last_name, u.phone, u.google_id, u.email_verified_at, u.created_at,
            (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) AS order_count,
            (SELECT COALESCE(SUM(o.total), 0) FROM orders o WHERE o.user_id = u.id AND o.status != 'cancelled') AS order_total
         FROM users u
         WHERE u.role = 'customer'";
    $params = [];
    if ($search !== '') {
        $sql .= ' AND (u.email LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.phone LIKE ?)';
        $like = '%' . $search . '%';
        $params = [$like, $like, $like, $like];
    }
    $sql .= ' ORDER BY u.created_at DESC';

    return [$sql, $params];
}

function fetch_customers(string $search, ?int $limit = null): array
{
    [$sql, $params] = build_customer_query($search);
    if ($limit !== null) {
        $sql .= ' LIMIT ' . (int) $limit;
    }
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        flash('danger', 'Invalid request.');
    } else {
        $action = $_POST['action'] ?? '';
        $redirectTab = $_POST['tab'] ?? $tab;
        $redirectUrl = 'users.php?tab=' . rawurlencode($redirectTab);
        if ($redirectTab === 'customers' && trim($_POST['q'] ?? '') !== '') {
            $redirectUrl .= '&q=' . rawurlencode(trim($_POST['q']));
        }

        try {
            if ($action === 'create_admin') {
                $result = create_or_promote_admin(
                    (string) ($_POST['email'] ?? ''),
                    (string) ($_POST['first_name'] ?? ''),
                    (string) ($_POST['last_name'] ?? ''),
                    trim($_POST['phone'] ?? '') ?: null,
                    (string) ($_POST['password'] ?? '')
                );
                flash(
                    'success',
                    $result['created']
                        ? 'Admin account created for ' . $result['email'] . '.'
                        : 'Existing customer promoted to admin: ' . $result['email'] . '.'
                );
            } elseif ($action === 'demote_admin') {
                demote_admin_user((int) ($_POST['user_id'] ?? 0), $currentAdminId);
                flash('success', 'Admin access removed. User is now a customer.');
            } elseif ($action === 'promote_admin') {
                promote_user_to_admin((int) ($_POST['user_id'] ?? 0));
                flash('success', 'Customer promoted to admin.');
                $redirectUrl = 'users.php?tab=admins';
            }
        } catch (Throwable $e) {
            flash('danger', $e->getMessage());
        }

        redirect($redirectUrl);
    }
}

if (isset($_GET['export']) && $_GET['export'] === 'csv' && $tab === 'customers') {
    $exportRows = fetch_customers($search);
    $filename = 'customers-' . date('Y-m-d');
    if ($search !== '') {
        $filename .= '-filtered';
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['First Name', 'Last Name', 'Email', 'Phone', 'Orders', 'Total Spent', 'Sign-in', 'Joined']);
    foreach ($exportRows as $row) {
        fputcsv($out, [
            $row['first_name'],
            $row['last_name'],
            $row['email'],
            $row['phone'] ?: '',
            (int) $row['order_count'],
            number_format((float) $row['order_total'], 2, '.', ''),
            !empty($row['google_id']) ? 'Google' : 'Email',
            date('Y-m-d', strtotime($row['created_at'])),
        ]);
    }
    fclose($out);
    exit;
}

$adminUsers = db()->query(
    "SELECT u.id, u.email, u.first_name, u.last_name, u.phone, u.google_id, u.email_verified_at, u.created_at
     FROM users u
     WHERE u.role = 'admin'
     ORDER BY u.created_at ASC"
)->fetchAll();

$customers = fetch_customers($search, 100);

$customerTotal = (int) db()->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn();
if ($search === '') {
    $customerFilteredTotal = $customerTotal;
} else {
    $countStmt = db()->prepare(
        "SELECT COUNT(*) FROM users u WHERE u.role = 'customer'
         AND (u.email LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.phone LIKE ?)"
    );
    $like = '%' . $search . '%';
    $countStmt->execute([$like, $like, $like, $like]);
    $customerFilteredTotal = (int) $countStmt->fetchColumn();
}

$pageTitle = 'Users';
$pageSubtitle = $tab === 'admins'
    ? 'Manage admin access and permissions'
    : 'Browse storefront customer accounts';
$exportUrl = 'users.php?tab=customers&export=csv' . ($search !== '' ? '&q=' . rawurlencode($search) : '');
$headerActions = $tab === 'admins'
    ? '<a href="#add-admin-panel" class="admin-btn admin-btn-primary"><i class="bi bi-person-plus"></i> Add admin</a>'
    : '<a href="' . e($exportUrl) . '" class="admin-btn admin-btn-outline"><i class="bi bi-download"></i> Export CSV</a>';

require dirname(__DIR__) . '/includes/admin_header.php';
?>

<div class="admin-tabbar mb-4">
    <nav class="admin-tabbar-nav" aria-label="User sections">
        <a href="users.php?tab=admins" class="admin-tabbar-item<?= $tab === 'admins' ? ' is-active' : '' ?>">
            <span class="admin-tabbar-icon"><i class="bi bi-shield-lock"></i></span>
            <span class="admin-tabbar-copy">
                <strong>System Admins</strong>
                <small>Dashboard access</small>
            </span>
            <span class="admin-tabbar-count"><?= count($adminUsers) ?></span>
        </a>
        <a href="users.php?tab=customers" class="admin-tabbar-item<?= $tab === 'customers' ? ' is-active' : '' ?>">
            <span class="admin-tabbar-icon"><i class="bi bi-people"></i></span>
            <span class="admin-tabbar-copy">
                <strong>Customers</strong>
                <small>Storefront accounts</small>
            </span>
            <span class="admin-tabbar-count"><?= $customerTotal ?></span>
        </a>
    </nav>
</div>

<?php if ($tab === 'admins'): ?>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="admin-card">
            <div class="admin-card-header">
                <h2>Admin accounts</h2>
                <span class="admin-badge admin-badge-green"><?= count($adminUsers) ?> admin<?= count($adminUsers) === 1 ? '' : 's' ?></span>
            </div>
            <div class="table-responsive">
                <table class="admin-table admin-table-compact">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Sign-in</th>
                            <th>Since</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($adminUsers as $admin): ?>
                        <tr>
                            <td>
                                <strong><?= e(trim($admin['first_name'] . ' ' . $admin['last_name'])) ?></strong>
                                <?php if ((int) $admin['id'] === $currentAdminId): ?>
                                <span class="admin-badge ms-1">You</span>
                                <?php endif; ?>
                            </td>
                            <td><?= e($admin['email']) ?></td>
                            <td><?= e($admin['phone'] ?: '—') ?></td>
                            <td>
                                <?php if (!empty($admin['google_id'])): ?>
                                <span class="admin-badge admin-badge-green">Google</span>
                                <?php else: ?>
                                <span class="admin-badge">Email</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-nowrap"><?= e(date('M j, Y', strtotime($admin['created_at']))) ?></td>
                            <td class="text-end">
                                <?php if ((int) $admin['id'] !== $currentAdminId && count($adminUsers) > 1): ?>
                                <form method="post" class="d-inline" onsubmit="return confirm('Remove admin access for <?= e($admin['email']) ?>? They will become a customer.');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="demote_admin">
                                    <input type="hidden" name="user_id" value="<?= (int) $admin['id'] ?>">
                                    <input type="hidden" name="tab" value="admins">
                                    <button type="submit" class="admin-btn admin-btn-outline admin-btn-sm text-danger">
                                        <i class="bi bi-person-dash"></i> Remove admin
                                    </button>
                                </form>
                                <?php else: ?>
                                <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="admin-card h-100" id="add-admin-panel">
            <div class="admin-card-header"><h2>Add an admin</h2></div>
            <div class="admin-card-body padded">
                <p class="text-muted small">Create a new admin or promote an existing customer by email. They can sign in with email/password or Google if linked.</p>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create_admin">
                    <input type="hidden" name="tab" value="admins">
                    <div class="admin-field">
                        <label for="admin_email">Email</label>
                        <input type="email" id="admin_email" name="email" class="admin-input" required placeholder="admin@example.com">
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="admin-field">
                                <label for="admin_first_name">First name</label>
                                <input type="text" id="admin_first_name" name="first_name" class="admin-input" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="admin-field">
                                <label for="admin_last_name">Last name</label>
                                <input type="text" id="admin_last_name" name="last_name" class="admin-input" required>
                            </div>
                        </div>
                    </div>
                    <div class="admin-field">
                        <label for="admin_phone">Phone <span class="text-muted">(optional)</span></label>
                        <input type="text" id="admin_phone" name="phone" class="admin-input" placeholder="(248) 555-0100">
                    </div>
                    <div class="admin-field mb-0">
                        <label for="admin_password">Password</label>
                        <input type="password" id="admin_password" name="password" class="admin-input" required minlength="8" autocomplete="new-password" placeholder="Min. 8 characters">
                        <div class="hint">Required for new admins. Resets password when promoting a customer.</div>
                    </div>
                    <button type="submit" class="admin-btn admin-btn-primary w-100 mt-3">
                        <i class="bi bi-person-plus"></i> Save admin
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php else: ?>

<div class="admin-card mb-4">
    <div class="admin-card-body padded">
        <form method="get" class="row g-3 align-items-end">
            <input type="hidden" name="tab" value="customers">
            <div class="col-md-8">
                <div class="admin-field mb-0">
                    <label for="q">Search customers</label>
                    <input type="search" id="q" name="q" class="admin-input" value="<?= e($search) ?>" placeholder="Name, email, or phone">
                </div>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="admin-btn admin-btn-primary flex-grow-1">Search</button>
                <?php if ($search !== ''): ?>
                <a href="users.php?tab=customers" class="admin-btn admin-btn-outline">Clear</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="admin-card">
    <div class="admin-card-header">
        <h2>Customer accounts</h2>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="admin-badge"><?= count($customers) ?> of <?= $customerFilteredTotal ?> shown</span>
            <a href="<?= e($exportUrl) ?>" class="admin-btn admin-btn-outline admin-btn-sm">
                <i class="bi bi-download"></i> Export CSV
            </a>
        </div>
    </div>
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Customer</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Orders</th>
                    <th>Spent</th>
                    <th>Joined</th>
                    <th>Sign-in</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($customers)): ?>
                <tr>
                    <td colspan="8">
                        <div class="admin-empty py-4">
                            <i class="bi bi-people"></i>
                            <p><?= $search !== '' ? 'No customers match your search.' : 'No customer accounts yet.' ?></p>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($customers as $customer): ?>
                <tr>
                    <td><strong><?= e(trim($customer['first_name'] . ' ' . $customer['last_name'])) ?></strong></td>
                    <td><?= e($customer['email']) ?></td>
                    <td><?= e($customer['phone'] ?: '—') ?></td>
                    <td><?= (int) $customer['order_count'] ?></td>
                    <td><?= e(format_money($customer['order_total'])) ?></td>
                    <td class="text-nowrap"><?= e(date('M j, Y', strtotime($customer['created_at']))) ?></td>
                    <td>
                        <?php if (!empty($customer['google_id'])): ?>
                        <span class="admin-badge admin-badge-green">Google</span>
                        <?php else: ?>
                        <span class="admin-badge">Email</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <form method="post" class="d-inline" onsubmit="return confirm('Promote <?= e($customer['email']) ?> to admin?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="promote_admin">
                            <input type="hidden" name="user_id" value="<?= (int) $customer['id'] ?>">
                            <input type="hidden" name="tab" value="customers">
                            <input type="hidden" name="q" value="<?= e($search) ?>">
                            <button type="submit" class="admin-btn admin-btn-outline admin-btn-sm">
                                <i class="bi bi-shield-plus"></i> Make admin
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>

<?php require dirname(__DIR__) . '/includes/admin_footer.php'; ?>
