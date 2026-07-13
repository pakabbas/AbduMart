<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

function shop_item_browser_response(array $data, int $code = 200): never
{
    if (is_ajax_request() || str_contains(strtolower($_SERVER['HTTP_ACCEPT'] ?? ''), 'json')) {
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
    echo '<li>Add product 123: <code>' . htmlspecialchars(asset_url('shop-item.php?product_id=123')) . '</code></li>';
    echo '<li>View basket: <code>' . htmlspecialchars(asset_url('shop-items.php')) . '</code></li>';
    echo '<li>Ping: <code>' . htmlspecialchars(asset_url('shop-item.php?do=ping')) . '</code></li>';
    echo '</ul><p><a href="' . htmlspecialchars(asset_url('index.php')) . '">Back to shop</a></p></body></html>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (($_GET['do'] ?? '') === 'ping') {
        shop_item_browser_response([
            'success' => true,
            'logged_in' => is_logged_in(),
            'user_id' => is_logged_in() ? (int) current_user()['id'] : null,
            'endpoint' => 'shop-item.php',
        ]);
    }

    if (!is_logged_in()) {
        shop_item_browser_response(['error' => 'Please sign in first.', 'login_required' => true], 401);
    }

    if (!isset($_GET['product_id'])) {
        shop_item_browser_response([
            'success' => true,
            'message' => 'Shop item API ready. Pass ?product_id=ID to test add-to-cart.',
            'example' => asset_url('shop-item.php?product_id=123'),
            'cart_count' => get_cart_count((int) current_user()['id']),
        ]);
    }

    $productId = (int) $_GET['product_id'];
    $product = get_product($productId);
    if (!$product || !product_is_purchasable($product)) {
        shop_item_browser_response(['error' => 'Product unavailable', 'product_id' => $productId], 400);
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
    ]);
}

if (!is_logged_in()) {
    if (is_ajax_request()) {
        json_response(['error' => 'Please sign in to continue.', 'login_required' => true], 401);
    }
    flash('warning', 'Please sign in to continue.');
    redirect('login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? 'index.php'));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (is_ajax_request()) {
        json_response(['error' => 'Method not allowed'], 405);
    }
    flash('danger', 'Invalid request.');
    redirect('cart.php');
}

if (!verify_csrf($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
    cart_respond(['error' => 'Invalid CSRF token. Refresh the page and try again.'], 403);
}

$userId = (int) current_user()['id'];
$action = $_POST['action'] ?? '';
$productId = (int) ($_POST['product_id'] ?? 0);
$redirectTo = $_SERVER['HTTP_REFERER'] ?? 'index.php';

switch ($action) {
    case 'add':
        $product = get_product($productId);
        if (!$product || !product_is_purchasable($product)) {
            cart_respond(['error' => 'Product unavailable'], 400, $redirectTo);
        }
        $stmt = db()->prepare(
            'INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?, ?, 1)
             ON DUPLICATE KEY UPDATE quantity = LEAST(quantity + 1, ?)'
        );
        $stmt->execute([$userId, $productId, (int) $product['inventory']]);
        cart_respond([
            'success' => true,
            'cart_count' => get_cart_count($userId),
            'message' => 'Added to cart',
        ], 200, $redirectTo);

    case 'update':
        $qty = max(0, (int) ($_POST['quantity'] ?? 0));
        if ($qty === 0) {
            $stmt = db()->prepare('DELETE FROM cart_items WHERE user_id = ? AND product_id = ?');
            $stmt->execute([$userId, $productId]);
        } else {
            $product = get_product($productId);
            $maxQty = $product ? (int) $product['inventory'] : 0;
            $qty = min($qty, $maxQty);
            $stmt = db()->prepare('UPDATE cart_items SET quantity = ? WHERE user_id = ? AND product_id = ?');
            $stmt->execute([$qty, $userId, $productId]);
        }
        cart_respond([
            'success' => true,
            'cart_count' => get_cart_count($userId),
            'message' => 'Cart updated',
        ], 200, 'cart.php');

    case 'remove':
        $stmt = db()->prepare('DELETE FROM cart_items WHERE user_id = ? AND product_id = ?');
        $stmt->execute([$userId, $productId]);
        cart_respond([
            'success' => true,
            'cart_count' => get_cart_count($userId),
            'message' => 'Item removed from cart',
        ], 200, 'cart.php');

    default:
        cart_respond(['error' => 'Unknown action'], 400, $redirectTo);
}
