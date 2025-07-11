<?php
require_once __DIR__ . '/../core/config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: admin_login.php');
    exit();
}

$conn = getDBConnection();

// Get dashboard statistics
$total_users = $conn->query('SELECT COUNT(*) FROM users WHERE user_type = "user"')->fetchColumn();
$total_admins = $conn->query('SELECT COUNT(*) FROM users WHERE user_type = "admin"')->fetchColumn();
$total_cookies = $conn->query('SELECT COUNT(*) FROM cookies')->fetchColumn();
$total_tools = $conn->query('SELECT COUNT(*) FROM tools')->fetchColumn();
$total_servers = $conn->query('SELECT COUNT(*) FROM servers')->fetchColumn();

// Get latest cookie data
$stmt = $conn->query('SELECT cookie_data, updated_at FROM cookies ORDER BY updated_at DESC LIMIT 1');
$latest_cookies = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
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

        .dashboard-header {
            margin-bottom: 32px;
        }
        .dashboard-header h1 {
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 8px;
            font-family: 'Montserrat', sans-serif;
        }
        .dashboard-header p {
            color: #6c757d;
            margin: 0;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid rgba(0,0,0,0.05);
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        }
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--accent-bar);
        }
        .stat-card .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            margin-bottom: 16px;
        }
        .stat-card .stat-icon.users { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .stat-card .stat-icon.admins { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .stat-card .stat-icon.cookies { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .stat-card .stat-icon.tools { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
        .stat-card .stat-icon.servers { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
        .stat-card .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 8px;
            font-family: 'Montserrat', sans-serif;
        }
        .stat-card .stat-label {
            color: #6c757d;
            font-weight: 500;
            margin-bottom: 8px;
        }
        .stat-card .stat-change {
            display: flex;
            align-items: center;
            font-size: 0.875rem;
        }
        .stat-card .stat-change.positive {
            color: #28a745;
        }
        .stat-card .stat-change.negative {
            color: #dc3545;
        }

        .dashboard-management {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 32px;
        }
        .dashboard-management .btn {
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 500;
            transition: all 0.2s;
            border: 2px solid transparent;
        }
        .dashboard-management .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .cookies-section {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid rgba(0,0,0,0.05);
        }
        .cookies-section h3 {
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
        }
        .cookies-section h3 i {
            margin-right: 8px;
            color: var(--primary-color);
        }
        .cookies-form textarea {
            border-radius: 12px;
            border: 2px solid #e9ecef;
            padding: 16px;
            font-family: 'Courier New', monospace;
            font-size: 0.875rem;
            resize: vertical;
            min-height: 120px;
        }
        .cookies-form textarea:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(67, 97, 238, 0.25);
        }
        .cookies-form .btn {
            border-radius: 12px;
            padding: 12px 24px;
            font-weight: 500;
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
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .dashboard-management {
                flex-direction: column;
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
            <div class="dashboard-header">
                <h1>Dashboard</h1>
                <p>Welcome back! Here's an overview of your system.</p>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon users">
                        <i class="bi bi-people"></i>
                    </div>
                    <div class="stat-number"><?php echo $total_users; ?></div>
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
                    <div class="stat-number"><?php echo $total_admins; ?></div>
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
                    <div class="stat-number"><?php echo $total_cookies; ?></div>
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
                    <div class="stat-number"><?php echo $total_tools; ?></div>
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
                    <div class="stat-number"><?php echo $total_servers; ?></div>
                    <div class="stat-label">Running Servers</div>
                    <div class="stat-change positive">
                        <i class="bi bi-arrow-up me-1"></i>
                        Active servers
                    </div>
                </div>
            </div>

            <!-- Dashboard Management Buttons -->
            <div class="dashboard-management">
                <a href="admin_tools.php" class="btn btn-outline-primary">
                    <i class="bi bi-tools"></i> Manage Tools
                </a>
                <a href="admin_servers.php" class="btn btn-outline-primary">
                    <i class="bi bi-hdd-stack"></i> Manage Servers
                </a>
                <a href="admin_users.php" class="btn btn-outline-primary">
                    <i class="bi bi-people"></i> Manage Users
                </a>
                <a href="admin_logs.php" class="btn btn-outline-primary">
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
    <script>
        // Sidebar collapse/expand functionality
        const sidebar = document.getElementById('sidebar');
        const toggleBtn = document.getElementById('sidebarToggleBtn');
        const mainContent = document.querySelector('.main-content');
        
        toggleBtn.addEventListener('click', function() {
            sidebar.classList.toggle('sidebar-collapsed');
            mainContent.classList.toggle('sidebar-collapsed-main');
        });

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
                alert('Cookies updated successfully!');
                window.location.reload();
            })
            .catch(error => {
                alert('Error updating cookies: ' + error.message);
            });
        });
    </script>
</body>
</html> 