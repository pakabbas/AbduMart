<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

$adminSection = 'orders';
$orderId = isset($_GET['id']) ? (int) $_GET['id'] : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        flash('danger', 'Invalid request.');
    } else {
        $orderId = (int) ($_POST['order_id'] ?? 0);
        $status = $_POST['status'] ?? '';
        if (in_array($status, ['paid', 'preparing', 'ready', 'picked_up', 'cancelled'], true)) {
            db()->prepare('UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?')->execute([$status, $orderId]);
            flash('success', 'Order status updated.');
        }
        redirect('orders.php?id=' . $orderId);
    }
}

if ($orderId) {
    $stmt = db()->prepare('SELECT o.*, u.first_name, u.last_name, u.email, u.phone FROM orders o JOIN users u ON u.id = o.user_id WHERE o.id = ?');
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    if (!$order) {
        flash('danger', 'Order not found.');
        redirect('orders.php');
    }
    $items = db()->prepare('SELECT * FROM order_items WHERE order_id = ?');
    $items->execute([$orderId]);
    $orderItems = $items->fetchAll();

    $pageTitle = 'Order ' . $order['order_number'];
    $pageSubtitle = $order['first_name'] . ' ' . $order['last_name'];
    $headerActions = '<a href="orders.php" class="admin-btn admin-btn-outline"><i class="bi bi-arrow-left"></i> All orders</a>';

    require dirname(__DIR__) . '/includes/admin_header.php';
    ?>

    <?php if ($order['customer_here_at']): ?>
    <div class="admin-toast admin-toast-warning"><i class="bi bi-geo-alt-fill"></i> Customer arrived at <?= e(date('g:i A', strtotime($order['customer_here_at']))) ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="admin-card">
                <div class="admin-card-header"><h2>Order items</h2></div>
                <div class="admin-card-body">
                    <table class="admin-table">
                        <thead><tr><th>Item</th><th>Qty</th><th class="text-end">Amount</th></tr></thead>
                        <tbody>
                            <?php foreach ($orderItems as $item): ?>
                            <tr>
                                <td><?= e($item['product_name']) ?></td>
                                <td><?= (int) $item['quantity'] ?></td>
                                <td class="text-end"><?= format_money($item['line_total']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <tr>
                                <td colspan="2" class="fw-bold">Total</td>
                                <td class="text-end fw-bold" style="color:var(--admin-red)"><?= format_money($order['total']) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php if ($order['vehicle_description'] || $order['pickup_notes']): ?>
            <div class="admin-card mt-4">
                <div class="admin-card-body padded">
                    <?php if ($order['vehicle_description']): ?><p class="mb-2"><strong>Vehicle:</strong> <?= e($order['vehicle_description']) ?></p><?php endif; ?>
                    <?php if ($order['pickup_notes']): ?><p class="mb-0"><strong>Notes:</strong> <?= e($order['pickup_notes']) ?></p><?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <div class="col-lg-4">
            <div class="admin-card">
                <div class="admin-card-header"><h2>Update status</h2></div>
                <div class="admin-card-body padded">
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                        <div class="admin-field">
                            <label>Status</label>
                            <select name="status" class="admin-input">
                                <?php foreach (['paid','preparing','ready','picked_up','cancelled'] as $s): ?>
                                <option value="<?= $s ?>" <?= $order['status'] === $s ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $s)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="admin-btn admin-btn-primary w-100">Save status</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php
    require dirname(__DIR__) . '/includes/admin_footer.php';
    exit;
}

$statusFilter = $_GET['status'] ?? '';
$sql = 'SELECT o.*, u.first_name, u.last_name FROM orders o JOIN users u ON u.id = o.user_id WHERE 1=1';
$params = [];
if ($statusFilter !== '') {
    $sql .= ' AND o.status = ?';
    $params[] = $statusFilter;
}
$sql .= ' ORDER BY o.created_at DESC LIMIT 100';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

$pageTitle = 'Orders';
$pageSubtitle = 'Manage curbside orders';
require dirname(__DIR__) . '/includes/admin_header.php';
?>

<form method="get" class="mb-4" style="max-width:220px">
    <select name="status" class="admin-input" onchange="this.form.submit()">
        <option value="">All statuses</option>
        <?php foreach (['paid','preparing','ready','picked_up','cancelled'] as $s): ?>
        <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $s)) ?></option>
        <?php endforeach; ?>
    </select>
</form>

<div class="admin-card">
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Order</th>
                    <th>Customer</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Here</th>
                    <th>Date</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                <tr><td colspan="7" class="text-center text-muted py-5">No orders found.</td></tr>
                <?php endif; ?>
                <?php foreach ($orders as $order): ?>
                <tr class="<?= $order['customer_here_at'] && !in_array($order['status'], ['picked_up','cancelled'], true) ? 'row-here' : '' ?>">
                    <td><strong><?= e($order['order_number']) ?></strong></td>
                    <td><?= e($order['first_name'] . ' ' . $order['last_name']) ?></td>
                    <td><?= format_money($order['total']) ?></td>
                    <td><?= e(ucfirst(str_replace('_', ' ', $order['status']))) ?></td>
                    <td><?= $order['customer_here_at'] ? '<span class="admin-badge admin-badge-red">HERE</span>' : '—' ?></td>
                    <td><?= e(date('M j, g:i A', strtotime($order['created_at']))) ?></td>
                    <td><a href="orders.php?id=<?= (int) $order['id'] ?>" class="admin-btn admin-btn-outline admin-btn-sm">Open</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require dirname(__DIR__) . '/includes/admin_footer.php'; ?>
