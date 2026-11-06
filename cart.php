<?php
// Display the current user's cart.
// Only users (not admins) have a cart.

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

if (!isset($_SESSION['cart_csrf'])) {
    $_SESSION['cart_csrf'] = bin2hex(random_bytes(32));
}

$userId = (int)$_SESSION['user_id'];
$db = new DbConnector($config['host'], $config['user'], $config['pass'], $config['name']);

// Load the cart with JOIN to get product details
$sth = $db->prepare(
    'SELECT cart.id AS cart_id, cart.quantity,
             product.id AS product_id, product.titolo,
             product.genere, product.prezzo, product.in_stock,
             product.deleted_at
      FROM cart
      JOIN product ON product.id = cart.product_id
      WHERE cart.user_id = :uid
      ORDER BY product.titolo'
);
$sth->bindParam(':uid', $userId, PDO::PARAM_INT);
$sth->execute();
$cartItems = $sth->fetchAll(PDO::FETCH_ASSOC);

// Calculate the total
$total = 0.0;
foreach ($cartItems as $item) {
    $total += (float)$item['prezzo'] * (int)$item['quantity'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manga Store - Cart</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header class="site-header">
    <div class="header-inner">
        <div class="logo"> MANGA STORE</div>
        <nav class="nav">
            <a href="index.php" class="nav-link">← Catalog</a>
            <a href="orders.php" class="nav-link"> My orders</a>
            <a href="logout.php" class="nav-link nav-logout">Exit</a>
        </nav>
    </div>
</header>

<main class="main-content">
    <h1 class="page-title">🛒 Your Cart</h1>

    <?php if (array_key_exists('error', $_GET)): ?>
        <div class="alert-error">
            <?php echo ($_GET['error'] === 'unavailable')
                ? 'Some products are no longer available. Remove them to continue.'
                : 'Checkout failed, please try again.'; ?>
        </div>
    <?php endif; ?>

    <?php if (empty($cartItems)): ?>
        <p class="empty-msg">Your cart is empty.
            <a href="index.php">Return to catalog</a>
        </p>
    <?php else: ?>
    <div class="cart-list">
        <?php foreach ($cartItems as $item): ?>
        <div class="cart-item">
            <div class="cart-item-info">
                <span class="cart-title"><?php echo htmlspecialchars($item['titolo']); ?></span>
                <span class="cart-genre"><?php echo htmlspecialchars($item['genere']); ?></span>
                <?php if ($item['in_stock'] == 0 || $item['deleted_at'] !== null): ?>
                    <span class="out-msg">Not available</span>
                <?php endif; ?>
            </div>
            <div class="cart-item-mid">
                <span>€ <?php echo number_format((float)$item['prezzo'], 2, ',', '.'); ?> × </span>

                <!-- Form to update quantity -->
                <form method="POST" action="update_cart.php" style="display:inline;">
                    <input type="hidden" name="csrf_token"
                            value="<?php echo htmlspecialchars($_SESSION['cart_csrf']); ?>">
                    <input type="hidden" name="cart_id"
                            value="<?php echo (int)$item['cart_id']; ?>">
                    <input type="number" name="quantity"
                            value="<?php echo (int)$item['quantity']; ?>"
                            min="1" max="10" class="qty-input-sm"
                            onchange="this.form.submit()">
                </form>
            </div>
            <div class="cart-item-total">
                € <?php echo number_format((float)$item['prezzo'] * (int)$item['quantity'], 2, ',', '.'); ?>
            </div>
            <!-- Remove single item -->
            <form method="POST" action="remove_from_cart.php">
                <input type="hidden" name="csrf_token"
                        value="<?php echo htmlspecialchars($_SESSION['cart_csrf']); ?>">
                <input type="hidden" name="cart_id"
                        value="<?php echo (int)$item['cart_id']; ?>">
                <button type="submit" class="btn-remove">✕</button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="cart-summary">
        <div class="cart-total">
            Total: <strong>€ <?php echo number_format($total, 2, ',', '.'); ?></strong>
        </div>

        <!-- Checkout: calls the sp_complete_order stored procedure -->
        <form method="POST" action="checkout.php">
            <input type="hidden" name="csrf_token"
                    value="<?php echo htmlspecialchars($_SESSION['cart_csrf']); ?>">
            <button type="submit" class="btn btn-success btn-lg">
                  Confirm order
            </button>
        </form>
    </div>
    <?php endif; ?>
</main>

</body>
</html>