<?php
require_once __DIR__ . '/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'user') {
    header('Location: user_login.php');
    exit();
}

// Get latest cookie data
$conn = getDBConnection();
$stmt = $conn->query('SELECT cookie_data FROM cookies ORDER BY updated_at DESC LIMIT 1');
$latest_cookies = $stmt->fetch(PDO::FETCH_ASSOC);

// Get user information
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare('SELECT username, email, last_login FROM users WHERE id = ?');
$stmt->execute([$user_id]);
$user_info = $stmt->fetch(PDO::FETCH_ASSOC);

// Get available tools for the user
$stmt = $conn->query('
    SELECT t.id, t.name, t.status, t.domain, t.directory, t.user_limit,
           COUNT(DISTINCT s.id) as server_count
    FROM tools t
    LEFT JOIN servers s ON t.id = s.tool_id AND s.status = "active"
    WHERE t.status = "active"
    GROUP BY t.id, t.name, t.status, t.domain, t.directory, t.user_limit
    ORDER BY t.name
');
$available_tools = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h2>Welcome, <?php echo htmlspecialchars($user_info['username']); ?>!</h2>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h5>User Information</h5>
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($user_info['email']); ?></p>
                                <p><strong>Last Login:</strong> <?php echo $user_info['last_login'] ? date('Y-m-d H:i:s', strtotime($user_info['last_login'])) : 'Never'; ?></p>
                            </div>
                            <div class="col-md-6">
                                <h5>Latest Cookie Data</h5>
                                <?php if ($latest_cookies): ?>
                                    <div class="alert alert-info">
                                        <small><strong>Updated:</strong> <?php echo date('Y-m-d H:i:s', strtotime($latest_cookies['updated_at'] ?? 'now')); ?></small>
                                        <pre class="mt-2" style="font-size: 0.8em; max-height: 200px; overflow-y: auto;"><?php echo htmlspecialchars($latest_cookies['cookie_data']); ?></pre>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-warning">No cookie data available</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5>Available Tools</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($available_tools): ?>
                            <div class="row">
                                <?php foreach ($available_tools as $tool): ?>
                                <div class="col-md-4 mb-3">
                                    <div class="card h-100">
                                        <div class="card-body">
                                            <h6 class="card-title"><?php echo htmlspecialchars($tool['name']); ?></h6>
                                            <p class="card-text">
                                                <small class="text-muted">
                                                    <strong>Domain:</strong> <?php echo htmlspecialchars($tool['domain']); ?><br>
                                                    <strong>Directory:</strong> <?php echo htmlspecialchars($tool['directory']); ?><br>
                                                    <strong>User Limit:</strong> <?php echo htmlspecialchars($tool['user_limit']); ?><br>
                                                    <strong>Active Servers:</strong> <?php echo htmlspecialchars($tool['server_count']); ?>
                                                </small>
                                            </p>
                                            <button class="btn btn-primary btn-sm">Access Tool</button>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">No tools are currently available.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 