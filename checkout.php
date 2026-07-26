<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_login();

use App\CloverCheckoutService;
use App\StripeService;

$user = current_user();
$userId = (int) $user['id'];
$fulfillment = fulfillment_mode();
if (!delivery_enabled()) {
    $fulfillment = 'pickup';
    set_fulfillment_mode('pickup');
}
if (isset($_POST['fulfillment_type']) && in_array($_POST['fulfillment_type'], ['pickup', 'delivery'], true)) {
    $fulfillment = delivery_enabled() ? $_POST['fulfillment_type'] : 'pickup';
    set_fulfillment_mode($fulfillment);
}
$cart = get_cart_totals($userId, $fulfillment);
$error = '';
$phoneValue = trim($_POST['phone'] ?? (string) ($user['phone'] ?? ''));
$needsPhone = trim((string) ($user['phone'] ?? '')) === '';
$allowPayOnArrival = pay_on_arrival_enabled();
$cloverPay = new CloverCheckoutService();
$stripePay = new StripeService();
$cloverConfigured = clover_payments_enabled();
$stripeConfigured = stripe_payments_enabled();
$defaultPayment = $cloverConfigured ? 'clover' : ($stripeConfigured ? 'stripe' : ($allowPayOnArrival ? 'arrival' : 'clover'));
$storeStatus = store_status();
$storeClosed = !$storeStatus['open'];
$deliveryFee = (float) $cart['delivery_fee'];
$deliveryMin = (float) $cart['delivery_min_order'];
$deliveryRemaining = max(0.0, round($deliveryMin - (float) $cart['subtotal'], 2));
$deliveryEnabled = delivery_enabled();

$addr1Value = trim((string) ($_POST['delivery_address_line1'] ?? ''));
$addr2Value = trim((string) ($_POST['delivery_address_line2'] ?? ''));
$cityValue = trim((string) ($_POST['delivery_city'] ?? 'Canton'));
$stateValue = trim((string) ($_POST['delivery_state'] ?? 'MI'));
$zipValue = trim((string) ($_POST['delivery_zip'] ?? ''));

$lastVehicle = '';
$lastVehicleStmt = db()->prepare(
    "SELECT vehicle_description
     FROM orders
     WHERE user_id = ?
       AND vehicle_description IS NOT NULL
       AND TRIM(vehicle_description) != ''
       AND status != 'cancelled'
     ORDER BY created_at DESC
     LIMIT 1"
);
$lastVehicleStmt->execute([$userId]);
$lastVehicle = trim((string) ($lastVehicleStmt->fetchColumn() ?: ''));
$vehicleMakeValue = trim((string) ($_POST['vehicle_make'] ?? ''));
$vehicleModelValue = trim((string) ($_POST['vehicle_model'] ?? ''));
$vehicleDetailsValue = trim((string) ($_POST['vehicle_details'] ?? $lastVehicle));
$vehicleValue = trim(implode(' · ', array_filter([$vehicleMakeValue, $vehicleModelValue, $vehicleDetailsValue])));

if (empty($cart['items'])) {
    flash('warning', 'Your cart is empty.');
    redirect('cart.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid request.';
    } elseif ($storeClosed) {
        $error = $storeStatus['banner_message'];
    } else {
        $fulfillment = (($_POST['fulfillment_type'] ?? $fulfillment) === 'delivery') ? 'delivery' : 'pickup';
        if (!$deliveryEnabled) {
            $fulfillment = 'pickup';
        }
        set_fulfillment_mode($fulfillment);
        $cart = get_cart_totals($userId, $fulfillment);
        $deliveryFee = (float) $cart['delivery_fee'];
        $deliveryRemaining = max(0.0, round((float) $cart['delivery_min_order'] - (float) $cart['subtotal'], 2));

        $pickupNotes = trim($_POST['pickup_notes'] ?? '');
        $vehicleMake = trim((string) ($_POST['vehicle_make'] ?? ''));
        $vehicleModel = trim((string) ($_POST['vehicle_model'] ?? ''));
        $vehicleDetails = trim((string) ($_POST['vehicle_details'] ?? ''));
        $vehicleParts = array_values(array_filter([$vehicleMake, $vehicleModel, $vehicleDetails], static fn ($p) => $p !== ''));
        $vehicle = implode(' · ', $vehicleParts);
        $phone = trim($_POST['phone'] ?? '');
        $phoneError = validate_customer_phone($phone);
        if ($phoneError !== null) {
            $error = $phoneError;
        }

        $addr1 = trim((string) ($_POST['delivery_address_line1'] ?? ''));
        $addr2 = trim((string) ($_POST['delivery_address_line2'] ?? ''));
        $city = trim((string) ($_POST['delivery_city'] ?? ''));
        $state = trim((string) ($_POST['delivery_state'] ?? ''));
        $zip = normalize_us_zip((string) ($_POST['delivery_zip'] ?? ''));
        $addr1Value = $addr1;
        $addr2Value = $addr2;
        $cityValue = $city !== '' ? $city : 'Canton';
        $stateValue = $state !== '' ? $state : 'MI';
        $zipValue = $zip;

        if ($error === '' && $fulfillment === 'delivery') {
            if (!$deliveryEnabled) {
                $error = 'Delivery is currently unavailable. Please choose Pickup.';
            } elseif ($cart['subtotal'] < $cart['delivery_min_order']) {
                $error = 'Add ' . format_money($deliveryRemaining) . ' more to meet the delivery minimum of ' . format_money($cart['delivery_min_order']) . '.';
            } elseif ($addr1 === '') {
                $error = 'Enter a delivery street address.';
            } elseif ($city === '') {
                $error = 'Enter a delivery city.';
            } elseif ($state === '') {
                $error = 'Enter a delivery state.';
            } elseif ($zip === '') {
                $error = 'Enter a delivery ZIP code.';
            } elseif (!is_canton_delivery_zip($zip)) {
                $error = 'Delivery is only available in Canton (ZIPs ' . implode(' / ', canton_delivery_zips()) . ').';
            }
        }

        $allowedPayments = [];
        if ($cloverConfigured) {
            $allowedPayments[] = 'clover';
        }
        if ($stripeConfigured) {
            $allowedPayments[] = 'stripe';
        }
        if ($allowPayOnArrival) {
            $allowedPayments[] = 'arrival';
        }
        if ($allowedPayments === []) {
            $error = 'No payment method is configured. Add Clover or Stripe in Admin → Settings.';
        }

        $paymentChoice = (string) ($_POST['payment_method'] ?? $defaultPayment);
        if (!in_array($paymentChoice, $allowedPayments, true)) {
            $paymentChoice = $allowedPayments[0] ?? $defaultPayment;
        }

        if ($error === '') {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            update_user_phone($userId, $phone);
            $user['phone'] = $phone;

            foreach ($cart['items'] as $item) {
                if ((int) $item['quantity'] > (int) $item['inventory']) {
                    throw new RuntimeException($item['name'] . ' no longer has enough stock.');
                }
            }

            $orderNumber = generate_order_number();
            if ($paymentChoice === 'arrival') {
                $status = $fulfillment === 'delivery' ? 'processing' : 'preparing';
            } else {
                $status = 'pending';
            }

            [$orderSql, $orderValues] = build_order_insert([
                'user_id' => $userId,
                'order_number' => $orderNumber,
                'subtotal' => $cart['subtotal'],
                'tax' => $cart['tax'],
                'delivery_fee' => $deliveryFee,
                'total' => $cart['total'],
                'status' => $status,
                'pickup_notes' => $pickupNotes ?: null,
                'vehicle_description' => $fulfillment === 'pickup' ? ($vehicle ?: null) : null,
                'payment_method' => $paymentChoice,
                'fulfillment_type' => $fulfillment,
                'delivery_address_line1' => $fulfillment === 'delivery' ? $addr1 : null,
                'delivery_address_line2' => $fulfillment === 'delivery' ? ($addr2 ?: null) : null,
                'delivery_city' => $fulfillment === 'delivery' ? $city : null,
                'delivery_state' => $fulfillment === 'delivery' ? $state : null,
                'delivery_zip' => $fulfillment === 'delivery' ? $zip : null,
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

            if ($paymentChoice === 'clover') {
                $session = $cloverPay->createCheckoutSession(
                    $orderId,
                    array_map(static fn (array $item): array => [
                        'name' => (string) $item['name'],
                        'price' => (float) $item['price'],
                        'quantity' => (int) $item['quantity'],
                    ], $cart['items']),
                    (float) $cart['tax'],
                    [
                        'email' => (string) ($user['email'] ?? ''),
                        'first_name' => (string) ($user['first_name'] ?? ''),
                        'last_name' => (string) ($user['last_name'] ?? ''),
                        'phone' => (string) $phone,
                    ],
                    $deliveryFee
                );
                $pdo->commit();
                header('Location: ' . $session['href']);
                exit;
            }

            if (!$stripeConfigured) {
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
            if ($deliveryFee > 0) {
                $lineItems[] = [
                    'price_data' => [
                        'currency' => 'usd',
                        'product_data' => ['name' => 'Delivery Fee'],
                        'unit_amount' => (int) round($deliveryFee * 100),
                    ],
                    'quantity' => 1,
                ];
            }

            $session = $stripePay->createCheckoutSession($orderId, $lineItems, $cart['total'], $user['email']);
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
                    <div class="checkout-fulfillment-panel">
                        <label class="form-label fw-semibold">Order type</label>
                        <?php if ($deliveryEnabled): ?>
                        <div class="fulfillment-toggle js-fulfillment-toggle" role="group" aria-label="Order type">
                            <button type="button" class="fulfillment-toggle-btn<?= $fulfillment === 'pickup' ? ' is-active' : '' ?>" data-mode="pickup" data-reload aria-pressed="<?= $fulfillment === 'pickup' ? 'true' : 'false' ?>">
                                <i class="bi bi-shop-window" aria-hidden="true"></i>
                                <span>Pickup</span>
                            </button>
                            <button type="button" class="fulfillment-toggle-btn<?= $fulfillment === 'delivery' ? ' is-active' : '' ?>" data-mode="delivery" data-reload aria-pressed="<?= $fulfillment === 'delivery' ? 'true' : 'false' ?>">
                                <i class="bi bi-truck" aria-hidden="true"></i>
                                <span>Delivery</span>
                            </button>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-light border mb-0 py-2">Pickup only</div>
                        <?php endif; ?>
                    </div>

                    <?php if ($fulfillment === 'delivery'): ?>
                    <h2 class="h5 mb-3"><i class="bi bi-truck text-danger"></i> Delivery Details</h2>
                    <p class="text-muted">Canton delivery only (ZIP <?= e(implode(' / ', canton_delivery_zips())) ?>). Fee <?= e(format_money($deliveryFee)) ?> · <?= e(format_money($deliveryMin)) ?> minimum.</p>
                    <?php else: ?>
                    <h2 class="h5 mb-3"><i class="bi bi-car-front text-danger"></i> Curbside Pickup Details</h2>
                    <p class="text-muted"><?= e(setting('mart.pickup_instructions', config('mart.pickup_instructions'))) ?></p>
                    <p class="small"><strong>Pickup at:</strong> <?= e(setting('mart.address', config('mart.address'))) ?></p>
                    <?php endif; ?>

                    <?php if ($storeClosed): ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-clock-history me-1"></i>
                        <?= e($storeStatus['banner_message']) ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                    <div class="alert alert-danger"><?= e($error) ?></div>
                    <?php endif; ?>
                    <?php if ($fulfillment === 'delivery' && !$cart['meets_delivery_minimum']): ?>
                    <div class="alert alert-warning checkout-delivery-minimum">
                        <div class="fw-bold mb-1">
                            Add <?= e(format_money($deliveryRemaining)) ?> more to unlock delivery checkout
                        </div>
                        <div class="small mb-0">
                            Current subtotal <?= e(format_money((float) $cart['subtotal'])) ?> · Delivery minimum <?= e(format_money($deliveryMin)) ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <form method="post"<?= $storeClosed ? ' class="pe-none opacity-75"' : '' ?>>
                        <?= csrf_field() ?>
                        <input type="hidden" name="fulfillment_type" value="<?= e($fulfillment) ?>">
                        <fieldset<?= $storeClosed ? ' disabled' : '' ?>>
                        <?php if ($needsPhone): ?>
                        <div class="alert alert-info">
                            <i class="bi bi-telephone me-1"></i>
                            Please add your phone number so we can reach you about your order.
                        </div>
                        <?php endif; ?>
                        <div class="mb-3">
                            <label class="form-label">Phone number <span class="text-muted">(required)</span></label>
                            <input type="tel" name="phone" class="form-control" required placeholder="(248) 555-0100" value="<?= e($phoneValue) ?>">
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Payment method</label>
                            <div class="d-grid gap-2">
                                <?php
                                $selectedPayment = (string) ($_POST['payment_method'] ?? $defaultPayment);
                                if ($cloverConfigured):
                                ?>
                                <label class="form-check border rounded-3 p-3">
                                    <input class="form-check-input" type="radio" name="payment_method" value="clover" <?= $selectedPayment === 'clover' ? 'checked' : '' ?>>
                                    <span class="ms-2"><strong>Pay online</strong> (Clover)</span>
                                    <div class="small text-muted ms-4">Secure card payment via Clover Hosted Checkout.</div>
                                </label>
                                <?php endif; ?>
                                <?php if ($stripeConfigured): ?>
                                <label class="form-check border rounded-3 p-3">
                                    <input class="form-check-input" type="radio" name="payment_method" value="stripe" <?= $selectedPayment === 'stripe' ? 'checked' : '' ?>>
                                    <span class="ms-2"><strong>Pay online</strong> (Stripe)</span>
                                </label>
                                <?php endif; ?>
                                <?php if ($allowPayOnArrival): ?>
                                <label class="form-check border rounded-3 p-3">
                                    <input class="form-check-input" type="radio" name="payment_method" value="arrival" <?= $selectedPayment === 'arrival' ? 'checked' : '' ?>>
                                    <span class="ms-2"><strong>Pay on Arrival</strong></span>
                                    <div class="small text-muted ms-4"><?= $fulfillment === 'delivery' ? 'Place the order now and pay when it arrives.' : 'Place the order now and pay when you arrive.' ?></div>
                                </label>
                                <?php endif; ?>
                                <?php if (!$cloverConfigured && !$stripeConfigured && !$allowPayOnArrival): ?>
                                <div class="alert alert-warning mb-0">No payment methods are configured yet.</div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="checkout-mode-fields">
                            <?php if ($fulfillment === 'pickup'): ?>
                            <div class="mb-3">
                                <label class="form-label mb-1">Vehicle details <span class="text-muted">(optional)</span></label>
                                <div class="form-text mb-2">Helpful for curbside pickup — select a make/model or type anything manually.</div>
                                <div class="row g-2 mb-2">
                                    <div class="col-md-6">
                                        <label class="form-label small text-muted mb-1" for="vehicle_make">Make</label>
                                        <select id="vehicle_make" name="vehicle_make" class="form-select">
                                            <option value="">Select make (optional)</option>
                                            <?php if ($vehicleMakeValue !== ''): ?>
                                            <option value="<?= e($vehicleMakeValue) ?>" selected><?= e($vehicleMakeValue) ?></option>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small text-muted mb-1" for="vehicle_model">Model</label>
                                        <select id="vehicle_model" name="vehicle_model" class="form-select" <?= $vehicleMakeValue === '' ? 'disabled' : '' ?>>
                                            <option value="">Select model (optional)</option>
                                            <?php if ($vehicleModelValue !== ''): ?>
                                            <option value="<?= e($vehicleModelValue) ?>" selected><?= e($vehicleModelValue) ?></option>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                </div>
                                <label class="form-label small text-muted mb-1" for="vehicle_details">Color, plate, or other notes</label>
                                <input
                                    type="text"
                                    id="vehicle_details"
                                    name="vehicle_details"
                                    class="form-control"
                                    placeholder="e.g. Red · ABC 1234 · parked near entrance"
                                    value="<?= e($vehicleDetailsValue) ?>"
                                >
                                <?php if ($lastVehicle !== '' && !isset($_POST['vehicle_details'])): ?>
                                <div class="form-text">Notes prefilled from your last order. You can change anything.</div>
                                <?php endif; ?>
                            </div>
                            <div class="mb-4">
                                <label class="form-label">Pickup notes <span class="text-muted">(optional)</span></label>
                                <textarea name="pickup_notes" class="form-control" rows="3" placeholder="Any special instructions..."><?= e($_POST['pickup_notes'] ?? '') ?></textarea>
                            </div>
                            <?php else: ?>
                            <div class="mb-3">
                                <label class="form-label">Street address</label>
                                <input type="text" name="delivery_address_line1" class="form-control" required placeholder="123 Main St" value="<?= e($addr1Value) ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Apt / suite <span class="text-muted">(optional)</span></label>
                                <input type="text" name="delivery_address_line2" class="form-control" placeholder="Apt 2B" value="<?= e($addr2Value) ?>">
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-md-5">
                                    <label class="form-label">City</label>
                                    <input type="text" name="delivery_city" class="form-control" required value="<?= e($cityValue) ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">State</label>
                                    <input type="text" name="delivery_state" class="form-control" required value="<?= e($stateValue) ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">ZIP</label>
                                    <input type="text" name="delivery_zip" class="form-control" required placeholder="48187 or 48188" value="<?= e($zipValue) ?>">
                                </div>
                            </div>
                            <div class="mb-4">
                                <label class="form-label">Delivery notes <span class="text-muted">(optional)</span></label>
                                <textarea name="pickup_notes" class="form-control" rows="3" placeholder="Gate code, leave at door, etc."><?= e($_POST['pickup_notes'] ?? '') ?></textarea>
                            </div>
                            <?php endif; ?>
                        </div>

                        <button type="submit" class="btn btn-danger btn-lg w-100"<?= ($storeClosed || ($fulfillment === 'delivery' && !$cart['meets_delivery_minimum'])) ? ' disabled' : '' ?>>
                            <i class="bi bi-lock"></i> Continue · <?= e(format_money($cart['total'])) ?>
                        </button>
                        </fieldset>
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
                    <div class="d-flex justify-content-between small text-muted mb-1">
                        <span>Subtotal</span>
                        <span><?= format_money($cart['subtotal']) ?></span>
                    </div>
                    <?php if ($fulfillment === 'delivery'): ?>
                    <div class="d-flex justify-content-between small text-muted mb-1">
                        <span>Delivery</span>
                        <span><?= format_money($cart['delivery_fee']) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="d-flex justify-content-between small text-muted mb-2">
                        <span>Tax</span>
                        <span><?= format_money($cart['tax']) ?></span>
                    </div>
                    <div class="d-flex justify-content-between fw-bold">
                        <span>Total</span>
                        <span class="text-danger"><?= format_money($cart['total']) ?></span>
                    </div>
                    <?php if ($fulfillment === 'delivery' && !$cart['meets_delivery_minimum']): ?>
                    <div class="alert alert-warning small mt-3 mb-0 py-2">
                        <strong>Need <?= e(format_money($deliveryRemaining)) ?> more</strong>
                        to reach the <?= e(format_money($deliveryMin)) ?> delivery minimum.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(() => {
    const makesUrl = <?= json_encode(asset_url('api/vehicle-options.php?action=makes')) ?>;
    const modelsUrl = <?= json_encode(asset_url('api/vehicle-options.php?action=models')) ?>;
    const makeSelect = document.getElementById('vehicle_make');
    const modelSelect = document.getElementById('vehicle_model');
    if (!makeSelect || !modelSelect) return;

    const selectedMake = <?= json_encode($vehicleMakeValue) ?>;
    const selectedModel = <?= json_encode($vehicleModelValue) ?>;

    function setOptions(select, items, placeholder, selected) {
        select.innerHTML = '';
        const empty = document.createElement('option');
        empty.value = '';
        empty.textContent = placeholder;
        select.appendChild(empty);
        items.forEach((name) => {
            const opt = document.createElement('option');
            opt.value = name;
            opt.textContent = name;
            if (selected && selected.toLowerCase() === String(name).toLowerCase()) {
                opt.selected = true;
            }
            select.appendChild(opt);
        });
    }

    async function loadMakes() {
        try {
            const res = await fetch(makesUrl, { credentials: 'same-origin' });
            const data = await res.json();
            const makes = Array.isArray(data.makes) ? data.makes : [];
            setOptions(makeSelect, makes, 'Select make (optional)', selectedMake);
            if (selectedMake) {
                modelSelect.disabled = false;
                await loadModels(selectedMake, selectedModel);
            }
        } catch (err) {
            // Keep manual entry usable if the free API is temporarily down.
        }
    }

    async function loadModels(make, selected) {
        modelSelect.disabled = true;
        setOptions(modelSelect, [], 'Loading models…', '');
        if (!make) {
            setOptions(modelSelect, [], 'Select model (optional)', '');
            modelSelect.disabled = true;
            return;
        }
        try {
            const res = await fetch(modelsUrl + '&make=' + encodeURIComponent(make), { credentials: 'same-origin' });
            const data = await res.json();
            const models = Array.isArray(data.models) ? data.models : [];
            setOptions(modelSelect, models, 'Select model (optional)', selected || '');
            modelSelect.disabled = false;
        } catch (err) {
            setOptions(modelSelect, [], 'Select model (optional)', '');
            modelSelect.disabled = false;
        }
    }

    makeSelect.addEventListener('change', function () {
        loadModels(makeSelect.value, '');
    });

    loadMakes();
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
