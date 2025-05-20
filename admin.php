<?php
require_once 'config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: admin_login.php');
    exit();
}

$conn = getDBConnection();
$message = '';
$error = '';

// Handle cookie update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_cookies') {
        $cookie_data = $_POST['cookie_data'];
        
        if (!empty($cookie_data)) {
            try {
                // Validate JSON
                json_decode($cookie_data);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $stmt = $conn->prepare('INSERT INTO cookies (cookie_data, updated_by) VALUES (?, ?)');
                    $stmt->execute([$cookie_data, $_SESSION['user_id']]);
                    $message = 'Cookies updated successfully';
                } else {
                    $error = 'Invalid JSON format';
                }
            } catch (Exception $e) {
                $error = 'Error updating cookies: ' . $e->getMessage();
            }
        } else {
            $error = 'Cookie data cannot be empty';
        }
    }
}

// Get latest cookie data
$stmt = $conn->query('SELECT cookie_data, updated_at FROM cookies ORDER BY updated_at DESC LIMIT 1');
$latest_cookies = $stmt->fetch(PDO::FETCH_ASSOC);

// Get server statistics
$total_users = $conn->query('SELECT COUNT(*) FROM users WHERE user_type = "user"')->fetchColumn();
$total_admins = $conn->query('SELECT COUNT(*) FROM users WHERE user_type = "admin"')->fetchColumn();
$total_cookies = $conn->query('SELECT COUNT(*) FROM cookies')->fetchColumn();
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
            background: linear-gradient(135deg, #f4f6f9 0%, #e9eafc 100%);
            padding-top: 0;
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
            border-radius: 8px;
            color: #fff;
            font-size: 1.5rem;
            padding: 6px 10px;
            cursor: pointer;
            transition: background 0.2s;
        }
        .sidebar .sidebar-toggle-btn:hover {
            background: var(--primary-color);
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,.88);
            padding: 16px 28px;
            margin: 10px 0;
            border-radius: 14px;
            font-size: 1.13rem;
            display: flex;
            align-items: center;
            transition: all 0.22s cubic-bezier(.4,2,.6,1);
            position: relative;
            font-weight: 500;
            letter-spacing: 0.5px;
        }
        .sidebar .nav-link.active, .sidebar .nav-link:hover {
            background: rgba(67,97,238,0.18);
            color: #fff;
            box-shadow: 0 4px 16px 0 rgba(67,97,238,0.13);
        }
        .sidebar .nav-link.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 10px;
            bottom: 10px;
            width: 6px;
            border-radius: 6px;
            background: var(--accent-bar);
            box-shadow: 0 0 8px 2px #4cc9f0;
            animation: glow 1.2s infinite alternate;
        }
        @keyframes glow {
            from { box-shadow: 0 0 8px 2px #4cc9f0; }
            to { box-shadow: 0 0 16px 4px #4361ee; }
        }
        .sidebar .nav-link i {
            font-size: 1.5rem;
            margin-right: 20px;
            transition: transform 0.18s;
        }
        .sidebar .nav-link:hover i {
            transform: scale(1.18);
        }
        .sidebar-collapsed .nav-link span {
            display: none;
        }
        .sidebar-collapsed .sidebar-profile span {
            display: none;
        }
        .sidebar-collapsed .sidebar-profile img {
            margin-right: 0 !important;
        }
        .sidebar-profile {
            background: rgba(255,255,255,0.04);
            border-radius: 16px;
            margin: 22px 16px 16px 16px;
            box-shadow: none;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 18px 0 10px 0;
        }
        .sidebar-profile .dropdown-toggle {
            color: #fff;
        }
        .sidebar-profile img {
            border: 2.5px solid var(--primary-color);
            margin-right: 12px;
        }
        .sidebar-profile .status-dot {
            width: 12px;
            height: 12px;
            background: #4cc9f0;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
            box-shadow: 0 0 8px #4cc9f0;
        }
        .sidebar-profile .dropdown-menu {
            background: #23242b;
            border-radius: 12px;
            border: none;
        }
        .sidebar-profile .dropdown-item {
            color: #fff;
            border-radius: 8px;
            transition: background 0.2s;
        }
        .sidebar-profile .dropdown-item:hover {
            background: var(--primary-color);
        }
        .main-content {
            min-height: 100vh;
            transition: margin-left 0.4s cubic-bezier(.4,2,.6,1), width 0.4s cubic-bezier(.4,2,.6,1);
            margin-left: var(--sidebar-width);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
        }
        .sidebar-collapsed ~ .main-content {
            margin-left: var(--sidebar-collapsed-width);
        }
        .main-floating-card {
            background: rgba(255,255,255,0.85);
            backdrop-filter: blur(12px);
            border-radius: 36px;
            box-shadow: 0 12px 48px 0 rgba(67,97,238,0.13);
            padding: 3.5rem 3.5rem 2.5rem 3.5rem;
            margin: 3.5rem auto 2.5rem auto;
            max-width: 1100px;
            width: 100%;
            transition: box-shadow 0.3s, padding 0.3s, max-width 0.3s;
            display: flex;
            flex-direction: column;
            gap: 3.5rem;
        }
        @media (max-width: 1200px) {
            .main-floating-card {
                max-width: 98vw;
                padding: 2.2rem 1.2rem 1.2rem 1.2rem;
            }
        }
        @media (max-width: 991.98px) {
            .main-content {
                margin-left: var(--sidebar-collapsed-width);
            }
        }
        .section-header {
            display: flex;
            align-items: center;
            gap: 14px;
            font-family: 'Montserrat', sans-serif;
            font-size: 1.6rem;
            font-weight: 800;
            color: #23283e;
            margin-bottom: 2.2rem;
            letter-spacing: 0.5px;
        }
        .section-header i {
            font-size: 2.2rem;
            color: var(--primary-color);
        }
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 2.2rem;
            margin-bottom: 2.5rem;
        }
        .dashboard-card, .server-card {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: flex-start;
            background: rgba(255,255,255,0.97);
            border-radius: 20px;
            box-shadow: 0 2px 12px rgba(67,97,238,0.08);
            border: 1.5px solid #f0f1f6;
            padding: 2.1rem 1.6rem 1.3rem 1.6rem;
            position: relative;
            transition: box-shadow 0.18s, border 0.18s, transform 0.18s;
            min-height: 160px;
        }
        .dashboard-card:hover, .server-card:hover {
            box-shadow: 0 8px 32px rgba(67,97,238,0.13);
            border: 1.5px solid var(--primary-color);
            transform: translateY(-2px) scale(1.015);
        }
        .dashboard-card .icon, .server-card .icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.7rem;
            margin-bottom: 1.1rem;
            background: #f2f6fc;
            color: var(--primary-color);
            box-shadow: none;
        }
        .dashboard-card .card-title, .server-card .card-title {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.08rem;
            font-weight: 700;
            color: #23283e;
            margin-bottom: 0.4rem;
        }
        .dashboard-card .card-number, .server-card .card-number {
            font-size: 2.2rem;
            font-weight: 800;
            color: var(--primary-color);
            margin-bottom: 0.4rem;
        }
        .dashboard-card .badge, .server-card .badge {
            font-size: 0.93rem;
            border-radius: 7px;
            padding: 0.32em 0.8em;
            font-weight: 500;
            background: #f2f6fc;
            color: var(--primary-color);
            border: none;
        }
        .dashboard-card .badge.text-success, .server-card .badge.text-success {
            color: #1b7c4a;
            background: #e6f9f0;
        }
        .dashboard-card .badge.text-info, .server-card .badge.text-info {
            color: #2274e0;
            background: #e9f4ff;
        }
        .server-table th, .server-table td {
            border: none;
            padding: 1.25rem 1.3rem;
            vertical-align: middle;
        }
        .server-table th {
            background: #f6f8fb;
            color: #23283e;
            font-weight: 800;
            font-size: 1.13rem;
            letter-spacing: 0.03em;
        }
        .server-table tbody tr {
            background: #fff;
            border-radius: 14px;
        }
        .server-table tbody tr:hover {
            background: #f2f6fc;
        }
        .server-table td {
            font-size: 1.08rem;
            color: #23283e;
        }
        .server-table .server-name {
            font-weight: 700;
            font-size: 1.13rem;
            color: var(--primary-color);
        }
        .server-table .badge {
            font-size: 0.98rem;
            border-radius: 8px;
            padding: 0.38em 1.1em;
            font-weight: 600;
            background: #f2f6fc;
            color: var(--primary-color);
            border: none;
        }
        .server-table .badge.text-success {
            color: #1b7c4a;
            background: #e6f9f0;
        }
        .server-table .btn {
            border-radius: 999px !important;
            font-size: 1.08rem;
            padding: 0.5rem 1.3rem;
            margin-right: 0.3rem;
        }
        .server-table .btn:last-child {
            margin-right: 0;
        }
        .floating-add-btn {
            position: fixed;
            bottom: 32px;
            right: 32px;
            z-index: 2000;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            color: #fff;
            border: none;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            box-shadow: 0 4px 24px rgba(67,97,238,0.18);
            transition: background 0.2s, box-shadow 0.2s;
        }
        .floating-add-btn:hover {
            background: linear-gradient(90deg, var(--secondary-color), var(--primary-color));
            box-shadow: 0 8px 32px rgba(67,97,238,0.28);
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-toggle">
            <button class="sidebar-toggle-btn" id="sidebarToggleBtn" title="Toggle Sidebar">
                <i class="bi bi-list"></i>
            </button>
        </div>
        <div class="sidebar-sticky d-flex flex-column justify-content-between h-100">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link active" href="#dashboard">
                        <i class="bi bi-speedometer2"></i>
                        Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#servers">
                        <i class="bi bi-hdd-stack"></i>
                        Servers
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#tools">
                        <i class="bi bi-tools"></i>
                        Tools
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#users">
                        <i class="bi bi-people"></i>
                        Users
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#analytics">
                        <i class="bi bi-graph-up"></i>
                        Analytics
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#settings">
                        <i class="bi bi-gear"></i>
                        Settings
                    </a>
                </li>
            </ul>
            <div class="sidebar-profile p-3 border-top mt-3">
                <div class="dropdown">
                    <button class="btn btn-link text-white d-flex align-items-center w-100 p-0 dropdown-toggle" type="button" id="userMenu" data-bs-toggle="dropdown">
                        <span class="status-dot"></span>
                        <img src="https://ui-avatars.com/api/?name=Admin&background=4361ee&color=fff" class="rounded-circle me-2" width="36" height="36">
                        <span class="me-2">Admin</span>
                        <i class="bi bi-chevron-up ms-auto"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-dark w-100">
                        <li><a class="dropdown-item" href="#"><i class="bi bi-person me-2"></i>Profile</a></li>
                        <li><a class="dropdown-item" href="#"><i class="bi bi-gear me-2"></i>Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Main content -->
            <main class="main-content">
                <div class="main-floating-card">
                    <div class="section-header"><i class="bi bi-speedometer2"></i> Dashboard Overview</div>
                    <div class="mb-4" style="margin-top:-1.2rem; color:#6c7a99; font-size:1.08rem;">Quick stats and system health at a glance</div>
                    <div class="dashboard-grid">
                        <div class="dashboard-card users">
                            <div class="icon"><i class="bi bi-people"></i></div>
                            <div class="card-title">Total Users</div>
                            <div class="card-number"><?php echo $total_users; ?></div>
                            <span class="badge bg-white text-primary">+12% from last month</span>
                        </div>
                        <div class="dashboard-card servers">
                            <div class="icon"><i class="bi bi-hdd-stack"></i></div>
                            <div class="card-title">Active Servers</div>
                            <div class="card-number">5</div>
                            <span class="badge bg-white text-success">All systems operational</span>
                        </div>
                        <div class="dashboard-card tools">
                            <div class="icon"><i class="bi bi-tools"></i></div>
                            <div class="card-title">Total Tools</div>
                            <div class="card-number">12</div>
                            <span class="badge bg-white text-info">3 new this week</span>
                        </div>
                    </div>

                    <div class="section-header"><i class="bi bi-hdd-stack"></i> Server Management</div>
                    <div class="server-card mb-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-end mb-3">
                                <button class="btn btn-primary">
                                    <i class="bi bi-plus-lg me-1"></i>
                                    Add Server
                                </button>
                            </div>
                            <div class="table-responsive">
                                <table class="table server-table align-middle">
                                    <thead>
                                        <tr>
                                            <th>Server Name</th>
                                            <th>Status</th>
                                            <th>Location</th>
                                            <th>Users</th>
                                            <th>Uptime</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td class="server-name">Server 1</td>
                                            <td><span class="badge text-success">Active</span></td>
                                            <td>US East</td>
                                            <td>150</td>
                                            <td>99.9%</td>
                                            <td>
                                                <button class="btn btn-outline-primary btn-sm"><i class="bi bi-gear"></i></button>
                                                <button class="btn btn-info btn-sm"><i class="bi bi-graph-up"></i></button>
                                                <button class="btn btn-danger btn-sm"><i class="bi bi-power"></i></button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="server-name">Server 2</td>
                                            <td><span class="badge text-success">Active</span></td>
                                            <td>EU West</td>
                                            <td>120</td>
                                            <td>99.8%</td>
                                            <td>
                                                <button class="btn btn-outline-primary btn-sm"><i class="bi bi-gear"></i></button>
                                                <button class="btn btn-info btn-sm"><i class="bi bi-graph-up"></i></button>
                                                <button class="btn btn-danger btn-sm"><i class="bi bi-power"></i></button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <button class="floating-add-btn" title="Add New">
        <i class="bi bi-plus-lg"></i>
    </button>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar collapse/expand functionality
        const sidebar = document.getElementById('sidebar');
        const toggleBtn = document.getElementById('sidebarToggleBtn');
        toggleBtn.addEventListener('click', function() {
            sidebar.classList.toggle('sidebar-collapsed');
        });
    </script>
</body>
</html> 