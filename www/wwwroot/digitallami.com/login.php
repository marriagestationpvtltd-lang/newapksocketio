<?php
/**
 * login.php – User login page for Marriage Station.
 */
require_once __DIR__ . '/includes/user_auth.php';

if (isUserLoggedIn()) {
    header('Location: home.php');
    exit;
}

$error   = '';
$success = '';
$email   = '';

// Check for redirect messages
if (isset($_GET['registered'])) {
    $success = 'Registration successful! Please log in.';
}
if (isset($_GET['reset'])) {
    $success = 'Password reset successful! Please log in with your new password.';
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Please enter both email and password.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $result = loginUser($email, $password);
        if ($result['success']) {
            header('Location: home.php');
            exit;
        }
        $error = $result['message'];
    }
}

$title = 'Login – Marriage Station';
require_once __DIR__ . '/includes/public_header.php';
?>

<style>
.ms-auth-card {
    max-width: 440px;
    margin: 30px auto;
    background: var(--ms-white);
    border-radius: 16px;
    box-shadow: var(--ms-shadow);
    padding: 40px 32px;
}
.ms-auth-card .ms-brand {
    text-align: center;
    margin-bottom: 28px;
}
.ms-auth-card .ms-brand i {
    font-size: 2.2rem;
    color: var(--ms-primary);
}
.ms-auth-card .ms-brand h3 {
    font-weight: 800;
    color: var(--ms-text);
    margin-top: 8px;
}
.ms-auth-card .ms-brand p {
    color: var(--ms-text-muted);
    font-size: 0.95rem;
}
.ms-auth-card .form-label { font-weight: 600; font-size: 0.92rem; }
.ms-auth-card .form-control {
    border-radius: 10px;
    padding: 10px 14px;
    border: 1.5px solid var(--ms-border);
}
.ms-auth-card .form-control:focus {
    border-color: var(--ms-primary);
    box-shadow: 0 0 0 3px rgba(249,14,24,0.1);
}
.ms-auth-card .btn-ms-primary { width: 100%; padding: 11px; font-size: 1rem; }
.ms-auth-card .ms-links { text-align: center; margin-top: 18px; font-size: 0.92rem; }
.ms-auth-card .ms-links a { color: var(--ms-primary); font-weight: 600; text-decoration: none; }
.ms-auth-card .ms-links a:hover { text-decoration: underline; }
</style>

<div class="ms-auth-card">
    <div class="ms-brand">
        <i class="fas fa-heart"></i>
        <h3>Marriage Station</h3>
        <p>Sign in to your account</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger d-flex align-items-center py-2" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success d-flex align-items-center py-2" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="login.php" novalidate>
        <div class="mb-3">
            <label for="email" class="form-label">Email Address</label>
            <input type="email" class="form-control" id="email" name="email"
                   placeholder="you@example.com" required
                   value="<?php echo htmlspecialchars($email); ?>">
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <input type="password" class="form-control" id="password" name="password"
                   placeholder="Enter your password" required>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="remember" name="remember">
                <label class="form-check-label" for="remember" style="font-size:0.9rem;">Remember me</label>
            </div>
            <a href="forgot-password.php" style="font-size:0.9rem;color:var(--ms-primary);font-weight:600;text-decoration:none;">Forgot Password?</a>
        </div>

        <button type="submit" class="btn btn-ms-primary">
            <i class="fas fa-sign-in-alt me-1"></i> Login
        </button>
    </form>

    <div class="ms-links">
        Don't have an account?
        <a href="register.php">Register Now</a>
    </div>
</div>

<?php require_once __DIR__ . '/includes/public_footer.php'; ?>
