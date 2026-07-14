<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

use App\MailService;
use App\SettingsService;
use App\StoreHoursService;

$adminSection = 'settings';
$message = null;
$error = null;

$fields = [
    'stripe_secret_key', 'stripe_publishable_key', 'stripe_webhook_secret',
    'clover_merchant_id', 'clover_api_token', 'clover_env',
    'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_from_email', 'smtp_from_name',
    'admin_notify_email_1', 'admin_notify_email_2', 'admin_notify_email_3',
    'google_client_id', 'google_client_secret',
    'mart_address', 'mart_phone', 'mart_pickup_instructions',
    'store_timezone', 'store_location', 'store_hours_json', 'store_holidays_json',
    'allow_pay_on_arrival',
    'theme_primary_color',
    'theme_secondary_color',
    'theme_mode',
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
            for ($i = 1; $i <= 3; $i++) {
                $notifyKey = 'admin_notify_email_' . $i;
                $notifyEmail = strtolower(trim($_POST[$notifyKey] ?? ''));
                if ($notifyEmail !== '' && !filter_var($notifyEmail, FILTER_VALIDATE_EMAIL)) {
                    $error = 'Enter a valid admin notification email for address ' . $i . ', or leave it blank.';
                    break;
                }
            }

            if (!$error) {
            $timezone = trim($_POST['store_timezone'] ?? '');
            if ($timezone !== '') {
                try {
                    new DateTimeZone($timezone);
                } catch (Throwable) {
                    $error = 'Choose a valid store timezone.';
                }
            }

            if (!$error) {
                try {
                    $encoded = StoreHoursService::encodeSettings(
                        is_array($_POST['store_hours'] ?? null) ? $_POST['store_hours'] : [],
                        is_array($_POST['store_holidays'] ?? null) ? $_POST['store_holidays'] : []
                    );
                    $_POST['store_hours_json'] = $encoded['hours_json'];
                    $_POST['store_holidays_json'] = $encoded['holidays_json'];
                } catch (Throwable) {
                    $error = 'Could not save store hours or holidays.';
                }
            }

            if (!$error) {
                $themeMode = (($_POST['theme_mode'] ?? 'solid') === 'gradient') ? 'gradient' : 'solid';
                $themeColor = normalize_theme_hex((string) ($_POST['theme_primary_color'] ?? ''));
                $themeSecondary = normalize_theme_hex((string) ($_POST['theme_secondary_color'] ?? theme_default_secondary()));
                if ($themeColor === null) {
                    $error = 'Choose a valid start color (hex format like #c8102e).';
                } elseif ($themeMode === 'gradient' && $themeSecondary === null) {
                    $error = 'Choose a valid end color for the gradient.';
                } else {
                    $_POST['theme_mode'] = $themeMode;
                    $_POST['theme_primary_color'] = $themeColor;
                    $_POST['theme_secondary_color'] = $themeSecondary ?? theme_default_secondary();
                }
            }

            if (!$error) {
            foreach ($fields as $field) {
                if ($field === 'smtp_password' && trim($_POST[$field] ?? '') === '') continue;
                if (in_array($field, ['stripe_secret_key', 'stripe_webhook_secret', 'clover_api_token', 'google_client_secret'], true)
                    && trim($_POST[$field] ?? '') === '') continue;
                if ($field === 'allow_pay_on_arrival') {
                    $updates[$field] = !empty($_POST[$field]) ? '1' : '';
                } elseif (str_starts_with($field, 'admin_notify_email_')) {
                    $updates[$field] = strtolower(trim($_POST[$field] ?? ''));
                } else {
                    $updates[$field] = trim($_POST[$field] ?? '');
                }
            }
            SettingsService::setMany($updates);
            $values = SettingsService::getGroup($fields);
            $message = 'Settings saved successfully.';
            }
            }
        }
    }
}

$values = SettingsService::getGroup($fields);
$themeMode = (($values['theme_mode'] ?? 'solid') === 'gradient') ? 'gradient' : 'solid';
$themePrimary = normalize_theme_hex((string) ($values['theme_primary_color'] ?? '')) ?? theme_default_primary();
$themeSecondary = normalize_theme_hex((string) ($values['theme_secondary_color'] ?? '')) ?? theme_default_secondary();
$themeFillPreview = $themeMode === 'gradient'
    ? 'linear-gradient(135deg, ' . $themePrimary . ' 0%, ' . $themeSecondary . ' 100%)'
    : $themePrimary;
$themePresets = [
    ['id' => 'red', 'label' => 'Red', 'mode' => 'solid', 'primary' => theme_default_primary(), 'secondary' => theme_default_secondary()],
    ['id' => 'green', 'label' => 'Green', 'mode' => 'solid', 'primary' => '#2bf728', 'secondary' => '#16a34a'],
    ['id' => 'red-glow', 'label' => 'Red glow', 'mode' => 'gradient', 'primary' => '#c8102e', 'secondary' => '#ff6b35'],
    ['id' => 'green-glow', 'label' => 'Green glow', 'mode' => 'gradient', 'primary' => '#2bf728', 'secondary' => '#0ea5e9'],
];
$storeHours = StoreHoursService::weeklyHours();
$storeHolidays = StoreHoursService::holidays();
$storeTimezone = StoreHoursService::timezone();
$timezoneOptions = StoreHoursService::usTimezoneOptions();
$storeOpenNow = StoreHoursService::isOpen();

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
            <a href="#theme"><i class="bi bi-palette"></i> Theme</a>
            <a href="#store-hours"><i class="bi bi-clock"></i> Hours & holidays</a>
        </nav>

        <div class="settings-content">
            <section class="settings-section" id="stripe">
                <div class="settings-section-head">
                    <h2><i class="bi bi-credit-card"></i> Stripe Payments</h2>
                    <p>Accept online card payments at checkout.</p>
                </div>
                <div class="settings-section-body">
                    <div class="admin-callout">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <div>
                                <strong>Allow “Pay on Arrival”</strong>
                                <div class="hint mb-0">If enabled, customers can place an order without paying online.</div>
                            </div>
                            <label class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" name="allow_pay_on_arrival" value="1" <?= ($values['allow_pay_on_arrival'] ?? '') === '1' ? 'checked' : '' ?>>
                            </label>
                        </div>
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
                    <h3 class="h6 mb-2">Admin order notifications</h3>
                    <p class="hint mb-3">Up to 3 addresses receive an email when a new order is placed and when a customer taps <strong>I'm Here</strong>.</p>
                    <div class="row g-3">
                        <?php for ($i = 1; $i <= 3; $i++): ?>
                        <div class="col-md-4">
                            <div class="admin-field mb-0">
                                <label>Notification email <?= $i ?></label>
                                <input
                                    type="email"
                                    name="admin_notify_email_<?= $i ?>"
                                    class="admin-input"
                                    value="<?= e($values['admin_notify_email_' . $i] ?? '') ?>"
                                    placeholder="<?= $i === 1 ? 'manager@example.com' : 'Optional' ?>"
                                >
                            </div>
                        </div>
                        <?php endfor; ?>
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

            <section class="settings-section" id="theme">
                <div class="settings-section-head">
                    <h2><i class="bi bi-palette"></i> Theme color</h2>
                    <p>Use a solid color or a two-color gradient. Whites and neutrals stay the same.</p>
                </div>
                <div class="settings-section-body">
                    <div class="admin-callout mb-4">
                        Updates buttons, badges, logos, and accents on both the storefront and admin panel.
                    </div>

                    <div class="theme-mode-toggle mb-3" role="group" aria-label="Theme style">
                        <label class="theme-mode-option<?= $themeMode === 'solid' ? ' is-selected' : '' ?>">
                            <input type="radio" name="theme_mode" value="solid" <?= $themeMode === 'solid' ? 'checked' : '' ?>>
                            <span>Solid</span>
                        </label>
                        <label class="theme-mode-option<?= $themeMode === 'gradient' ? ' is-selected' : '' ?>">
                            <input type="radio" name="theme_mode" value="gradient" <?= $themeMode === 'gradient' ? 'checked' : '' ?>>
                            <span>Gradient</span>
                        </label>
                    </div>

                    <div class="theme-picker-grid">
                        <?php foreach ($themePresets as $preset): ?>
                        <?php
                            $presetFill = $preset['mode'] === 'gradient'
                                ? 'linear-gradient(135deg, ' . $preset['primary'] . ' 0%, ' . $preset['secondary'] . ' 100%)'
                                : $preset['primary'];
                            $isSelected = $themeMode === $preset['mode']
                                && $themePrimary === $preset['primary']
                                && ($preset['mode'] === 'solid' || $themeSecondary === $preset['secondary']);
                        ?>
                        <label class="theme-preset<?= $isSelected ? ' is-selected' : '' ?>" data-theme-preset="<?= e($preset['id']) ?>">
                            <input
                                type="radio"
                                name="theme_preset"
                                value="<?= e($preset['id']) ?>"
                                class="theme-preset-radio"
                                data-mode="<?= e($preset['mode']) ?>"
                                data-primary="<?= e($preset['primary']) ?>"
                                data-secondary="<?= e($preset['secondary']) ?>"
                                <?= $isSelected ? 'checked' : '' ?>
                            >
                            <span class="theme-preset-swatch" style="background:<?= e($presetFill) ?>"></span>
                            <span class="theme-preset-meta">
                                <strong><?= e($preset['label']) ?></strong>
                                <small><?= $preset['mode'] === 'gradient' ? 'Gradient' : strtoupper($preset['primary']) ?></small>
                            </span>
                        </label>
                        <?php endforeach; ?>
                        <?php
                            $customSelected = true;
                            foreach ($themePresets as $preset) {
                                if (
                                    $themeMode === $preset['mode']
                                    && $themePrimary === $preset['primary']
                                    && ($preset['mode'] === 'solid' || $themeSecondary === $preset['secondary'])
                                ) {
                                    $customSelected = false;
                                    break;
                                }
                            }
                        ?>
                        <label class="theme-preset theme-preset-custom<?= $customSelected ? ' is-selected' : '' ?>">
                            <input type="radio" name="theme_preset" value="custom" class="theme-preset-radio" data-mode="custom" <?= $customSelected ? 'checked' : '' ?>>
                            <span class="theme-preset-swatch theme-preset-swatch-custom" id="themeCustomSwatch" style="background:<?= e($themeFillPreview) ?>"></span>
                            <span class="theme-preset-meta">
                                <strong>Custom</strong>
                                <small>Your colors</small>
                            </span>
                        </label>
                    </div>

                    <div class="row g-3 align-items-end mt-1">
                        <div class="col-md-3">
                            <div class="admin-field mb-0">
                                <label for="theme_primary_picker">Start color</label>
                                <input type="color" id="theme_primary_picker" class="admin-input theme-color-input" value="<?= e($themePrimary) ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="admin-field mb-0">
                                <label for="theme_primary_color">Start hex</label>
                                <input type="text" id="theme_primary_color" name="theme_primary_color" class="admin-input" value="<?= e($themePrimary) ?>" pattern="^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$" required>
                            </div>
                        </div>
                        <div class="col-md-3 theme-secondary-fields<?= $themeMode === 'gradient' ? '' : ' d-none' ?>">
                            <div class="admin-field mb-0">
                                <label for="theme_secondary_picker">End color</label>
                                <input type="color" id="theme_secondary_picker" class="admin-input theme-color-input" value="<?= e($themeSecondary) ?>">
                            </div>
                        </div>
                        <div class="col-md-3 theme-secondary-fields<?= $themeMode === 'gradient' ? '' : ' d-none' ?>">
                            <div class="admin-field mb-0">
                                <label for="theme_secondary_color">End hex</label>
                                <input type="text" id="theme_secondary_color" name="theme_secondary_color" class="admin-input" value="<?= e($themeSecondary) ?>" pattern="^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$">
                            </div>
                        </div>
                        <div class="col-12 col-lg-3">
                            <div class="theme-live-preview" id="themeLivePreview" style="--preview-fill:<?= e($themeFillPreview) ?>; --preview-color:<?= e($themePrimary) ?>">
                                <span class="theme-live-btn">Primary button</span>
                                <span class="theme-live-link">Accent text</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="settings-section" id="store-hours">
                <div class="settings-section-head">
                    <h2><i class="bi bi-clock"></i> Store Timings & Holidays</h2>
                    <p>Control when customers can place orders and when Clover auto-sync runs (hourly while open).</p>
                </div>
                <div class="settings-section-body">
                    <div class="admin-callout d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
                        <div>
                            <strong>Store status right now</strong>
                            <div class="hint mb-0">Based on timezone, weekly hours, and holidays below.</div>
                        </div>
                        <span class="admin-badge <?= $storeOpenNow ? 'admin-badge-green' : 'admin-badge-red' ?>">
                            <?= $storeOpenNow ? 'Open' : 'Closed' ?>
                        </span>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <div class="admin-field">
                                <label for="store_timezone">Store timezone</label>
                                <select id="store_timezone" name="store_timezone" class="admin-input">
                                    <?php foreach ($timezoneOptions as $tzValue => $tzLabel): ?>
                                    <option value="<?= e($tzValue) ?>" <?= $storeTimezone === $tzValue ? 'selected' : '' ?>><?= e($tzLabel) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="hint">All opening hours use this timezone.</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="admin-field">
                                <label for="store_location">Store location label</label>
                                <input type="text" id="store_location" name="store_location" class="admin-input" value="<?= e($values['store_location'] ?? '') ?>" placeholder="e.g. Abdu Market — Canton, MI">
                                <div class="hint">Optional short label for internal reference.</div>
                            </div>
                        </div>
                    </div>

                    <div class="store-hours-panel mb-4">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                            <h3 class="h6 mb-0">Weekly store hours</h3>
                            <button type="button" class="admin-btn admin-btn-outline admin-btn-sm" id="copyMondayHoursBtn">
                                <i class="bi bi-copy"></i> Copy Monday to all days
                            </button>
                        </div>
                        <div class="store-hours-grid">
                            <?php foreach (StoreHoursService::DAY_KEYS as $dayKey): ?>
                            <?php $day = $storeHours[$dayKey] ?? ['closed' => false, 'open' => '09:00', 'close' => '21:00']; ?>
                            <div class="store-hours-row" data-day="<?= e($dayKey) ?>">
                                <div class="store-hours-day"><?= e(StoreHoursService::DAY_LABELS[$dayKey]) ?></div>
                                <label class="store-hours-closed-toggle">
                                    <input type="checkbox" name="store_hours[<?= e($dayKey) ?>][closed]" value="1" class="store-day-closed" <?= !empty($day['closed']) ? 'checked' : '' ?>>
                                    <span>Closed</span>
                                </label>
                                <div class="store-hours-times">
                                    <input type="time" name="store_hours[<?= e($dayKey) ?>][open]" class="admin-input store-day-open" value="<?= e($day['open']) ?>" <?= !empty($day['closed']) ? 'disabled' : '' ?>>
                                    <span class="store-hours-sep">to</span>
                                    <input type="time" name="store_hours[<?= e($dayKey) ?>][close]" class="admin-input store-day-close" value="<?= e($day['close']) ?>" <?= !empty($day['closed']) ? 'disabled' : '' ?>>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="store-holidays-panel">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                            <div>
                                <h3 class="h6 mb-1">Holidays & closed dates</h3>
                                <p class="hint mb-0">The store is closed all day on these dates (in store timezone).</p>
                            </div>
                            <button type="button" class="admin-btn admin-btn-outline admin-btn-sm" id="addHolidayBtn">
                                <i class="bi bi-plus-lg"></i> Add holiday
                            </button>
                        </div>
                        <div id="holidayRows" class="store-holidays-list">
                            <?php if (empty($storeHolidays)): ?>
                            <p class="hint mb-0" id="holidayEmptyHint">No holidays added yet.</p>
                            <?php endif; ?>
                            <?php foreach ($storeHolidays as $index => $holiday): ?>
                            <div class="store-holiday-row">
                                <input type="date" name="store_holidays[<?= (int) $index ?>][date]" class="admin-input" value="<?= e($holiday['date']) ?>" required>
                                <input type="text" name="store_holidays[<?= (int) $index ?>][name]" class="admin-input" value="<?= e($holiday['name']) ?>" placeholder="Holiday name (optional)">
                                <button type="button" class="admin-btn admin-btn-outline admin-btn-sm text-danger holiday-remove-btn" aria-label="Remove holiday"><i class="bi bi-trash"></i></button>
                            </div>
                            <?php endforeach; ?>
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

<script>
(function () {
    const holidayRows = document.getElementById('holidayRows');
    const addHolidayBtn = document.getElementById('addHolidayBtn');
    const copyMondayBtn = document.getElementById('copyMondayHoursBtn');
    let holidayIndex = holidayRows ? holidayRows.querySelectorAll('.store-holiday-row').length : 0;

    function toggleDayRow(row) {
        const closed = row.querySelector('.store-day-closed');
        const openInput = row.querySelector('.store-day-open');
        const closeInput = row.querySelector('.store-day-close');
        const isClosed = closed && closed.checked;
        if (openInput) openInput.disabled = isClosed;
        if (closeInput) closeInput.disabled = isClosed;
        row.classList.toggle('is-closed', !!isClosed);
    }

    document.querySelectorAll('.store-hours-row').forEach(function (row) {
        const closed = row.querySelector('.store-day-closed');
        toggleDayRow(row);
        closed?.addEventListener('change', function () {
            toggleDayRow(row);
        });
    });

    copyMondayBtn?.addEventListener('click', function () {
        const monday = document.querySelector('.store-hours-row[data-day="monday"]');
        if (!monday) return;
        const closed = monday.querySelector('.store-day-closed')?.checked || false;
        const openVal = monday.querySelector('.store-day-open')?.value || '09:00';
        const closeVal = monday.querySelector('.store-day-close')?.value || '21:00';
        document.querySelectorAll('.store-hours-row').forEach(function (row) {
            if (row.dataset.day === 'monday') return;
            const closedInput = row.querySelector('.store-day-closed');
            const openInput = row.querySelector('.store-day-open');
            const closeInput = row.querySelector('.store-day-close');
            if (closedInput) closedInput.checked = closed;
            if (openInput) openInput.value = openVal;
            if (closeInput) closeInput.value = closeVal;
            toggleDayRow(row);
        });
    });

    function bindHolidayRemove(row) {
        row.querySelector('.holiday-remove-btn')?.addEventListener('click', function () {
            row.remove();
            if (holidayRows && holidayRows.querySelectorAll('.store-holiday-row').length === 0) {
                const hint = document.createElement('p');
                hint.className = 'hint mb-0';
                hint.id = 'holidayEmptyHint';
                hint.textContent = 'No holidays added yet.';
                holidayRows.appendChild(hint);
            }
        });
    }

    holidayRows?.querySelectorAll('.store-holiday-row').forEach(bindHolidayRemove);

    addHolidayBtn?.addEventListener('click', function () {
        document.getElementById('holidayEmptyHint')?.remove();
        const row = document.createElement('div');
        row.className = 'store-holiday-row';
        row.innerHTML =
            '<input type="date" name="store_holidays[' + holidayIndex + '][date]" class="admin-input" required>' +
            '<input type="text" name="store_holidays[' + holidayIndex + '][name]" class="admin-input" placeholder="Holiday name (optional)">' +
            '<button type="button" class="admin-btn admin-btn-outline admin-btn-sm text-danger holiday-remove-btn" aria-label="Remove holiday"><i class="bi bi-trash"></i></button>';
        holidayRows.appendChild(row);
        bindHolidayRemove(row);
        holidayIndex++;
        row.querySelector('input[type="date"]')?.focus();
    });

    const hexInput = document.getElementById('theme_primary_color');
    const colorPicker = document.getElementById('theme_primary_picker');
    const secondaryHexInput = document.getElementById('theme_secondary_color');
    const secondaryPicker = document.getElementById('theme_secondary_picker');
    const customSwatch = document.getElementById('themeCustomSwatch');
    const livePreview = document.getElementById('themeLivePreview');
    const presetRadios = document.querySelectorAll('.theme-preset-radio');
    const modeRadios = document.querySelectorAll('input[name="theme_mode"]');
    const secondaryFields = document.querySelectorAll('.theme-secondary-fields');

    function normalizeHex(value) {
        const raw = String(value || '').trim();
        if (/^#[0-9a-fA-F]{6}$/.test(raw)) return raw.toLowerCase();
        if (/^#[0-9a-fA-F]{3}$/.test(raw)) {
            return ('#' + raw[1] + raw[1] + raw[2] + raw[2] + raw[3] + raw[3]).toLowerCase();
        }
        return null;
    }

    function currentMode() {
        const checked = document.querySelector('input[name="theme_mode"]:checked');
        return checked && checked.value === 'gradient' ? 'gradient' : 'solid';
    }

    function fillValue(primary, secondary, mode) {
        if (mode === 'gradient') {
            return 'linear-gradient(135deg, ' + primary + ' 0%, ' + secondary + ' 100%)';
        }
        return primary;
    }

    function syncModeUi(mode) {
        document.querySelectorAll('.theme-mode-option').forEach(function (el) {
            const input = el.querySelector('input[name="theme_mode"]');
            el.classList.toggle('is-selected', !!(input && input.value === mode && input.checked));
        });
        secondaryFields.forEach(function (el) {
            el.classList.toggle('d-none', mode !== 'gradient');
        });
    }

    function markCustomPreset() {
        const customRadio = document.querySelector('.theme-preset-radio[value="custom"]');
        if (customRadio) customRadio.checked = true;
        document.querySelectorAll('.theme-preset').forEach(function (el) {
            const radio = el.querySelector('.theme-preset-radio');
            el.classList.toggle('is-selected', !!(radio && radio.value === 'custom'));
        });
    }

    function applyThemePreview(opts) {
        const mode = opts.mode || currentMode();
        const primary = normalizeHex(opts.primary || hexInput?.value) || '#c8102e';
        const secondary = normalizeHex(opts.secondary || secondaryHexInput?.value) || '#9b0c24';
        const fill = fillValue(primary, secondary, mode);

        if (hexInput) hexInput.value = primary;
        if (colorPicker) colorPicker.value = primary;
        if (secondaryHexInput) secondaryHexInput.value = secondary;
        if (secondaryPicker) secondaryPicker.value = secondary;
        if (customSwatch) customSwatch.style.background = fill;
        if (livePreview) {
            livePreview.style.setProperty('--preview-fill', fill);
            livePreview.style.setProperty('--preview-color', primary);
        }
        syncModeUi(mode);

        if (opts.fromPreset) {
            document.querySelectorAll('.theme-preset').forEach(function (el) {
                const radio = el.querySelector('.theme-preset-radio');
                el.classList.toggle('is-selected', !!(radio && radio.checked));
            });
        }
    }

    modeRadios.forEach(function (radio) {
        radio.addEventListener('change', function () {
            if (!radio.checked) return;
            markCustomPreset();
            applyThemePreview({ mode: radio.value });
        });
    });

    presetRadios.forEach(function (radio) {
        radio.addEventListener('change', function () {
            if (!radio.checked) return;
            if (radio.value === 'custom') {
                applyThemePreview({ fromPreset: true });
                return;
            }
            const mode = radio.dataset.mode === 'gradient' ? 'gradient' : 'solid';
            const modeInput = document.querySelector('input[name="theme_mode"][value="' + mode + '"]');
            if (modeInput) modeInput.checked = true;
            applyThemePreview({
                mode: mode,
                primary: radio.dataset.primary,
                secondary: radio.dataset.secondary,
                fromPreset: true,
            });
        });
    });

    function onColorChange() {
        markCustomPreset();
        applyThemePreview({});
    }

    colorPicker?.addEventListener('input', onColorChange);
    secondaryPicker?.addEventListener('input', onColorChange);

    hexInput?.addEventListener('input', function () {
        if (normalizeHex(hexInput.value)) onColorChange();
    });
    secondaryHexInput?.addEventListener('input', function () {
        if (normalizeHex(secondaryHexInput.value)) onColorChange();
    });

    syncModeUi(currentMode());
})();
</script>

<?php require dirname(__DIR__) . '/includes/admin_footer.php'; ?>
