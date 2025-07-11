<?php
require_once __DIR__ . '/../core/config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: /Rev-Proxy/admin');
    exit();
}

$conn = getDBConnection();

// Get dashboard statistics
$stats = [];

// Total users
$stmt = $conn->query('SELECT COUNT(*) FROM users WHERE user_type = "user"');
$stats['total_users'] = $stmt->fetchColumn();

// Total admins
$stmt = $conn->query('SELECT COUNT(*) FROM users WHERE user_type = "admin"');
$stats['total_admins'] = $stmt->fetchColumn();

// Total tools
$stmt = $conn->query('SELECT COUNT(*) FROM tools');
$stats['total_tools'] = $stmt->fetchColumn();

// Total servers
$stmt = $conn->query('SELECT COUNT(*) FROM servers');
$stats['total_servers'] = $stmt->fetchColumn();

// Total cookie updates
$stmt = $conn->query('SELECT COUNT(*) FROM cookies');
$stats['total_cookies'] = $stmt->fetchColumn();

// Get latest cookies
$stmt = $conn->query('SELECT * FROM cookies ORDER BY updated_at DESC LIMIT 1');
$latest_cookies = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle cookie form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_cookies') {
    $cookie_data = $_POST['cookie_data'] ?? '';
    
    try {
        $stmt = $conn->prepare('INSERT INTO cookies (cookie_data, updated_by) VALUES (?, ?)');
        $stmt->execute([$cookie_data, $_SESSION['user_id']]);
        
        logActivity($_SESSION['user_id'], 'Updated Cookies', 'Cookie data updated');
        
        // Redirect to prevent form resubmission
        header('Location: /Rev-Proxy/dashboard');
        exit();
    } catch (Exception $e) {
        $error = 'Error updating cookies: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@700&display=swap" rel="stylesheet">
    <link href="assets/admin-styles.css" rel="stylesheet">
</head>
<body>
    <div class="d-flex">
        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="sidebar-toggle">
                <button class="sidebar-toggle-btn" id="sidebarToggleBtn">
                    <i class="bi bi-list"></i>
                </button>
            </div>
            
            <div class="sidebar-header">
                <h3>Admin Panel</h3>
                <p>Reverse Proxy Management</p>
            </div>
            
            <nav class="sidebar-nav">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link active" href="/Rev-Proxy/dashboard">
                            <i class="bi bi-speedometer2"></i>
                            Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/Rev-Proxy/tools">
                            <i class="bi bi-tools"></i>
                            Tool Management
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/Rev-Proxy/servers">
                            <i class="bi bi-hdd-stack"></i>
                            Server Management
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/Rev-Proxy/users">
                            <i class="bi bi-people"></i>
                            User Management
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/Rev-Proxy/logs">
                            <i class="bi bi-clipboard-data"></i>
                            Activity Logs
                        </a>
                    </li>
                </ul>
            </nav>
            
            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)); ?>
                    </div>
                    <div class="user-details">
                        <h6><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></h6>
                        <p>Administrator</p>
                    </div>
                </div>
                <a href="/Rev-Proxy/logout" class="btn logout-btn">
                    <i class="bi bi-box-arrow-right me-2"></i>
                    Logout
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content" id="mainContent">
            <div class="page-header">
                <h1>Dashboard</h1>
                <p>Welcome back! Here's an overview of your system.</p>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon users">
                        <i class="bi bi-people"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['total_users']; ?></div>
                    <div class="stat-label">Total Users</div>
                    <div class="stat-change positive">
                        <i class="bi bi-arrow-up me-1"></i>
                        Active users in system
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon admins">
                        <i class="bi bi-shield-check"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['total_admins']; ?></div>
                    <div class="stat-label">Administrators</div>
                    <div class="stat-change positive">
                        <i class="bi bi-arrow-up me-1"></i>
                        System administrators
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon cookies">
                        <i class="bi bi-cookie"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['total_cookies']; ?></div>
                    <div class="stat-label">Cookie Updates</div>
                    <div class="stat-change positive">
                        <i class="bi bi-arrow-up me-1"></i>
                        Total cookie updates
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon tools">
                        <i class="bi bi-tools"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['total_tools']; ?></div>
                    <div class="stat-label">Active Tools</div>
                    <div class="stat-change positive">
                        <i class="bi bi-arrow-up me-1"></i>
                        Available tools
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon servers">
                        <i class="bi bi-hdd-stack"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['total_servers']; ?></div>
                    <div class="stat-label">Running Servers</div>
                    <div class="stat-change positive">
                        <i class="bi bi-arrow-up me-1"></i>
                        Active servers
                    </div>
                </div>
            </div>

            <!-- Dashboard Management Buttons -->
            <div class="dashboard-management">
                <a href="/Rev-Proxy/tools" class="btn btn-outline-primary">
                    <i class="bi bi-tools"></i> Manage Tools
                </a>
                <a href="/Rev-Proxy/servers" class="btn btn-outline-primary">
                    <i class="bi bi-hdd-stack"></i> Manage Servers
                </a>
                <a href="/Rev-Proxy/users" class="btn btn-outline-primary">
                    <i class="bi bi-people"></i> Manage Users
                </a>
                <a href="/Rev-Proxy/logs" class="btn btn-outline-primary">
                    <i class="bi bi-clipboard-data"></i> See Logs
                </a>
            </div>

            <!-- Cookies Management Section -->
            <div class="cookies-section">
                <h3><i class="bi bi-cookie"></i> Cookie Management</h3>
                <form class="cookies-form" method="POST">
                    <input type="hidden" name="action" value="update_cookies">
                    <div class="mb-3">
                        <label for="cookieData" class="form-label">Cookie Data (JSON Format)</label>
                        <textarea class="form-control" id="cookieData" name="cookie_data" placeholder='{"cookie1": "value1", "cookie2": "value2"}'><?php echo htmlspecialchars($latest_cookies['cookie_data'] ?? ''); ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-2"></i>
                        Update Cookies
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/admin-scripts.js"></script>
    <script>
        // Handle cookie form submission
        document.querySelector('.cookies-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(() => {
                showMessage('Cookies updated successfully!', 'success');
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            })
            .catch(error => {
                showMessage('Error updating cookies: ' + error.message, 'danger');
            });
        });
    </script>
</body>
</html> 