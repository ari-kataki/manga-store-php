<?php
// Shows the order history of the logged-in user.
// Each user sees ONLY their own orders (user_id in WHERE clause).

session_start();
require_once 'DbConnector.php';
$config = require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if ($_SESSION['role'] === 'ADMIN') {
    header('Location: index.php');
    exit();
}

$userId  = (int)$_SESSION['user_id'];
$db      = new DbConnector($config['host'], $config['user'], $config['pass'], $config['name']);
$success = array_key_exists('success', $_GET) && $_GET['success'] === '1';
$newOrder = array_key_exists('order', $_GET) ? (int)$_GET['order'] : 0;

// Load the user's orders (from most recent)
$sth = $db->prepare(
    'SELECT id, total_price, created_at
     FROM orders
     WHERE user_id = :uid
     ORDER BY created_at DESC'
);
$sth->bindParam(':uid', $userId, PDO::PARAM_INT);
$sth->execute();
$orders = $sth->fetchAll(PDO::FETCH_ASSOC);

// For each order, also load its items (JOIN with product)
$itemsSth = $db->prepare(
    'SELECT oi.quantity, oi.price_at_purchase,
            p.titolo, p.genere
     FROM order_items oi
     JOIN product p ON p.id = oi.product_id
     WHERE oi.order_id = :oid'
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manga Store - My orders</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header class="site-header">
    <div class="header-inner">
        <div class="logo"> MANGA STORE</div>
        <nav class="nav">
            <a href="index.php" class="nav-link">← Catalog</a>
            <a href="cart.php" class="nav-link">🛒 Cart</a>
            <a href="logout.php" class="nav-link nav-logout">Exit</a>
        </nav>
    </div>
</header>

<main class="main-content">
     <h1 class="page-title"> My Orders</h1>

    <?php if ($success && $newOrder > 0): ?>
        <div class="alert-success">
              Order #<?php echo $newOrder; ?> completed successfully!
        </div>
    <?php endif; ?>

    <?php if (empty($orders)): ?>
         <p class="empty-msg">You haven't placed any orders yet.
             <a href="index.php">Return to catalog</a>
         </p>
    <?php else: ?>
        <?php foreach ($orders as $order): ?>
        <div class="order-card <?php echo ($order['id'] == $newOrder) ? 'order-new' : ''; ?>">
            <div class="order-header">
                <span>Order <strong>#<?php echo $order['id']; ?></strong></span>
                <span><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></span>
                <span class="order-total">
                     Total: <strong>€ <?php echo number_format((float)$order['total_price'], 2, ',', '.'); ?></strong>
                </span>
            </div>
            <div class="order-items">
<?php
$itemsSth->bindParam(':oid', $order['id'], PDO::PARAM_INT);
$itemsSth->execute();
$items = $itemsSth->fetchAll(PDO::FETCH_ASSOC);
$itemsSth->closeCursor();
foreach ($items as $item):
?>
                <div class="order-item-row">
                    <span>📖 <?php echo htmlspecialchars($item['titolo']); ?>
                        <small>(<?php echo htmlspecialchars($item['genere']); ?>)</small>
                    </span>
                    <span>
                        <?php echo $item['quantity']; ?> ×
                        € <?php echo number_format((float)$item['price_at_purchase'], 2, ',', '.'); ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</main>

</body>
</html>
