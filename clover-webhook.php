<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

use App\CloverCheckoutService;

$payload = file_get_contents('php://input') ?: '';
$signature = $_SERVER['HTTP_CLOVER_SIGNATURE'] ?? $_SERVER['HTTP_X_CLOVER_SIGNATURE'] ?? '';

try {
    $clover = new CloverCheckoutService();
    $clover->handleWebhook($payload, $signature !== '' ? $signature : null);
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode(['received' => true]);
} catch (Throwable $e) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
}
