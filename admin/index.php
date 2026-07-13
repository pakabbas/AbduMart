<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

$adminSection = 'dashboard';

$stats = [
    'orders_today' => (int) db()->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE() AND status != 'cancelled'")->fetchColumn(),
    'waiting' => (int) db()->query("SELECT COUNT(*) FROM orders WHERE customer_here_at IS NOT NULL AND status IN ('paid','preparing','ready')")->fetchColumn(),
    'ready' => (int) db()->query("SELECT COUNT(*) FROM orders WHERE status = 'ready'")->fetchColumn(),
    'products' => (int) db()->query('SELECT COUNT(*) FROM products WHERE is_active = 1')->fetchColumn(),
];

$arrivals = db()->query(
    "SELECT o.*, u.first_name, u.last_name, u.phone
     FROM orders o JOIN users u ON u.id = o.user_id
     WHERE o.customer_here_at IS NOT NULL AND o.status IN ('paid','preparing','ready')
     ORDER BY o.customer_here_at DESC LIMIT 10"
)->fetchAll();

$recentOrders = db()->query(
    "SELECT o.*, u.first_name, u.last_name
     FROM orders o JOIN users u ON u.id = o.user_id
     ORDER BY o.created_at DESC LIMIT 8"
)->fetchAll();

$pageTitle = 'Dashboard';
$pageSubtitle = 'Canton curbside operations overview';
$headerActions = '<a href="clover-sync.php" class="admin-btn admin-btn-outline"><i class="bi bi-arrow-repeat"></i> Clover Sync</a>';

require dirname(__DIR__) . '/includes/admin_header.php';
?>

<div class="admin-stats">
    <div class="admin-stat">
        <div class="admin-stat-label">Orders today</div>
        <div class="admin-stat-value"><?= $stats['orders_today'] ?></div>
    </div>
    <div class="admin-stat highlight">
        <div class="admin-stat-label">Customers waiting</div>
        <div class="admin-stat-value" id="waiting-count"><?= $stats['waiting'] ?></div>
    </div>
    <div class="admin-stat">
        <div class="admin-stat-label">Ready for pickup</div>
        <div class="admin-stat-value"><?= $stats['ready'] ?></div>
    </div>
    <div class="admin-stat">
        <div class="admin-stat-label">Active products</div>
        <div class="admin-stat-value"><?= $stats['products'] ?></div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="admin-card h-100">
            <div class="admin-card-header red">
                <h2><i class="bi bi-geo-alt-fill me-2"></i>Customers here now</h2>
                <span class="admin-badge" style="background:#fff;color:var(--admin-red)" id="arrival-badge"><?= count($arrivals) ?></span>
            </div>
            <div class="admin-card-body" id="arrivals-list">
                <?php if (empty($arrivals)): ?>
                <div class="admin-empty">
                    <i class="bi bi-car-front"></i>
                    <p>No customers checked in yet.</p>
                </div>
                <?php else: ?>
                <?php foreach ($arrivals as $order): ?>
                <div class="arrival-row">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <strong><?= e($order['first_name'] . ' ' . $order['last_name']) ?></strong>
                        <span class="admin-badge admin-badge-red"><?= e($order['order_number']) ?></span>
                    </div>
                    <div class="small text-muted mb-2">
                        Arrived <?= e(date('g:i A', strtotime($order['customer_here_at']))) ?>
                        <?php if ($order['vehicle_description']): ?> · <?= e($order['vehicle_description']) ?><?php endif; ?>
                    </div>
                    <a href="orders.php?id=<?= (int) $order['id'] ?>" class="admin-btn admin-btn-primary admin-btn-sm">Manage order</a>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="admin-card">
            <div class="admin-card-header">
                <h2>Recent orders</h2>
                <a href="orders.php" class="admin-btn admin-btn-outline admin-btn-sm">View all</a>
            </div>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
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
                        <tr class="<?= $order['customer_here_at'] ? 'row-here' : '' ?>">
                            <td><strong><?= e($order['order_number']) ?></strong></td>
                            <td><?= e($order['first_name'] . ' ' . $order['last_name']) ?></td>
                            <td><?= format_money($order['total']) ?></td>
                            <td>
                                <?= e(ucfirst(str_replace('_', ' ', $order['status']))) ?>
                                <?php if ($order['customer_here_at']): ?>
                                <span class="admin-badge admin-badge-red ms-1">HERE</span>
                                <?php endif; ?>
                            </td>
                            <td><a href="orders.php?id=<?= (int) $order['id'] ?>" class="admin-btn admin-btn-outline admin-btn-sm">Open</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>window.ADMIN_POLL_URL = 'api/arrivals.php';</script>

<?php require dirname(__DIR__) . '/includes/admin_footer.php'; ?>
