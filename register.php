<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
    redirect('index.php');
}

$errors = [];
$redirect = $_GET['redirect'] ?? 'index.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $email = strtolower(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['password_confirm'] ?? '';
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
        if ($password !== $confirm) {
            $errors[] = 'Passwords do not match.';
        }
        if ($firstName === '' || $lastName === '') {
            $errors[] = 'First and last name are required.';
        }

        if (empty($errors)) {
            $check = db()->prepare('SELECT id FROM users WHERE email = ?');
            $check->execute([$email]);
            if ($check->fetch()) {
                $errors[] = 'An account with this email already exists.';
            } else {
                try {
                    send_signup_otp($email, $firstName, $lastName, $phone, $password);
                    $_SESSION['otp_email'] = $email;
                    $_SESSION['otp_purpose'] = 'signup';
                    flash('success', 'We sent a verification code to your email.');
                    redirect('verify-otp.php?purpose=signup&email=' . urlencode($email));
                } catch (Throwable $e) {
                    $errors[] = $e->getMessage();
                }
            }
        }
    }
}

$pageTitle = 'Sign Up';
require __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="auth-card card border-0 shadow">
                <div class="card-body p-4 p-md-5">
                    <h1 class="h3 mb-1">Create your account</h1>
                    <p class="text-muted mb-4">We'll email you a verification code to confirm your account.</p>
                    <?php foreach ($errors as $err): ?>
                    <div class="alert alert-danger"><?= e($err) ?></div>
                    <?php endforeach; ?>

                    <?php require __DIR__ . '/includes/auth_social.php'; ?>

                    <form method="post">
                        <?= csrf_field() ?>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">First name</label>
                                <input type="text" name="first_name" class="form-control" required value="<?= e($_POST['first_name'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Last name</label>
                                <input type="text" name="last_name" class="form-control" required value="<?= e($_POST['last_name'] ?? '') ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" required value="<?= e($_POST['email'] ?? '') ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Phone <span class="text-muted">(for pickup)</span></label>
                                <input type="tel" name="phone" class="form-control" placeholder="(248) 555-0100" value="<?= e($_POST['phone'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Password</label>
                                <input type="password" name="password" class="form-control" required minlength="8">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Confirm password</label>
                                <input type="password" name="password_confirm" class="form-control" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-danger w-100 btn-lg mt-4">Send Verification Code</button>
                    </form>
                    <p class="text-center mt-4 mb-0 small">
                        Already have an account? <a href="login.php" class="text-danger">Sign in</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
