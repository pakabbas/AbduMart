<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (is_ajax_request()) {
        json_response(['error' => 'Method not allowed'], 405);
    }
    flash('danger', 'Invalid request.');
    redirect('cart.php');
}

if (!verify_csrf($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
    cart_respond(['error' => 'Invalid CSRF token'], 403);
}

$userId = (int) current_user()['id'];
$action = $_POST['action'] ?? '';
$productId = (int) ($_POST['product_id'] ?? 0);
$redirectTo = $_SERVER['HTTP_REFERER'] ?? 'index.php';

switch ($action) {
    case 'add':
        $product = get_product($productId);
        if (!$product || !(int) $product['is_active'] || (int) $product['inventory'] < 1) {
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
