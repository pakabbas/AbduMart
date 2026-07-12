<?php

declare(strict_types=1);

return [
    'app' => [
        'name' => $_ENV['APP_NAME'] ?? "Abdu Mart's Curb Side Pickup",
        'url' => rtrim($_ENV['APP_URL'] ?? 'http://localhost', '/'),
        'env' => $_ENV['APP_ENV'] ?? 'production',
    ],
    'mart' => [
        'address' => $_ENV['MART_ADDRESS'] ?? 'Michigan',
        'phone' => $_ENV['MART_PHONE'] ?? '',
        'pickup_instructions' => $_ENV['MART_PICKUP_INSTRUCTIONS'] ?? 'Pull up to curb-side pickup. Tap I\'m Here when you arrive.',
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
    'tax_rate' => 0.06,
];
