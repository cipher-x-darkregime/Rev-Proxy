<?php
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'user') {
    header('Location: user_login.php');
    exit();
}

// Get latest cookie data
$conn = getDBConnection();
$stmt = $conn->query('SELECT cookie_data FROM cookies ORDER BY updated_at DESC LIMIT 1');
$latest_cookies = $stmt->fetch(PDO::FETCH_ASSOC);

// Your existing rev.php code here
// You can access the cookie data using $latest_cookies['cookie_data']
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reverse Proxy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">Reverse Proxy</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h2>Welcome to Reverse Proxy</h2>
        <!-- Add your reverse proxy content here -->
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 