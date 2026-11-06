<?php
// Main page: displays the manga catalog.
// - ADMIN: sees all products (including out of stock) + management buttons
// - USER: sees only products with in_stock = 1
// Uses the Stored Procedure sp_get_catalog().

session_start();
require_once 'DbConnector.php';
$config = require 'config.php';

// autenticazione
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Gen CSRF token for form "Aggiungi al carrello"
if (!isset($_SESSION['add_cart_csrf'])) {
    $_SESSION['add_cart_csrf'] = bin2hex(random_bytes(32));
}

$isAdmin = ($_SESSION['role'] === 'ADMIN');

// Usa la Stored Procedure sp_get_catalog(is_admin).
// Le stored procedure si invocano con CALL tramite getConnection().
// A differenza dei Trigger (automatici), le SP si chiamano
// esplicitamente dal codice.

$db   = new DbConnector($config['host'], $config['user'], $config['pass'], $config['name']);
$conn = $db->getConnection();

$stmt = $conn->prepare('CALL sp_get_catalog(:is_admin)');
$stmt->bindValue(':is_admin', $isAdmin ? 1 : 0, PDO::PARAM_INT);
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt->closeCursor();

// Conta quanti articoli ci sono nel carrello (per il badge)
$cartSth = $db->prepare(
    'SELECT COALESCE(SUM(quantity), 0) AS total
     FROM cart 
     WHERE user_id = :uid'
);
$cartSth->bindParam(':uid', $_SESSION['user_id'], PDO::PARAM_INT);
$cartSth->execute();
$cartCount = (int)$cartSth->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manga Store - Catalog</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    
<!-- ===== HEADER ===== -->
<header class="site-header">
    <div class="header-inner">
        <div class="logo"> MANGA STORE</div>
        <nav class="nav">
            <span class="nav-user">
                Hi, <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>
                <?php if ($isAdmin): ?>
                    <span class="badge-admin">ADMIN</span>
                <?php endif; ?>
            </span>
            <?php if (!$isAdmin): ?>
            <a href="cart.php" class="nav-link">
                🛒 Cart
                <?php if ($cartCount > 0): ?>
                    <span class="cart-badge"><?php echo $cartCount; ?></span>
                <?php endif; ?>
            </a>
            <a href="orders.php" class="nav-link"> My orders</a>
            <?php endif; ?>
            <?php if ($isAdmin): ?>
            <a href="admin.php" class="nav-link"> Management</a>
            <?php endif; ?>
            <a href="logout.php" class="nav-link nav-logout">Logout</a>
        </nav>
    </div>
</header>

<!-- ===== CATALOG ===== -->
<main class="main-content">
    <h1 class="page-title">
        <?php echo $isAdmin ? ' Catalog (Admin View)' : ' Manga Catalog'; ?>
    </h1>

    <?php if (empty($products)): ?>
        <p class="empty-msg">No products available at the moment.</p>
    <?php else: ?>
    <div class="product-grid">
        <?php foreach ($products as $prd): ?>
        <div class="product-card <?php echo ($prd['in_stock'] == 0) ? 'out-of-stock' : ''; ?>">

            <!-- Stock badge visible only to admin -->
            <?php if ($isAdmin): ?>
                <div class="stock-badge <?php echo ($prd['in_stock'] == 1) ? 'in' : 'out'; ?>">
                    <?php echo ($prd['in_stock'] == 1) ? ' In stock' : ' Out of stock'; ?>
                </div>
            <?php endif; ?>

            <div class="product-emoji">📖</div>
            <h3 class="product-title"><?php echo htmlspecialchars($prd['titolo']); ?></h3>
            <p class="product-genre"><?php echo htmlspecialchars($prd['genere']); ?></p>
            <p class="product-price">€ <?php echo number_format((float)$prd['prezzo'], 2, ',', '.'); ?></p>

            <?php if (!$isAdmin && $prd['in_stock'] == 1): ?>
            <!-- Add to cart form (only for regular users) -->
            <form method="POST" action="add_to_cart.php">
                <input type="hidden" name="csrf_token"
                       value="<?php echo htmlspecialchars($_SESSION['add_cart_csrf']); ?>">
                <input type="hidden" name="product_id"
                       value="<?php echo (int)$prd['id']; ?>">
                <div class="qty-row">
                    <label>Qty:</label>
                    <input type="number" name="quantity" value="1" min="1" max="10"
                           class="qty-input">
                </div>
                <button type="submit" class="btn btn-primary">🛒 Add</button>
            </form>

            <?php elseif (!$isAdmin && $prd['in_stock'] == 0): ?>
                <p class="out-msg"> Not available</p>

            <?php elseif ($isAdmin): ?>
            <!-- Admin actions: toggle stock + delete -->
            <div class="admin-actions">
                <form method="POST" action="toggle_stock.php" style="display:inline;">
                    <input type="hidden" name="csrf_token"
                           value="<?php echo htmlspecialchars($_SESSION['add_cart_csrf']); ?>">
                    <input type="hidden" name="product_id"
                           value="<?php echo (int)$prd['id']; ?>">
                    <button type="submit" class="btn btn-warning">
                        <?php echo ($prd['in_stock'] == 1) ? ' Remove stock' : ' Restock'; ?>
                    </button>
                </form>
                <form method="POST" action="delete_product.php" style="display:inline;"
                      onsubmit="return confirm('Delete this product?')">
                    <input type="hidden" name="csrf_token"
                           value="<?php echo htmlspecialchars($_SESSION['add_cart_csrf']); ?>">
                    <input type="hidden" name="product_id"
                           value="<?php echo (int)$prd['id']; ?>">
                    <button type="submit" class="btn btn-danger">🗑️ Delete</button>
                </form>
            </div>
            <?php endif; ?>

        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</main>
</body>
</html>
