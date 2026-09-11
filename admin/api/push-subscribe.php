<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_admin();

use App\WebPushService;

header('Content-Type: application/json');

$push = new WebPushService();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode([
        'configured' => $push->isConfigured(),
        'publicKey' => $push->publicKey(),
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$raw = file_get_contents('php://input') ?: '';
$json = json_decode($raw, true);
if (!is_array($json)) {
    json_response(['error' => 'Invalid JSON.'], 400);
}
if (!verify_csrf($json['csrf_token'] ?? null)) {
    json_response(['error' => 'Invalid request.'], 400);
}

$action = (string) ($json['action'] ?? 'subscribe');
$userId = (int) (current_user()['id'] ?? 0);

try {
    if ($action === 'unsubscribe') {
        $push->deleteSubscription((string) ($json['endpoint'] ?? ''), $userId);
        echo json_encode(['ok' => true]);
        exit;
    }

    if (!$push->isConfigured()) {
        json_response(['error' => 'Browser notifications are not configured. Generate VAPID keys in Settings.'], 400);
    }

    $keys = is_array($json['keys'] ?? null) ? $json['keys'] : [];
    $ua = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    $push->saveSubscription(
        $userId,
        (string) ($json['endpoint'] ?? ''),
        (string) ($keys['p256dh'] ?? ''),
        (string) ($keys['auth'] ?? ''),
        $ua !== '' ? $ua : null
    );
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    json_response(['error' => $e->getMessage()], 400);
}
