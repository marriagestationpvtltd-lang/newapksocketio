<?php
/**
 * Database connection helper for Marriage Station user-facing site.
 * Returns a PDO instance configured for the ms database.
 */

define('MS_DB_HOST', 'localhost');
define('MS_DB_NAME', 'ms');
define('MS_DB_USER', 'ms');
define('MS_DB_PASS', 'ms');

try {
    $pdo = new PDO(
        'mysql:host=' . MS_DB_HOST . ';dbname=' . MS_DB_NAME . ';charset=utf8mb4',
        MS_DB_USER,
        MS_DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    error_log('Marriage Station DB connection failed: ' . $e->getMessage());
    die('Database connection error. Please try again later.');
}
