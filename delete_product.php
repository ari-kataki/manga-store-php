<?php
// LOGICAL deletion of a product (soft delete).
// did NOT use DELETE FROM — i set deleted_at = NOW().
// This preserves referential integrity: order_items
// that reference that product remain valid.
// The after_product_soft_delete trigger will log the event.

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
        exit('Invalid security token!');
    }

    if (!array_key_exists('product_id', $_POST)) {
        header('Location: index.php');
        exit();
    }

    $productId = (int)$_POST['product_id'];
    $adminId   = (int)$_SESSION['user_id'];

    $db = new DbConnector($config['host'], $config['user'], $config['pass'], $config['name']);

    // Set deleted_at = NOW() → logical deletion.
    // The product remains in the DB (and in historical order_items)
    // but is filtered out by all queries with WHERE deleted_at IS NULL.
    // The after_product_soft_delete trigger will automatically fire
    // and log 'DELETED' in product_log.
    $update = $db->prepare(
        'UPDATE product
         SET deleted_at = NOW(), updated_by = :admin_id
         WHERE id = :id AND deleted_at IS NULL'
    );
    $update->bindParam(':admin_id', $adminId, PDO::PARAM_INT);
    $update->bindParam(':id', $productId, PDO::PARAM_INT);
    $update->execute();
}

header('Location: index.php');
exit();