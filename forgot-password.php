<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
    redirect('index.php');
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid request. Please try again.';
    } else {
        $email = strtolower(trim($_POST['email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            $stmt = db()->prepare('SELECT first_name FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                try {
                    send_password_reset_otp($email, $user['first_name']);
                } catch (Throwable $e) {
                    $error = $e->getMessage();
                }
            }

            if ($error === '') {
                $_SESSION['otp_email'] = $email;
                $_SESSION['otp_purpose'] = 'password_reset';
                flash('success', 'If an account exists for that email, we sent a verification code.');
                redirect('verify-otp.php?purpose=password_reset&email=' . urlencode($email));
            }
        }
    }
}

$pageTitle = 'Forgot Password';
require __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="auth-card card border-0 shadow">
                <div class="card-body p-4 p-md-5">
                    <h1 class="h3 mb-1">Forgot password?</h1>
                    <p class="text-muted mb-4">Enter your email and we'll send a verification code to reset your password.</p>
                    <?php if ($error): ?>
                    <div class="alert alert-danger"><?= e($error) ?></div>
                    <?php endif; ?>
                    <form method="post">
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" required value="<?= e($_POST['email'] ?? '') ?>">
                        </div>
                        <button type="submit" class="btn btn-danger w-100 btn-lg">Send Code</button>
                    </form>
                    <p class="text-center mt-4 mb-0 small">
                        <a href="login.php" class="text-danger">Back to sign in</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
