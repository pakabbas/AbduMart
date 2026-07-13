<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_login();

use App\StripeService;

$user = current_user();
$userId = (int) $user['id'];
$cart = get_cart_totals($userId);
$error = '';
$allowPayOnArrival = pay_on_arrival_enabled();

if (empty($cart['items'])) {
    flash('warning', 'Your cart is empty.');
    redirect('cart.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid request.';
    } else {
        $pickupNotes = trim($_POST['pickup_notes'] ?? '');
        $vehicle = trim($_POST['vehicle_description'] ?? '');
        $paymentChoice = $_POST['payment_method'] ?? 'stripe';
        if (!$allowPayOnArrival) {
            $paymentChoice = 'stripe';
        }
        if (!in_array($paymentChoice, ['stripe', 'arrival'], true)) {
            $paymentChoice = 'stripe';
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            foreach ($cart['items'] as $item) {
                if ((int) $item['quantity'] > (int) $item['inventory']) {
                    throw new RuntimeException($item['name'] . ' no longer has enough stock.');
                }
            }

            $orderNumber = generate_order_number();
            $status = ($paymentChoice === 'arrival') ? 'preparing' : 'pending';
            [$orderSql, $orderValues] = build_order_insert([
                'user_id' => $userId,
                'order_number' => $orderNumber,
                'subtotal' => $cart['subtotal'],
                'tax' => $cart['tax'],
                'total' => $cart['total'],
                'status' => $status,
                'pickup_notes' => $pickupNotes ?: null,
                'vehicle_description' => $vehicle ?: null,
                'payment_method' => $paymentChoice,
            ]);
            $stmt = $pdo->prepare($orderSql);
            $stmt->execute($orderValues);
            $orderId = (int) $pdo->lastInsertId();

            $itemStmt = $pdo->prepare(
                'INSERT INTO order_items (order_id, product_id, product_name, quantity, unit_price, line_total)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            foreach ($cart['items'] as $item) {
                $lineTotal = (float) $item['price'] * (int) $item['quantity'];
                $itemStmt->execute([
                    $orderId,
                    $item['product_id'],
                    $item['name'],
                    $item['quantity'],
                    $item['price'],
                    $lineTotal,
                ]);

                $inv = $pdo->prepare('UPDATE products SET inventory = inventory - ? WHERE id = ? AND inventory >= ?');
                $inv->execute([(int) $item['quantity'], $item['product_id'], (int) $item['quantity']]);
                if ($inv->rowCount() === 0) {
                    throw new RuntimeException('Inventory update failed for ' . $item['name']);
                }
            }

            $pdo->prepare('DELETE FROM cart_items WHERE user_id = ?')->execute([$userId]);

            if ($paymentChoice === 'arrival') {
                $pdo->commit();
                notify_admins_new_order($orderId);
                flash('success', 'Order placed. You can pay on arrival.');
                redirect('orders.php?order=' . $orderId);
            }

            $stripe = new StripeService();
            if (!$stripe->isConfigured()) {
                throw new RuntimeException('Stripe is not configured. Add keys to Admin → Settings.');
            }

            $lineItems = [];
            foreach ($cart['items'] as $item) {
                $lineItems[] = [
                    'price_data' => [
                        'currency' => 'usd',
                        'product_data' => [
                            'name' => $item['name'],
                        ],
                        'unit_amount' => (int) round((float) $item['price'] * 100),
                    ],
                    'quantity' => (int) $item['quantity'],
                ];
            }
            if ($cart['tax'] > 0) {
                $lineItems[] = [
                    'price_data' => [
                        'currency' => 'usd',
                        'product_data' => ['name' => 'Michigan Sales Tax'],
                        'unit_amount' => (int) round($cart['tax'] * 100),
                    ],
                    'quantity' => 1,
                ];
            }

            $session = $stripe->createCheckoutSession($orderId, $lineItems, $cart['total'], $user['email']);
            $pdo->prepare('UPDATE orders SET stripe_session_id = ? WHERE id = ?')->execute([$session->id, $orderId]);

            $pdo->commit();
            header('Location: ' . $session->url);
            exit;
        } catch (Throwable $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
}

$pageTitle = 'Checkout';
require __DIR__ . '/includes/header.php';
?>

<div class="container py-4">
    <h1 class="section-title mb-4">Checkout</h1>
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h2 class="h5 mb-3"><i class="bi bi-car-front text-danger"></i> Curbside Pickup Details</h2>
                    <p class="text-muted"><?= e(setting('mart.pickup_instructions', config('mart.pickup_instructions'))) ?></p>
                    <p class="small"><strong>Pickup at:</strong> <?= e(setting('mart.address', config('mart.address'))) ?></p>
                    <?php if ($error): ?>
                    <div class="alert alert-danger"><?= e($error) ?></div>
                    <?php endif; ?>
                    <form method="post">
                        <?= csrf_field() ?>
                        <div class="mb-4">
                            <label class="form-label">Payment method</label>
                            <div class="d-grid gap-2">
                                <label class="form-check border rounded-3 p-3">
                                    <input class="form-check-input" type="radio" name="payment_method" value="stripe" checked>
                                    <span class="ms-2"><strong>Pay online</strong> (Stripe)</span>
                                </label>
                                <?php if ($allowPayOnArrival): ?>
                                <label class="form-check border rounded-3 p-3">
                                    <input class="form-check-input" type="radio" name="payment_method" value="arrival" <?= (($_POST['payment_method'] ?? '') === 'arrival') ? 'checked' : '' ?>>
                                    <span class="ms-2"><strong>Pay on Arrival</strong></span>
                                    <div class="small text-muted ms-4">Place the order now and pay when you arrive.</div>
                                </label>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Vehicle description <span class="text-muted">(color, make, plate)</span></label>
                            <input type="text" name="vehicle_description" class="form-control" placeholder="e.g. Red Toyota Camry — ABC 1234" value="<?= e($_POST['vehicle_description'] ?? '') ?>">
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Pickup notes <span class="text-muted">(optional)</span></label>
                            <textarea name="pickup_notes" class="form-control" rows="3" placeholder="Any special instructions..."><?= e($_POST['pickup_notes'] ?? '') ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-danger btn-lg w-100">
                            <i class="bi bi-lock"></i> Continue
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h2 class="h5 mb-3">Your Items</h2>
                    <?php foreach ($cart['items'] as $item): ?>
                    <div class="d-flex justify-content-between mb-2 small">
                        <span><?= (int) $item['quantity'] ?>× <?= e($item['name']) ?></span>
                        <span><?= format_money((float) $item['price'] * (int) $item['quantity']) ?></span>
                    </div>
                    <?php endforeach; ?>
                    <hr>
                    <div class="d-flex justify-content-between fw-bold">
                        <span>Total</span>
                        <span class="text-danger"><?= format_money($cart['total']) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
