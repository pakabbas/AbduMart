#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$onlyMissing = !in_array('--all', $argv ?? [], true);
$updated = repair_product_images($onlyMissing);

echo $onlyMissing
    ? "Repaired images for {$updated} products missing or using broken remote URLs.\n"
    : "Refreshed images for {$updated} products.\n";
