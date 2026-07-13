<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

function shop_item_browser_response(array $data, int $code = 200, bool $forceJson = false): never
{
    if ($forceJson || is_ajax_request() || str_contains(strtolower($_SERVER['HTTP_ACCEPT'] ?? ''), 'json')) {
        json_response($data, $code);
    }

    http_response_code($code);
    header('Content-Type: text/html; charset=UTF-8');
    $title = ($data['success'] ?? false) ? 'Shop item API — OK' : 'Shop item API — Error';
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' . htmlspecialchars($title) . '</title>';
    echo '<style>body{font-family:system-ui,sans-serif;max-width:720px;margin:2rem auto;padding:0 1rem}pre{background:#f4f4f5;padding:1rem;border-radius:8px;overflow:auto}code{background:#f4f4f5;padding:2px 6px;border-radius:4px}</style></head><body>';
    echo '<h1>' . htmlspecialchars($title) . '</h1>';
    echo '<pre>' . htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . '</pre>';
    echo '<p><strong>Test URLs</strong> (must be signed in):</p><ul>';
    echo '<li>Add product 123 (GET test): <code>' . htmlspecialchars(asset_url('shop-item.php?product_id=123')) . '</code></li>';
    echo '<li>Add product 123 (pure JSON): <code>' . htmlspecialchars(asset_url('shop-item.php?product_id=123&format=json')) . '</code></li>';
    echo '<li>Storefront POST endpoint: <code>' . htmlspecialchars(asset_url('mart-line.php')) . '</code></li>';
    echo '<li>View basket: <code>' . htmlspecialchars(asset_url('shop-items.php')) . '</code></li>';
    echo '<li>Ping: <code>' . htmlspecialchars(asset_url('shop-item.php?do=ping')) . '</code></li>';
    echo '</ul><p><a href="' . htmlspecialchars(asset_url('index.php')) . '">Back to shop</a></p></body></html>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $jsonOnly = ($_GET['format'] ?? '') === 'json';

    if (($_GET['do'] ?? '') === 'ping') {
        shop_item_browser_response([
            'success' => true,
            'logged_in' => is_logged_in(),
            'user_id' => is_logged_in() ? (int) current_user()['id'] : null,
            'endpoint' => 'shop-item.php',
            'post_endpoint' => asset_url('mart-line.php'),
        ], 200, $jsonOnly);
    }

    if (!is_logged_in()) {
        shop_item_browser_response(['error' => 'Please sign in first.', 'login_required' => true], 401, $jsonOnly);
    }

    if (!isset($_GET['product_id'])) {
        shop_item_browser_response([
            'success' => true,
            'message' => 'Shop item API ready. Pass ?product_id=ID to test add-to-cart.',
            'example' => asset_url('shop-item.php?product_id=123'),
            'cart_count' => get_cart_count((int) current_user()['id']),
        ], 200, $jsonOnly);
    }

    $productId = (int) $_GET['product_id'];
    $product = get_product($productId);
    if (!$product || !product_is_purchasable($product)) {
        shop_item_browser_response(['error' => 'Product unavailable', 'product_id' => $productId], 400, $jsonOnly);
    }

    $userId = (int) current_user()['id'];
    $stmt = db()->prepare(
        'INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?, ?, 1)
         ON DUPLICATE KEY UPDATE quantity = LEAST(quantity + 1, ?)'
    );
    $stmt->execute([$userId, $productId, (int) $product['inventory']]);

    shop_item_browser_response([
        'success' => true,
        'test_mode' => true,
        'action' => 'add',
        'product_id' => $productId,
        'product_name' => $product['name'],
        'cart_count' => get_cart_count($userId),
        'message' => 'Added to cart (GET test)',
    ], 200, $jsonOnly);
}

require_once __DIR__ . '/includes/cart_mutate.php';
handle_cart_mutation(false);
