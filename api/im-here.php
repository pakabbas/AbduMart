<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_login();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$token = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

if (!verify_csrf($token)) {
    json_response(['error' => 'Invalid CSRF token'], 403);
}

$orderId = (int) ($input['order_id'] ?? 0);
$userId = (int) current_user()['id'];

$stmt = db()->prepare(
    'SELECT * FROM orders WHERE id = ? AND user_id = ? AND status IN (?, ?, ?)'
);
$stmt->execute([$orderId, $userId, 'paid', 'preparing', 'ready']);
$order = $stmt->fetch();

if (!$order) {
    json_response(['error' => 'Order not found or not eligible for check-in'], 404);
}

if ($order['customer_here_at']) {
    json_response([
        'success' => true,
        'already_checked_in' => true,
        'checked_in_at' => $order['customer_here_at'],
        'message' => 'You are already checked in. We are on our way!',
    ]);
}

$update = db()->prepare(
    'UPDATE orders SET customer_here_at = NOW(), status = IF(status = ?, ?, status), updated_at = NOW() WHERE id = ?'
);
$update->execute(['paid', 'ready', $orderId]);

json_response([
    'success' => true,
    'message' => 'Thanks! Abdu Mart has been notified. Please stay in your vehicle.',
    'checked_in_at' => date('c'),
]);
