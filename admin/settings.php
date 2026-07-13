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
                    throw new RuntimeException('Enter a valid email address.');
                }
                (new MailService())->sendTestEmail($testEmail);
                $message = 'Test email sent to ' . $testEmail;
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        } else {
            $updates = [];
            foreach ($fields as $field) {
                if ($field === 'smtp_password' && trim($_POST[$field] ?? '') === '') continue;
                if (in_array($field, ['stripe_secret_key', 'stripe_webhook_secret', 'clover_api_token', 'google_client_secret'], true)
                    && trim($_POST[$field] ?? '') === '') continue;
                $updates[$field] = trim($_POST[$field] ?? '');
            }
            SettingsService::setMany($updates);
            $values = SettingsService::getGroup($fields);
            $message = 'Settings saved successfully.';
        }
    }
}

$status = [
    ['key' => 'stripe', 'label' => 'Stripe', 'icon' => 'bi-credit-card', 'ok' => SettingsService::isGroupConfigured('stripe')],
    ['key' => 'clover', 'label' => 'Clover', 'icon' => 'bi-shop', 'ok' => SettingsService::isGroupConfigured('clover')],
    ['key' => 'smtp', 'label' => 'Email', 'icon' => 'bi-envelope', 'ok' => SettingsService::isGroupConfigured('smtp')],
    ['key' => 'google', 'label' => 'Google', 'icon' => 'bi-google', 'ok' => SettingsService::isGroupConfigured('google')],
];

$pageTitle = 'Settings';
$pageSubtitle = 'Integrations, email, and store information';
$headerActions = '<button type="submit" form="settings-form" class="admin-btn admin-btn-primary"><i class="bi bi-check2"></i> Save changes</button>';

require dirname(__DIR__) . '/includes/admin_header.php';

if ($message): ?>
<div class="admin-toast admin-toast-success"><i class="bi bi-check-circle"></i> <?= e($message) ?></div>
<?php endif;
if ($error): ?>
<div class="admin-toast admin-toast-danger"><i class="bi bi-exclamation-triangle"></i> <?= e($error) ?></div>
<?php endif; ?>

<div class="settings-status-row">
    <?php foreach ($status as $s): ?>
    <div class="status-chip <?= $s['ok'] ? 'ok' : '' ?>">
        <div class="chip-icon"><i class="bi <?= e($s['icon']) ?>"></i></div>
        <div>
            <strong><?= e($s['label']) ?></strong>
            <span><?= $s['ok'] ? 'Connected' : 'Not configured' ?></span>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<form method="post" id="settings-form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">

    <div class="settings-shell">
        <nav class="settings-nav">
            <a href="#stripe" class="active"><i class="bi bi-credit-card"></i> Stripe</a>
            <a href="#clover"><i class="bi bi-shop"></i> Clover POS</a>
            <a href="#smtp"><i class="bi bi-envelope"></i> Email SMTP</a>
            <a href="#google"><i class="bi bi-google"></i> Google Auth</a>
            <a href="#store"><i class="bi bi-geo-alt"></i> Store info</a>
        </nav>

        <div class="settings-content">
            <section class="settings-section" id="stripe">
                <div class="settings-section-head">
                    <h2><i class="bi bi-credit-card"></i> Stripe Payments</h2>
                    <p>Accept online card payments at checkout.</p>
                </div>
                <div class="settings-section-body">
                    <div class="admin-callout">
                        Webhook URL: <code><?= e(rtrim(config('app.url'), '/') . '/stripe-webhook.php') ?></code>
                    </div>
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="admin-field">
                                <label>Secret key</label>
                                <input type="password" name="stripe_secret_key" class="admin-input" placeholder="sk_live_..." autocomplete="off">
                                <div class="hint">Leave blank to keep existing key.</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="admin-field">
                                <label>Publishable key</label>
                                <input type="text" name="stripe_publishable_key" class="admin-input" value="<?= e($values['stripe_publishable_key']) ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="admin-field">
                                <label>Webhook secret</label>
                                <input type="password" name="stripe_webhook_secret" class="admin-input" placeholder="whsec_..." autocomplete="off">
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="settings-section" id="clover">
                <div class="settings-section-head">
                    <h2><i class="bi bi-shop"></i> Clover POS</h2>
                    <p>Sync products, prices, and inventory from Clover.</p>
                </div>
                <div class="settings-section-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="admin-field">
                                <label>Merchant ID</label>
                                <input type="text" name="clover_merchant_id" class="admin-input" value="<?= e($values['clover_merchant_id']) ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="admin-field">
                                <label>Environment</label>
                                <select name="clover_env" class="admin-input">
                                    <option value="sandbox" <?= $values['clover_env'] === 'sandbox' ? 'selected' : '' ?>>Sandbox</option>
                                    <option value="production" <?= $values['clover_env'] === 'production' ? 'selected' : '' ?>>Production</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="admin-field">
                                <label>API token</label>
                                <input type="password" name="clover_api_token" class="admin-input" placeholder="Enter new token to update" autocomplete="off">
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="settings-section" id="smtp">
                <div class="settings-section-head">
                    <h2><i class="bi bi-envelope"></i> Gmail SMTP</h2>
                    <p>Send OTP codes, password resets, and order confirmations.</p>
                </div>
                <div class="settings-section-body">
                    <div class="admin-callout">
                        Use a Gmail <strong>App Password</strong> — Google Account → Security → 2-Step Verification → App passwords.
                    </div>
                    <div class="row g-3">
                        <div class="col-md-8">
                            <div class="admin-field">
                                <label>SMTP host</label>
                                <input type="text" name="smtp_host" class="admin-input" value="<?= e($values['smtp_host'] ?: 'smtp.gmail.com') ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="admin-field">
                                <label>Port</label>
                                <input type="number" name="smtp_port" class="admin-input" value="<?= e($values['smtp_port'] ?: '587') ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="admin-field">
                                <label>Gmail address</label>
                                <input type="email" name="smtp_username" class="admin-input" value="<?= e($values['smtp_username']) ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="admin-field">
                                <label>App password</label>
                                <input type="password" name="smtp_password" class="admin-input" placeholder="16-character password" autocomplete="off">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="admin-field">
                                <label>From email</label>
                                <input type="email" name="smtp_from_email" class="admin-input" value="<?= e($values['smtp_from_email']) ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="admin-field">
                                <label>From name</label>
                                <input type="text" name="smtp_from_name" class="admin-input" value="<?= e($values['smtp_from_name'] ?: 'Abdu Market') ?>">
                            </div>
                        </div>
                    </div>
                    <hr class="my-4">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-8">
                            <div class="admin-field mb-0">
                                <label>Send test email to</label>
                                <input type="email" name="test_email" form="test-email-form" class="admin-input" placeholder="you@example.com">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" form="test-email-form" class="admin-btn admin-btn-outline w-100">
                                <i class="bi bi-send"></i> Send test
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <section class="settings-section" id="google">
                <div class="settings-section-head">
                    <h2><i class="bi bi-google"></i> Google Sign-In</h2>
                    <p>Allow customers to sign in with Google.</p>
                </div>
                <div class="settings-section-body">
                    <div class="admin-callout">
                        Redirect URI: <code><?= e(rtrim(config('app.url'), '/') . '/auth/google-callback.php') ?></code>
                    </div>
                    <div class="admin-field">
                        <label>Client ID</label>
                        <input type="text" name="google_client_id" class="admin-input" value="<?= e($values['google_client_id']) ?>">
                    </div>
                    <div class="admin-field mb-0">
                        <label>Client secret</label>
                        <input type="password" name="google_client_secret" class="admin-input" placeholder="Enter new secret to update" autocomplete="off">
                    </div>
                </div>
            </section>

            <section class="settings-section" id="store">
                <div class="settings-section-head">
                    <h2><i class="bi bi-geo-alt"></i> Store Information</h2>
                    <p>Pickup location shown to customers.</p>
                </div>
                <div class="settings-section-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="admin-field">
                                <label>Address</label>
                                <input type="text" name="mart_address" class="admin-input" value="<?= e($values['mart_address']) ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="admin-field">
                                <label>Phone</label>
                                <input type="text" name="mart_phone" class="admin-input" value="<?= e($values['mart_phone']) ?>">
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="admin-field mb-0">
                                <label>Pickup instructions</label>
                                <textarea name="mart_pickup_instructions" class="admin-input" rows="3"><?= e($values['mart_pickup_instructions']) ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <div class="settings-footer-bar">
                <span><i class="bi bi-shield-lock"></i> Secrets are encrypted in the database</span>
                <button type="submit" class="admin-btn admin-btn-primary">
                    <i class="bi bi-check2"></i> Save all changes
                </button>
            </div>
        </div>
    </div>
</form>

<form method="post" id="test-email-form" class="d-none">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="test_email">
</form>

<?php require dirname(__DIR__) . '/includes/admin_footer.php'; ?>
