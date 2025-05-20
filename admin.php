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
            --dark-color: #1a1b1e;
            --light-color: #f8f9fa;
            --sidebar-width: 250px;
            --header-height: 0px;
            --glass-bg: rgba(30, 34, 45, 0.7);
            --glass-blur: 16px;
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
            padding: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: var(--glass-bg);
            box-shadow: 0 0 30px rgba(0,0,0,0.12);
            backdrop-filter: blur(var(--glass-blur));
            border-right: 1.5px solid rgba(255,255,255,0.08);
            transition: width 0.3s;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .sidebar-collapsed {
            width: 70px !important;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,.85);
            padding: 14px 24px;
            margin: 6px 0;
            border-radius: 10px;
            font-size: 1.08rem;
            display: flex;
            align-items: center;
            transition: all 0.2s;
            position: relative;
        }
        .sidebar .nav-link.active, .sidebar .nav-link:hover {
            background: rgba(67,97,238,0.18);
            color: #fff;
            box-shadow: 0 2px 8px rgba(67,97,238,0.08);
        }
        .sidebar .nav-link.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 10px;
            bottom: 10px;
            width: 4px;
            border-radius: 4px;
            background: linear-gradient(180deg, var(--primary-color), var(--secondary-color));
        }
        .sidebar .nav-link i {
            font-size: 1.3rem;
            margin-right: 16px;
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
            background: rgba(255,255,255,0.07);
            border-radius: 12px;
            margin: 18px 12px 12px 12px;
            box-shadow: 0 2px 8px rgba(67,97,238,0.04);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .sidebar-profile .dropdown-toggle {
            color: #fff;
        }
        .sidebar-profile img {
            border: 2px solid var(--primary-color);
            margin-right: 10px;
        }
        .sidebar-profile .status-dot {
            width: 10px;
            height: 10px;
            background: #4cc9f0;
            border-radius: 50%;
            display: inline-block;
            margin-right: 6px;
        }
        .sidebar-profile .dropdown-menu {
            background: #23242b;
            border-radius: 10px;
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
            width: calc(100vw - var(--sidebar-width));
            margin-left: var(--sidebar-width);
            padding: 2.5rem 2.5rem 2.5rem 2.5rem;
            transition: all 0.3s ease;
            min-height: 100vh;
            margin-top: 0;
            background: transparent;
            overflow: visible;
        }
        .card, .stat-card {
            border-radius: 18px;
            box-shadow: 0 4px 24px rgba(67,97,238,0.07);
            border: none;
            animation: fadeInUp 0.7s cubic-bezier(.39,.575,.565,1) both;
        }
        @keyframes fadeInUp {
            0% { opacity: 0; transform: translateY(30px); }
            100% { opacity: 1; transform: translateY(0); }
        }
        .stat-card .card-title, .card-header h5 {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            letter-spacing: 1px;
            font-size: 1.1rem;
        }
        .stat-card .card-text {
            font-size: 2.2rem;
            font-weight: 700;
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
        @media (max-width: 991.98px) {
            .sidebar {
                width: 70px !important;
            }
            .main-content {
                margin-left: 70px;
                width: calc(100vw - 70px);
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
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
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3">
                    <h1 class="h2 mb-0">Dashboard Overview</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-download me-1"></i>
                                Export
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-printer me-1"></i>
                                Print
                            </button>
                        </div>
                        <button type="button" class="btn btn-sm btn-primary">
                            <i class="bi bi-plus-lg me-1"></i>
                            Add New
                        </button>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle me-2"></i>
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-circle me-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="stat-card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="card-title">Total Users</h5>
                                        <h2 class="card-text"><?php echo $total_users; ?></h2>
                                    </div>
                                    <i class="bi bi-people fs-1 opacity-50"></i>
                                </div>
                                <div class="mt-3">
                                    <span class="badge bg-white text-primary">+12% from last month</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="card-title">Active Servers</h5>
                                        <h2 class="card-text">5</h2>
                                    </div>
                                    <i class="bi bi-hdd-stack fs-1 opacity-50"></i>
                                </div>
                                <div class="mt-3">
                                    <span class="badge bg-white text-success">All systems operational</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="card-title">Total Tools</h5>
                                        <h2 class="card-text">12</h2>
                                    </div>
                                    <i class="bi bi-tools fs-1 opacity-50"></i>
                                </div>
                                <div class="mt-3">
                                    <span class="badge bg-white text-info">3 new this week</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Server Management Section -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Server Management</h5>
                        <button class="btn btn-primary btn-sm">
                            <i class="bi bi-plus-lg me-1"></i>
                            Add Server
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
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
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <i class="bi bi-hdd-network text-primary me-2"></i>
                                                Server 1
                                            </div>
                                        </td>
                                        <td><span class="badge bg-success">Active</span></td>
                                        <td>US East</td>
                                        <td>150</td>
                                        <td>99.9%</td>
                                        <td>
                                            <div class="btn-group">
                                                <button class="btn btn-sm btn-primary">
                                                    <i class="bi bi-gear"></i>
                                                </button>
                                                <button class="btn btn-sm btn-info">
                                                    <i class="bi bi-graph-up"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger">
                                                    <i class="bi bi-power"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <i class="bi bi-hdd-network text-primary me-2"></i>
                                                Server 2
                                            </div>
                                        </td>
                                        <td><span class="badge bg-success">Active</span></td>
                                        <td>EU West</td>
                                        <td>120</td>
                                        <td>99.8%</td>
                                        <td>
                                            <div class="btn-group">
                                                <button class="btn btn-sm btn-primary">
                                                    <i class="bi bi-gear"></i>
                                                </button>
                                                <button class="btn btn-sm btn-info">
                                                    <i class="bi bi-graph-up"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger">
                                                    <i class="bi bi-power"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Cookie Management Section -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Cookie Management</h5>
                        <div class="btn-group">
                            <button class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-download me-1"></i>
                                Export
                            </button>
                            <button class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-upload me-1"></i>
                                Import
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="update_cookies">
                            <div class="mb-3">
                                <label for="cookie_data" class="form-label">Cookie Data (JSON format)</label>
                                <textarea class="form-control" id="cookie_data" name="cookie_data" rows="10" required><?php echo htmlspecialchars($latest_cookies['cookie_data'] ?? ''); ?></textarea>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">Last updated: <?php echo $latest_cookies['updated_at'] ?? 'Never'; ?></small>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save me-1"></i>
                                    Update Cookies
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <button class="floating-add-btn" title="Add New">
        <i class="bi bi-plus-lg"></i>
    </button>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 