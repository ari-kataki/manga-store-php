CREATE DATABASE IF NOT EXISTS manga_store;
USE manga_store;

CREATE TABLE `user` (
    `id`         INT          NOT NULL AUTO_INCREMENT,
    `username`   VARCHAR(40)  NOT NULL UNIQUE,
    `email`      VARCHAR(100) NOT NULL UNIQUE,
    `password`   VARCHAR(72)  NOT NULL,
    `role`       ENUM('USER','ADMIN') NOT NULL DEFAULT 'USER',
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `deleted_at` TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `product` (
    `id`         INT           NOT NULL AUTO_INCREMENT,
    `titolo`     VARCHAR(100)  NOT NULL,
    `genere`     VARCHAR(60)   NOT NULL,
    `prezzo`     DECIMAL(10,2) NOT NULL CHECK (prezzo > 0),
    `immagine`   VARCHAR(100)  NOT NULL DEFAULT 'default.jpg',
    `in_stock`   TINYINT(1)    NOT NULL DEFAULT 1,
    `deleted_at` TIMESTAMP     NULL DEFAULT NULL,
    `updated_by` INT           NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_product_updated_by`
        FOREIGN KEY (`updated_by`) REFERENCES `user`(`id`)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `cart` (
    `id`         INT NOT NULL AUTO_INCREMENT,
    `user_id`    INT NOT NULL,
    `product_id` INT NOT NULL,
    `quantity`   INT NOT NULL DEFAULT 1 CHECK (quantity > 0),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_user_product` (`user_id`, `product_id`), 
    CONSTRAINT `fk_cart_user`
        FOREIGN KEY (`user_id`) REFERENCES `user`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_cart_product`
        FOREIGN KEY (`product_id`) REFERENCES `product`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `orders` (
    `id`          INT           NOT NULL AUTO_INCREMENT,
    `user_id`     INT           NOT NULL,
    `total_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_orders_user`
        FOREIGN KEY (`user_id`) REFERENCES `user`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `order_items` (
    `id`                INT           NOT NULL AUTO_INCREMENT,
    `order_id`          INT           NOT NULL,
    `product_id`        INT           NOT NULL,
    `quantity`          INT           NOT NULL DEFAULT 1,
    `price_at_purchase` DECIMAL(10,2) NOT NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_oi_order`
        FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_oi_product`
        FOREIGN KEY (`product_id`) REFERENCES `product`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE `product_log` (
    `id`         INT          NOT NULL AUTO_INCREMENT,
    `product_id` INT          NOT NULL,
    `action`     VARCHAR(30)  NOT NULL,
    `note`       VARCHAR(200) NULL,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_log_product`
        FOREIGN KEY (`product_id`) REFERENCES `product`(`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DELIMITER $$
CREATE TRIGGER after_order_item_insert
AFTER INSERT ON order_items
FOR EACH ROW
BEGIN
    UPDATE orders
    SET total_price = total_price + (NEW.price_at_purchase * NEW.quantity)
    WHERE id = NEW.order_id;
END$$
DELIMITER ;

DELIMITER $$
CREATE TRIGGER after_product_stock_update
AFTER UPDATE ON product
FOR EACH ROW
BEGIN
    IF OLD.in_stock != NEW.in_stock THEN
        INSERT INTO product_log (product_id, action, note, created_at)
        VALUES (
            NEW.id,
            IF(NEW.in_stock = 1, 'RESTOCKED', 'OUT_OF_STOCK'),
            CONCAT('Stock changed from ', OLD.in_stock, ' to ', NEW.in_stock),
            NOW()
        );
    END IF;
END$$
DELIMITER ;

DELIMITER $$
CREATE TRIGGER after_product_soft_delete
AFTER UPDATE ON product
FOR EACH ROW
BEGIN
    IF OLD.deleted_at IS NULL AND NEW.deleted_at IS NOT NULL THEN
        INSERT INTO product_log (product_id, action, note, created_at)
        VALUES (
            NEW.id,
            'DELETED',
            'Product soft-deleted from catalog',
            NOW()
        );
    END IF;
END$$
DELIMITER ;

DELIMITER $$
CREATE PROCEDURE sp_get_catalog(IN is_admin TINYINT)
BEGIN
    IF is_admin = 1 THEN
        SELECT id, titolo, genere, prezzo, immagine, in_stock, deleted_at
        FROM product
        WHERE deleted_at IS NULL
        ORDER BY titolo;
    ELSE
        SELECT id, titolo, genere, prezzo, immagine, in_stock
        FROM product
        WHERE deleted_at IS NULL AND in_stock = 1
        ORDER BY titolo;
    END IF;
END$$
DELIMITER ;

DELIMITER $$
CREATE PROCEDURE sp_complete_order(IN p_user_id INT, OUT p_order_id INT)
BEGIN
    DECLARE exit handler FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_order_id = -1;  
    END;

    START TRANSACTION;

    INSERT INTO orders (user_id, total_price, created_at)
    VALUES (p_user_id, 0.00, NOW());
    SET p_order_id = LAST_INSERT_ID();

    INSERT INTO order_items (order_id, product_id, quantity, price_at_purchase)
    SELECT p_order_id, c.product_id, c.quantity, p.prezzo
    FROM cart c
    JOIN product p ON p.id = c.product_id
    WHERE c.user_id = p_user_id;

    DELETE FROM cart WHERE user_id = p_user_id;

    COMMIT;
END$$
DELIMITER ;

INSERT INTO `product` (titolo, genere, prezzo, immagine, in_stock) VALUES
('Naruto Vol. 1',           'Action / Adventure',      5.90, 'naruto.jpg',      1),
('Chainsaw Man Vol. 1',     'Action / Fantasy',        5.90, 'chainsaw.jpg',    1),
('Dragon Ball Vol. 1',      'Action / Comedy',         6.50, 'dragonball.jpg',  0),
('Attack on Titan Vol. 1',  'Action / Horror',         6.90, 'aot.jpg',         1),
('Demon Slayer Vol. 1',     'Action / Story',          5.90, 'demonslayer.jpg', 1),
('My Hero Academia Vol. 1', 'Action / Superhero',      5.90, 'mha.jpg',         0),
('Death Note Vol. 1',       'Thriller / Mystery',      6.50, 'deathnote.jpg',   1),
('Your Lie in April Vol. 1','Slice of Life / Drama',   6.00, 'ylia.jpg',        1);

-- demo accounts
INSERT INTO `user` (username, email, password, role) VALUES
('Admin', 'admin@example.com', '$2y$12$W2jTd74IZIG6e9r09dXT/uyPUGpdEGYomcTIgW78GQwvVjKGb3vGW', 'ADMIN'),
('User',  'user@example.com',  '$2y$12$W2jTd74IZIG6e9r09dXT/uyPUGpdEGYomcTIgW78GQwvVjKGb3vGW', 'USER');
