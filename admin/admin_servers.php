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
                        <a class="nav-link" href="/Rev-Proxy/admin/admin_dashboard.php">
                            <i class="bi bi-speedometer2"></i>
                            Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/Rev-Proxy/admin/admin_tools.php">
                            <i class="bi bi-tools"></i>
                            Tool Management
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="/Rev-Proxy/admin/admin_servers.php">
                            <i class="bi bi-hdd-stack"></i>
                            Server Management
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/Rev-Proxy/admin/admin_users.php">
                            <i class="bi bi-people"></i>
                            User Management
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/Rev-Proxy/admin/admin_logs.php">
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
                <a href="/Rev-Proxy/users/logout.php" class="btn logout-btn">
                    <i class="bi bi-box-arrow-right me-2"></i>
                    Logout
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content" id="mainContent">
            <div class="page-header">
                <h1>Server Management</h1>
                <p>Manage proxy servers and their configurations.</p>
            </div>

            <!-- Servers Management Section -->
            <div class="servers-section">
                <div class="section-header-row">
                    <div class="section-header"><i class="bi bi-hdd-stack"></i> Server Management</div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addServerModal">
                        <i class="bi bi-plus-lg me-1"></i>
                        Add Server
                    </button>
                </div>
                <div class="server-table-responsive">
                    <table class="table server-table align-middle">
                        <thead>
                            <tr>
                                <th>Server Name</th>
                                <th>Tool</th>
                                <th>Status</th>
                                <th>IP Address</th>
                                <th>Port</th>
                                <th>Users</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($servers_data as $server): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($server['name']); ?></td>
                                <td><?php echo htmlspecialchars($server['tool_name']); ?></td>
                                <td>
                                    <span class="badge <?php echo $server['status'] === 'active' ? 'text-success' : 'text-danger'; ?>">
                                        <?php echo ucfirst(htmlspecialchars($server['status'])); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($server['ip_address']); ?></td>
                                <td><?php echo htmlspecialchars($server['port']); ?></td>
                                <td><?php echo htmlspecialchars($server['user_count']); ?></td>
                                <td>
                                    <button class="btn btn-outline-primary btn-sm toggle-status" title="Toggle Status" data-server-id="<?php echo $server['id']; ?>" data-current-status="<?php echo $server['status']; ?>">
                                        <i class="bi bi-toggle-on"></i>
                                    </button>
                                    <button class="btn btn-danger btn-sm delete-server" title="Delete Server" data-server-id="<?php echo $server['id']; ?>" data-server-name="<?php echo htmlspecialchars($server['name']); ?>">
                                        <i class="bi bi-trash"></i>
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
                                    <label for="newServerName" class="form-label">Server Name</label>
                                    <input type="text" class="form-control" id="newServerName" required>
                                </div>
                                <div class="mb-3">
                                    <label for="newServerTool" class="form-label">Tool</label>
                                    <select class="form-select" id="newServerTool" required>
                                        <option value="">Select Tool</option>
                                        <?php foreach ($tools_data as $tool): ?>
                                        <option value="<?php echo $tool['id']; ?>"><?php echo htmlspecialchars($tool['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="newServerStatus" class="form-label">Status</label>
                                    <select class="form-select" id="newServerStatus" required>
                                        <option value="active" selected>Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="newServerIP" class="form-label">IP Address</label>
                                    <input type="text" class="form-control" id="newServerIP" required>
                                </div>
                                <div class="mb-3">
                                    <label for="newServerPort" class="form-label">Port</label>
                                    <input type="number" class="form-control" id="newServerPort" required min="1" max="65535">
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
    <script src="assets/admin-scripts.js"></script>
    <script>
        // Add Server form submission
        document.getElementById('addServerButton').addEventListener('click', function() {
            const serverName = document.getElementById('newServerName').value.trim();
            const serverTool = document.getElementById('newServerTool').value;
            const serverStatus = document.getElementById('newServerStatus').value;
            const serverIP = document.getElementById('newServerIP').value.trim();
            const serverPort = document.getElementById('newServerPort').value;

            if (!serverName || !serverTool || !serverStatus || !serverIP || !serverPort) {
                showMessage('All fields are required.');
                return;
            }

            // Create form data for AJAX submission
            const formData = new FormData();
            formData.append('ajax_action', 'add_server');
            formData.append('server_name', serverName);
            formData.append('server_tool', serverTool);
            formData.append('server_status', serverStatus);
            formData.append('server_ip', serverIP);
            formData.append('server_port', serverPort);

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

        // Toggle server status functionality
        document.querySelectorAll('.toggle-status').forEach(button => {
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

        // Delete server functionality
        document.querySelectorAll('.delete-server').forEach(button => {
            button.addEventListener('click', function() {
                const serverId = this.getAttribute('data-server-id');
                const serverName = this.getAttribute('data-server-name');
                
                if (confirm(`Are you sure you want to delete server "${serverName}"?`)) {
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
    </script>
</body>
</html> 