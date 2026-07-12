<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

$orderId = isset($_GET['id']) ? (int) $_GET['id'] : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        flash('danger', 'Invalid request.');
    } else {
        $orderId = (int) ($_POST['order_id'] ?? 0);
        $status = $_POST['status'] ?? '';
        $allowed = ['paid', 'preparing', 'ready', 'picked_up', 'cancelled'];
        if (in_array($status, $allowed, true)) {
            $stmt = db()->prepare('UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?');
            $stmt->execute([$status, $orderId]);
            flash('success', 'Order status updated.');
        }
        redirect('orders.php?id=' . $orderId);
    }
}

if ($orderId) {
    $stmt = db()->prepare(
        'SELECT o.*, u.first_name, u.last_name, u.email, u.phone
         FROM orders o JOIN users u ON u.id = o.user_id WHERE o.id = ?'
    );
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
    require dirname(__DIR__) . '/includes/header.php';
    ?>

    <div class="container py-4">
        <a href="orders.php" class="btn btn-link text-danger ps-0 mb-3">&larr; All orders</a>
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <div class="d-flex flex-wrap justify-content-between gap-2 mb-4">
                    <div>
                        <h1 class="h3 mb-1">Order <?= e($order['order_number']) ?></h1>
                        <p class="text-muted mb-0"><?= e($order['first_name'] . ' ' . $order['last_name']) ?> · <?= e($order['phone'] ?? $order['email']) ?></p>
                    </div>
                    <?php if ($order['customer_here_at']): ?>
                    <div class="alert alert-danger mb-0 py-2 px-3">
                        <i class="bi bi-geo-alt-fill"></i> Customer arrived at <?= e(date('g:i A', strtotime($order['customer_here_at']))) ?>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="row g-4">
                    <div class="col-md-7">
                        <h2 class="h6 text-muted text-uppercase">Items</h2>
                        <?php foreach ($orderItems as $item): ?>
                        <div class="d-flex justify-content-between border-bottom py-2">
                            <span><?= (int) $item['quantity'] ?>× <?= e($item['product_name']) ?></span>
                            <span><?= format_money($item['line_total']) ?></span>
                        </div>
                        <?php endforeach; ?>
                        <div class="d-flex justify-content-between pt-3 fw-bold">
                            <span>Total</span>
                            <span class="text-danger"><?= format_money($order['total']) ?></span>
                        </div>
                        <?php if ($order['vehicle_description']): ?>
                        <p class="mt-3 mb-0"><strong>Vehicle:</strong> <?= e($order['vehicle_description']) ?></p>
                        <?php endif; ?>
                        <?php if ($order['pickup_notes']): ?>
                        <p class="mb-0"><strong>Notes:</strong> <?= e($order['pickup_notes']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-5">
                        <form method="post" class="card bg-light border-0">
                            <div class="card-body">
                                <h2 class="h6 mb-3">Update Status</h2>
                                <?= csrf_field() ?>
                                <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                                <select name="status" class="form-select mb-3">
                                    <?php foreach (['paid','preparing','ready','picked_up','cancelled'] as $s): ?>
                                    <option value="<?= $s ?>" <?= $order['status'] === $s ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $s)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-danger w-100">Save Status</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php
    require dirname(__DIR__) . '/includes/footer.php';
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

$pageTitle = 'Manage Orders';
require dirname(__DIR__) . '/includes/header.php';
?>

<div class="container-fluid py-4">
    <h1 class="section-title mb-4">All Orders</h1>
    <form method="get" class="row g-2 mb-4">
        <div class="col-auto">
            <select name="status" class="form-select" onchange="this.form.submit()">
                <option value="">All statuses</option>
                <?php foreach (['paid','preparing','ready','picked_up','cancelled'] as $s): ?>
                <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $s)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Order</th>
                        <th>Customer</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Here?</th>
                        <th>Date</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                    <tr class="<?= $order['customer_here_at'] && !in_array($order['status'], ['picked_up','cancelled'], true) ? 'table-warning' : '' ?>">
                        <td><?= e($order['order_number']) ?></td>
                        <td><?= e($order['first_name'] . ' ' . $order['last_name']) ?></td>
                        <td><?= format_money($order['total']) ?></td>
                        <td><?= e(ucfirst(str_replace('_', ' ', $order['status']))) ?></td>
                        <td><?= $order['customer_here_at'] ? '<span class="badge bg-danger">HERE</span>' : '—' ?></td>
                        <td><?= e(date('M j g:i A', strtotime($order['created_at']))) ?></td>
                        <td><a href="orders.php?id=<?= (int) $order['id'] ?>" class="btn btn-sm btn-outline-danger">Open</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
