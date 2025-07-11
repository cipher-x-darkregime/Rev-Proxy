<?php
require_once __DIR__ . '/../core/config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: admin_login.php');
    exit();
}

// Start output buffering to prevent unwanted output in JSON responses
ob_start();

$conn = getDBConnection();

// Handle AJAX requests for CRUD operations FIRST (before any HTML output)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    // Suppress deprecation warnings for AJAX requests to prevent JSON corruption
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    
    $response = array('success' => false, 'message' => '');
    
    try {
        switch ($_POST['ajax_action']) {
            case 'add_server':
                $name = htmlspecialchars(trim($_POST['server_name'] ?? ''), ENT_QUOTES, 'UTF-8');
                $status = htmlspecialchars(trim($_POST['server_status'] ?? ''), ENT_QUOTES, 'UTF-8');
                $tool_id = filter_input(INPUT_POST, 'tool_id', FILTER_VALIDATE_INT);
                $start_date = htmlspecialchars(trim($_POST['start_date'] ?? ''), ENT_QUOTES, 'UTF-8');
                $end_date = htmlspecialchars(trim($_POST['end_date'] ?? ''), ENT_QUOTES, 'UTF-8');
                $max_users = filter_input(INPUT_POST, 'max_users', FILTER_VALIDATE_INT);
                
                if (empty($name) || empty($status) || empty($tool_id) || $max_users === false) {
                    $response['message'] = 'Required fields are missing or invalid';
                } else {
                    $stmt = $conn->prepare('INSERT INTO servers (name, status, tool_id, start_date, end_date, max_users, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
                    $stmt->execute([$name, $status, $tool_id, $start_date, $end_date, $max_users, $_SESSION['user_id']]);
                    
                    logActivity($_SESSION['user_id'], 'Added Server', "Server: $name");
                    $response['success'] = true;
                    $response['message'] = 'Server added successfully';
                }
                break;
                
            case 'delete_server':
                $server_id = filter_input(INPUT_POST, 'server_id', FILTER_VALIDATE_INT);
                if ($server_id) {
                    // Check if server has associated users
                    $stmt = $conn->prepare('SELECT COUNT(*) FROM server_users WHERE server_id = ?');
                    $stmt->execute([$server_id]);
                    $user_count = $stmt->fetchColumn();
                    
                    if ($user_count > 0) {
                        $response['message'] = "Cannot delete server: It has $user_count associated user(s). Please remove users from server first.";
                    } else {
                        // Get server name for logging
                        $stmt = $conn->prepare('SELECT name FROM servers WHERE id = ?');
                        $stmt->execute([$server_id]);
                        $server_name = $stmt->fetchColumn();
                        
                        $stmt = $conn->prepare('DELETE FROM servers WHERE id = ?');
                        $stmt->execute([$server_id]);
                        
                        logActivity($_SESSION['user_id'], 'Deleted Server', "Server: $server_name");
                        $response['success'] = true;
                        $response['message'] = 'Server deleted successfully';
                    }
                } else {
                    $response['message'] = 'Invalid server ID';
                }
                break;
                
            case 'toggle_server_status':
                $server_id = filter_input(INPUT_POST, 'server_id', FILTER_VALIDATE_INT);
                $new_status = htmlspecialchars(trim($_POST['new_status'] ?? ''), ENT_QUOTES, 'UTF-8');
                
                if ($server_id && in_array($new_status, ['active', 'inactive'])) {
                    $stmt = $conn->prepare('UPDATE servers SET status = ? WHERE id = ?');
                    $stmt->execute([$new_status, $server_id]);
                    
                    logActivity($_SESSION['user_id'], 'Updated Server Status', "Server ID: $server_id, Status: $new_status");
                    $response['success'] = true;
                    $response['message'] = 'Server status updated successfully';
                } else {
                    $response['message'] = 'Invalid server ID or status';
                }
                break;
                
            default:
                $response['message'] = 'Invalid action';
        }
    } catch (Exception $e) {
        $response['message'] = 'Error: ' . $e->getMessage();
    }
    
    // Clean any output buffer to prevent unwanted output
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// Main page logic (only if not AJAX request)
if (!isset($_POST['ajax_action'])) {
    // Get servers data
    $servers_query = $conn->query('
        SELECT s.id, s.name, s.status, s.current_users, s.start_date, s.end_date,
               t.name as tool_name
        FROM servers s
        LEFT JOIN tools t ON s.tool_id = t.id
        ORDER BY s.id
    ');
    $servers_data = $servers_query->fetchAll(PDO::FETCH_ASSOC);

    // Get tools for dropdown
    $tools_query = $conn->query('SELECT id, name FROM tools ORDER BY name');
    $tools_data = $tools_query->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Server Management - Admin Dashboard</title>
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

        .filter-bar {
            display: flex;
            gap: 12px;
            margin-bottom: 24px;
            flex-wrap: wrap;
            align-items: center;
            background: white;
            padding: 16px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .filter-bar input,
        .filter-bar select {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 0.875rem;
            transition: border-color 0.2s;
        }
        .filter-bar input:focus,
        .filter-bar select:focus {
            border-color: var(--primary-color);
            outline: none;
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
        .server-table .btn {
            margin: 0 2px;
            border-radius: 8px;
            font-size: 0.875rem;
            padding: 6px 10px;
        }

        .badge {
            font-size: 0.75rem;
            padding: 6px 12px;
            border-radius: 20px;
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
            .filter-bar {
                flex-direction: column;
                align-items: stretch;
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
                        <a class="nav-link" href="admin_dashboard.php">
                            <i class="bi bi-speedometer2"></i>
                            Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_tools.php">
                            <i class="bi bi-tools"></i>
                            Tool Management
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="admin_servers.php">
                            <i class="bi bi-hdd-stack"></i>
                            Server Management
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_users.php">
                            <i class="bi bi-people"></i>
                            User Management
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_logs.php">
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
                <a href="logout.php" class="btn logout-btn">
                    <i class="bi bi-box-arrow-right me-2"></i>
                    Logout
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content" id="mainContent">
            <div class="page-header">
                <h1>Server Management</h1>
                <p>Manage your reverse proxy servers and configurations.</p>
            </div>

            <!-- Server Management Section -->
            <div class="servers-section">
                <div class="section-header-row">
                    <div class="section-header"><i class="bi bi-hdd-stack"></i> Server Management</div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addServerModal">
                        <i class="bi bi-plus-lg me-1"></i>
                        Add Server
                    </button>
                </div>
                <form class="filter-bar" id="servers-filter">
                    <input type="text" placeholder="Server ID" data-filter-col="0">
                    <input type="text" placeholder="Server Name" data-filter-col="1">
                    <select data-filter-col="2">
                        <option value="">Status</option>
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                    <input type="number" placeholder="Users" data-filter-col="3" min="0">
                    <input type="text" placeholder="Tool" data-filter-col="4">
                    <input type="date" placeholder="Start Date" data-filter-col="5">
                    <input type="date" placeholder="End Date" data-filter-col="6">
                    <button type="reset" class="btn btn-outline-secondary">Clear</button>
                </form>
                <div class="server-table-responsive">
                    <table class="table server-table align-middle" id="servers-table">
                        <thead>
                            <tr>
                                <th>Server ID</th>
                                <th>Server Name</th>
                                <th>Status</th>
                                <th>Users</th>
                                <th>Tool</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($servers_data as $server): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($server['id']); ?></td>
                                <td class="server-name"><?php echo htmlspecialchars($server['name']); ?></td>
                                <td>
                                    <span class="badge <?php echo $server['status'] === 'active' ? 'text-success' : 'text-danger'; ?>">
                                        <?php echo ucfirst(htmlspecialchars($server['status'])); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($server['current_users']); ?></td>
                                <td><?php echo htmlspecialchars($server['tool_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($server['start_date'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($server['end_date'] ?? 'N/A'); ?></td>
                                <td>
                                    <button class="btn btn-outline-primary btn-sm" title="Settings"><i class="bi bi-gear"></i></button>
                                    <button class="btn btn-danger btn-sm delete-server" title="Delete Server" data-server-id="<?php echo $server['id']; ?>"><i class="bi bi-trash"></i></button>
                                    <button class="btn btn-secondary btn-sm" title="Logs"><i class="bi bi-clipboard-data"></i></button>
                                    <button class="btn btn-<?php echo $server['status'] === 'active' ? 'success' : 'warning'; ?> btn-sm toggle-server-status" 
                                            title="<?php echo $server['status'] === 'active' ? 'Deactivate' : 'Activate'; ?> Server" 
                                            data-server-id="<?php echo $server['id']; ?>" 
                                            data-current-status="<?php echo $server['status']; ?>">
                                        <i class="bi bi-<?php echo $server['status'] === 'active' ? 'check-circle' : 'x-circle'; ?>"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Add Server Modal -->
            <div class="modal fade" id="addServerModal" tabindex="-1" aria-labelledby="addServerModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="addServerModalLabel">Add Server</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form id="addServerForm">
                                <div class="mb-3">
                                    <label for="serverName" class="form-label">Server Name</label>
                                    <input type="text" class="form-control" id="serverName" required>
                                </div>
                                <div class="mb-3">
                                    <label for="serverStatus" class="form-label">Status</label>
                                    <select class="form-select" id="serverStatus" required>
                                        <option value="active" selected>Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="serverTool" class="form-label">Tool</label>
                                    <select class="form-select" id="serverTool" required>
                                        <option value="">Select a tool</option>
                                        <?php foreach ($tools_data as $tool): ?>
                                        <option value="<?php echo $tool['id']; ?>"><?php echo htmlspecialchars($tool['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="startDate" class="form-label">Start Date</label>
                                    <input type="date" class="form-control" id="startDate">
                                </div>
                                <div class="mb-3">
                                    <label for="endDate" class="form-label">End Date</label>
                                    <input type="date" class="form-control" id="endDate">
                                </div>
                                <div class="mb-3">
                                    <label for="maxUsers" class="form-label">Max Users</label>
                                    <input type="number" class="form-control" id="maxUsers" value="100" min="1" required>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="button" class="btn btn-primary" id="addServerButton">Add Server</button>
                        </div>
                    </div>
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

        // Generic filter function for tables
        function setupTableFilter(formId, tableId) {
            const form = document.getElementById(formId);
            const table = document.getElementById(tableId);
            if (!form || !table) return;
            const inputs = form.querySelectorAll('[data-filter-col]');
            form.addEventListener('input', function() {
                const filterValues = Array.from(inputs).map(input => input.value.toLowerCase().trim());
                Array.from(table.tBodies[0].rows).forEach(row => {
                    let show = true;
                    Array.from(row.cells).forEach((cell, idx) => {
                        const filterVal = filterValues[idx];
                        let cellText = cell.textContent.toLowerCase().trim();
                        if (filterVal) {
                            if (idx === 2) { // Status column
                                if (cellText !== filterVal) {
                                    show = false;
                                }
                            } else if (idx === 3 || idx === 4) { // Users or Tool column
                                if (cellText !== filterVal) {
                                    show = false;
                                }
                            } else {
                                if (!cellText.includes(filterVal)) {
                                    show = false;
                                }
                            }
                        }
                    });
                    row.style.display = show ? '' : 'none';
                });
            });
            form.addEventListener('reset', function() {
                setTimeout(() => {
                    Array.from(table.tBodies[0].rows).forEach(row => row.style.display = '');
                }, 10);
            });
        }
        setupTableFilter('servers-filter', 'servers-table');

        // Universal message popup function
        function showMessage(message, type = 'danger') {
            const popup = document.createElement('div');
            popup.className = `alert alert-${type}`;
            popup.style.position = 'fixed';
            popup.style.top = '20px';
            popup.style.right = '-300px';
            popup.style.zIndex = '9999';
            popup.style.transition = 'right 0.3s ease-in-out';
            popup.style.padding = '15px 20px';
            popup.style.borderRadius = '8px 0 0 8px';
            popup.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.15)';
            popup.style.display = 'flex';
            popup.style.alignItems = 'center';
            popup.style.justifyContent = 'space-between';
            
            // Set background based on type
            if (type === 'success') {
                popup.style.background = 'linear-gradient(135deg, #d4edda, #c3e6cb)';
                popup.style.color = '#155724';
            } else if (type === 'info') {
                popup.style.background = 'linear-gradient(135deg, #d1ecf1, #bee5eb)';
                popup.style.color = '#0c5460';
            } else {
                popup.style.background = 'linear-gradient(135deg, #f8f9fa, #e9ecef)';
                popup.style.color = '#212529';
            }
            
            popup.style.fontWeight = '500';
            popup.style.fontSize = '1rem';
            popup.style.animation = 'fadeIn 0.3s ease-in-out';
            popup.innerHTML = `<span>${message}</span>`;
            document.body.appendChild(popup);
            setTimeout(() => {
                popup.style.right = '0';
            }, 10);
            setTimeout(() => {
                popup.style.right = '-300px';
                setTimeout(() => {
                    popup.remove();
                }, 300);
            }, 3000);
        }

        // Add animation keyframes
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }
        `;
        document.head.appendChild(style);

        // Add Server form submission
        document.getElementById('addServerButton').addEventListener('click', function() {
            const serverName = document.getElementById('serverName').value.trim();
            const serverStatus = document.getElementById('serverStatus').value;
            const serverTool = document.getElementById('serverTool').value;
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            const maxUsers = document.getElementById('maxUsers').value.trim();

            if (!serverName || !serverStatus || !serverTool || !maxUsers) {
                showMessage('Required fields are missing.');
                return;
            }

            // Create form data for AJAX submission
            const formData = new FormData();
            formData.append('ajax_action', 'add_server');
            formData.append('server_name', serverName);
            formData.append('server_status', serverStatus);
            formData.append('tool_id', serverTool);
            formData.append('start_date', startDate);
            formData.append('end_date', endDate);
            formData.append('max_users', maxUsers);

            // Submit via AJAX
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                return response.text();
            })
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        showMessage(data.message, 'success');
                        const addServerModal = bootstrap.Modal.getInstance(document.getElementById('addServerModal'));
                        addServerModal.hide();
                        // Reload the page to show updated data
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        showMessage(data.message, 'danger');
                    }
                } catch (e) {
                    showMessage('Server returned invalid response: ' + text.substring(0, 100), 'danger');
                }
            })
            .catch(error => {
                showMessage('Error submitting form: ' + error.message, 'danger');
            });
        });

        // Reset form when modal is closed
        document.getElementById('addServerModal').addEventListener('hidden.bs.modal', function() {
            document.getElementById('addServerForm').reset();
            // Remove any visible showMessage popup
            const popup = document.querySelector('.alert[style*="fixed"]');
            if (popup) popup.remove();
        });

        // Delete server functionality
        document.querySelectorAll('.delete-server').forEach(button => {
            button.addEventListener('click', function() {
                const serverId = this.getAttribute('data-server-id');
                if (confirm('Are you sure you want to delete this server?')) {
                    const formData = new FormData();
                    formData.append('ajax_action', 'delete_server');
                    formData.append('server_id', serverId);
                    
                    fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.text())
                    .then(text => {
                        try {
                            const data = JSON.parse(text);
                            showMessage(data.message, data.success ? 'success' : 'danger');
                            if (data.success) {
                                setTimeout(() => {
                                    window.location.reload();
                                }, 1000);
                            }
                        } catch (e) {
                            showMessage('Server returned invalid response', 'danger');
                        }
                    })
                    .catch(error => {
                        showMessage('Error deleting server: ' + error.message, 'danger');
                    });
                }
            });
        });

        // Toggle server status functionality
        document.querySelectorAll('.toggle-server-status').forEach(button => {
            button.addEventListener('click', function() {
                const serverId = this.getAttribute('data-server-id');
                const currentStatus = this.getAttribute('data-current-status');
                const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
                
                const formData = new FormData();
                formData.append('ajax_action', 'toggle_server_status');
                formData.append('server_id', serverId);
                formData.append('new_status', newStatus);
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(text => {
                    try {
                        const data = JSON.parse(text);
                        showMessage(data.message, data.success ? 'success' : 'danger');
                        if (data.success) {
                            setTimeout(() => {
                                window.location.reload();
                            }, 1000);
                        }
                    } catch (e) {
                        showMessage('Server returned invalid response', 'danger');
                    }
                })
                .catch(error => {
                    showMessage('Error updating server status: ' + error.message, 'danger');
                });
            });
        });
    </script>
</body>
</html> 