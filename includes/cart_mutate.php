<?php

declare(strict_types=1);

function handle_cart_mutation(bool $jsonOnly = false): never
{
    $redirectTo = $_SERVER['HTTP_REFERER'] ?? 'index.php';

    $respond = static function (array $data, int $code = 200, ?string $redirect = null) use ($jsonOnly, $redirectTo): never {
        if ($jsonOnly) {
            cart_api_respond($data, $code);
        }
        cart_respond($data, $code, $redirect ?? $redirectTo);
    };

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        if ($jsonOnly) {
            cart_api_respond(['error' => 'Method not allowed'], 405);
        }
        flash('danger', 'Invalid request.');
        redirect('cart.php');
    }

    if (!is_logged_in()) {
        $respond(['error' => 'Please sign in to continue.', 'login_required' => true], 401, 'login.php');
    }

    if (!verify_csrf($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
        $respond(['error' => 'Invalid CSRF token. Refresh the page and try again.'], 403);
    }

    $userId = (int) current_user()['id'];
    $action = $_POST['action'] ?? '';
    $productId = (int) ($_POST['product_id'] ?? 0);

    switch ($action) {
        case 'add':
            $product = get_product($productId);
            if (!$product || !product_is_purchasable($product)) {
                $respond(['error' => 'Product unavailable'], 400);
            }
            $stmt = db()->prepare(
                'INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?, ?, 1)
                 ON DUPLICATE KEY UPDATE quantity = LEAST(quantity + 1, ?)'
            );
            $stmt->execute([$userId, $productId, (int) $product['inventory']]);
            $respond([
                'success' => true,
                'cart_count' => get_cart_count($userId),
                'message' => 'Added to cart',
            ]);

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
            $respond([
                'success' => true,
                'cart_count' => get_cart_count($userId),
                'message' => 'Cart updated',
            ], 200, 'cart.php');

        case 'remove':
            $stmt = db()->prepare('DELETE FROM cart_items WHERE user_id = ? AND product_id = ?');
            $stmt->execute([$userId, $productId]);
            $respond([
                'success' => true,
                'cart_count' => get_cart_count($userId),
                'message' => 'Item removed from cart',
            ], 200, 'cart.php');

        default:
            $respond(['error' => 'Unknown action'], 400);
    }
}
