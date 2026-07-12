<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$redirect = $_GET['redirect'] ?? 'index.php';
google_login_redirect($redirect);
