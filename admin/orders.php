<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

$adminSection = 'orders';
$orderId = isset($_GET['id']) ? (int) $_GET['id'] : null;
$hasPickedUpColumns = db_has_column('orders', 'picked_up_at') && db_has_column('orders', 'picked_up_by');
$hasOrderLogsTable = db_has_table('order_status_logs');
$dbError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        flash('danger', 'Invalid request.');
    } else {
        try {
            $orderId = (int) ($_POST['order_id'] ?? 0);
            $status = $_POST['status'] ?? '';
            $quick = $_POST['quick'] ?? '';

            $stmt = db()->prepare('SELECT status FROM orders WHERE id = ?');
            $stmt->execute([$orderId]);
            $old = $stmt->fetchColumn();

            if ($quick === 'picked_up') {
                $status = 'picked_up';
            }

            if (in_array($status, ['paid', 'preparing', 'ready', 'picked_up', 'cancelled'], true)) {
                if ($status === 'picked_up') {
                    if ($hasPickedUpColumns) {
                        db()->prepare(
                            'UPDATE orders SET status = ?, picked_up_at = IFNULL(picked_up_at, NOW()), picked_up_by = ?, updated_at = NOW() WHERE id = ?'
                        )->execute([$status, (int) current_user()['id'], $orderId]);
                    } else {
                        db()->prepare('UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?')->execute([$status, $orderId]);
                    }
                } else {
                    db()->prepare('UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?')->execute([$status, $orderId]);
                }
                if ($hasOrderLogsTable) {
                    log_order_status_change($orderId, is_string($old) ? $old : null, $status, (int) current_user()['id']);
                }
                flash('success', 'Order status updated.');
            }
        } catch (Throwable $e) {
            flash('danger', 'Could not update order: ' . $e->getMessage());
        }
        redirect('orders.php?id=' . $orderId);
    }
}

if ($orderId) {
    $order = null;
    $orderItems = [];
    try {
        $sql = 'SELECT o.*, u.first_name, u.last_name, u.email, u.phone';
        if ($hasPickedUpColumns) {
            $sql .= ', pu.first_name AS picked_up_first_name, pu.last_name AS picked_up_last_name';
        }
        $sql .= ' FROM orders o JOIN users u ON u.id = o.user_id';
        if ($hasPickedUpColumns) {
            $sql .= ' LEFT JOIN users pu ON pu.id = o.picked_up_by';
        }
        $sql .= ' WHERE o.id = ?';
        $stmt = db()->prepare($sql);
        $stmt->execute([$orderId]);
        $order = $stmt->fetch() ?: null;
        if (!$order) {
            flash('danger', 'Order not found.');
            redirect('orders.php');
        }
        $items = db()->prepare('SELECT * FROM order_items WHERE order_id = ?');
        $items->execute([$orderId]);
        $orderItems = $items->fetchAll();
    } catch (Throwable $e) {
        $dbError = $e->getMessage();
    }

    $pageTitle = 'Order ' . $order['order_number'];
    $pageSubtitle = $order['first_name'] . ' ' . $order['last_name'];
    $headerActions = '<a href="orders.php" class="admin-btn admin-btn-outline"><i class="bi bi-arrow-left"></i> All orders</a>';

    require dirname(__DIR__) . '/includes/admin_header.php';
    ?>

    <?php if ($dbError): ?>
    <div class="admin-toast admin-toast-danger"><i class="bi bi-exclamation-triangle"></i> Order view error: <?= e($dbError) ?></div>
    <?php endif; ?>

    <?php if ($order['customer_here_at']): ?>
    <div class="admin-toast admin-toast-warning"><i class="bi bi-geo-alt-fill"></i> Customer arrived at <?= e(date('g:i A', strtotime($order['customer_here_at']))) ?></div>
    <?php endif; ?>
    <?php if ($hasPickedUpColumns && !empty($order['picked_up_at'])): ?>
    <div class="admin-toast admin-toast-success">
        <i class="bi bi-bag-check-fill"></i>
        Picked up at <?= e(date('g:i A', strtotime($order['picked_up_at']))) ?>
        <?php if (!empty($order['picked_up_first_name'])): ?>
            by <?= e(trim($order['picked_up_first_name'] . ' ' . $order['picked_up_last_name'])) ?>
        <?php endif; ?>
    </div>
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
                    <?php if ($hasOrderLogsTable): ?>
                    <?php
                    $logs = [];
                    try {
                        $logsStmt = db()->prepare(
                            'SELECT l.*, u.first_name, u.last_name
                             FROM order_status_logs l
                             LEFT JOIN users u ON u.id = l.actor_user_id
                             WHERE l.order_id = ?
                             ORDER BY l.created_at DESC
                             LIMIT 25'
                        );
                        $logsStmt->execute([(int) $order['id']]);
                        $logs = $logsStmt->fetchAll();
                    } catch (Throwable $e) {
                        $logs = [];
                    }
                    ?>
                    <?php if (!empty($logs)): ?>
                    <hr class="my-4">
                    <h3 class="h6 mb-2">Status change log</h3>
                    <div class="small text-muted">
                        <?php foreach ($logs as $log): ?>
                        <div class="mb-2">
                            <div><strong><?= e(ucfirst(str_replace('_', ' ', $log['new_status']))) ?></strong> <span class="text-muted">from <?= e($log['old_status'] ?: '—') ?></span></div>
                            <div>
                                <?= e(date('M j, g:i A', strtotime($log['created_at']))) ?>
                                <?php if (!empty($log['first_name'])): ?>
                                    · <?= e(trim($log['first_name'] . ' ' . $log['last_name'])) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <?php else: ?>
                    <div class="admin-callout mt-3">
                        <strong>Database update pending</strong>
                        <div class="hint mb-0">Run migration <code>005_pay_on_arrival_and_order_logs.sql</code> to enable status change logs.</div>
                    </div>
                    <?php endif; ?>
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
