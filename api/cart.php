<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_login();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

if (!verify_csrf($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
    json_response(['error' => 'Invalid CSRF token'], 403);
}

$userId = (int) current_user()['id'];
$action = $_POST['action'] ?? '';
$productId = (int) ($_POST['product_id'] ?? 0);

switch ($action) {
    case 'add':
        $product = get_product($productId);
        if (!$product || !(int) $product['is_active'] || (int) $product['inventory'] < 1) {
            json_response(['error' => 'Product unavailable'], 400);
        }
        $stmt = db()->prepare(
            'INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?, ?, 1)
             ON DUPLICATE KEY UPDATE quantity = LEAST(quantity + 1, ?)'
        );
        $stmt->execute([$userId, $productId, (int) $product['inventory']]);
        json_response(['success' => true, 'cart_count' => get_cart_count($userId), 'message' => 'Added to cart']);

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
        json_response(['success' => true, 'cart_count' => get_cart_count($userId)]);

    case 'remove':
        $stmt = db()->prepare('DELETE FROM cart_items WHERE user_id = ? AND product_id = ?');
        $stmt->execute([$userId, $productId]);
        json_response(['success' => true, 'cart_count' => get_cart_count($userId)]);

    default:
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            json_response(['error' => 'Unknown action'], 400);
        }
        flash('danger', 'Unknown cart action.');
        redirect('cart.php');
}
