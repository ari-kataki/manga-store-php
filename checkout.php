<?php
// Complete the order by calling the Stored Procedure sp_complete_order.
//
/// The SP handles everything as a TRANSACTION:
///   1. Creates a record in orders (total = 0)
///   2. Copies items from cart to order_items
///      → The after_order_item_insert Trigger automatically
///        updates the total in orders for each inserted item
///   3. Empties the cart
/// If something goes wrong → ROLLBACK (nothing is saved).


session_start();
require_once 'DbConnector.php';
$config = require 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] === 'ADMIN') {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: cart.php');
    exit();
}

if (!array_key_exists('csrf_token', $_POST) ||
     $_POST['csrf_token'] !== $_SESSION['cart_csrf']) {
     die('Invalid security token!');
 }

$userId = (int)$_SESSION['user_id'];
$db     = new DbConnector($config['host'], $config['user'], $config['pass'], $config['name']);

// Check that the cart is not empty
$checkCart = $db->prepare(
    'SELECT COUNT(*) FROM cart WHERE user_id = :uid'
);
$checkCart->bindParam(':uid', $userId, PDO::PARAM_INT);
$checkCart->execute();
$cartCount = (int)$checkCart->fetchColumn();

if ($cartCount === 0) {
    header('Location: cart.php');
    exit();
}

// Check that every product in the cart is still available
// (not deleted and in stock), otherwise the user must fix the cart first.
$checkAvail = $db->prepare(
    'SELECT COUNT(*) FROM cart
     JOIN product ON product.id = cart.product_id
     WHERE cart.user_id = :uid
       AND (product.in_stock = 0 OR product.deleted_at IS NOT NULL)'
);
$checkAvail->bindParam(':uid', $userId, PDO::PARAM_INT);
$checkAvail->execute();

if ((int)$checkAvail->fetchColumn() > 0) {
    header('Location: cart.php?error=unavailable');
    exit();
}

// Call the Stored Procedure with an OUT parameter.
// PDO doesn't directly support OUT parameters in SP,
// so we use SQL session variables as a workaround.

$conn = $db->getConnection();

// Step 1: call the SP passing user_id and a variable @order_id
$callStmt = $conn->prepare('CALL sp_complete_order(:uid, @order_id)');
$callStmt->bindParam(':uid', $userId, PDO::PARAM_INT);
$callStmt->execute();
$callStmt->closeCursor();

// Step 2: read the value of the OUT parameter from the SQL variable
$outStmt  = $conn->query('SELECT @order_id AS order_id');
$outRow   = $outStmt->fetch(PDO::FETCH_ASSOC);
$orderId  = (int)$outRow['order_id'];

if ($orderId === -1 || $orderId === 0) {
    // The SP performed ROLLBACK (internal error)
    header('Location: cart.php?error=checkout_failed');
    exit();
}

// Regenerate the CSRF token after checkout
$_SESSION['cart_csrf'] = bin2hex(random_bytes(32));

header('Location: orders.php?success=1&order=' . $orderId);
exit();