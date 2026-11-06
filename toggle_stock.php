<?php
// Changes the in_stock status of a product (0→1 or 1→0)bool.
// After the UPDATE, the after_product_stock_update trigger
// automatically logs the change in product_log.

session_start();
require_once 'DbConnector.php';
$config = require 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'ADMIN') {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!array_key_exists('csrf_token', $_POST) ||
        $_POST['csrf_token'] !== $_SESSION['add_cart_csrf']) {
        die('Invalid security token!');
    }

    if (!array_key_exists('product_id', $_POST)) {
        header('Location: index.php');
        exit();
    }

    $productId = (int)$_POST['product_id'];
    $adminId   = (int)$_SESSION['user_id'];

    $db = new DbConnector($config['host'], $config['user'], $config['pass'], $config['name']);

    // Reads the current state
    $sth = $db->prepare(
        'SELECT in_stock FROM product WHERE id = :id AND deleted_at IS NULL'
    );
    $sth->bindParam(':id', $productId, PDO::PARAM_INT);
    $sth->execute();
    $product = $sth->fetch(PDO::FETCH_ASSOC);

    if ($product) {
        $newStock = ($product['in_stock'] == 1) ? 0 : 1;

        // The UPDATE will trigger the after_product_stock_update trigger
        // which will automatically log the change in product_log.
        $update = $db->prepare(
            'UPDATE product
              SET in_stock = :new_stock, updated_by = :admin_id
              WHERE id = :id AND deleted_at IS NULL'
        );
        $update->bindParam(':new_stock', $newStock,   PDO::PARAM_INT);
        $update->bindParam(':admin_id',  $adminId,    PDO::PARAM_INT);
        $update->bindParam(':id',        $productId,  PDO::PARAM_INT);
        $update->execute();
    }
}

header('Location: index.php');
exit();
