<?php

declare(strict_types=1);

return [
    'app' => [
        'name' => $_ENV['APP_NAME'] ?? "Abdu Market Curb Side Pickup",
        'url' => rtrim($_ENV['APP_URL'] ?? 'http://localhost', '/'),
        'env' => $_ENV['APP_ENV'] ?? 'production',
    ],
    'mart' => [
        'address' => $_ENV['MART_ADDRESS'] ?? '46090 Michigan Ave, Canton Township, MI 48188, United States',
        'phone' => $_ENV['MART_PHONE'] ?? '+1 734-322-9240',
        'pickup_instructions' => $_ENV['MART_PICKUP_INSTRUCTIONS'] ?? 'Pull up to curb-side pickup. Tap I\'m Here when you arrive.',
        'map_embed_url' => $_ENV['MART_MAP_EMBED_URL'] ?? 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2556.3665793208093!2d-83.49491778903355!3d42.27179694055423!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x883b5146ea01d80b%3A0x7e74e89d4308c79a!2s46090%20Michigan%20Ave%2C%20Canton%20Township%2C%20MI%2048188%2C%20USA!5e1!3m2!1sen!2s!4v1783928011573!5m2!1sen!2s',
    ],
    'stripe' => [
        'secret_key' => $_ENV['STRIPE_SECRET_KEY'] ?? '',
        'publishable_key' => $_ENV['STRIPE_PUBLISHABLE_KEY'] ?? '',
        'webhook_secret' => $_ENV['STRIPE_WEBHOOK_SECRET'] ?? '',
    ],
    'clover' => [
        'merchant_id' => $_ENV['CLOVER_MERCHANT_ID'] ?? '',
        'api_token' => $_ENV['CLOVER_API_TOKEN'] ?? '',
        'env' => $_ENV['CLOVER_ENV'] ?? 'sandbox',
    ],
    'smtp' => [
        'host' => $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com',
        'port' => $_ENV['SMTP_PORT'] ?? '587',
        'username' => $_ENV['SMTP_USERNAME'] ?? '',
        'password' => $_ENV['SMTP_PASSWORD'] ?? '',
        'from_email' => $_ENV['SMTP_FROM_EMAIL'] ?? '',
        'from_name' => $_ENV['SMTP_FROM_NAME'] ?? "Abdu Market",
    ],
    'google' => [
        'client_id' => $_ENV['GOOGLE_CLIENT_ID'] ?? '',
        'client_secret' => $_ENV['GOOGLE_CLIENT_SECRET'] ?? '',
    ],
    'tax_rate' => 0.06,
];
