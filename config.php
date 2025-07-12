<?php
// Set session ini settings BEFORE session_start()
define('SECURE_SESSION', true);
define('SESSION_LIFETIME', 3600); // 1 hour
define('COOKIE_LIFETIME', 86400); // 24 hours

if (SECURE_SESSION) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 1);
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
}

session_start();

define('DB_HOST', 'localhost'); // Use just 'localhost'
define('DB_USER', 'root');
define('DB_PASS', ''); // Set your MySQL root password if you have one
define('DB_NAME', 'ownera_test');

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

// Helper function to log activities
function logActivity($user_id, $action, $details = null) {
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare('INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)');
        $stmt->execute([$user_id, $action, $details, $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
    } catch (Exception $e) {
        // Silently fail if logging fails
        error_log("Failed to log activity: " . $e->getMessage());
    }
} 