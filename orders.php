<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$userId = (int) current_user()['id'];

$stmt = db()->prepare(
    'SELECT o.*, 
        (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count
     FROM orders o
     WHERE o.user_id = ?
     ORDER BY o.created_at DESC'
);
$stmt->execute([$userId]);
$orders = $stmt->fetchAll();

$activeOrderId = isset($_GET['order']) ? (int) $_GET['order'] : null;
$activeOrder = null;
$activeItems = [];

if ($activeOrderId) {
    $oStmt = db()->prepare('SELECT * FROM orders WHERE id = ? AND user_id = ?');
    $oStmt->execute([$activeOrderId, $userId]);
    $activeOrder = $oStmt->fetch() ?: null;
    if ($activeOrder) {
        $iStmt = db()->prepare('SELECT * FROM order_items WHERE order_id = ?');
        $iStmt->execute([$activeOrderId]);
        $activeItems = $iStmt->fetchAll();
    }
}

$pageTitle = 'My Orders';
require __DIR__ . '/includes/header.php';

$statusLabels = [
    'pending' => ['label' => 'Pending Payment', 'class' => 'secondary'],
    'paid' => ['label' => 'Paid — Preparing', 'class' => 'warning'],
    'preparing' => ['label' => 'Preparing', 'class' => 'warning'],
    'ready' => ['label' => 'Ready for Pickup', 'class' => 'success'],
    'picked_up' => ['label' => 'Picked Up', 'class' => 'dark'],
    'cancelled' => ['label' => 'Cancelled', 'class' => 'danger'],
];
?>

<div class="container py-4">
    <h1 class="section-title mb-4">My Orders</h1>

    <?php if ($activeOrder): ?>
    <?php $st = $statusLabels[$activeOrder['status']] ?? ['label' => $activeOrder['status'], 'class' => 'secondary']; ?>
    <div class="card border-0 shadow-sm mb-4 order-detail-card" id="order-<?= (int) $activeOrder['id'] ?>">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                <div>
                    <h2 class="h4 mb-1">Order <?= e($activeOrder['order_number']) ?></h2>
                    <span class="badge bg-<?= e($st['class']) ?>"><?= e($st['label']) ?></span>
                </div>
                <div class="text-end">
                    <div class="fw-bold text-danger fs-4"><?= format_money($activeOrder['total']) ?></div>
                    <small class="text-muted"><?= e(date('M j, Y g:i A', strtotime($activeOrder['created_at']))) ?></small>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <?php foreach ($activeItems as $item): ?>
                <div class="col-md-6">
                    <div class="d-flex justify-content-between small">
                        <span><?= (int) $item['quantity'] ?>× <?= e($item['product_name']) ?></span>
                        <span><?= format_money($item['line_total']) ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if ($activeOrder['vehicle_description']): ?>
            <p class="mb-2"><i class="bi bi-car-front"></i> <strong>Vehicle:</strong> <?= e($activeOrder['vehicle_description']) ?></p>
            <?php endif; ?>

            <?php if (in_array($activeOrder['status'], ['paid', 'preparing', 'ready'], true)): ?>
            <div class="im-here-panel p-4 rounded-3" data-order-id="<?= (int) $activeOrder['id'] ?>">
                <?php if ($activeOrder['customer_here_at']): ?>
                <div class="text-center">
                    <i class="bi bi-geo-alt-fill text-danger fs-1"></i>
                    <h3 class="h5 mt-2">We're on our way!</h3>
                    <p class="text-muted mb-0">You checked in at <?= e(date('g:i A', strtotime($activeOrder['customer_here_at']))) ?>. Please stay in your vehicle.</p>
                </div>
                <?php else: ?>
                <div class="text-center">
                    <p class="mb-3"><?= e(config('mart.pickup_instructions')) ?></p>
                    <button type="button" class="btn btn-danger btn-lg px-5 im-here-btn" data-order-id="<?= (int) $activeOrder['id'] ?>">
                        <i class="bi bi-geo-alt-fill"></i> I'M HERE
                    </button>
                    <p class="small text-muted mt-2 mb-0">Tap when you've arrived at the curb</p>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (empty($orders)): ?>
    <div class="empty-state text-center py-5">
        <i class="bi bi-receipt display-4 text-danger"></i>
        <p class="mt-3 text-muted">You haven't placed any orders yet.</p>
        <a href="index.php" class="btn btn-danger">Start Shopping</a>
    </div>
    <?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Order</th>
                        <th>Date</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                    <?php $st = $statusLabels[$order['status']] ?? ['label' => $order['status'], 'class' => 'secondary']; ?>
                    <tr>
                        <td class="fw-semibold"><?= e($order['order_number']) ?></td>
                        <td><?= e(date('M j, Y', strtotime($order['created_at']))) ?></td>
                        <td><?= (int) $order['item_count'] ?></td>
                        <td><?= format_money($order['total']) ?></td>
                        <td><span class="badge bg-<?= e($st['class']) ?>"><?= e($st['label']) ?></span></td>
                        <td>
                            <a href="orders.php?order=<?= (int) $order['id'] ?>" class="btn btn-sm btn-outline-danger">View</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
