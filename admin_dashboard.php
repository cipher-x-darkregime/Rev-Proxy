<?php
require_once 'config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        echo 'UNAUTHORIZED';
        exit();
    } else {
        header('Location: admin_login.php');
        exit();
    }
}

$conn = getDBConnection();

// Get server statistics
$total_users = $conn->query('SELECT COUNT(*) FROM users WHERE user_type = "user"')->fetchColumn();
$total_admins = $conn->query('SELECT COUNT(*) FROM users WHERE user_type = "admin"')->fetchColumn();
$total_cookies = $conn->query('SELECT COUNT(*) FROM cookies')->fetchColumn();
$total_tools = $conn->query('SELECT COUNT(*) FROM tools')->fetchColumn();
$total_servers = $conn->query('SELECT COUNT(*) FROM servers')->fetchColumn();
?>

<!-- Dashboard Overview Section -->
<div class="dashboard-section" id="dashboard-section">
    <div class="section-header-row">
        <div class="section-header"><i class="bi bi-speedometer2"></i> Dashboard Overview</div>
        <button class="btn btn-outline-primary" id="refresh-dashboard">
            <i class="bi bi-arrow-clockwise"></i> Refresh
        </button>
    </div>
    <div class="dashboard-grid">
        <div class="dashboard-card users">
            <div class="icon"><i class="bi bi-people"></i></div>
            <div class="card-title">Total Users</div>
            <div class="card-number"><?php echo $total_users; ?></div>
            <span class="badge bg-white text-primary">Active</span>
        </div>
        <div class="dashboard-card admins">
            <div class="icon"><i class="bi bi-shield-check"></i></div>
            <div class="card-title">Total Admins</div>
            <div class="card-number"><?php echo $total_admins; ?></div>
            <span class="badge bg-white text-info">System</span>
        </div>
        <div class="dashboard-card cookies">
            <div class="icon"><i class="bi bi-cookie"></i></div>
            <div class="card-title">Total Cookies</div>
            <div class="card-number"><?php echo $total_cookies; ?></div>
            <span class="badge bg-white text-success">Stored</span>
        </div>
        <div class="dashboard-card tools">
            <div class="icon"><i class="bi bi-tools"></i></div>
            <div class="card-title">Total Tools</div>
            <div class="card-number"><?php echo $total_tools; ?></div>
            <span class="badge bg-white text-info">Available</span>
        </div>
        <div class="dashboard-card servers">
            <div class="icon"><i class="bi bi-hdd-stack"></i></div>
            <div class="card-title">Total Servers</div>
            <div class="card-number"><?php echo $total_servers; ?></div>
            <span class="badge bg-white text-success">Running</span>
        </div>
    </div>
    <!-- Dashboard Management Buttons -->
    <div class="dashboard-management">
        <button class="btn btn-outline-primary" id="dashboard-manage-tools"><i class="bi bi-tools"></i> Manage Tools</button>
        <button class="btn btn-outline-primary" id="dashboard-manage-servers"><i class="bi bi-hdd-stack"></i> Manage Servers</button>
        <button class="btn btn-outline-primary" id="dashboard-manage-users"><i class="bi bi-people"></i> Manage Users</button>
        <button class="btn btn-outline-primary" id="dashboard-see-logs"><i class="bi bi-clipboard-data"></i> See Logs</button>
        <button class="btn btn-outline-primary" id="dashboard-settings"><i class="bi bi-gear"></i> Settings</button>
    </div>
</div> 