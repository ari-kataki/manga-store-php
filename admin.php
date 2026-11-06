<?php
// Admin management panel.
// Shows the product modifications log (product_log)
// and the summary of orders from all users.

session_start();
require_once 'DbConnector.php';
$config = require 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'ADMIN') {
    header('Location: login.php');
    exit();
}

$db = new DbConnector($config['host'], $config['user'], $config['pass'], $config['name']);

// Product log (populated automatically by TRIGGERS)
$logSth = $db->prepare(
    'SELECT pl.id, pl.action, pl.note, pl.created_at, p.titolo
     FROM product_log pl
     JOIN product p ON p.id = pl.product_id
     ORDER BY pl.created_at DESC
     LIMIT 30'
);
$logSth->execute();
$logs = $logSth->fetchAll(PDO::FETCH_ASSOC);
$logSth->closeCursor();

// Recent orders from all users
$ordersSth = $db->prepare(
    'SELECT o.id, o.total_price, o.created_at, u.username
     FROM orders o
     JOIN user u ON u.id = o.user_id
     ORDER BY o.created_at DESC
     LIMIT 20'
);
$ordersSth->execute();
$allOrders = $ordersSth->fetchAll(PDO::FETCH_ASSOC);
$ordersSth->closeCursor();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manga Store - Admin Panel</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header class="site-header">
    <div class="header-inner">
        <div class="logo"> MANGA STORE</div>
        <nav class="nav">
            <a href="index.php" class="nav-link">← Catalog</a>
            <a href="insert_product.php" class="nav-link nav-add">➕ New product</a>
            <a href="logout.php" class="nav-link nav-logout">Logout</a>
        </nav>
    </div>
</header>

<main class="main-content">
    <h1 class="page-title"> Admin Panel</h1>

    <!-- ===== LOG SECTION ===== -->
    <section class="admin-section">
        <h2> Product Changes Log
            <small>(automatically updated by SQL Triggers)</small>
        </h2>

        <?php if (empty($logs)): ?>
            <p class="empty-msg">No changes recorded.</p>
        <?php else: ?>
        <div class="log-table-wrap">
            <table class="log-table">
                <thead>
                <tr>
                    <th>#</th>
                    <th>Product</th>
                    <th>Action</th>
                    <th>Notes</th>
                    <th>Date</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?php echo (int)$log['id']; ?></td>
                    <td><?php echo htmlspecialchars($log['titolo']); ?></td>
                    <td>
                        <span class="log-action log-<?php echo strtolower($log['action']); ?>">
                            <?php echo htmlspecialchars($log['action']); ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($log['note'] ?? '-'); ?></td>
                    <td><?php echo date('d/m/Y H:i', strtotime($log['created_at'])); ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>

    <!-- ===== ORDERS SECTION ===== -->
    <section class="admin-section">
        <h2> Recent Orders (all users)</h2>

        <?php if (empty($allOrders)): ?>
            <p class="empty-msg">No orders yet.</p>
        <?php else: ?>
        <div class="log-table-wrap">
            <table class="log-table">
                <thead>
                <tr>
                    <th>#Order</th>
                    <th>User</th>
                    <th>Total</th>
                    <th>Date</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($allOrders as $ord): ?>
                <tr>
                    <td><?php echo (int)$ord['id']; ?></td>
                    <td><?php echo htmlspecialchars($ord['username']); ?></td>
                    <td>€ <?php echo number_format((float)$ord['total_price'], 2, ',', '.'); ?></td>
                    <td><?php echo date('d/m/Y H:i', strtotime($ord['created_at'])); ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>

</main>

</body>
</html>