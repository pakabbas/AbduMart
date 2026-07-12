<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
    redirect('index.php');
}

$error = '';
$redirect = $_GET['redirect'] ?? 'index.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid request. Please try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $user = authenticate($email, $password);
        if ($user) {
            login_user($user);
            flash('success', 'Welcome back, ' . $user['first_name'] . '!');
            redirect($redirect);
        }
        $check = db()->prepare('SELECT password_hash, google_id FROM users WHERE email = ?');
        $check->execute([$email]);
        $existing = $check->fetch();
        if ($existing && empty($existing['password_hash']) && !empty($existing['google_id'])) {
            $error = 'This account uses Google sign-in. Please continue with Google below.';
        } else {
            $error = 'Invalid email or password.';
        }
    }
}

$pageTitle = 'Sign In';
require __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="auth-card card border-0 shadow">
                <div class="card-body p-4 p-md-5">
                    <h1 class="h3 mb-1">Welcome back</h1>
                    <p class="text-muted mb-4">Sign in to shop and track your curbside orders.</p>
                    <?php if ($error): ?>
                    <div class="alert alert-danger"><?= e($error) ?></div>
                    <?php endif; ?>

                    <?php require __DIR__ . '/includes/auth_social.php'; ?>

                    <form method="post">
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" required value="<?= e($_POST['email'] ?? '') ?>">
                        </div>
                        <div class="mb-3 d-flex justify-content-between align-items-center">
                            <label class="form-label mb-0">Password</label>
                            <a href="forgot-password.php" class="small text-danger">Forgot password?</a>
                        </div>
                        <input type="password" name="password" class="form-control mb-3" required>
                        <button type="submit" class="btn btn-danger w-100 btn-lg">Sign In</button>
                    </form>
                    <p class="text-center mt-4 mb-0 small">
                        New to Abdu Mart? <a href="register.php" class="text-danger">Create an account</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
