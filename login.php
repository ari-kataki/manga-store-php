<?php
// Handles user login with CSRF token and PDO prepared statement.
//   - session_start() at the top
//   - $_SERVER['REQUEST_METHOD'] === 'POST' to detect form submission
//   - array_key_exists() to validate POST

session_start();
require_once 'DbConnector.php';
$config = require 'config.php';

// If already logged in, redirect directly
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Generate CSRF token to protect the login form
if (!isset($_SESSION['login_csrf_token'])) {
    $_SESSION['login_csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate presence of fields with array_key_exists 
    if (!array_key_exists('username', $_POST) ||
        !array_key_exists('password', $_POST) ||
        !array_key_exists('csrf_token', $_POST)) {
        $error = 'Missing form data.';
    } elseif ($_POST['csrf_token'] !== $_SESSION['login_csrf_token']) {
        $error = 'Invalid security token.';
    } else {
        $username = trim($_POST['username']);
        $password = $_POST['password'];

        if ($username === '' || $password === '') {
            $error = 'Enter username and password.';
        } else {
            // Prepared Statement: protects against SQL Injection.
            // The :username value is never directly pasted into the query
            // but treated as a literal string.
            $db  = new DbConnector($config['host'], $config['user'], $config['pass'], $config['name']);
            $sth = $db->prepare(
                'SELECT id, username, password, role
                 FROM user
                 WHERE username = :username AND deleted_at IS NULL'
            );
            $sth->bindParam(':username', $username, PDO::PARAM_STR);
            $sth->execute();
            $user = $sth->fetch(PDO::FETCH_ASSOC);

            // password_verify compares plain password with bcrypt hash
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id']  = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role']     = $user['role'];
                // Regenerate CSRF token after login 
                $_SESSION['login_csrf_token'] = bin2hex(random_bytes(32));
                header('Location: index.php');
                exit();
            } else {
                $error = 'Username or password incorrect.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manga Store - Login</title>
    <link rel="stylesheet" href="style_login.css">
</head>
<body>
<div class="page-wrapper">
    <div class="logo"> MANGA STORE</div>
    <div class="card">
        <h2 class="card-title">Sign in to your account</h2>

        <?php if ($error !== ''): ?>
            <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <!-- CSRF token hidden: protects against Cross-Site Request Forgery attacks -->
            <input type="hidden" name="csrf_token"
                   value="<?php echo htmlspecialchars($_SESSION['login_csrf_token']); ?>">

            <div class="form-group">
                <label class="form-label">Username</label>
                <!-- Value persistence: if there's an error, the field remains filled -->
                <input type="text" name="username" class="form-input"
                       value="<?php echo array_key_exists('username', $_POST)
                                    ? htmlspecialchars($_POST['username']) : ''; ?>"
                       required>
            </div>

            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-input" required>
            </div>

            <button type="submit" class="btn-submit">Enter</button>
        </form>

        <p class="link-text">Don't have an account?
            <a href="register.php" class="link">Sign up</a>
        </p>
    </div>
</div>
</body>
</html>