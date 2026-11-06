<?php
// If the product is already in the cart, update the quantity.
// Uses INSERT ... ON DUPLICATE KEY UPDATE (UNIQUE constraint
// on (user_id, product_id) in the cart table).

session_start();
require_once 'DbConnector.php';
$config = require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Validation
    if (!array_key_exists('csrf_token', $_POST) ||
        $_POST['csrf_token'] !== $_SESSION['add_cart_csrf']) {
        die('Invalid security token!');
    }

    if (!array_key_exists('product_id', $_POST) ||
        !array_key_exists('quantity', $_POST)) {
        header('Location: index.php');
        exit();
    }

    $productId = (int)$_POST['product_id'];
    $quantity  = (int)$_POST['quantity'];
    $userId    = (int)$_SESSION['user_id'];

    if ($productId <= 0 || $quantity <= 0 || $quantity > 10) {
        header('Location: index.php');
        exit();
    }

    $db = new DbConnector($config['host'], $config['user'], $config['pass'], $config['name']);

    // Verify that the product exists, is in stock and not deleted
    $check = $db->prepare(
        'SELECT id FROM product
         WHERE id = :id AND in_stock = 1 AND deleted_at IS NULL'
    );
    $check->bindParam(':id', $productId, PDO::PARAM_INT);
    $check->execute();

    if (!$check->fetch()) {
        header('Location: index.php');
        exit();
    }

    // INSERT with duplicate handling: if the user already has that product
    // in the cart, sum the quantity instead of inserting a new record.
    // This leverages the UNIQUE KEY constraint uq_user_product (user_id, product_id).
    $insert = $db->prepare(
        'INSERT INTO cart (user_id, product_id, quantity)
         VALUES (:user_id, :product_id, :quantity)
         ON DUPLICATE KEY UPDATE quantity = LEAST(quantity + :quantity2, 10)'
    );
    $insert->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $insert->bindParam(':product_id', $productId, PDO::PARAM_INT);
    $insert->bindParam(':quantity', $quantity, PDO::PARAM_INT);
    $insert->bindParam(':quantity2', $quantity, PDO::PARAM_INT);
    $insert->execute();
}

header('Location: index.php');
exit();