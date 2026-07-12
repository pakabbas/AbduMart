<?php

declare(strict_types=1);

namespace App;

class SettingsService
{
    private const ENCRYPTED_KEYS = [
        'stripe_secret_key',
        'stripe_webhook_secret',
        'clover_api_token',
        'smtp_password',
        'google_client_secret',
    ];

    private static ?array $cache = null;

    public static function get(string $key, ?string $default = null): ?string
    {
        self::loadCache();
        $value = self::$cache[$key] ?? null;
        if ($value !== null && $value !== '') {
            return $value;
        }
        return self::envFallback($key) ?? $default;
    }

    public static function set(string $key, ?string $value): void
    {
        if ($value === null || $value === '') {
            db()->prepare('DELETE FROM settings WHERE setting_key = ?')->execute([$key]);
            unset(self::$cache[$key]);
            return;
        }

        $stored = in_array($key, self::ENCRYPTED_KEYS, true)
            ? self::encrypt($value)
            : $value;

        db()->prepare(
            'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        )->execute([$key, $stored]);

        self::$cache[$key] = $value;
    }

    public static function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            if (is_string($key)) {
                self::set($key, $value !== '' ? (string) $value : null);
            }
        }
    }

    public static function getGroup(array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = self::get($key, '');
        }
        return $result;
    }

    public static function flushCache(): void
    {
        self::$cache = null;
    }

    public static function isGroupConfigured(string $group): bool
    {
        return match ($group) {
            'stripe' => self::get('stripe_secret_key') !== '' && self::get('stripe_publishable_key') !== '',
            'clover' => self::get('clover_merchant_id') !== '' && self::get('clover_api_token') !== '',
            'smtp' => self::get('smtp_host') !== '' && self::get('smtp_username') !== '' && self::get('smtp_password') !== '',
            'google' => self::get('google_client_id') !== '' && self::get('google_client_secret') !== '',
            default => false,
        };
    }

    private static function loadCache(): void
    {
        if (self::$cache !== null) {
            return;
        }

        self::$cache = [];
        try {
            $rows = db()->query('SELECT setting_key, setting_value FROM settings')->fetchAll();
            foreach ($rows as $row) {
                $key = $row['setting_key'];
                $value = $row['setting_value'];
                if (in_array($key, self::ENCRYPTED_KEYS, true) && $value !== '') {
                    $value = self::decrypt($value);
                }
                self::$cache[$key] = $value;
            }
        } catch (\Throwable) {
            self::$cache = [];
        }
    }

    private static function envFallback(string $key): ?string
    {
        $map = [
            'stripe_secret_key' => 'STRIPE_SECRET_KEY',
            'stripe_publishable_key' => 'STRIPE_PUBLISHABLE_KEY',
            'stripe_webhook_secret' => 'STRIPE_WEBHOOK_SECRET',
            'clover_merchant_id' => 'CLOVER_MERCHANT_ID',
            'clover_api_token' => 'CLOVER_API_TOKEN',
            'clover_env' => 'CLOVER_ENV',
            'smtp_host' => 'SMTP_HOST',
            'smtp_port' => 'SMTP_PORT',
            'smtp_username' => 'SMTP_USERNAME',
            'smtp_password' => 'SMTP_PASSWORD',
            'smtp_from_email' => 'SMTP_FROM_EMAIL',
            'smtp_from_name' => 'SMTP_FROM_NAME',
            'google_client_id' => 'GOOGLE_CLIENT_ID',
            'google_client_secret' => 'GOOGLE_CLIENT_SECRET',
            'mart_address' => 'MART_ADDRESS',
            'mart_phone' => 'MART_PHONE',
            'mart_pickup_instructions' => 'MART_PICKUP_INSTRUCTIONS',
        ];

        $envKey = $map[$key] ?? null;
        if ($envKey && !empty($_ENV[$envKey])) {
            return $_ENV[$envKey];
        }
        return null;
    }

    private static function encryptionKey(): string
    {
        $key = $_ENV['APP_KEY'] ?? 'abdu-mart-default-change-me-in-production';
        return hash('sha256', $key, true);
    }

    private static function encrypt(string $value): string
    {
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($value, 'AES-256-CBC', self::encryptionKey(), OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $encrypted);
    }

    private static function decrypt(string $value): string
    {
        $data = base64_decode($value, true);
        if ($data === false || strlen($data) < 17) {
            return '';
        }
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', self::encryptionKey(), OPENSSL_RAW_DATA, $iv);
        return $decrypted !== false ? $decrypted : '';
    }
}
