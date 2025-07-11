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
            case 'add_tool':
                $name = htmlspecialchars(trim($_POST['tool_name'] ?? ''), ENT_QUOTES, 'UTF-8');
                $status = htmlspecialchars(trim($_POST['tool_status'] ?? ''), ENT_QUOTES, 'UTF-8');
                $domain = htmlspecialchars(trim($_POST['tool_domain'] ?? ''), ENT_QUOTES, 'UTF-8');
                $directory = htmlspecialchars(trim($_POST['tool_directory'] ?? ''), ENT_QUOTES, 'UTF-8');
                $user_limit = filter_input(INPUT_POST, 'tool_limit', FILTER_VALIDATE_INT);
                
                if (empty($name) || empty($status) || empty($domain) || empty($directory) || $user_limit === false) {
                    $response['message'] = 'All fields are required and must be valid';
                } else {
                    try {
                        $stmt = $conn->prepare('INSERT INTO tools (name, status, domain, directory, user_limit, created_by) VALUES (?, ?, ?, ?, ?, ?)');
                        $stmt->execute([$name, $status, $domain, $directory, $user_limit, $_SESSION['user_id']]);
                        
                        logActivity($_SESSION['user_id'], 'Added Tool', "Tool: $name");
                        $response['success'] = true;
                        $response['message'] = 'Tool added successfully';
                    } catch (Exception $e) {
                        $response['message'] = 'Database error: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'delete_tool':
                $tool_id = filter_input(INPUT_POST, 'tool_id', FILTER_VALIDATE_INT);
                $force_delete = isset($_POST['force_delete']) && $_POST['force_delete'] === 'true';
                
                if ($tool_id) {
                    // Check if tool has associated servers
                    $stmt = $conn->prepare('SELECT COUNT(*) FROM servers WHERE tool_id = ?');
                    $stmt->execute([$tool_id]);
                    $server_count = $stmt->fetchColumn();
                    
                    if ($server_count > 0 && !$force_delete) {
                        $response['message'] = "Cannot delete tool: It has $server_count associated server(s). Please delete the servers first or use force delete.";
                    } else {
                        // Get tool name for logging
                        $stmt = $conn->prepare('SELECT name FROM tools WHERE id = ?');
                        $stmt->execute([$tool_id]);
                        $tool_name = $stmt->fetchColumn();
                        
                        if ($force_delete && $server_count > 0) {
                            // Delete associated servers first
                            $stmt = $conn->prepare('DELETE FROM servers WHERE tool_id = ?');
                            $stmt->execute([$tool_id]);
                            logActivity($_SESSION['user_id'], 'Force Deleted Tool', "Tool: $tool_name (with $server_count servers)");
                        } else {
                            logActivity($_SESSION['user_id'], 'Deleted Tool', "Tool: $tool_name");
                        }
                        
                        $stmt = $conn->prepare('DELETE FROM tools WHERE id = ?');
                        $stmt->execute([$tool_id]);
                        
                        $response['success'] = true;
                        $response['message'] = $force_delete ? "Tool and $server_count associated server(s) deleted successfully" : 'Tool deleted successfully';
                    }
                } else {
                    $response['message'] = 'Invalid tool ID';
                }
                break;
                
            case 'toggle_tool_status':
                $tool_id = filter_input(INPUT_POST, 'tool_id', FILTER_VALIDATE_INT);
                $new_status = htmlspecialchars(trim($_POST['new_status'] ?? ''), ENT_QUOTES, 'UTF-8');
                
                if ($tool_id && in_array($new_status, ['active', 'inactive'])) {
                    $stmt = $conn->prepare('UPDATE tools SET status = ? WHERE id = ?');
                    $stmt->execute([$new_status, $tool_id]);
                    
                    logActivity($_SESSION['user_id'], 'Updated Tool Status', "Tool ID: $tool_id, Status: $new_status");
                    $response['success'] = true;
                    $response['message'] = 'Tool status updated successfully';
                } else {
                    $response['message'] = 'Invalid tool ID or status';
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
    // Get tools data
    $tools_query = $conn->query('
        SELECT t.id, t.name, t.status, 
               COUNT(DISTINCT s.id) as server_count,
               COUNT(DISTINCT su.user_id) as user_count
        FROM tools t
        LEFT JOIN servers s ON t.id = s.tool_id
        LEFT JOIN server_users su ON s.id = su.server_id
        GROUP BY t.id, t.name, t.status
        ORDER BY t.id
    ');
    $tools_data = $tools_query->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tool Management - Admin Dashboard</title>
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
                        <a class="nav-link active" href="admin_tools.php">
                            <i class="bi bi-tools"></i>
                            Tool Management
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_servers.php">
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
                <h1>Tool Management</h1>
                <p>Manage your reverse proxy tools and configurations.</p>
            </div>

            <!-- Tool Management Section -->
            <div class="tools-section">
                <div class="section-header-row">
                    <div class="section-header"><i class="bi bi-tools"></i> Tool Management</div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addToolModal">
                        <i class="bi bi-plus-lg me-1"></i>
                        Add Tool
                    </button>
                </div>
                <form class="filter-bar" id="tools-filter">
                    <input type="text" placeholder="Tool ID" data-filter-col="0">
                    <input type="text" placeholder="Tool Name" data-filter-col="1">
                    <select data-filter-col="2" name="status">
                        <option value="">Status</option>
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                    <input type="number" placeholder="Servers" data-filter-col="3" min="0">
                    <input type="number" placeholder="Users" data-filter-col="4" min="0">
                    <button type="reset" class="btn btn-outline-secondary">Clear</button>
                </form>
                <div class="server-table-responsive">
                    <table class="table server-table align-middle" id="tools-table">
                        <thead>
                            <tr>
                                <th>Tool ID</th>
                                <th>Tool Name</th>
                                <th>Status</th>
                                <th>Servers</th>
                                <th>Users</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tools_data as $tool): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($tool['id']); ?></td>
                                <td><?php echo htmlspecialchars($tool['name']); ?></td>
                                <td>
                                    <span class="badge <?php echo $tool['status'] === 'active' ? 'text-success' : 'text-danger'; ?>">
                                        <?php echo ucfirst(htmlspecialchars($tool['status'])); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($tool['server_count']); ?></td>
                                <td><?php echo htmlspecialchars($tool['user_count']); ?></td>
                                <td>
                                    <button class="btn btn-outline-primary btn-sm" title="Configure"><i class="bi bi-gear"></i></button>
                                    <button class="btn btn-danger btn-sm delete-tool" title="Delete Tool" data-tool-id="<?php echo $tool['id']; ?>"><i class="bi bi-trash"></i></button>
                                    <button class="btn btn-secondary btn-sm" title="Check Logs"><i class="bi bi-clipboard-data"></i></button>
                                    <button class="btn btn-info btn-sm" title="Add Server"><i class="bi bi-plus-circle"></i></button>
                                    <button class="btn btn-warning btn-sm" title="Check Users"><i class="bi bi-people"></i></button>
                                    <button class="btn btn-<?php echo $tool['status'] === 'active' ? 'success' : 'warning'; ?> btn-sm toggle-tool-status" 
                                            title="<?php echo $tool['status'] === 'active' ? 'Deactivate' : 'Activate'; ?> Tool" 
                                            data-tool-id="<?php echo $tool['id']; ?>" 
                                            data-current-status="<?php echo $tool['status']; ?>">
                                        <i class="bi bi-<?php echo $tool['status'] === 'active' ? 'check-circle' : 'x-circle'; ?>"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Add Tool Modal -->
            <div class="modal fade" id="addToolModal" tabindex="-1" aria-labelledby="addToolModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="addToolModalLabel">Add Tool</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form id="addToolForm">
                                <div class="mb-3">
                                    <label for="toolName" class="form-label">Tool Name</label>
                                    <input type="text" class="form-control" id="toolName" required>
                                </div>
                                <div class="mb-3">
                                    <label for="toolStatus" class="form-label">Status</label>
                                    <select class="form-select" id="toolStatus" required>
                                        <option value="active" selected>Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="toolDomain" class="form-label">Domain (Sub-domain)</label>
                                    <input type="text" class="form-control" id="toolDomain" required>
                                </div>
                                <div class="mb-3">
                                    <label for="toolDirectory" class="form-label">Directory for Domain</label>
                                    <input type="text" class="form-control" id="toolDirectory" required>
                                </div>
                                <div class="mb-3">
                                    <label for="toolLimit" class="form-label">Limit per User</label>
                                    <input type="number" class="form-control" id="toolLimit" value="5" min="1" required>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="button" class="btn btn-primary" id="addToolButton">Add Tool</button>
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
        setupTableFilter('tools-filter', 'tools-table');

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

        // Add Tool form validation and submission
        document.getElementById('addToolButton').addEventListener('click', function() {
            const toolName = document.getElementById('toolName').value.trim();
            const toolStatus = document.getElementById('toolStatus').value;
            const toolDomain = document.getElementById('toolDomain').value.trim();
            const toolDirectory = document.getElementById('toolDirectory').value.trim();
            const toolLimit = document.getElementById('toolLimit').value.trim();

            if (!toolName || !toolStatus || !toolDomain || !toolDirectory || !toolLimit) {
                showMessage('All fields are required.');
                return;
            }

            // Create form data for AJAX submission
            const formData = new FormData();
            formData.append('ajax_action', 'add_tool');
            formData.append('tool_name', toolName);
            formData.append('tool_status', toolStatus);
            formData.append('tool_domain', toolDomain);
            formData.append('tool_directory', toolDirectory);
            formData.append('tool_limit', toolLimit);

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
                        const addToolModal = bootstrap.Modal.getInstance(document.getElementById('addToolModal'));
                        addToolModal.hide();
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
        document.getElementById('addToolModal').addEventListener('hidden.bs.modal', function() {
            document.getElementById('addToolForm').reset();
            // Remove any visible showMessage popup
            const popup = document.querySelector('.alert[style*="fixed"]');
            if (popup) popup.remove();
        });

        // Delete tool functionality
        document.querySelectorAll('.delete-tool').forEach(button => {
            button.addEventListener('click', function() {
                const toolId = this.getAttribute('data-tool-id');
                if (confirm('Are you sure you want to delete this tool?')) {
                    const formData = new FormData();
                    formData.append('ajax_action', 'delete_tool');
                    formData.append('tool_id', toolId);
                    
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
                        showMessage('Error deleting tool: ' + error.message, 'danger');
                    });
                }
            });
        });

        // Toggle tool status functionality
        document.querySelectorAll('.toggle-tool-status').forEach(button => {
            button.addEventListener('click', function() {
                const toolId = this.getAttribute('data-tool-id');
                const currentStatus = this.getAttribute('data-current-status');
                const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
                
                const formData = new FormData();
                formData.append('ajax_action', 'toggle_tool_status');
                formData.append('tool_id', toolId);
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
                    showMessage('Error updating tool status: ' + error.message, 'danger');
                });
            });
        });
    </script>
</body>
</html> 