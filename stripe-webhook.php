<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

use App\StripeService;

$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

try {
    $stripe = new StripeService();
    $stripe->handleWebhook($payload, $signature);
    http_response_code(200);
    echo json_encode(['received' => true]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
