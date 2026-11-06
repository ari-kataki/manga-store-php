# Manga Store (PHP)

A small online manga shop made with PHP and MySQL, built while studying web development and databases at ITS. It is the web version of a manga store idea I had also built as a JavaFX desktop app.

## What it does
- Register and log in (two roles: USER and ADMIN)
- Catalog loaded through a stored procedure (`sp_get_catalog`)
- Cart per user (add, change quantity, remove)
- Checkout through a stored procedure with a transaction (`sp_complete_order`)
- Order history for each user
- Admin: add products, mark in/out of stock, delete (soft delete), see a log filled by SQL triggers

## Stack
PHP 8 (PDO, prepared statements, sessions, CSRF tokens), MySQL (triggers, stored procedures, transactions), plain CSS.

## How to run
1. Create the database by importing `manga_store.sql` (phpMyAdmin or the mysql client).
2. Copy `config.example.php` to `config.php` and put your own MySQL user and password.
3. Put the folder in your web server (MAMP/XAMPP) and open `login.php`.

The SQL file also creates two demo accounts (an admin and a normal user), look in `manga_store.sql` for their usernames; the passwords are not written here.

## What it shows about my learning
- Moving from plain queries to a `DbConnector` class with PDO
- Using triggers, stored procedures and transactions from PHP
- Soft delete instead of `DELETE`
- CSRF tokens on every form

## Known issues
- Demo accounts use a weak password on purpose, only for local testing
- No `session_regenerate_id()` after login
- CSRF tokens are shared between some forms (for example the admin buttons reuse the catalog token)
- Table and column names are a mix of Italian (`titolo`, `genere`, `prezzo`) and English
- The `immagine` column exists but the images are not part of the repo and are not shown
