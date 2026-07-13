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
    if (!str_starts_with($path, 'http')) {
        $path = ltrim($path, '/');
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        if (str_contains($script, '/admin/') && !str_starts_with($path, 'admin/')) {
            $path = 'admin/' . $path;
        }
        $path = config('app.url') . '/' . $path;
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

function is_ajax_request(): bool
{
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        return true;
    }

    if (!empty($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        return true;
    }

    $accept = strtolower($_SERVER['HTTP_ACCEPT'] ?? '');
    return str_contains($accept, 'json');
}

function asset_url(string $path): string
{
    return '/' . ltrim($path, '/');
}

function db_has_table(string $table): bool
{
    try {
        $stmt = db()->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        return (bool) $stmt->fetchColumn();
    } catch (Throwable) {
        return false;
    }
}

function db_has_column(string $table, string $column): bool
{
    try {
        $stmt = db()->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $stmt->execute([$column]);
        return (bool) $stmt->fetchColumn();
    } catch (Throwable) {
        return false;
    }
}

function store_uploaded_image(string $field, string $prefix): ?string
{
    if (empty($_FILES[$field]) || !is_array($_FILES[$field])) {
        return null;
    }

    $f = $_FILES[$field];
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }

    $tmp = (string) ($f['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return null;
    }

    $size = (int) ($f['size'] ?? 0);
    if ($size <= 0 || $size > 5 * 1024 * 1024) {
        throw new RuntimeException('Image upload must be under 5MB.');
    }

    $mime = mime_content_type($tmp) ?: '';
    $ext = match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        default => null,
    };
    if ($ext === null) {
        throw new RuntimeException('Unsupported image type. Use JPG, PNG, or WebP.');
    }

    $dir = dirname(__DIR__) . '/assets/uploads';
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not create uploads directory.');
    }

    $name = $prefix . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(5)) . '.' . $ext;
    $dest = $dir . '/' . $name;
    if (!move_uploaded_file($tmp, $dest)) {
        throw new RuntimeException('Image upload failed.');
    }

    return asset_url('assets/uploads/' . $name);
}

function catalog_has_image(?string $url): bool
{
    $url = trim((string) $url);
    if ($url === '') {
        return false;
    }
    if (preg_match('#^https?://#i', $url)) {
        return true;
    }
    // Allow local uploaded assets like /assets/uploads/...
    return str_starts_with($url, '/assets/');
}

function category_stock_image_slug(string $name, int $id): string
{
    $n = strtolower(trim($name));

    return match (true) {
        str_contains($n, 'produce') => 'produce',
        str_contains($n, 'dairy') || str_contains($n, 'egg') => 'dairy-eggs',
        str_contains($n, 'bakery') || str_contains($n, 'bread') => 'bakery',
        str_contains($n, 'beverage') || str_contains($n, 'drink') => 'beverages',
        str_contains($n, 'snack') || str_contains($n, 'chips') => 'snacks',
        str_contains($n, 'household') || str_contains($n, 'clean') => 'household',
        default => 'default',
    };
}

function category_stock_image_url(string $name, int $id): string
{
    $slug = category_stock_image_slug($name, $id);
    $file = dirname(__DIR__) . '/assets/images/categories/' . $slug . '.svg';

    if (!is_file($file)) {
        $slug = 'default';
    }

    return asset_url('assets/images/categories/' . $slug . '.svg');
}

function category_image_needs_assign(?string $url): bool
{
    $url = trim((string) $url);
    if ($url === '') {
        return true;
    }
    if (str_starts_with($url, '/assets/')) {
        return false;
    }

    $lower = strtolower($url);
    foreach (['picsum.photos', 'unsplash.com', 'placehold.co', 'placeholder.com', 'dummyimage.com'] as $host) {
        if (str_contains($lower, $host)) {
            return true;
        }
    }

    return false;
}

function product_food_image_sources(): array
{
    return [
        'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=800&q=80',
        'https://images.unsplash.com/photo-1511920170033-f8396924c10b?auto=format&fit=crop&w=800&q=80',
        'https://images.unsplash.com/photo-1495474472287-4d71bcdd2085?auto=format&fit=crop&w=800&q=80',
        'https://images.unsplash.com/photo-1567620905732-2d1ec7ab7445?auto=format&fit=crop&w=800&q=80',
        'https://images.unsplash.com/photo-1565958011703-44f9829ba187?auto=format&fit=crop&w=800&q=80',
        'https://images.unsplash.com/photo-1482049010928-7a0e5d61c5f5?auto=format&fit=crop&w=800&q=80',
        'https://images.unsplash.com/photo-1504674900247-0877df9cc836?auto=format&fit=crop&w=800&q=80',
        'https://images.unsplash.com/photo-1551024506-0bccd828d307?auto=format&fit=crop&w=800&q=80',
        'https://images.unsplash.com/photo-1464300568806-2f0a293cbeca?auto=format&fit=crop&w=800&q=80',
        'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?auto=format&fit=crop&w=800&q=80',
        'https://images.unsplash.com/photo-1571091718767-18b5b1457add?auto=format&fit=crop&w=800&q=80',
        'https://images.unsplash.com/photo-1606313564200-e75d5e30476c?auto=format&fit=crop&w=800&q=80',
        'https://images.unsplash.com/photo-1525385133512-2f3bdd039059?auto=format&fit=crop&w=800&q=80',
        'https://images.unsplash.com/photo-1553909489-cd47e0d71592?auto=format&fit=crop&w=800&q=80',
        'https://images.unsplash.com/photo-1486427944299-d1955d23e34d?auto=format&fit=crop&w=800&q=80',
        'https://images.unsplash.com/photo-1555507036-abbf19da2a2d?auto=format&fit=crop&w=800&q=80',
    ];
}

function product_image_needs_assign(?string $url): bool
{
    $url = trim((string) $url);
    if ($url === '') {
        return true;
    }
    if (str_starts_with($url, '/assets/')) {
        return false;
    }

    $lower = strtolower($url);
    foreach (['picsum.photos', 'fastly.picsum', 'unsplash.com', 'placehold.co', 'placeholder.com', 'dummyimage.com'] as $host) {
        if (str_contains($lower, $host)) {
            return true;
        }
    }

    return false;
}

function import_remote_image(string $url, string $prefix, int $id): ?string
{
    $dir = dirname(__DIR__) . '/assets/uploads';
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        return null;
    }

    $ctx = stream_context_create([
        'http' => [
            'timeout' => 20,
            'user_agent' => 'AbduMart/1.0 (+https://abdumart.btkdeals.com)',
            'follow_location' => 1,
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);

    $data = @file_get_contents($url, false, $ctx);
    if ($data === false || strlen($data) < 1000) {
        return null;
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($data) ?: '';
    $ext = match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        default => null,
    };
    if ($ext === null) {
        return null;
    }

    $name = $prefix . '-' . $id . '-' . substr(md5($url), 0, 10) . '.' . $ext;
    $dest = $dir . '/' . $name;
    if (file_put_contents($dest, $data) === false) {
        return null;
    }

    return asset_url('assets/uploads/' . $name);
}

function assign_product_food_image(int $productId, string $name): string
{
    $sources = product_food_image_sources();
    $idx = abs(crc32(strtolower(trim($name)) . ':' . $productId)) % count($sources);
    $remote = $sources[$idx];
    $local = import_remote_image($remote, 'product', $productId);

    return $local ?? $remote;
}

function products_have_featured_column(): bool
{
    return db_has_column('products', 'is_featured');
}

function get_featured_products(int $limit = 12): array
{
    if (!products_have_featured_column()) {
        return [];
    }

    return get_products([
        'featured_only' => true,
        'per_page' => max(1, min(24, $limit)),
        'sort' => 'name',
    ]);
}

function shop_page_url(int $pageNum, array $queryParams): string
{
    $params = $queryParams;
    if ($pageNum > 1) {
        $params['page'] = $pageNum;
    }
    $query = http_build_query($params);

    return 'shop.php' . ($query !== '' ? '?' . $query : '');
}

function pay_on_arrival_enabled(): bool
{
    return setting('allow_pay_on_arrival', '') === '1' && db_has_column('orders', 'payment_method');
}

function order_status_display(string $status): array
{
    return match ($status) {
        'pending' => ['label' => 'Pending Payment', 'class' => 'secondary'],
        'paid' => ['label' => 'Paid — Preparing', 'class' => 'warning'],
        'preparing' => ['label' => 'Preparing', 'class' => 'warning'],
        'ready' => ['label' => 'Ready for Pickup', 'class' => 'success'],
        'picked_up' => ['label' => 'Picked Up', 'class' => 'dark'],
        'cancelled' => ['label' => 'Cancelled', 'class' => 'danger'],
        default => ['label' => ucfirst(str_replace('_', ' ', $status)), 'class' => 'secondary'],
    };
}

function get_active_pickup_order(int $userId): ?array
{
    $stmt = db()->prepare(
        "SELECT o.*,
            (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count
         FROM orders o
         WHERE o.user_id = ?
           AND o.status IN ('paid', 'preparing', 'ready')
         ORDER BY o.created_at DESC
         LIMIT 1"
    );
    $stmt->execute([$userId]);
    $order = $stmt->fetch();

    return $order ?: null;
}

/**
 * @param array{user_id:int,order_number:string,subtotal:float|int|string,tax:float|int|string,total:float|int|string,status:string,pickup_notes:?string,vehicle_description:?string,payment_method?:string} $data
 * @return array{0:string,1:array<int,mixed>}
 */
function build_order_insert(array $data): array
{
    $columns = [
        'user_id',
        'order_number',
        'subtotal',
        'tax',
        'total',
        'status',
        'pickup_notes',
        'vehicle_description',
    ];
    $values = [
        $data['user_id'],
        $data['order_number'],
        $data['subtotal'],
        $data['tax'],
        $data['total'],
        $data['status'],
        $data['pickup_notes'],
        $data['vehicle_description'],
    ];

    if (db_has_column('orders', 'payment_method')) {
        $columns[] = 'payment_method';
        $values[] = $data['payment_method'] ?? 'stripe';
    }

    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
    $sql = 'INSERT INTO orders (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')';

    return [$sql, $values];
}

function name_initials(string $name): string
{
    $name = trim(preg_replace('/\s+/u', ' ', $name));
    if ($name === '') {
        return '?';
    }

    $words = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY);

    $pick = static function (string $s): ?string {
        if (preg_match('/[\p{L}\p{N}]/u', $s, $m) === 1) {
            return $m[0];
        }
        return null;
    };

    $first = null;
    $second = null;

    foreach ($words as $w) {
        $c = $pick($w);
        if ($c === null) {
            continue;
        }
        if ($first === null) {
            $first = $c;
            continue;
        }
        $second = $c;
        break;
    }

    if ($first !== null && $second !== null) {
        return mb_strtoupper($first . $second);
    }

    if ($first !== null) {
        if (preg_match_all('/[\p{L}\p{N}]/u', $name, $m) === 1 && !empty($m[0])) {
            $letters = $m[0];
            $two = ($letters[0] ?? '?') . ($letters[1] ?? '');
            return mb_strtoupper($two);
        }
        return mb_strtoupper($first);
    }

    return '?';
}

function catalog_tile_media(string $name, ?string $imageUrl): string
{
    $initials = e(name_initials($name));
    $html = '<span class="catalog-tile-initials" aria-hidden="true">' . $initials . '</span>';

    if (catalog_has_image($imageUrl)) {
        $html = '<img src="' . e(trim((string) $imageUrl)) . '" alt="' . e($name) . '" loading="lazy" class="catalog-tile-img">' . $html;
    }

    return $html;
}

function catalog_image_url(?string $url, string $kind = 'product'): string
{
    $url = trim((string) $url);
    if ($url !== '' && preg_match('#^https?://#i', $url)) {
        return $url;
    }

    return asset_url(match ($kind) {
        'category' => 'assets/images/placeholder-category.svg',
        default => 'assets/images/placeholder-product.svg',
    });
}

function json_response(array $data, int $code = 200): never
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($code);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data);
    exit;
}

function log_order_status_change(int $orderId, ?string $oldStatus, string $newStatus, ?int $actorUserId = null, ?string $note = null): void
{
    try {
        db()->prepare(
            'INSERT INTO order_status_logs (order_id, old_status, new_status, actor_user_id, note)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([$orderId, $oldStatus, $newStatus, $actorUserId, $note]);
    } catch (Throwable) {
        // ignore logging errors so status changes still work
    }
}

function cart_api_respond(array $data, int $code = 200): never
{
    json_response($data, $code);
}

function cart_respond(array $data, int $code = 200, ?string $redirectTo = null): never
{
    if (is_ajax_request()) {
        json_response($data, $code);
    }

    if (!empty($data['error'])) {
        flash('danger', (string) $data['error']);
    } elseif (!empty($data['message'])) {
        flash('success', (string) $data['message']);
    } elseif (!empty($data['success'])) {
        flash('success', 'Cart updated.');
    }

    redirect($redirectTo ?? 'index.php');
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
    $params = [];
    $where = product_filter_sql($filters, $params);

    $sort = $filters['sort'] ?? 'name';
    $order = match ($sort) {
        'price_asc' => ' ORDER BY p.price ASC',
        'price_desc' => ' ORDER BY p.price DESC',
        'featured' => products_have_featured_column()
            ? ' ORDER BY p.is_featured DESC, p.name ASC'
            : ' ORDER BY p.name ASC',
        'name' => ' ORDER BY p.name ASC',
        default => ' ORDER BY p.name ASC',
    };

    $sql = 'SELECT p.*, c.name AS category_name FROM products p
            LEFT JOIN categories c ON c.id = p.category_id WHERE 1=1' . $where . $order;

    if (!empty($filters['per_page'])) {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = max(1, min(100, (int) $filters['per_page']));
        $sql .= ' LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage);
    }

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function count_products(array $filters = []): int
{
    $params = [];
    $where = product_filter_sql($filters, $params);
    $stmt = db()->prepare('SELECT COUNT(*) FROM products p WHERE 1=1' . $where);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

function product_filter_sql(array $filters, array &$params): string
{
    $sql = '';

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
        $sql .= ' AND p.is_active = 1';
    }
    if (!empty($filters['featured_only']) && products_have_featured_column()) {
        $sql .= ' AND p.is_featured = 1';
    }

    return $sql;
}

function product_is_purchasable(array $product): bool
{
    return (int) ($product['is_active'] ?? 0) === 1 && (int) ($product['inventory'] ?? 0) > 0;
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
