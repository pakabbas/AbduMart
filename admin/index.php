<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

use App\CloverService;

$syncMessage = null;
$syncError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'sync') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $syncError = 'Invalid request.';
    } else {
        try {
            $clover = new CloverService();
            $result = $clover->syncAll();
            $syncMessage = sprintf('Synced %d categories and %d products from Clover.', $result['categories'], $result['products']);
        } catch (Throwable $e) {
            $syncError = $e->getMessage();
        }
    }
}

$stats = [
    'orders_today' => (int) db()->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE() AND status != 'cancelled'")->fetchColumn(),
    'waiting' => (int) db()->query("SELECT COUNT(*) FROM orders WHERE customer_here_at IS NOT NULL AND status IN ('paid','preparing','ready')")->fetchColumn(),
    'ready' => (int) db()->query("SELECT COUNT(*) FROM orders WHERE status = 'ready'")->fetchColumn(),
    'products' => (int) db()->query('SELECT COUNT(*) FROM products WHERE is_active = 1')->fetchColumn(),
];

$arrivals = db()->query(
    "SELECT o.*, u.first_name, u.last_name, u.phone
     FROM orders o
     JOIN users u ON u.id = o.user_id
     WHERE o.customer_here_at IS NOT NULL AND o.status IN ('paid','preparing','ready')
     ORDER BY o.customer_here_at DESC
     LIMIT 10"
)->fetchAll();

$recentOrders = db()->query(
    "SELECT o.*, u.first_name, u.last_name
     FROM orders o JOIN users u ON u.id = o.user_id
     ORDER BY o.created_at DESC LIMIT 8"
)->fetchAll();

$pageTitle = 'Mart Dashboard';
require dirname(__DIR__) . '/includes/header.php';
?>

<div class="container-fluid py-4 admin-dashboard">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h1 class="section-title mb-1">Mart Dashboard</h1>
            <p class="text-muted mb-0">Abdu Mart · Curbside operations</p>
        </div>
        <form method="post" class="d-inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="sync">
            <button type="submit" class="btn btn-outline-danger">
                <i class="bi bi-arrow-repeat"></i> Sync Clover POS
            </button>
        </form>
    </div>

    <?php if ($syncMessage): ?>
    <div class="alert alert-success"><?= e($syncMessage) ?></div>
    <?php endif; ?>
    <?php if ($syncError): ?>
    <div class="alert alert-danger"><?= e($syncError) ?></div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <span class="stat-label">Orders Today</span>
                <span class="stat-value"><?= $stats['orders_today'] ?></span>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card stat-alert">
                <span class="stat-label">Customers Waiting</span>
                <span class="stat-value" id="waiting-count"><?= $stats['waiting'] ?></span>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <span class="stat-label">Ready for Pickup</span>
                <span class="stat-value"><?= $stats['ready'] ?></span>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <span class="stat-label">Active Products</span>
                <span class="stat-value"><?= $stats['products'] ?></span>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
                    <strong><i class="bi bi-geo-alt-fill"></i> Customers Here Now</strong>
                    <span class="badge bg-white text-danger" id="arrival-badge"><?= count($arrivals) ?></span>
                </div>
                <div class="card-body p-0" id="arrivals-list">
                    <?php if (empty($arrivals)): ?>
                    <p class="text-muted text-center py-5 mb-0">No customers checked in yet.</p>
                    <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($arrivals as $order): ?>
                        <div class="list-group-item arrival-item">
                            <div class="d-flex justify-content-between">
                                <strong><?= e($order['first_name'] . ' ' . $order['last_name']) ?></strong>
                                <span class="text-danger fw-semibold"><?= e($order['order_number']) ?></span>
                            </div>
                            <div class="small text-muted">
                                Arrived <?= e(date('g:i A', strtotime($order['customer_here_at']))) ?>
                                <?php if ($order['vehicle_description']): ?> · <?= e($order['vehicle_description']) ?><?php endif; ?>
                            </div>
                            <div class="mt-2">
                                <a href="orders.php?id=<?= (int) $order['id'] ?>" class="btn btn-sm btn-danger">Manage Order</a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom d-flex justify-content-between">
                    <strong>Recent Orders</strong>
                    <a href="orders.php" class="small">View all</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Order</th>
                                <th>Customer</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentOrders as $order): ?>
                            <tr class="<?= $order['customer_here_at'] ? 'table-warning' : '' ?>">
                                <td><?= e($order['order_number']) ?></td>
                                <td><?= e($order['first_name'] . ' ' . $order['last_name']) ?></td>
                                <td><?= format_money($order['total']) ?></td>
                                <td>
                                    <?= e(ucfirst(str_replace('_', ' ', $order['status']))) ?>
                                    <?php if ($order['customer_here_at']): ?>
                                    <span class="badge bg-danger ms-1">HERE</span>
                                    <?php endif; ?>
                                </td>
                                <td><a href="orders.php?id=<?= (int) $order['id'] ?>" class="btn btn-sm btn-outline-danger">Open</a></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
window.ADMIN_POLL_URL = 'api/arrivals.php';
</script>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
