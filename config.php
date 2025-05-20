<?php
session_start();

define('DB_HOST', 'localhost');
define('DB_USER', 'ownera_atest');
define('DB_PASS', 'O_Hv_7QZgR&(');
define('DB_NAME', 'ownera_test');

// Security settings
define('SECURE_SESSION', true);
define('SESSION_LIFETIME', 3600); // 1 hour
define('COOKIE_LIFETIME', 86400); // 24 hours

// Set secure session parameters
if (SECURE_SESSION) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 1);
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
}

// Database connection
function getDBConnection() {
    try {
        $conn = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
            DB_USER,
            DB_PASS,
            array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
        );
        return $conn;
    } catch(PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
} 