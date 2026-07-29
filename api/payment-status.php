<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_login();

use App\CloverCheckoutService;
use App\StripeService;

header('Content-Type: application/json');

$sessionId = trim((string) ($_GET['session_id'] ?? ''));
$provider = strtolower(trim((string) ($_GET['provider'] ?? '')));

if ($sessionId === '') {
    echo json_encode(['status' => 'error', 'message' => 'Missing session id.']);
    exit;
}

$order = null;
$looksLikeStripe = str_starts_with($sessionId, 'cs_');

try {
    if ($provider === 'clover' || ($provider === '' && !$looksLikeStripe)) {
        $order = (new CloverCheckoutService())->fulfillSession($sessionId);
    }
    if (!$order && ($provider === 'stripe' || $looksLikeStripe || $provider === '')) {
        $order = (new StripeService())->fulfillSession($sessionId);
    }
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
}

if (!$order) {
    // Also check if a Clover order exists but is still pending
    if ($provider === 'clover' || ($provider === '' && !$looksLikeStripe)) {
        $svc = new CloverCheckoutService();
        $pending = $svc->findOrderBySession($sessionId);
        if ($pending && ($pending['status'] ?? '') === 'pending') {
            echo json_encode(['status' => 'pending']);
            exit;
        }
    }
    echo json_encode(['status' => 'pending']);
    exit;
}

$orderStatus = (string) ($order['status'] ?? '');
if ($orderStatus === 'pending') {
    echo json_encode(['status' => 'pending']);
    exit;
}

if (in_array($orderStatus, ['cancelled'], true)) {
    echo json_encode(['status' => 'failed', 'message' => 'Payment was not completed.']);
    exit;
}

echo json_encode([
    'status' => 'paid',
    'order_id' => (int) $order['id'],
    'order_number' => $order['order_number'],
]);
