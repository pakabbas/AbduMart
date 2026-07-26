<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

logout_user();
header('Location: ' . config('app.url') . '/index.php?signed_out=1');
exit;
