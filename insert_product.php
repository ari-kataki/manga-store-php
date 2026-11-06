<?php
// Allows admin to add a new product to the catalog.

session_start();
require_once 'DbConnector.php';
$config = require 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'ADMIN') {
    header('Location: login.php');
    exit();
}

if (!isset($_SESSION['insert_csrf'])) {
    $_SESSION['insert_csrf'] = bin2hex(random_bytes(32));
}

$error = '';
$success = '';
$generi = [
    'Action / Adventure', 'Action / Fantasy',  'Action / Comedy',
    'Action / Horror',    'Action / Story',     'Action / Superhero',
    'Thriller / Mystery', 'Slice of Life / Drama',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!array_key_exists('csrf_token', $_POST) ||
        $_POST['csrf_token'] !== $_SESSION['insert_csrf']) {
        die('Invalid security token!');
    }

    // Validate required fields
    if (!array_key_exists('titolo', $_POST)  ||
        !array_key_exists('genere', $_POST)  ||
        !array_key_exists('prezzo', $_POST)) {
        $error = 'Missing data.';
    } else {
        $titolo = trim($_POST['titolo']);
        $genere = trim($_POST['genere']);
        $prezzo = trim($_POST['prezzo']);
        $inStock = array_key_exists('in_stock', $_POST) ? 1 : 0;

        if ($titolo === '' || $genere === '' || $prezzo === '') {
            $error = 'All fields are required.';
        } elseif (!is_numeric($prezzo) || (float)$prezzo <= 0) {
            $error = 'Price must be a positive number.';
        } elseif (!in_array($genere, $generi)) {
            $error = 'Invalid genre.';
        } else {
            $prezzoFloat = round((float)$prezzo, 2);
            $adminId     = (int)$_SESSION['user_id'];

            $db = new DbConnector($config['host'], $config['user'], $config['pass'], $config['name']);
            $insert = $db->prepare(
                'INSERT INTO product (titolo, genere, prezzo, in_stock, updated_by)
                VALUES (:titolo, :genere, :prezzo, :in_stock, :admin_id)'
            );
            $insert->bindParam(':titolo', $titolo, PDO::PARAM_STR);
            $insert->bindParam(':genere', $genere, PDO::PARAM_STR);
            $insert->bindParam(':prezzo', $prezzoFloat);
            $insert->bindParam(':in_stock', $inStock, PDO::PARAM_INT);
            $insert->bindParam(':admin_id', $adminId, PDO::PARAM_INT);
$insert->execute();
$insert->closeCursor();

$success = 'Product "' . htmlspecialchars($titolo) . '" added successfully!';
            $_SESSION['insert_csrf'] = bin2hex(random_bytes(32));
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manga Store - Add Product</title>
    <link rel="stylesheet" href="style_login.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header class="site-header">
    <div class="header-inner">
        <div class="logo"> MANGA STORE</div>
        <nav class="nav">
            <a href="index.php" class="nav-link">← Catalog</a>
            <a href="admin.php" class="nav-link"> Admin</a>
            <a href="logout.php" class="nav-link nav-logout">Logout</a>
        </nav>
    </div>
</header>

<main class="main-content">
<div class="page-wrapper" style="max-width:500px;">
    <div class="card">
        <h2 class="card-title">➕ Add new product</h2>

        <?php if ($error !== ''): ?>
            <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success !== ''): ?>
            <div class="alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token"
                   value="<?php echo htmlspecialchars($_SESSION['insert_csrf']); ?>">

            <div class="form-group">
                <label class="form-label">Title</label>
                <input type="text" name="titolo" class="form-input"
                       value="<?php echo array_key_exists('titolo', $_POST)
                                    ? htmlspecialchars($_POST['titolo']) : ''; ?>"
                       required>
            </div>

            <div class="form-group">
                <label class="form-label">Genre</label>
                <select name="genere" class="form-input">
                    <?php foreach ($generi as $g): ?>
                    <option value="<?php echo htmlspecialchars($g); ?>"
                        <?php echo (array_key_exists('genere', $_POST) &&
                                    $_POST['genere'] === $g)
                                   ? 'selected=selected' : ''; ?>>
                        <?php echo htmlspecialchars($g); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Price (€)</label>
                <input type="number" name="prezzo" class="form-input"
                       step="0.01" min="0.01"
                       value="<?php echo array_key_exists('prezzo', $_POST)
                                    ? htmlspecialchars($_POST['prezzo']) : ''; ?>"
                       required>
            </div>

            <div class="form-group">
                <label class="form-label">
                    <input type="checkbox" name="in_stock"
                           <?php echo (!array_key_exists('in_stock', $_POST) ||
                                       $_POST['in_stock'] === 'on') ? 'checked' : ''; ?>>
                    Available (In Stock)
                </label>
            </div>

            <button type="submit" class="btn-submit">Add product</button>
            <a href="index.php" style="display:block;text-align:center;margin-top:10px;">Cancel</a>
        </form>
    </div>
</div>
</main>
</body>
</html>