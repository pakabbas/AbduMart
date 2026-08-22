<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_login();

use App\CloverCheckoutService;
use App\StripeService;

header('Content-Type: application/json');

$userId = (int) current_user()['id'];
$sessionId = trim((string) ($_GET['session_id'] ?? ''));
$provider = strtolower(trim((string) ($_GET['provider'] ?? '')));
$orderIdParam = (int) ($_GET['order_id'] ?? 0);

if ($orderIdParam > 0) {
    $stmt = db()->prepare('SELECT * FROM orders WHERE id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$orderIdParam, $userId]);
    $order = $stmt->fetch() ?: null;
    if ($order) {
        $orderStatus = (string) ($order['status'] ?? '');
        if ($orderStatus === 'pending') {
            echo json_encode(['status' => 'pending']);
            exit;
        }
        if ($orderStatus === 'cancelled') {
            echo json_encode(['status' => 'failed', 'message' => 'Payment was not completed.']);
            exit;
        }
        echo json_encode([
            'status' => 'paid',
            'order_id' => (int) $order['id'],
            'order_number' => $order['order_number'],
        ]);
        exit;
    }
}

$order = null;
$looksLikeStripe = $sessionId !== '' && str_starts_with($sessionId, 'cs_');

try {
    if ($provider === 'clover' || ($provider === '' && !$looksLikeStripe)) {
        $order = (new CloverCheckoutService())->fulfillReturnForUser($userId, $sessionId);
    }
    if (!$order && ($provider === 'stripe' || $looksLikeStripe)) {
        if ($sessionId === '') {
            echo json_encode(['status' => 'error', 'message' => 'Missing session id.']);
            exit;
        }
        $order = (new StripeService())->fulfillSession($sessionId);
    }
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
}

if (!$order) {
    echo json_encode(['status' => 'pending']);
    exit;
}

$orderStatus = (string) ($order['status'] ?? '');
if ($orderStatus === 'pending') {
    echo json_encode(['status' => 'pending']);
    exit;
}

if ($orderStatus === 'cancelled') {
    echo json_encode(['status' => 'failed', 'message' => 'Payment was not completed.']);
    exit;
}

echo json_encode([
    'status' => 'paid',
    'order_id' => (int) $order['id'],
    'order_number' => $order['order_number'],
]);
