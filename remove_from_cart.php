<?php
// Removes an item from the cart.

session_start();
require_once 'DbConnector.php';
$config = require 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] === 'ADMIN') {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!array_key_exists('csrf_token', $_POST) ||
        $_POST['csrf_token'] !== $_SESSION['cart_csrf']) {
        die('Invalid security token!');
    }

    if (!array_key_exists('cart_id', $_POST)) {
        header('Location: cart.php');
        exit();
    }

    $cartId = (int)$_POST['cart_id'];
    $userId = (int)$_SESSION['user_id'];

    $db  = new DbConnector($config['host'], $config['user'], $config['pass'], $config['name']);
    // Delete only if the record belongs to the logged-in user
    $sth = $db->prepare(
        'DELETE FROM cart WHERE id = :cart_id AND user_id = :uid'
    );
    $sth->bindParam(':cart_id', $cartId, PDO::PARAM_INT);
    $sth->bindParam(':uid', $userId, PDO::PARAM_INT);
    $sth->execute();
}

header('Location: cart.php');
exit();
