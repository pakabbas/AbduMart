<?php

declare(strict_types=1);

use App\SettingsService;

function setting(string $key, mixed $default = ''): mixed
{
    $flatKey = str_replace('.', '_', $key);
    $value = SettingsService::get($flatKey);
    if ($value !== null && $value !== '') {
        return $value;
    }

    $configKey = str_replace('_', '.', $flatKey);
    $configValue = config($configKey, null);
    if ($configValue !== null && $configValue !== '') {
        return $configValue;
    }

    return $default;
}

function google_auth_enabled(): bool
{
    return SettingsService::isGroupConfigured('google');
}
