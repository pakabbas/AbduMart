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

$integrations = [
    'stripe' => [
        'label' => 'Stripe',
        'icon' => 'bi-credit-card-2-front',
        'ok' => SettingsService::isGroupConfigured('stripe'),
        'hint' => 'Payments & checkout',
    ],
    'clover' => [
        'label' => 'Clover POS',
        'icon' => 'bi-shop',
        'ok' => SettingsService::isGroupConfigured('clover'),
        'hint' => 'Products & inventory',
    ],
    'smtp' => [
        'label' => 'Gmail SMTP',
        'icon' => 'bi-envelope-paper',
        'ok' => SettingsService::isGroupConfigured('smtp'),
        'hint' => 'OTP & order emails',
    ],
    'google' => [
        'label' => 'Google Sign-In',
        'icon' => 'bi-google',
        'ok' => SettingsService::isGroupConfigured('google'),
        'hint' => 'OAuth login',
    ],
];

$pageTitle = 'Settings';
require dirname(__DIR__) . '/includes/header.php';
?>

<div class="admin-layout admin-settings-page">
    <?php require dirname(__DIR__) . '/includes/admin_nav.php'; ?>

    <div class="container-fluid admin-content">
        <div class="admin-page-header">
            <div>
                <p class="admin-eyebrow mb-1">Configuration</p>
                <h1 class="section-title mb-1">Settings</h1>
                <p class="text-muted mb-0">Manage integrations, email, and store details for Abdu Market.</p>
            </div>
            <button type="submit" form="settings-form" class="btn btn-danger btn-lg admin-save-btn">
                <i class="bi bi-check2-circle"></i> Save All Settings
            </button>
        </div>

        <?php if ($message): ?>
        <div class="alert alert-success admin-alert"><i class="bi bi-check-circle me-2"></i><?= e($message) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="alert alert-danger admin-alert"><i class="bi bi-exclamation-triangle me-2"></i><?= e($error) ?></div>
        <?php endif; ?>

        <div class="row g-3 mb-4">
            <?php foreach ($integrations as $key => $item): ?>
            <div class="col-6 col-xl-3">
                <div class="integration-card <?= $item['ok'] ? 'is-connected' : 'is-pending' ?>">
                    <div class="integration-icon"><i class="bi <?= e($item['icon']) ?>"></i></div>
                    <div class="integration-body">
                        <span class="integration-label"><?= e($item['label']) ?></span>
                        <span class="integration-status">
                            <span class="status-dot"></span>
                            <?= $item['ok'] ? 'Connected' : 'Not configured' ?>
                        </span>
                        <small class="text-muted"><?= e($item['hint']) ?></small>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <form method="post" id="settings-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">

            <ul class="nav nav-pills admin-settings-tabs mb-4" id="settingsTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="stripe-tab" data-bs-toggle="pill" data-bs-target="#stripe-panel" type="button" role="tab">
                        <i class="bi bi-credit-card-2-front"></i> Stripe
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="clover-tab" data-bs-toggle="pill" data-bs-target="#clover-panel" type="button" role="tab">
                        <i class="bi bi-shop"></i> Clover
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="smtp-tab" data-bs-toggle="pill" data-bs-target="#smtp-panel" type="button" role="tab">
                        <i class="bi bi-envelope-paper"></i> Email
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="google-tab" data-bs-toggle="pill" data-bs-target="#google-panel" type="button" role="tab">
                        <i class="bi bi-google"></i> Google
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="store-tab" data-bs-toggle="pill" data-bs-target="#store-panel" type="button" role="tab">
                        <i class="bi bi-geo-alt"></i> Store
                    </button>
                </li>
            </ul>

            <div class="tab-content">
                <div class="tab-pane fade show active" id="stripe-panel" role="tabpanel">
                    <div class="settings-panel-card">
                        <div class="settings-panel-header">
                            <div class="settings-panel-icon"><i class="bi bi-credit-card-2-front"></i></div>
                            <div>
                                <h2>Stripe Payments</h2>
                                <p>Accept card payments at checkout. Add your webhook for order confirmation.</p>
                            </div>
                        </div>
                        <div class="settings-panel-body">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Secret key</label>
                                    <input type="password" name="stripe_secret_key" class="form-control form-control-lg" placeholder="sk_live_..." autocomplete="off">
                                    <div class="form-text">Leave blank to keep the current secret key.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Publishable key</label>
                                    <input type="text" name="stripe_publishable_key" class="form-control" value="<?= e($values['stripe_publishable_key']) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Webhook secret</label>
                                    <input type="password" name="stripe_webhook_secret" class="form-control" placeholder="whsec_..." autocomplete="off">
                                </div>
                            </div>
                            <div class="settings-hint-box mt-3">
                                <i class="bi bi-link-45deg"></i>
                                Webhook URL: <code><?= e(rtrim(config('app.url'), '/') . '/stripe-webhook.php') ?></code>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="clover-panel" role="tabpanel">
                    <div class="settings-panel-card">
                        <div class="settings-panel-header">
                            <div class="settings-panel-icon"><i class="bi bi-shop"></i></div>
                            <div>
                                <h2>Clover POS</h2>
                                <p>Sync categories, products, prices, and inventory from your Clover account.</p>
                            </div>
                        </div>
                        <div class="settings-panel-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Merchant ID</label>
                                    <input type="text" name="clover_merchant_id" class="form-control" value="<?= e($values['clover_merchant_id']) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Environment</label>
                                    <select name="clover_env" class="form-select">
                                        <option value="sandbox" <?= $values['clover_env'] === 'sandbox' ? 'selected' : '' ?>>Sandbox (testing)</option>
                                        <option value="production" <?= $values['clover_env'] === 'production' ? 'selected' : '' ?>>Production (live)</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">API token</label>
                                    <input type="password" name="clover_api_token" class="form-control" placeholder="Enter new token to update" autocomplete="off">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="smtp-panel" role="tabpanel">
                    <div class="settings-panel-card">
                        <div class="settings-panel-header">
                            <div class="settings-panel-icon"><i class="bi bi-envelope-paper"></i></div>
                            <div>
                                <h2>Gmail SMTP</h2>
                                <p>Send sign-up OTPs, password resets, and order confirmation emails.</p>
                            </div>
                        </div>
                        <div class="settings-panel-body">
                            <div class="settings-hint-box mb-4">
                                <i class="bi bi-info-circle"></i>
                                Use a Gmail <strong>App Password</strong> — Google Account → Security → 2-Step Verification → App passwords.
                            </div>
                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label class="form-label">SMTP host</label>
                                    <input type="text" name="smtp_host" class="form-control" value="<?= e($values['smtp_host'] ?: 'smtp.gmail.com') ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Port</label>
                                    <input type="number" name="smtp_port" class="form-control" value="<?= e($values['smtp_port'] ?: '587') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Gmail address</label>
                                    <input type="email" name="smtp_username" class="form-control" value="<?= e($values['smtp_username']) ?>" placeholder="yourstore@gmail.com">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">App password</label>
                                    <input type="password" name="smtp_password" class="form-control" placeholder="16-character app password" autocomplete="off">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">From email</label>
                                    <input type="email" name="smtp_from_email" class="form-control" value="<?= e($values['smtp_from_email']) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">From name</label>
                                    <input type="text" name="smtp_from_name" class="form-control" value="<?= e($values['smtp_from_name'] ?: 'Abdu Market') ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="settings-panel-card mt-4">
                        <div class="settings-panel-header compact">
                            <div class="settings-panel-icon small"><i class="bi bi-send"></i></div>
                            <div>
                                <h2>Test Email</h2>
                                <p>Verify SMTP is working before going live.</p>
                            </div>
                        </div>
                        <div class="settings-panel-body">
                            <div class="d-flex flex-wrap gap-2 align-items-end">
                                <div class="flex-grow-1" style="min-width: 220px;">
                                    <label class="form-label">Send test to</label>
                                    <input type="email" name="test_email" form="test-email-form" class="form-control" placeholder="admin@example.com">
                                </div>
                                <button type="submit" form="test-email-form" class="btn btn-outline-danger btn-lg">
                                    <i class="bi bi-send"></i> Send Test
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="google-panel" role="tabpanel">
                    <div class="settings-panel-card">
                        <div class="settings-panel-header">
                            <div class="settings-panel-icon"><i class="bi bi-google"></i></div>
                            <div>
                                <h2>Google Sign-In</h2>
                                <p>Let customers sign in with their Google account.</p>
                            </div>
                        </div>
                        <div class="settings-panel-body">
                            <div class="settings-hint-box mb-4">
                                <i class="bi bi-link-45deg"></i>
                                Authorized redirect URI:<br>
                                <code><?= e(rtrim(config('app.url'), '/') . '/auth/google-callback.php') ?></code>
                            </div>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Client ID</label>
                                    <input type="text" name="google_client_id" class="form-control" value="<?= e($values['google_client_id']) ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Client secret</label>
                                    <input type="password" name="google_client_secret" class="form-control" placeholder="Enter new secret to update" autocomplete="off">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="store-panel" role="tabpanel">
                    <div class="settings-panel-card">
                        <div class="settings-panel-header">
                            <div class="settings-panel-icon"><i class="bi bi-geo-alt"></i></div>
                            <div>
                                <h2>Store Details</h2>
                                <p>Pickup location and instructions shown to customers.</p>
                            </div>
                        </div>
                        <div class="settings-panel-body">
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
                                    <textarea name="mart_pickup_instructions" class="form-control" rows="3"><?= e($values['mart_pickup_instructions']) ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="settings-save-bar">
                <span class="text-muted small"><i class="bi bi-shield-lock"></i> Secrets are encrypted in the database.</span>
                <button type="submit" class="btn btn-danger btn-lg">
                    <i class="bi bi-check2-circle"></i> Save All Settings
                </button>
            </div>
        </form>

        <form method="post" id="test-email-form" class="d-none">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="test_email">
        </form>
    </div>
</div>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
