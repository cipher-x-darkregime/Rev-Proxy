<?php
require_once __DIR__ . '/../core/config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: admin_login.php');
    exit();
}

$conn = getDBConnection();

// Get activity logs
$logs_query = $conn->query('
    SELECT al.created_at, u.username, al.action, al.details
    FROM activity_logs al
    LEFT JOIN users u ON al.user_id = u.id
    ORDER BY al.created_at DESC
    LIMIT 50
');
$logs_data = $logs_query->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Montserrat:wght@700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --success-color: #4cc9f0;
            --info-color: #4895ef;
            --warning-color: #f72585;
            --dark-color: #23283e;
            --light-color: #f8f9fa;
            --sidebar-width: 270px;
            --sidebar-collapsed-width: 72px;
            --sidebar-bg: #23283e;
            --sidebar-border: #20243a;
            --accent-bar: linear-gradient(180deg, #4361ee 0%, #4cc9f0 100%);
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f6f8fb 0%, #e9eafc 100%);
            min-height: 100vh;
            overflow-x: hidden;
        }

        .sidebar {
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
            width: var(--sidebar-width);
            height: 100vh;
            background: var(--sidebar-bg);
            box-shadow: none;
            border-right: 1.5px solid var(--sidebar-border);
            transition: width 0.3s cubic-bezier(.4,2,.6,1), box-shadow 0.2s;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow: hidden;
        }
        .sidebar-collapsed {
            width: var(--sidebar-collapsed-width) !important;
        }
        .sidebar .sidebar-toggle {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding: 18px 18px 0 0;
        }
        .sidebar .sidebar-toggle-btn {
            background: rgba(255,255,255,0.12);
            border: none;
            color: white;
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        .sidebar .sidebar-toggle-btn:hover {
            background: rgba(255,255,255,0.2);
        }
        .sidebar .sidebar-header {
            padding: 24px 24px 0 24px;
            margin-bottom: 32px;
        }
        .sidebar .sidebar-header h3 {
            color: white;
            font-weight: 700;
            font-size: 1.5rem;
            margin: 0;
            font-family: 'Montserrat', sans-serif;
        }
        .sidebar .sidebar-header p {
            color: rgba(255,255,255,0.7);
            margin: 8px 0 0 0;
            font-size: 0.875rem;
        }
        .sidebar .sidebar-nav {
            flex: 1;
            padding: 0 16px;
        }
        .sidebar .nav-item {
            margin-bottom: 8px;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 16px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            text-decoration: none;
            transition: all 0.2s;
            font-weight: 500;
        }
        .sidebar .nav-link:hover {
            color: white;
            background: rgba(255,255,255,0.1);
        }
        .sidebar .nav-link.active {
            background: var(--primary-color);
            color: white;
        }
        .sidebar .nav-link i {
            margin-right: 12px;
            font-size: 1.1rem;
            width: 20px;
            text-align: center;
        }
        .sidebar .sidebar-footer {
            padding: 24px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        .sidebar .user-info {
            display: flex;
            align-items: center;
            color: white;
        }
        .sidebar .user-info .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            font-weight: 600;
        }
        .sidebar .user-info .user-details h6 {
            margin: 0;
            font-weight: 600;
            font-size: 0.875rem;
        }
        .sidebar .user-info .user-details p {
            margin: 0;
            font-size: 0.75rem;
            opacity: 0.7;
        }
        .sidebar .logout-btn {
            background: rgba(255,255,255,0.1);
            border: none;
            color: white;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 0.875rem;
            margin-top: 12px;
            width: 100%;
            transition: all 0.2s;
        }
        .sidebar .logout-btn:hover {
            background: rgba(255,255,255,0.2);
        }

        .main-content {
            margin-left: var(--sidebar-width);
            transition: margin-left 0.3s cubic-bezier(.4,2,.6,1);
            min-height: 100vh;
            padding: 32px;
        }
        .main-content.sidebar-collapsed-main {
            margin-left: var(--sidebar-collapsed-width);
        }

        .page-header {
            margin-bottom: 32px;
        }
        .page-header h1 {
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 8px;
            font-family: 'Montserrat', sans-serif;
        }
        .page-header p {
            color: #6c757d;
            margin: 0;
        }

        .section-header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        .section-header {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark-color);
            display: flex;
            align-items: center;
        }
        .section-header i {
            margin-right: 12px;
            color: var(--primary-color);
        }

        .server-table-responsive {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid rgba(0,0,0,0.05);
            overflow-x: auto;
        }
        .server-table {
            margin: 0;
            border: none;
        }
        .server-table th {
            background: #f8f9fa;
            border: none;
            padding: 16px 12px;
            font-weight: 600;
            color: var(--dark-color);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .server-table td {
            border: none;
            padding: 16px 12px;
            vertical-align: middle;
            border-bottom: 1px solid #f1f3f4;
        }
        .server-table tbody tr:hover {
            background: #f8f9fa;
        }

        .log-action {
            font-weight: 600;
            color: var(--primary-color);
        }
        .log-details {
            color: #6c757d;
            font-size: 0.875rem;
        }
        .log-time {
            color: #adb5bd;
            font-size: 0.75rem;
        }
        .log-user {
            font-weight: 500;
            color: var(--dark-color);
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
            }
        }
    </style>
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
                        <a class="nav-link" href="/Rev-Proxy/dashboard">
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
                        <a class="nav-link active" href="/Rev-Proxy/logs">
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
                <h1>Activity Logs</h1>
                <p>Monitor system activities and user actions.</p>
            </div>

            <!-- Logs Management Section -->
            <div class="logs-section">
                <div class="section-header-row">
                    <div class="section-header"><i class="bi bi-clipboard-data"></i> Activity Logs</div>
                </div>
                <div class="server-table-responsive">
                    <table class="table server-table align-middle">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs_data as $log): ?>
                            <tr>
                                <td class="log-time"><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($log['created_at']))); ?></td>
                                <td class="log-user"><?php echo htmlspecialchars($log['username'] ?? 'Unknown'); ?></td>
                                <td class="log-action"><?php echo htmlspecialchars($log['action']); ?></td>
                                <td class="log-details"><?php echo htmlspecialchars($log['details'] ?? ''); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar collapse/expand functionality
        const sidebar = document.getElementById('sidebar');
        const toggleBtn = document.getElementById('sidebarToggleBtn');
        const mainContent = document.querySelector('.main-content');
        
        toggleBtn.addEventListener('click', function() {
            sidebar.classList.toggle('sidebar-collapsed');
            mainContent.classList.toggle('sidebar-collapsed-main');
        });
    </script>
</body>
</html> 