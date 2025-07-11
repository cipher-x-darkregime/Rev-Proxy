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
        SELECT t.id, t.name, t.status, t.domain, t.directory, t.user_limit,
               COUNT(DISTINCT s.id) as server_count,
               COUNT(DISTINCT su.user_id) as user_count
        FROM tools t
        LEFT JOIN servers s ON t.id = s.tool_id
        LEFT JOIN server_users su ON s.id = su.server_id
        GROUP BY t.id, t.name, t.status, t.domain, t.directory, t.user_limit
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
                        <a class="nav-link active" href="/Rev-Proxy/admin/admin_tools.php">
                            <i class="bi bi-tools"></i>
                            Tool Management
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/Rev-Proxy/admin/admin_servers.php">
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
                <h1>Tool Management</h1>
                <p>Manage system tools and their configurations.</p>
            </div>

            <!-- Tools Management Section -->
            <div class="tools-section">
                <div class="section-header-row">
                    <div class="section-header"><i class="bi bi-tools"></i> Tool Management</div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addToolModal">
                        <i class="bi bi-plus-lg me-1"></i>
                        Add Tool
                    </button>
                </div>
                <div class="server-table-responsive">
                    <table class="table server-table align-middle" id="tools-table">
                        <thead>
                            <tr>
                                <th>Tool Name</th>
                                <th>Status</th>
                                <th>Domain</th>
                                <th>Directory</th>
                                <th>User Limit</th>
                                <th>Servers</th>
                                <th>Users</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tools_data as $tool): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($tool['name']); ?></td>
                                <td>
                                    <span class="badge <?php echo $tool['status'] === 'active' ? 'text-success' : 'text-danger'; ?>">
                                        <?php echo ucfirst(htmlspecialchars($tool['status'])); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($tool['domain']); ?></td>
                                <td><?php echo htmlspecialchars($tool['directory']); ?></td>
                                <td><?php echo htmlspecialchars($tool['user_limit']); ?></td>
                                <td><?php echo htmlspecialchars($tool['server_count']); ?></td>
                                <td><?php echo htmlspecialchars($tool['user_count']); ?></td>
                                <td>
                                    <button class="btn btn-outline-primary btn-sm toggle-status" title="Toggle Status" data-tool-id="<?php echo $tool['id']; ?>" data-current-status="<?php echo $tool['status']; ?>">
                                        <i class="bi bi-toggle-on"></i>
                                    </button>
                                    <button class="btn btn-danger btn-sm delete-tool" title="Delete Tool" data-tool-id="<?php echo $tool['id']; ?>" data-tool-name="<?php echo htmlspecialchars($tool['name']); ?>">
                                        <i class="bi bi-trash"></i>
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
                                    <label for="newToolName" class="form-label">Tool Name</label>
                                    <input type="text" class="form-control" id="newToolName" required>
                                </div>
                                <div class="mb-3">
                                    <label for="newToolStatus" class="form-label">Status</label>
                                    <select class="form-select" id="newToolStatus" required>
                                        <option value="active" selected>Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="newToolDomain" class="form-label">Domain</label>
                                    <input type="text" class="form-control" id="newToolDomain" required>
                                </div>
                                <div class="mb-3">
                                    <label for="newToolDirectory" class="form-label">Directory</label>
                                    <input type="text" class="form-control" id="newToolDirectory" required>
                                </div>
                                <div class="mb-3">
                                    <label for="newToolLimit" class="form-label">User Limit</label>
                                    <input type="number" class="form-control" id="newToolLimit" required min="1">
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
    <script src="assets/admin-scripts.js"></script>
    <script>
        // Add Tool form submission
        document.getElementById('addToolButton').addEventListener('click', function() {
            const toolName = document.getElementById('newToolName').value.trim();
            const toolStatus = document.getElementById('newToolStatus').value;
            const toolDomain = document.getElementById('newToolDomain').value.trim();
            const toolDirectory = document.getElementById('newToolDirectory').value.trim();
            const toolLimit = document.getElementById('newToolLimit').value;

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

        // Toggle tool status functionality
        document.querySelectorAll('.toggle-status').forEach(button => {
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

        // Delete tool functionality
        document.querySelectorAll('.delete-tool').forEach(button => {
            button.addEventListener('click', function() {
                const toolId = this.getAttribute('data-tool-id');
                const toolName = this.getAttribute('data-tool-name');
                
                if (confirm(`Are you sure you want to delete tool "${toolName}"?`)) {
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
    </script>
</body>
</html> 