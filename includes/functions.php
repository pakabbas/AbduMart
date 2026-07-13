<?php

declare(strict_types=1);

function config(string $key = null, mixed $default = null): mixed
{
    static $config = null;
    if ($config === null) {
        $config = require dirname(__DIR__) . '/config/config.php';
    }
    if ($key === null) {
        return $config;
    }
    $parts = explode('.', $key);
    $value = $config;
    foreach ($parts as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): never
{
    $base = config('app.url');
    if (!str_starts_with($path, 'http')) {
        $path = $base . '/' . ltrim($path, '/');
    }
    header('Location: ' . $path);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

function format_money(float|int|string $amount): string
{
    return '$' . number_format((float) $amount, 2);
}

function generate_order_number(): string
{
    return 'AM' . strtoupper(substr(uniqid(), -8));
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(?string $token): bool
{
    return is_string($token) && hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function json_response(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function get_categories(bool $activeOnly = true): array
{
    $sql = 'SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS product_count FROM categories c';
    if ($activeOnly) {
        $sql .= ' WHERE c.is_active = 1';
    }
    $sql .= ' ORDER BY c.sort_order ASC, c.name ASC';
    return db()->query($sql)->fetchAll();
}

function get_category(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM categories WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function get_products(array $filters = []): array
{
    $sql = 'SELECT p.*, c.name AS category_name FROM products p
            LEFT JOIN categories c ON c.id = p.category_id WHERE 1=1';
    $params = [];

    if (!empty($filters['category_id'])) {
        $sql .= ' AND p.category_id = ?';
        $params[] = (int) $filters['category_id'];
    }
    if (!empty($filters['search'])) {
        $sql .= ' AND (p.name LIKE ? OR p.description LIKE ?)';
        $term = '%' . $filters['search'] . '%';
        $params[] = $term;
        $params[] = $term;
    }
    if (!isset($filters['include_inactive']) || !$filters['include_inactive']) {
        $sql .= ' AND p.is_active = 1 AND p.inventory > 0';
    }

    $sort = $filters['sort'] ?? 'name';
    $sql .= match ($sort) {
        'price_asc' => ' ORDER BY p.price ASC',
        'price_desc' => ' ORDER BY p.price DESC',
        'name' => ' ORDER BY p.name ASC',
        default => ' ORDER BY p.name ASC',
    };

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function get_product(int $id): ?array
{
    $stmt = db()->prepare('SELECT p.*, c.name AS category_name FROM products p
        LEFT JOIN categories c ON c.id = p.category_id WHERE p.id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function get_cart_items(int $userId): array
{
    $stmt = db()->prepare(
        'SELECT ci.*, p.name, p.price, p.image_url, p.inventory
         FROM cart_items ci
         JOIN products p ON p.id = ci.product_id
         WHERE ci.user_id = ? AND p.is_active = 1'
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function get_cart_totals(int $userId): array
{
    $items = get_cart_items($userId);
    $subtotal = 0.0;
    foreach ($items as $item) {
        $subtotal += (float) $item['price'] * (int) $item['quantity'];
    }
    $tax = round($subtotal * (float) config('tax_rate'), 2);
    return [
        'items' => $items,
        'subtotal' => $subtotal,
        'tax' => $tax,
        'total' => $subtotal + $tax,
        'count' => array_sum(array_column($items, 'quantity')),
    ];
}

function get_cart_count(int $userId): int
{
    $stmt = db()->prepare('SELECT COALESCE(SUM(quantity), 0) FROM cart_items WHERE user_id = ?');
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}
