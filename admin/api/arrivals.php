<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

$arrivals = db()->query(
    "SELECT o.id, o.order_number, o.customer_here_at, o.vehicle_description, o.status,
            u.first_name, u.last_name, u.phone
     FROM orders o
     JOIN users u ON u.id = o.user_id
     WHERE o.customer_here_at IS NOT NULL AND o.status IN ('paid','preparing','ready')
     ORDER BY o.customer_here_at DESC"
)->fetchAll();

$waitingCount = count($arrivals);

json_response([
    'waiting_count' => $waitingCount,
    'arrivals' => array_map(static function (array $order): array {
        return [
            'id' => (int) $order['id'],
            'order_number' => $order['order_number'],
            'customer_name' => trim($order['first_name'] . ' ' . $order['last_name']),
            'phone' => $order['phone'],
            'vehicle' => $order['vehicle_description'],
            'arrived_at' => $order['customer_here_at'],
            'arrived_label' => date('g:i A', strtotime($order['customer_here_at'])),
            'status' => $order['status'],
        ];
    }, $arrivals),
]);
