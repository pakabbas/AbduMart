<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
if (!verify_csrf(is_string($token) ? $token : null)) {
    json_response(['error' => 'Invalid CSRF token'], 403);
}

$mode = (string) ($_POST['mode'] ?? '');
if (!in_array($mode, ['pickup', 'delivery'], true)) {
    json_response(['error' => 'Invalid fulfillment mode'], 422);
}

set_fulfillment_mode($mode);

$userId = is_logged_in() ? (int) current_user()['id'] : null;
$cart = get_cart_totals($userId, $mode);

json_response([
    'success' => true,
    'mode' => $mode,
    'delivery_fee' => $cart['delivery_fee'],
    'delivery_fee_label' => format_money($cart['delivery_fee']),
    'delivery_min_order' => $cart['delivery_min_order'],
    'delivery_min_order_label' => format_money($cart['delivery_min_order']),
    'meets_delivery_minimum' => $cart['meets_delivery_minimum'],
    'subtotal' => $cart['subtotal'],
    'subtotal_label' => format_money($cart['subtotal']),
    'tax' => $cart['tax'],
    'tax_label' => format_money($cart['tax']),
    'total' => $cart['total'],
    'total_label' => format_money($cart['total']),
    'count' => $cart['count'],
    'canton_zips' => canton_delivery_zips(),
]);
