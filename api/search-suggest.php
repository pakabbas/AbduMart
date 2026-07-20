<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$query = trim((string) ($_GET['q'] ?? ''));
$limit = max(1, min(12, (int) ($_GET['limit'] ?? 8)));

if (mb_strlen($query) < 2) {
    json_response([
        'query' => $query,
        'products' => [],
        'view_all_url' => asset_url('shop.php'),
    ]);
}

$products = get_products([
    'search' => $query,
    'sort' => 'name',
    'page' => 1,
    'per_page' => $limit,
]);

$items = [];
foreach ($products as $product) {
    $image = trim((string) ($product['image_url'] ?? ''));
    if (!catalog_has_image($image)) {
        $image = asset_url('assets/images/placeholder-product.svg');
    }

    $items[] = [
        'id' => (int) $product['id'],
        'name' => (string) $product['name'],
        'price' => format_money($product['price']),
        'category' => (string) ($product['category_name'] ?? ''),
        'image' => $image,
        'url' => asset_url('product.php?id=' . (int) $product['id']),
        'in_stock' => product_is_purchasable($product),
    ];
}

json_response([
    'query' => $query,
    'products' => $items,
    'view_all_url' => asset_url('shop.php?' . http_build_query(['q' => $query])),
]);
