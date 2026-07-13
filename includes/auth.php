<?php

declare(strict_types=1);

use App\GoogleAuthService;
use App\MailService;

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    static $user = null;
    if ($user === null) {
        $stmt = db()->prepare('SELECT id, email, first_name, last_name, phone, role, google_id, email_verified_at FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch() ?: null;
        if (!$user) {
            unset($_SESSION['user_id']);
        }
    }
    return $user;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function is_admin(): bool
{
    $user = current_user();
    return $user && $user['role'] === 'admin';
}

function require_login(): void
{
    if (!is_logged_in()) {
        if (is_ajax_request()) {
            json_response(['error' => 'Please sign in to continue.', 'login_required' => true], 401);
        }
        flash('warning', 'Please sign in to continue.');
        redirect('login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? 'index.php'));
    }
}

function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        flash('danger', 'Admin access required.');
        redirect('index.php');
    }
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function register_user(string $email, string $password, string $firstName, string $lastName, string $phone, bool $verified = true): array
{
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $verifiedAt = $verified ? date('Y-m-d H:i:s') : null;
    $stmt = db()->prepare(
        'INSERT INTO users (email, password_hash, first_name, last_name, phone, email_verified_at) VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$email, $hash, $firstName, $lastName, $phone ?: null, $verifiedAt]);
    return [
        'id' => (int) db()->lastInsertId(),
        'email' => $email,
        'first_name' => $firstName,
        'last_name' => $lastName,
        'phone' => $phone,
        'role' => 'customer',
    ];
}

function authenticate(string $email, string $password): ?array
{
    $stmt = db()->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if (!$user || empty($user['password_hash'])) {
        return null;
    }
    if (password_verify($password, $user['password_hash'])) {
        return $user;
    }
    return null;
}

function generate_otp(): string
{
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function hash_otp(string $otp): string
{
    return hash('sha256', $otp);
}

function invalidate_auth_tokens(string $email, string $purpose): void
{
    db()->prepare('UPDATE auth_tokens SET used_at = NOW() WHERE email = ? AND purpose = ? AND used_at IS NULL')
        ->execute([$email, $purpose]);
}

function create_auth_otp(string $email, string $purpose, ?array $payload = null): string
{
    invalidate_auth_tokens($email, $purpose);
    $otp = generate_otp();
    $expires = date('Y-m-d H:i:s', time() + 600);

    db()->prepare(
        'INSERT INTO auth_tokens (email, token_hash, purpose, payload, expires_at) VALUES (?, ?, ?, ?, ?)'
    )->execute([
        $email,
        hash_otp($otp),
        $purpose,
        $payload ? json_encode($payload) : null,
        $expires,
    ]);

    return $otp;
}

function verify_auth_otp(string $email, string $purpose, string $otp): ?array
{
    $stmt = db()->prepare(
        'SELECT * FROM auth_tokens
         WHERE email = ? AND purpose = ? AND used_at IS NULL AND expires_at > NOW()
         ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([$email, $purpose]);
    $token = $stmt->fetch();
    if (!$token) {
        return null;
    }

    if ((int) $token['attempts'] >= 5) {
        return null;
    }

    if (!hash_equals($token['token_hash'], hash_otp($otp))) {
        db()->prepare('UPDATE auth_tokens SET attempts = attempts + 1 WHERE id = ?')->execute([$token['id']]);
        return null;
    }

    db()->prepare('UPDATE auth_tokens SET used_at = NOW() WHERE id = ?')->execute([$token['id']]);
    $payload = $token['payload'] ? json_decode($token['payload'], true) : null;
    return is_array($payload) ? $payload : [];
}

function send_signup_otp(string $email, string $firstName, string $lastName, string $phone, string $password): void
{
    $payload = [
        'first_name' => $firstName,
        'last_name' => $lastName,
        'phone' => $phone,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    ];
    $otp = create_auth_otp($email, 'signup', $payload);
    $mail = new MailService();
    $mail->sendOtp($email, $firstName, $otp, 'account sign up');
}

function send_password_reset_otp(string $email, string $firstName): void
{
    $otp = create_auth_otp($email, 'password_reset');
    $mail = new MailService();
    $mail->sendOtp($email, $firstName, $otp, 'password reset');
}

function complete_signup_from_otp(string $email, array $payload): array
{
    $verifiedAt = date('Y-m-d H:i:s');
    $stmt = db()->prepare(
        'INSERT INTO users (email, password_hash, first_name, last_name, phone, email_verified_at) VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $email,
        $payload['password_hash'],
        $payload['first_name'],
        $payload['last_name'],
        $payload['phone'] ?? null,
        $verifiedAt,
    ]);
    return [
        'id' => (int) db()->lastInsertId(),
        'email' => $email,
        'first_name' => $payload['first_name'],
        'last_name' => $payload['last_name'],
        'phone' => $payload['phone'] ?? '',
        'role' => 'customer',
    ];
}

function find_or_create_google_user(array $profile): array
{
    $googleId = $profile['google_id'];
    $email = strtolower($profile['email']);

    $stmt = db()->prepare('SELECT * FROM users WHERE google_id = ?');
    $stmt->execute([$googleId]);
    $user = $stmt->fetch();
    if ($user) {
        return $user;
    }

    $stmt = db()->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user) {
        db()->prepare('UPDATE users SET google_id = ?, email_verified_at = COALESCE(email_verified_at, NOW()) WHERE id = ?')
            ->execute([$googleId, $user['id']]);
        $user['google_id'] = $googleId;
        return $user;
    }

    $stmt = db()->prepare(
        'INSERT INTO users (email, google_id, first_name, last_name, email_verified_at) VALUES (?, ?, ?, ?, NOW())'
    );
    $stmt->execute([
        $email,
        $googleId,
        $profile['first_name'],
        $profile['last_name'] ?: 'User',
    ]);

    return [
        'id' => (int) db()->lastInsertId(),
        'email' => $email,
        'first_name' => $profile['first_name'],
        'last_name' => $profile['last_name'] ?: 'User',
        'role' => 'customer',
        'google_id' => $googleId,
    ];
}

function google_login_redirect(string $redirect = 'index.php'): never
{
    $service = new GoogleAuthService();
    if (!$service->isConfigured()) {
        flash('danger', 'Google sign-in is not configured yet.');
        redirect('login.php');
    }

    $state = bin2hex(random_bytes(16));
    $_SESSION['google_oauth_state'] = $state;
    $_SESSION['google_oauth_redirect'] = $redirect;
    header('Location: ' . $service->getAuthorizationUrl($state));
    exit;
}

function send_order_confirmation_email(int $orderId): void
{
    $orderStmt = db()->prepare('SELECT * FROM orders WHERE id = ?');
    $orderStmt->execute([$orderId]);
    $order = $orderStmt->fetch();
    if (!$order || $order['confirmation_email_sent_at']) {
        return;
    }

    $userStmt = db()->prepare('SELECT * FROM users WHERE id = ?');
    $userStmt->execute([$order['user_id']]);
    $user = $userStmt->fetch();
    if (!$user) {
        return;
    }

    $itemsStmt = db()->prepare('SELECT * FROM order_items WHERE order_id = ?');
    $itemsStmt->execute([$orderId]);
    $items = $itemsStmt->fetchAll();

    try {
        $mail = new MailService();
        if ($mail->isConfigured()) {
            $mail->sendOrderConfirmation($order, $user, $items);
            db()->prepare('UPDATE orders SET confirmation_email_sent_at = NOW() WHERE id = ?')->execute([$orderId]);
        }
    } catch (Throwable) {
        // Do not block checkout if email fails
    }
}
