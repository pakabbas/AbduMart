<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
    redirect('index.php');
}

$purpose = $_GET['purpose'] ?? $_SESSION['otp_purpose'] ?? 'signup';
$email = strtolower(trim($_GET['email'] ?? $_SESSION['otp_email'] ?? ''));
$errors = [];

if (!in_array($purpose, ['signup', 'password_reset'], true)) {
    redirect('login.php');
}

if ($email === '') {
    flash('warning', 'Please start the verification process again.');
    redirect($purpose === 'signup' ? 'register.php' : 'forgot-password.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $otp = trim($_POST['otp'] ?? '');
        if (!preg_match('/^\d{6}$/', $otp)) {
            $errors[] = 'Please enter the 6-digit code from your email.';
        } else {
            $payload = verify_auth_otp($email, $purpose, $otp);
            if ($payload === null) {
                $errors[] = 'Invalid or expired code. Please try again or request a new one.';
            } elseif ($purpose === 'signup') {
                $check = db()->prepare('SELECT id FROM users WHERE email = ?');
                $check->execute([$email]);
                if ($check->fetch()) {
                    $errors[] = 'An account with this email already exists. Please sign in.';
                } else {
                    $user = complete_signup_from_otp($email, $payload);
                    unset($_SESSION['otp_email'], $_SESSION['otp_purpose']);
                    login_user($user);
                    flash('success', 'Account verified! Start shopping.');
                    redirect('index.php');
                }
            } else {
                $_SESSION['password_reset_email'] = $email;
                $_SESSION['password_reset_verified'] = true;
                unset($_SESSION['otp_email'], $_SESSION['otp_purpose']);
                flash('success', 'Code verified. Set your new password.');
                redirect('reset-password.php');
            }
        }
    }
}

$_SESSION['otp_email'] = $email;
$_SESSION['otp_purpose'] = $purpose;

$pageTitle = 'Verify Email';
require __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="auth-card card border-0 shadow">
                <div class="card-body p-4 p-md-5 text-center">
                    <i class="bi bi-envelope-check text-danger fs-1 mb-3"></i>
                    <h1 class="h3 mb-1">Check your email</h1>
                    <p class="text-muted mb-4">We sent a 6-digit code to <strong><?= e($email) ?></strong></p>
                    <?php foreach ($errors as $err): ?>
                    <div class="alert alert-danger"><?= e($err) ?></div>
                    <?php endforeach; ?>
                    <form method="post">
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <input type="text" name="otp" class="form-control form-control-lg text-center otp-input" maxlength="6" pattern="\d{6}" inputmode="numeric" placeholder="000000" required autofocus>
                        </div>
                        <button type="submit" class="btn btn-danger w-100 btn-lg">Verify Code</button>
                    </form>
                    <p class="small text-muted mt-4 mb-0">Code expires in 10 minutes.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
