<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
    redirect('index.php');
}

if (empty($_SESSION['password_reset_verified']) || empty($_SESSION['password_reset_email'])) {
    flash('warning', 'Please verify your email first.');
    redirect('forgot-password.php');
}

$email = $_SESSION['password_reset_email'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['password_confirm'] ?? '';

        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
        if ($password !== $confirm) {
            $errors[] = 'Passwords do not match.';
        }

        if (empty($errors)) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            db()->prepare('UPDATE users SET password_hash = ?, email_verified_at = COALESCE(email_verified_at, NOW()) WHERE email = ?')
                ->execute([$hash, $email]);

            unset($_SESSION['password_reset_verified'], $_SESSION['password_reset_email']);
            flash('success', 'Password updated. Please sign in.');
            redirect('login.php');
        }
    }
}

$pageTitle = 'Reset Password';
require __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="auth-card card border-0 shadow">
                <div class="card-body p-4 p-md-5">
                    <h1 class="h3 mb-1">Set new password</h1>
                    <p class="text-muted mb-4">Create a new password for <strong><?= e($email) ?></strong></p>
                    <?php foreach ($errors as $err): ?>
                    <div class="alert alert-danger"><?= e($err) ?></div>
                    <?php endforeach; ?>
                    <form method="post">
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label class="form-label">New password</label>
                            <input type="password" name="password" class="form-control" required minlength="8">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirm password</label>
                            <input type="password" name="password_confirm" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-danger w-100 btn-lg">Update Password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
