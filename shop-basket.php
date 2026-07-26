<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$userId = is_logged_in() ? (int) current_user()['id'] : null;
$cart = get_cart_totals($userId);

$items = [];
foreach ($cart['items'] as $item) {
    $qty = (int) $item['quantity'];
    $price = (float) $item['price'];
    $lineTotal = $price * $qty;
    $items[] = [
        'product_id' => (int) $item['product_id'],
        'name' => $item['name'],
        'price' => $price,
        'price_label' => format_money($price),
        'quantity' => $qty,
        'line_total' => $lineTotal,
        'line_total_label' => format_money($lineTotal),
        'inventory' => (int) $item['inventory'],
        'initials' => name_initials($item['name']),
        'image_url' => catalog_has_image($item['image_url'] ?? null) ? $item['image_url'] : null,
    ];
}

json_response([
    'success' => true,
    'count' => $cart['count'],
    'items' => $items,
    'fulfillment' => $cart['fulfillment'],
    'subtotal' => $cart['subtotal'],
    'subtotal_label' => format_money($cart['subtotal']),
    'tax' => $cart['tax'],
    'tax_label' => format_money($cart['tax']),
    'delivery_fee' => $cart['delivery_fee'],
    'delivery_fee_label' => format_money($cart['delivery_fee']),
    'delivery_min_order' => $cart['delivery_min_order'],
    'delivery_min_order_label' => format_money($cart['delivery_min_order']),
    'meets_delivery_minimum' => $cart['meets_delivery_minimum'],
    'total' => $cart['total'],
    'total_label' => format_money($cart['total']),
]);
