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
            case 'add_user':
                $username = htmlspecialchars(trim($_POST['username'] ?? ''), ENT_QUOTES, 'UTF-8');
                $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
                $password = $_POST['password'];
                $user_type = htmlspecialchars(trim($_POST['user_type'] ?? ''), ENT_QUOTES, 'UTF-8');
                
                if (empty($username) || empty($email) || empty($password) || empty($user_type)) {
                    $response['message'] = 'All fields are required';
                } else {
                    // Check if username or email already exists
                    $stmt = $conn->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
                    $stmt->execute([$username, $email]);
                    if ($stmt->fetch()) {
                        $response['message'] = 'Username or email already exists';
                    } else {
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $conn->prepare('INSERT INTO users (username, email, password, user_type) VALUES (?, ?, ?, ?)');
                        $stmt->execute([$username, $email, $hashed_password, $user_type]);
                        
                        logActivity($_SESSION['user_id'], 'Added User', "User: $username");
                        $response['success'] = true;
                        $response['message'] = 'User added successfully';
                    }
                }
                break;
                
            case 'delete_user':
                $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
                if ($user_id && $user_id != $_SESSION['user_id']) { // Prevent self-deletion
                    // Check if user is associated with any servers
                    $stmt = $conn->prepare('SELECT COUNT(*) FROM server_users WHERE user_id = ?');
                    $stmt->execute([$user_id]);
                    $server_count = $stmt->fetchColumn();
                    
                    if ($server_count > 0) {
                        $response['message'] = "Cannot delete user: User is associated with $server_count server(s). Please remove user from servers first.";
                    } else {
                        // Get user info for logging
                        $stmt = $conn->prepare('SELECT username FROM users WHERE id = ?');
                        $stmt->execute([$user_id]);
                        $username = $stmt->fetchColumn();
                        
                        $stmt = $conn->prepare('DELETE FROM users WHERE id = ?');
                        $stmt->execute([$user_id]);
                        
                        logActivity($_SESSION['user_id'], 'Deleted User', "User: $username");
                        $response['success'] = true;
                        $response['message'] = 'User deleted successfully';
                    }
                } else {
                    $response['message'] = 'Invalid user ID or cannot delete yourself';
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
    // Get users data
    try {
        $users_query = $conn->query('
            SELECT id, username, email, user_type, status, last_login
            FROM users
            ORDER BY id
        ');
        $users_data = $users_query->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // If status column doesn't exist, get users without status
        if (strpos($e->getMessage(), 'status') !== false) {
            $users_query = $conn->query('
                SELECT id, username, email, user_type, last_login
                FROM users
                ORDER BY id
            ');
            $users_data = $users_query->fetchAll(PDO::FETCH_ASSOC);
            // Add default status for each user
            foreach ($users_data as &$user) {
                $user['status'] = 'active';
            }
        } else {
            throw $e;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Admin Dashboard</title>
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
                        <a class="nav-link active" href="/Rev-Proxy/users">
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
            <div class="page-header">
                <h1>User Management</h1>
                <p>Manage system users and their permissions.</p>
            </div>

            <!-- Users Management Section -->
            <div class="users-section">
                <div class="section-header-row">
                    <div class="section-header"><i class="bi bi-people"></i> User Management</div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <i class="bi bi-plus-lg me-1"></i>
                        Add User
                    </button>
                </div>
                <div class="server-table-responsive">
                    <table class="table server-table align-middle">
                        <thead>
                            <tr>
                                <th>User Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users_data as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo ucfirst(htmlspecialchars($user['user_type'])); ?></td>
                                <td>
                                    <span class="badge <?php echo $user['status'] === 'active' ? 'text-success' : 'text-danger'; ?>">
                                        <?php echo ucfirst(htmlspecialchars($user['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-outline-primary btn-sm"><i class="bi bi-gear"></i></button>
                                    <button class="btn btn-danger btn-sm delete-user" title="Delete User" data-user-id="<?php echo $user['id'] ?? ''; ?>" data-username="<?php echo htmlspecialchars($user['username']); ?>"><i class="bi bi-trash"></i></button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Add User Modal -->
            <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="addUserModalLabel">Add User</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form id="addUserForm">
                                <div class="mb-3">
                                    <label for="newUsername" class="form-label">Username</label>
                                    <input type="text" class="form-control" id="newUsername" required>
                                </div>
                                <div class="mb-3">
                                    <label for="newEmail" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="newEmail" required>
                                </div>
                                <div class="mb-3">
                                    <label for="newPassword" class="form-label">Password</label>
                                    <input type="password" class="form-control" id="newPassword" required>
                                </div>
                                <div class="mb-3">
                                    <label for="newUserType" class="form-label">User Type</label>
                                    <select class="form-select" id="newUserType" required>
                                        <option value="user" selected>User</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="button" class="btn btn-primary" id="addUserButton">Add User</button>
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

        // Add User form submission
        document.getElementById('addUserButton').addEventListener('click', function() {
            const username = document.getElementById('newUsername').value.trim();
            const email = document.getElementById('newEmail').value.trim();
            const password = document.getElementById('newPassword').value;
            const userType = document.getElementById('newUserType').value;

            if (!username || !email || !password || !userType) {
                showMessage('All fields are required.');
                return;
            }

            // Create form data for AJAX submission
            const formData = new FormData();
            formData.append('ajax_action', 'add_user');
            formData.append('username', username);
            formData.append('email', email);
            formData.append('password', password);
            formData.append('user_type', userType);

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
                        const addUserModal = bootstrap.Modal.getInstance(document.getElementById('addUserModal'));
                        addUserModal.hide();
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
        document.getElementById('addUserModal').addEventListener('hidden.bs.modal', function() {
            document.getElementById('addUserForm').reset();
            // Remove any visible showMessage popup
            const popup = document.querySelector('.alert[style*="fixed"]');
            if (popup) popup.remove();
        });

        // Delete user functionality
        document.querySelectorAll('.delete-user').forEach(button => {
            button.addEventListener('click', function() {
                const userId = this.getAttribute('data-user-id');
                const username = this.getAttribute('data-username');
                
                if (confirm(`Are you sure you want to delete user "${username}"?`)) {
                    const formData = new FormData();
                    formData.append('ajax_action', 'delete_user');
                    formData.append('user_id', userId);
                    
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
                        showMessage('Error deleting user: ' + error.message, 'danger');
                    });
                }
            });
        });
    </script>
</body>
</html> 