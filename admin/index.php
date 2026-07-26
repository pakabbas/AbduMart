<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

$adminSection = 'dashboard';
$hasFulfillment = db_has_column('orders', 'fulfillment_type');
$tabRaw = (string) ($_GET['tab'] ?? 'all');
$tab = in_array($tabRaw, ['all', 'pickup', 'delivery'], true) ? $tabRaw : 'all';
if (!$hasFulfillment && $tab === 'delivery') {
    $tab = 'all';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        flash('danger', 'Invalid request.');
    } else {
        $orderId = (int) ($_POST['order_id'] ?? 0);
        $action = $_POST['action'] ?? '';
        if ($action === 'picked_up' && $orderId > 0) {
            $stmt = db()->prepare('SELECT status FROM orders WHERE id = ?');
            $stmt->execute([$orderId]);
            $old = $stmt->fetchColumn();

            db()->prepare(
                'UPDATE orders SET status = ?, picked_up_at = IFNULL(picked_up_at, NOW()), picked_up_by = ?, updated_at = NOW() WHERE id = ?'
            )->execute(['picked_up', (int) current_user()['id'], $orderId]);
            log_order_status_change($orderId, is_string($old) ? $old : null, 'picked_up', (int) current_user()['id'], 'Picked up (quick action)');

            flash('success', 'Order marked as picked up.');
        }
    }
    redirect('index.php?tab=' . $tab);
}

$pickupActiveSql = "SELECT COUNT(*) FROM orders WHERE status IN ('paid','preparing','ready')";
if ($hasFulfillment) {
    $pickupActiveSql .= " AND fulfillment_type = 'pickup'";
}
$pickupActive = (int) db()->query($pickupActiveSql)->fetchColumn();

$deliveryActive = 0;
if ($hasFulfillment) {
    $deliveryActive = (int) db()->query(
        "SELECT COUNT(*) FROM orders WHERE fulfillment_type = 'delivery' AND status IN ('processing','out_for_delivery')"
    )->fetchColumn();
}
$allActive = $pickupActive + $deliveryActive;

$stats = [
    'orders_today' => (int) db()->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE() AND status != 'cancelled'")->fetchColumn(),
    'waiting' => (int) (function () use ($hasFulfillment) {
        $sql = "SELECT COUNT(*) FROM orders WHERE customer_here_at IS NOT NULL AND status IN ('paid','preparing','ready')";
        if ($hasFulfillment) {
            $sql .= " AND fulfillment_type = 'pickup'";
        }
        return db()->query($sql)->fetchColumn();
    })(),
    'ready' => (int) (function () use ($hasFulfillment) {
        $sql = "SELECT COUNT(*) FROM orders WHERE status = 'ready'";
        if ($hasFulfillment) {
            $sql .= " AND fulfillment_type = 'pickup'";
        }
        return db()->query($sql)->fetchColumn();
    })(),
    'pickup_active' => $pickupActive,
    'delivery_active' => $deliveryActive,
    'all_active' => $allActive,
    'products' => (int) db()->query('SELECT COUNT(*) FROM products WHERE is_active = 1')->fetchColumn(),
];

$arrivalsSql = "SELECT o.*, u.first_name, u.last_name, u.phone
     FROM orders o JOIN users u ON u.id = o.user_id
     WHERE o.customer_here_at IS NOT NULL AND o.status IN ('paid','preparing','ready')";
if ($hasFulfillment) {
    $arrivalsSql .= " AND o.fulfillment_type = 'pickup'";
}
$arrivalsSql .= ' ORDER BY o.customer_here_at DESC LIMIT 10';
$arrivals = db()->query($arrivalsSql)->fetchAll();

$activeDeliveries = [];
if ($hasFulfillment) {
    $activeDeliveries = db()->query(
        "SELECT o.*, u.first_name, u.last_name, u.phone
         FROM orders o JOIN users u ON u.id = o.user_id
         WHERE o.fulfillment_type = 'delivery'
           AND o.status IN ('processing', 'out_for_delivery')
         ORDER BY o.created_at DESC
         LIMIT 10"
    )->fetchAll();
}

$recentSql = 'SELECT o.*, u.first_name, u.last_name, u.phone
     FROM orders o JOIN users u ON u.id = o.user_id WHERE 1=1';
$recentParams = [];
if ($hasFulfillment && $tab === 'pickup') {
    $recentSql .= ' AND o.fulfillment_type = ?';
    $recentParams[] = 'pickup';
} elseif ($hasFulfillment && $tab === 'delivery') {
    $recentSql .= ' AND o.fulfillment_type = ?';
    $recentParams[] = 'delivery';
}
$recentSql .= ' ORDER BY o.created_at DESC LIMIT 8';
$recentStmt = db()->prepare($recentSql);
$recentStmt->execute($recentParams);
$recentOrders = $recentStmt->fetchAll();

$pageTitle = 'Dashboard';
$pageSubtitle = match ($tab) {
    'delivery' => 'Delivery operations overview',
    'pickup' => 'Curbside pickup operations overview',
    default => 'All current orders overview',
};
$headerActions = '<a href="clover-sync.php" class="admin-btn admin-btn-outline"><i class="bi bi-arrow-repeat"></i> Clover Sync</a>';

require dirname(__DIR__) . '/includes/admin_header.php';
?>

<div class="admin-tabbar admin-tabbar--dashboard mb-4">
    <nav class="admin-tabbar-nav admin-tabbar-nav--3" aria-label="Order fulfillment">
        <a href="index.php?tab=all" class="admin-tabbar-item<?= $tab === 'all' ? ' is-active' : '' ?>">
            <span class="admin-tabbar-icon"><i class="bi bi-grid-1x2"></i></span>
            <span class="admin-tabbar-copy">
                <strong>All</strong>
                <small>Pickup + delivery</small>
            </span>
            <span class="admin-tabbar-count"><?= (int) $stats['all_active'] ?></span>
        </a>
        <a href="index.php?tab=pickup" class="admin-tabbar-item<?= $tab === 'pickup' ? ' is-active' : '' ?>">
            <span class="admin-tabbar-icon"><i class="bi bi-shop-window"></i></span>
            <span class="admin-tabbar-copy">
                <strong>Pick Up</strong>
                <small>Paid · preparing · ready</small>
            </span>
            <span class="admin-tabbar-count"><?= (int) $stats['pickup_active'] ?></span>
        </a>
        <a href="index.php?tab=delivery" class="admin-tabbar-item<?= $tab === 'delivery' ? ' is-active' : '' ?>">
            <span class="admin-tabbar-icon"><i class="bi bi-truck"></i></span>
            <span class="admin-tabbar-copy">
                <strong>Delivery</strong>
                <small>Processing · out for delivery</small>
            </span>
            <span class="admin-tabbar-count"><?= (int) $stats['delivery_active'] ?></span>
        </a>
    </nav>
</div>

<div class="admin-stats">
    <div class="admin-stat">
        <div class="admin-stat-label">Orders today</div>
        <div class="admin-stat-value"><?= $stats['orders_today'] ?></div>
    </div>
    <?php if ($tab === 'all'): ?>
    <div class="admin-stat highlight">
        <div class="admin-stat-label">Current orders</div>
        <div class="admin-stat-value"><?= $stats['all_active'] ?></div>
    </div>
    <div class="admin-stat">
        <div class="admin-stat-label">Customers waiting</div>
        <div class="admin-stat-value" id="waiting-count"><?= $stats['waiting'] ?></div>
    </div>
    <div class="admin-stat">
        <div class="admin-stat-label">Active deliveries</div>
        <div class="admin-stat-value"><?= $stats['delivery_active'] ?></div>
    </div>
    <?php elseif ($tab === 'pickup'): ?>
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
    <?php else: ?>
    <div class="admin-stat highlight">
        <div class="admin-stat-label">Active deliveries</div>
        <div class="admin-stat-value"><?= $stats['delivery_active'] ?></div>
    </div>
    <div class="admin-stat">
        <div class="admin-stat-label">Active products</div>
        <div class="admin-stat-value"><?= $stats['products'] ?></div>
    </div>
    <?php endif; ?>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="admin-card h-100">
            <?php if ($tab === 'delivery'): ?>
            <div class="admin-card-header red">
                <h2><i class="bi bi-truck me-2"></i>Active deliveries</h2>
                <span class="admin-badge" style="background:#fff;color:var(--admin-red)"><?= count($activeDeliveries) ?></span>
            </div>
            <div class="admin-card-body">
                <?php if (empty($activeDeliveries)): ?>
                <div class="admin-empty">
                    <i class="bi bi-truck"></i>
                    <p>No active delivery orders.</p>
                </div>
                <?php else: ?>
                <?php foreach ($activeDeliveries as $order): ?>
                <div class="arrival-row">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <strong><?= e($order['first_name'] . ' ' . $order['last_name']) ?></strong>
                        <span class="admin-badge admin-badge-red"><?= e($order['order_number']) ?></span>
                    </div>
                    <div class="small text-muted mb-2">
                        <?= e(order_status_display((string) $order['status'])['label']) ?>
                        <?php if (!empty($order['delivery_zip'])): ?> · ZIP <?= e((string) $order['delivery_zip']) ?><?php endif; ?>
                    </div>
                    <a href="orders.php?id=<?= (int) $order['id'] ?>" class="admin-btn admin-btn-primary admin-btn-sm">Manage order</a>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php elseif ($tab === 'all'): ?>
            <div class="admin-card-header red">
                <h2><i class="bi bi-lightning-charge-fill me-2"></i>Needs attention</h2>
                <span class="admin-badge" style="background:#fff;color:var(--admin-red)"><?= count($arrivals) + count($activeDeliveries) ?></span>
            </div>
            <div class="admin-card-body" id="arrivals-list">
                <?php if (empty($arrivals) && empty($activeDeliveries)): ?>
                <div class="admin-empty">
                    <i class="bi bi-check2-circle"></i>
                    <p>No customers waiting or active deliveries.</p>
                </div>
                <?php else: ?>
                <?php foreach ($arrivals as $order): ?>
                <div class="arrival-row">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <strong><?= e($order['first_name'] . ' ' . $order['last_name']) ?></strong>
                        <span class="admin-badge admin-badge-red">PICKUP · <?= e($order['order_number']) ?></span>
                    </div>
                    <div class="small text-muted mb-2">
                        Arrived <?= e(date('g:i A', strtotime($order['customer_here_at']))) ?>
                        <?php if ($order['vehicle_description']): ?> · <?= e($order['vehicle_description']) ?><?php endif; ?>
                    </div>
                    <a href="orders.php?id=<?= (int) $order['id'] ?>" class="admin-btn admin-btn-primary admin-btn-sm">Manage order</a>
                </div>
                <?php endforeach; ?>
                <?php foreach ($activeDeliveries as $order): ?>
                <div class="arrival-row">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <strong><?= e($order['first_name'] . ' ' . $order['last_name']) ?></strong>
                        <span class="admin-badge admin-badge-red">DELIVERY · <?= e($order['order_number']) ?></span>
                    </div>
                    <div class="small text-muted mb-2">
                        <?= e(order_status_display((string) $order['status'])['label']) ?>
                        <?php if (!empty($order['delivery_zip'])): ?> · ZIP <?= e((string) $order['delivery_zip']) ?><?php endif; ?>
                    </div>
                    <a href="orders.php?id=<?= (int) $order['id'] ?>" class="admin-btn admin-btn-primary admin-btn-sm">Manage order</a>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php else: ?>
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
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="admin-card">
            <div class="admin-card-header">
                <h2>
                    <?php if ($tab === 'all'): ?>
                    Recent orders
                    <?php elseif ($tab === 'delivery'): ?>
                    Recent delivery orders
                    <?php else: ?>
                    Recent pickup orders
                    <?php endif; ?>
                </h2>
                <a href="orders.php<?= $tab === 'all' ? '' : '?type=' . e($tab) ?>" class="admin-btn admin-btn-outline admin-btn-sm">View all</a>
            </div>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <?php if ($tab === 'all' && $hasFulfillment): ?><th>Type</th><?php endif; ?>
                            <th>Customer</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentOrders)): ?>
                        <tr><td colspan="<?= ($tab === 'all' && $hasFulfillment) ? 6 : 5 ?>" class="text-center text-muted py-4">No orders yet.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($recentOrders as $order): ?>
                        <?php $rowType = (string) ($order['fulfillment_type'] ?? 'pickup'); ?>
                        <tr class="<?= !empty($order['customer_here_at']) ? 'row-here' : '' ?>">
                            <td><strong><?= e($order['order_number']) ?></strong></td>
                            <?php if ($tab === 'all' && $hasFulfillment): ?>
                            <td><?= fulfillment_type_badge($rowType) ?></td>
                            <?php endif; ?>
                            <td><?= e($order['first_name'] . ' ' . $order['last_name']) ?></td>
                            <td><?= format_money($order['total']) ?></td>
                            <td>
                                <?= e(order_status_display((string) $order['status'])['label']) ?>
                                <?php if (!empty($order['customer_here_at']) && $rowType !== 'delivery'): ?>
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

<?php if ($tab === 'pickup' || $tab === 'all'): ?>
<script>window.ADMIN_POLL_URL = 'api/arrivals.php';</script>
<?php endif; ?>

<?php require dirname(__DIR__) . '/includes/admin_footer.php'; ?>
