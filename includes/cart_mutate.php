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

    if (!verify_csrf($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
        $respond(['error' => 'Invalid CSRF token. Refresh the page and try again.'], 403);
    }

    $loggedIn = is_logged_in();
    $userId = $loggedIn ? (int) current_user()['id'] : null;
    $action = $_POST['action'] ?? '';
    $productId = (int) ($_POST['product_id'] ?? 0);

    switch ($action) {
        case 'add':
            $product = get_product($productId);
            if (!$product || !product_is_purchasable($product)) {
                $respond(['error' => 'Product unavailable'], 400);
            }
            if ($loggedIn) {
                $stmt = db()->prepare(
                    'INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?, ?, 1)
                     ON DUPLICATE KEY UPDATE quantity = LEAST(quantity + 1, ?)'
                );
                $stmt->execute([$userId, $productId, (int) $product['inventory']]);
            } else {
                guest_cart_add($productId, (int) $product['inventory']);
            }
            $respond([
                'success' => true,
                'cart_count' => get_cart_count($userId),
                'message' => 'Added to cart',
            ]);

        case 'update':
            $qty = max(0, (int) ($_POST['quantity'] ?? 0));
            $product = get_product($productId);
            $maxQty = $product ? (int) $product['inventory'] : 0;
            if ($qty === 0) {
                if ($loggedIn) {
                    $stmt = db()->prepare('DELETE FROM cart_items WHERE user_id = ? AND product_id = ?');
                    $stmt->execute([$userId, $productId]);
                } else {
                    guest_cart_set_quantity($productId, 0);
                }
            } else {
                $qty = min($qty, $maxQty);
                if ($loggedIn) {
                    $stmt = db()->prepare('UPDATE cart_items SET quantity = ? WHERE user_id = ? AND product_id = ?');
                    $stmt->execute([$qty, $userId, $productId]);
                } else {
                    guest_cart_set_quantity($productId, $qty);
                }
            }
            $respond([
                'success' => true,
                'cart_count' => get_cart_count($userId),
                'message' => 'Cart updated',
            ], 200, 'cart.php');

        case 'remove':
            if ($loggedIn) {
                $stmt = db()->prepare('DELETE FROM cart_items WHERE user_id = ? AND product_id = ?');
                $stmt->execute([$userId, $productId]);
            } else {
                guest_cart_set_quantity($productId, 0);
            }
            $respond([
                'success' => true,
                'cart_count' => get_cart_count($userId),
                'message' => 'Item removed from cart',
            ], 200, 'cart.php');

        default:
            $respond(['error' => 'Unknown action'], 400);
    }
}
