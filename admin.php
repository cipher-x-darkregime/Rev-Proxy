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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
            --header-height: 70px;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f4f6f9;
            padding-top: var(--header-height);
        }

        .sidebar {
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
            padding: 0;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            background: linear-gradient(180deg, var(--dark-color) 0%, #2d2f34 100%);
            width: var(--sidebar-width);
            transition: all 0.3s ease;
            height: 100vh;
        }

        .sidebar-sticky {
            position: relative;
            height: 100%;
            padding-top: 1rem;
            overflow-x: hidden;
            overflow-y: auto;
        }

        .navbar {
            box-shadow: 0 2px 15px rgba(0,0,0,.1);
            background: white !important;
            padding: 0.5rem 2rem;
            height: var(--header-height);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
        }

        .navbar-brand {
            font-weight: 600;
            color: var(--primary-color) !important;
        }

        .main-content {
            width: calc(100vw - var(--sidebar-width));
            margin-left: var(--sidebar-width);
            padding: 2rem;
            transition: all 0.3s ease;
            min-height: 100vh;
            margin-top: 0;
            background: transparent;
            overflow: visible;
        }

        @media (max-width: 991.98px) {
            .sidebar {
                position: fixed;
                top: var(--header-height);
                left: 0;
                width: 100vw;
                height: auto;
                z-index: 1050;
            }
            .main-content {
                margin-left: 0;
                margin-top: calc(var(--header-height) + 60px);
                padding: 1rem;
            }
        }

        .stat-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0,0,0,.05);
            transition: all 0.3s ease;
            overflow: hidden;
            position: relative;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, rgba(255,255,255,.1), rgba(255,255,255,0));
            z-index: 1;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 25px rgba(0,0,0,.1);
        }

        .stat-card .card-body {
            position: relative;
            z-index: 2;
            padding: 1.5rem;
        }

        .stat-card .card-title {
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 1rem;
            opacity: 0.8;
        }

        .stat-card .card-text {
            font-size: 2rem;
            font-weight: 600;
            margin: 0;
        }

        .nav-link {
            color: rgba(255,255,255,.8);
            padding: 12px 20px;
            margin: 4px 0;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .nav-link:hover {
            background-color: rgba(255,255,255,.1);
            color: white;
            transform: translateX(5px);
        }

        .nav-link.active {
            background-color: var(--primary-color);
            color: white;
        }

        .nav-link i {
            margin-right: 10px;
            font-size: 1.1rem;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0,0,0,.05);
            margin-bottom: 1.5rem;
        }

        .card-header {
            background-color: white;
            border-bottom: 1px solid rgba(0,0,0,.05);
            padding: 1.25rem;
            border-radius: 15px 15px 0 0 !important;
        }

        .card-header h5 {
            margin: 0;
            font-weight: 600;
            color: var(--dark-color);
        }

        .table {
            margin: 0;
        }

        .table th {
            font-weight: 600;
            color: var(--dark-color);
            border-top: none;
        }

        .table td {
            vertical-align: middle;
        }

        .badge {
            padding: 0.5em 1em;
            border-radius: 6px;
            font-weight: 500;
        }

        .btn {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-primary:hover {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
            transform: translateY(-2px);
        }

        .btn-danger {
            background-color: var(--warning-color);
            border-color: var(--warning-color);
        }

        .btn-danger:hover {
            background-color: #d90429;
            border-color: #d90429;
            transform: translateY(-2px);
        }

        .form-control {
            border-radius: 8px;
            border: 1px solid rgba(0,0,0,.1);
            padding: 0.75rem 1rem;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(67, 97, 238, 0.15);
        }

        .alert {
            border-radius: 10px;
            border: none;
            padding: 1rem 1.5rem;
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        ::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .stat-card, .card {
            animation: fadeIn 0.5s ease-out;
        }
    </style>
</head>
<body>
    <!-- Sidebar only, no header -->
    <nav class="sidebar">
        <div class="sidebar-sticky d-flex flex-column justify-content-between h-100">
            <div>
                <ul class="nav flex-column mb-4">
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
                <div class="d-grid gap-2 px-3 mb-4">
                    <button type="button" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-download me-1"></i> Export
                    </button>
                    <button type="button" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-printer me-1"></i> Print
                    </button>
                    <button type="button" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus-lg me-1"></i> Add New
                    </button>
                </div>
            </div>
            <div class="px-3 pb-4">
                <div class="dropdown">
                    <button class="btn btn-link text-white d-flex align-items-center w-100" type="button" id="userMenu" data-bs-toggle="dropdown">
                        <img src="https://ui-avatars.com/api/?name=Admin&background=4361ee&color=fff" class="rounded-circle me-2" width="32" height="32">
                        <span class="me-2">Admin</span>
                        <i class="bi bi-chevron-down"></i>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 