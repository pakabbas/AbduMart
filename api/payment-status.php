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
$cloverPay = new CloverCheckoutService();

try {
    if ($provider === 'clover' || CloverCheckoutService::isUnresolvedSessionId($sessionId)) {
        $order = $cloverPay->fulfillReturnForUser($userId, $sessionId, $orderIdParam);
    } elseif ($sessionId !== '' && str_starts_with($sessionId, 'cs_')) {
        $order = (new StripeService())->fulfillSession($sessionId);
    } elseif ($orderIdParam > 0) {
        $order = $cloverPay->fulfillOrderForUser($userId, $orderIdParam);
    } else {
        $order = null;
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
