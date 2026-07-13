#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Hourly Clover catalog sync — runs only while the store is open.
 * Crontab: 0 * * * * cd /var/www/abdumart && /usr/bin/php scripts/clover-sync.php >> /var/log/abdumart-clover-sync.log 2>&1
 */

require_once dirname(__DIR__) . '/includes/bootstrap.php';

use App\CloverService;
use App\SettingsService;
use App\StoreHoursService;

if (!StoreHoursService::isOpen()) {
    echo '[' . date('c') . "] Store closed — skipping Clover sync\n";
    exit(0);
}

if (!SettingsService::isGroupConfigured('clover')) {
    echo '[' . date('c') . "] Clover not configured — skipping\n";
    exit(0);
}

try {
    $result = (new CloverService())->syncAll();
    echo '[' . date('c') . '] Synced ' . $result['categories'] . ' categories and ' . $result['products'] . " products\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, '[' . date('c') . '] Clover sync failed: ' . $e->getMessage() . "\n");
    exit(1);
}
