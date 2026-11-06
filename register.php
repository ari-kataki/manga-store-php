<?php
// New user registration with validation and password hashing.

session_start();
require_once 'DbConnector.php';
$config = require 'config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

if (!isset($_SESSION['register_csrf_token'])) {
    $_SESSION['register_csrf_token'] = bin2hex(random_bytes(32));
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!array_key_exists('username', $_POST) ||
        !array_key_exists('email', $_POST)    ||
        !array_key_exists('password', $_POST) ||
        !array_key_exists('csrf_token', $_POST)) {
        $error = 'Form data missing.';
    } elseif ($_POST['csrf_token'] !== $_SESSION['register_csrf_token']) {
        $error = 'Invalid security token.';
    } else {
        $username = trim($_POST['username']);
        $email    = trim($_POST['email']);
        $password = $_POST['password'];

        if ($username === '' || $email === '' || $password === '') {
            $error = 'All fields are required.';
        } elseif (strlen($username) < 3 || strlen($username) > 40) {
            $error = 'Username must be between 3 and 40 characters.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } else {
            $db  = new DbConnector($config['host'], $config['user'], $config['pass'], $config['name']);

            // Check if username or email already exist
            $sth = $db->prepare(
                'SELECT id FROM user
                  WHERE (username = :username OR email = :email) AND deleted_at IS NULL'
            );
            $sth->bindParam(':username', $username, PDO::PARAM_STR);
            $sth->bindParam(':email',    $email,    PDO::PARAM_STR);
            $sth->execute();

            if ($sth->fetch()) {
                $error = 'Username or email already in use.';
            } else {
                // Password hashing with bcrypt (PASSWORD_BCRYPT)
                $hash = password_hash($password, PASSWORD_BCRYPT);

                $insert = $db->prepare(
                    'INSERT INTO user (username, email, password, role)
                      VALUES (:username, :email, :password, "USER")'
                );
                $insert->bindParam(':username', $username, PDO::PARAM_STR);
                $insert->bindParam(':email',    $email,    PDO::PARAM_STR);
                $insert->bindParam(':password', $hash,     PDO::PARAM_STR);
                $insert->execute();

                $success = 'Registration completed! You can now log in.';
                $_SESSION['register_csrf_token'] = bin2hex(random_bytes(32));
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manga Store - Registration</title>
    <link rel="stylesheet" href="style_login.css">
</head>
<body>
<div class="page-wrapper">
    <div class="logo"> MANGA STORE</div>
    <div class="card">
        <h2 class="card-title">Create an account</h2>

        <?php if ($error !== ''): ?>
            <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success !== ''): ?>
            <div class="alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token"
                    value="<?php echo htmlspecialchars($_SESSION['register_csrf_token']); ?>">

            <div class="form-group">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-input"
                        value="<?php echo array_key_exists('username', $_POST)
                                    ? htmlspecialchars($_POST['username']) : ''; ?>"
                        required>
            </div>

            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-input"
                        value="<?php echo array_key_exists('email', $_POST)
                                    ? htmlspecialchars($_POST['email']) : ''; ?>"
                        required>
            </div>

            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-input" required>
            </div>

            <button type="submit" class="btn-submit">Register</button>
        </form>

        <p class="link-text">Already have an account?
            <a href="login.php" class="link">Log in</a>
        </p>
    </div>
</div>
</body>
</html>