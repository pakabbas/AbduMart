<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

use App\MailService;
use App\SettingsService;

$adminSection = 'settings';
$message = null;
$error = null;

$fields = [
    'stripe_secret_key', 'stripe_publishable_key', 'stripe_webhook_secret',
    'clover_merchant_id', 'clover_api_token', 'clover_env',
    'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_from_email', 'smtp_from_name',
    'google_client_id', 'google_client_secret',
    'mart_address', 'mart_phone', 'mart_pickup_instructions',
];

$values = SettingsService::getGroup($fields);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid request. Please try again.';
    } else {
        $action = $_POST['action'] ?? 'save';

        if ($action === 'test_email') {
            try {
                $testEmail = trim($_POST['test_email'] ?? '');
                if (!filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('Enter a valid email address for the test.');
                }
                (new MailService())->sendTestEmail($testEmail);
                $message = 'Test email sent to ' . $testEmail;
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        } else {
            $updates = [];
            foreach ($fields as $field) {
                if ($field === 'smtp_password' && trim($_POST[$field] ?? '') === '') {
                    continue;
                }
                if (in_array($field, ['stripe_secret_key', 'stripe_webhook_secret', 'clover_api_token', 'google_client_secret'], true)
                    && trim($_POST[$field] ?? '') === '') {
                    continue;
                }
                $updates[$field] = trim($_POST[$field] ?? '');
            }
            SettingsService::setMany($updates);
            $values = SettingsService::getGroup($fields);
            $message = 'Settings saved successfully.';
        }
    }
}

$status = [
    'stripe' => SettingsService::isGroupConfigured('stripe'),
    'clover' => SettingsService::isGroupConfigured('clover'),
    'smtp' => SettingsService::isGroupConfigured('smtp'),
    'google' => SettingsService::isGroupConfigured('google'),
];

$pageTitle = 'Admin Settings';
require dirname(__DIR__) . '/includes/header.php';
?>

<div class="container-fluid py-4">
    <?php require dirname(__DIR__) . '/includes/admin_nav.php'; ?>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h1 class="section-title mb-1">Settings</h1>
            <p class="text-muted mb-0">Manage Stripe, Clover POS, Gmail SMTP, and Google sign-in credentials.</p>
        </div>
    </div>

    <?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

    <div class="row g-3 mb-4">
        <?php foreach ($status as $name => $ok): ?>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <span class="stat-label"><?= ucfirst($name) ?></span>
                <span class="badge <?= $ok ? 'bg-success' : 'bg-secondary' ?>"><?= $ok ? 'Configured' : 'Not set' ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm settings-card">
                    <div class="card-header bg-white"><strong><i class="bi bi-credit-card text-danger"></i> Stripe</strong></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Secret key</label>
                            <input type="password" name="stripe_secret_key" class="form-control" placeholder="sk_live_..." autocomplete="off">
                            <div class="form-text">Leave blank to keep the current secret key.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Publishable key</label>
                            <input type="text" name="stripe_publishable_key" class="form-control" value="<?= e($values['stripe_publishable_key']) ?>">
                        </div>
                        <div class="mb-0">
                            <label class="form-label">Webhook secret</label>
                            <input type="password" name="stripe_webhook_secret" class="form-control" placeholder="whsec_..." autocomplete="off">
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card border-0 shadow-sm settings-card">
                    <div class="card-header bg-white"><strong><i class="bi bi-shop text-danger"></i> Clover POS</strong></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Merchant ID</label>
                            <input type="text" name="clover_merchant_id" class="form-control" value="<?= e($values['clover_merchant_id']) ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">API token</label>
                            <input type="password" name="clover_api_token" class="form-control" placeholder="Enter new token to update" autocomplete="off">
                        </div>
                        <div class="mb-0">
                            <label class="form-label">Environment</label>
                            <select name="clover_env" class="form-select">
                                <option value="sandbox" <?= $values['clover_env'] === 'sandbox' ? 'selected' : '' ?>>Sandbox</option>
                                <option value="production" <?= $values['clover_env'] === 'production' ? 'selected' : '' ?>>Production</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card border-0 shadow-sm settings-card">
                    <div class="card-header bg-white"><strong><i class="bi bi-envelope text-danger"></i> Gmail SMTP</strong></div>
                    <div class="card-body">
                        <p class="small text-muted">Use a Gmail App Password (Google Account → Security → 2-Step Verification → App passwords).</p>
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label">SMTP host</label>
                                <input type="text" name="smtp_host" class="form-control" value="<?= e($values['smtp_host'] ?: 'smtp.gmail.com') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Port</label>
                                <input type="number" name="smtp_port" class="form-control" value="<?= e($values['smtp_port'] ?: '587') ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Gmail address</label>
                                <input type="email" name="smtp_username" class="form-control" value="<?= e($values['smtp_username']) ?>" placeholder="yourstore@gmail.com">
                            </div>
                            <div class="col-12">
                                <label class="form-label">App password</label>
                                <input type="password" name="smtp_password" class="form-control" placeholder="16-character app password" autocomplete="off">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">From email</label>
                                <input type="email" name="smtp_from_email" class="form-control" value="<?= e($values['smtp_from_email']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">From name</label>
                                <input type="text" name="smtp_from_name" class="form-control" value="<?= e($values['smtp_from_name'] ?: "Abdu Market") ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card border-0 shadow-sm settings-card">
                    <div class="card-header bg-white"><strong><i class="bi bi-google text-danger"></i> Google Sign-In</strong></div>
                    <div class="card-body">
                        <p class="small text-muted">Create OAuth credentials in Google Cloud Console. Authorized redirect URI:</p>
                        <code class="d-block small mb-3 p-2 bg-light rounded"><?= e(rtrim(config('app.url'), '/') . '/auth/google-callback.php') ?></code>
                        <div class="mb-3">
                            <label class="form-label">Client ID</label>
                            <input type="text" name="google_client_id" class="form-control" value="<?= e($values['google_client_id']) ?>">
                        </div>
                        <div class="mb-0">
                            <label class="form-label">Client secret</label>
                            <input type="password" name="google_client_secret" class="form-control" placeholder="Enter new secret to update" autocomplete="off">
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card border-0 shadow-sm settings-card">
                    <div class="card-header bg-white"><strong><i class="bi bi-geo-alt text-danger"></i> Mart Details</strong></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Pickup address</label>
                                <input type="text" name="mart_address" class="form-control" value="<?= e($values['mart_address']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone</label>
                                <input type="text" name="mart_phone" class="form-control" value="<?= e($values['mart_phone']) ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Pickup instructions</label>
                                <textarea name="mart_pickup_instructions" class="form-control" rows="2"><?= e($values['mart_pickup_instructions']) ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2 mt-4">
            <button type="submit" class="btn btn-danger btn-lg">Save Settings</button>
        </div>
    </form>

    <form method="post" class="card border-0 shadow-sm settings-card mt-4">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="test_email">
        <div class="card-body d-flex flex-wrap gap-2 align-items-end">
            <div class="flex-grow-1">
                <label class="form-label">Send SMTP test email</label>
                <input type="email" name="test_email" class="form-control" placeholder="admin@example.com" required>
            </div>
            <button type="submit" class="btn btn-outline-danger">Send Test</button>
        </div>
    </form>
</div>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
