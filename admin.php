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
            margin: 0;
            padding: 0;
            min-height: 100vh;
            overflow-x: hidden;
        }
        .layout {
            display: flex;
            width: 100vw;
            min-height: 100vh;
            overflow-x: hidden;
        }
        .main-content {
            flex: 1 1 0%;
            min-width: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            transition: margin 0.3s cubic-bezier(.4,2,.6,1), width 0.3s cubic-bezier(.4,2,.6,1);
            background: transparent;
            padding: 0;
        }
        .main-floating-card {
            width: 100%;
            max-width: 1200px;
            margin: 2.5rem auto 2.5rem auto;
            background: rgba(255,255,255,0.95);
            border-radius: 32px;
            box-shadow: 0 8px 40px rgba(67,97,238,0.13);
            padding: 3.5rem 3.5vw 2.5rem 3.5vw;
            display: flex;
            flex-direction: column;
            gap: 2.5rem;
            transition: margin 0.3s cubic-bezier(.4,2,.6,1), width 0.3s cubic-bezier(.4,2,.6,1), padding 0.3s;
        }
        .sidebar-collapsed ~ .main-content .main-floating-card {
            max-width: 98vw;
            padding-left: 2vw;
            padding-right: 2vw;
        }
        .section-header {
            display: flex;
            align-items: center;
            gap: 14px;
            font-family: 'Montserrat', sans-serif;
            font-size: 1.45rem;
            font-weight: 700;
            color: #23283e;
            margin-bottom: 1.7rem;
            letter-spacing: 0.5px;
        }
        .section-header i {
            font-size: 2rem;
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
            background: rgba(255,255,255,0.98);
            border-radius: 18px;
            box-shadow: 0 2px 8px rgba(67,97,238,0.06);
            border: 1.5px solid #f0f1f6;
            padding: 2.1rem 1.6rem 1.3rem 1.6rem;
            position: relative;
            transition: box-shadow 0.18s, border 0.18s, transform 0.18s;
            min-height: 160px;
        }
        .dashboard-card:hover, .server-card:hover {
            box-shadow: 0 8px 24px rgba(67,97,238,0.10);
            border: 1.5px solid var(--primary-color);
            transform: translateY(-2px) scale(1.015);
        }
        .dashboard-card .icon, .server-card .icon {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1.1rem;
            background: #f2f6fc;
            color: var(--primary-color);
            box-shadow: none;
        }
        .dashboard-card .card-title, .server-card .card-title {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.05rem;
            font-weight: 700;
            color: #23283e;
            margin-bottom: 0.3rem;
        }
        .dashboard-card .card-number, .server-card .card-number {
            font-size: 2.1rem;
            font-weight: 800;
            color: var(--primary-color);
            margin-bottom: 0.3rem;
        }
        .dashboard-card .badge, .server-card .badge {
            font-size: 0.92rem;
            border-radius: 7px;
            padding: 0.28em 0.7em;
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
        @media (max-width: 991.98px) {
            .main-floating-card {
                padding: 1.2rem 2vw 0.7rem 2vw;
            }
            .dashboard-card, .server-card {
                padding: 1rem 0.6rem 0.6rem 0.6rem;
            }
        }
    </style>
</head>
<body>
    <div class="layout">
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