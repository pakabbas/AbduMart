<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

use App\GoogleAuthService;

if (is_logged_in()) {
    redirect('index.php');
}

$state = $_GET['state'] ?? '';
$code = $_GET['code'] ?? '';
$redirect = $_SESSION['google_oauth_redirect'] ?? 'index.php';

if ($state === '' || !hash_equals($_SESSION['google_oauth_state'] ?? '', $state)) {
    flash('danger', 'Google sign-in failed. Please try again.');
    redirect('login.php');
}

unset($_SESSION['google_oauth_state'], $_SESSION['google_oauth_redirect']);

if ($code === '') {
    flash('danger', 'Google sign-in was cancelled.');
    redirect('login.php');
}

try {
    $service = new GoogleAuthService();
    $profile = $service->fetchUser($code);

    if ($profile['email'] === '') {
        throw new RuntimeException('Google did not return an email address.');
    }

    $user = find_or_create_google_user($profile);
    login_user($user);
    flash('success', 'Welcome, ' . $user['first_name'] . '!');
    redirect($redirect);
} catch (Throwable $e) {
    flash('danger', 'Google sign-in error: ' . $e->getMessage());
    redirect('login.php');
}
