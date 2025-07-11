<?php
// Start output buffering to prevent unwanted output in JSON responses
ob_start();

require_once 'config.php';

// Suppress deprecation warnings for AJAX requests to prevent JSON corruption
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
}

// Handle AJAX requests for CRUD operations FIRST (before any HTML output)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    // Check if user is logged in and is admin for AJAX requests
    if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
        exit();
    }
    
    $conn = getDBConnection();
    $response = array('success' => false, 'message' => '');
    
    // Debug logging
    error_log('AJAX Action: ' . $_POST['ajax_action']);
    error_log('POST Data: ' . print_r($_POST, true));
    
    try {
        switch ($_POST['ajax_action']) {
            case 'add_tool':
                error_log('Processing add_tool case');
                $name = htmlspecialchars(trim($_POST['tool_name'] ?? ''), ENT_QUOTES, 'UTF-8');
                $status = htmlspecialchars(trim($_POST['tool_status'] ?? ''), ENT_QUOTES, 'UTF-8');
                $domain = htmlspecialchars(trim($_POST['tool_domain'] ?? ''), ENT_QUOTES, 'UTF-8');
                $directory = htmlspecialchars(trim($_POST['tool_directory'] ?? ''), ENT_QUOTES, 'UTF-8');
                $user_limit = filter_input(INPUT_POST, 'tool_limit', FILTER_VALIDATE_INT);
                
                error_log('Filtered values: ' . print_r([
                    'name' => $name,
                    'status' => $status,
                    'domain' => $domain,
                    'directory' => $directory,
                    'user_limit' => $user_limit
                ], true));
                
                if (empty($name) || empty($status) || empty($domain) || empty($directory) || $user_limit === false) {
                    $response['message'] = 'All fields are required and must be valid';
                    error_log('Validation failed: ' . $response['message']);
                } else {
                    try {
                        $stmt = $conn->prepare('INSERT INTO tools (name, status, domain, directory, user_limit, created_by) VALUES (?, ?, ?, ?, ?, ?)');
                        $stmt->execute([$name, $status, $domain, $directory, $user_limit, $_SESSION['user_id']]);
                        
                        logActivity($_SESSION['user_id'], 'Added Tool', "Tool: $name");
                        $response['success'] = true;
                        $response['message'] = 'Tool added successfully';
                        error_log('Tool added successfully');
                    } catch (Exception $e) {
                        error_log('Database error: ' . $e->getMessage());
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
                        logActivity($_SESSION['user_id'], 'Updated Cookies', 'Cookie data updated');
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
$total_tools = $conn->query('SELECT COUNT(*) FROM tools')->fetchColumn();
$total_servers = $conn->query('SELECT COUNT(*) FROM servers')->fetchColumn();

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

// Get servers data
$servers_query = $conn->query('
    SELECT s.id, s.name, s.status, s.current_users, s.start_date, s.end_date,
           t.name as tool_name
    FROM servers s
    LEFT JOIN tools t ON s.tool_id = t.id
    ORDER BY s.id
');
$servers_data = $servers_query->fetchAll(PDO::FETCH_ASSOC);

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

// Get activity logs
$logs_query = $conn->query('
    SELECT al.created_at, u.username, al.action, al.details
    FROM activity_logs al
    LEFT JOIN users u ON al.user_id = u.id
    ORDER BY al.created_at DESC
    LIMIT 50
');
$logs_data = $logs_query->fetchAll(PDO::FETCH_ASSOC);
} // End of main page logic
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
            width: calc(100vw - var(--sidebar-width));
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            transition: width 0.35s cubic-bezier(.4,2,.6,1), margin-left 0.35s cubic-bezier(.4,2,.6,1), padding 0.25s;
            padding: 0;
            display: flex;
            flex-direction: column;
            align-items: stretch;
        }
        .main-content.sidebar-collapsed-main {
            width: calc(100vw - var(--sidebar-collapsed-width));
            margin-left: var(--sidebar-collapsed-width);
        }
        .section-block {
            background: #f8fafc;
            border-radius: 24px;
            margin-bottom: 2.8rem;
            padding: 2.2rem 2.2rem 1.2rem 2.2rem;
            box-shadow: 0 2px 8px rgba(67,97,238,0.04);
        }
        .section-header {
            display: flex;
            align-items: center;
            gap: 16px;
            font-family: 'Montserrat', sans-serif;
            font-size: 1.7rem;
            font-weight: 800;
            color: #23283e;
            margin-bottom: 2.2rem;
            letter-spacing: 0.5px;
        }
        .section-header i {
            font-size: 2.2rem;
            color: var(--primary-color);
        }
        .card, .stat-card {
            border-radius: 22px;
            box-shadow: 0 6px 32px rgba(67,97,238,0.10);
            border: none;
            background: #fff;
            margin-bottom: 2.2rem;
            transition: box-shadow 0.2s, transform 0.2s;
        }
        .card:hover, .stat-card:hover {
            box-shadow: 0 12px 40px rgba(67,97,238,0.16);
            transform: translateY(-4px) scale(1.01);
        }
        .stat-card .card-title, .card-header h5 {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            letter-spacing: 1px;
            font-size: 1.1rem;
            color: #23283e;
        }
        .stat-card .card-text {
            font-size: 2.4rem;
            font-weight: 700;
            color: var(--primary-color);
        }
        .stat-card .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, #e9eafc 0%, #f4f6f9 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: var(--primary-color);
            margin-left: auto;
        }
        .table {
            background: #fff;
            border-radius: 18px;
            overflow: hidden;
            margin-bottom: 0;
        }
        .table th, .table td {
            border: none;
            padding: 1.1rem 1.2rem;
            vertical-align: middle;
        }
        .table th {
            background: #f6f8fb;
            color: #23283e;
            font-weight: 700;
            font-size: 1.05rem;
        }
        .table-striped > tbody > tr:nth-of-type(odd) {
            background-color: #f6f8fb;
        }
        .table-hover tbody tr:hover {
            background: #e9eafc;
        }
        .table thead tr {
            border-radius: 18px 18px 0 0;
        }
        .table-responsive {
            border-radius: 18px;
            overflow: hidden;
        }
        .btn, .btn-primary, .btn-danger, .btn-info, .btn-outline-primary {
            border-radius: 999px !important;
            font-weight: 600;
            padding: 0.6rem 1.4rem;
            font-size: 1.05rem;
            box-shadow: 0 2px 8px rgba(67,97,238,0.07);
            transition: background 0.18s, box-shadow 0.18s, color 0.18s;
        }
        .btn-primary {
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            border: none;
        }
        .btn-primary:hover {
            background: linear-gradient(90deg, var(--secondary-color), var(--primary-color));
            color: #fff;
        }
        .btn-danger {
            background: var(--warning-color);
            border: none;
        }
        .btn-danger:hover {
            background: #d90429;
            color: #fff;
        }
        .btn-info {
            background: var(--info-color);
            border: none;
            color: #fff;
        }
        .btn-info:hover {
            background: #2274e0;
            color: #fff;
        }
        .btn-outline-primary {
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
            background: #fff;
        }
        .btn-outline-primary:hover {
            background: var(--primary-color);
            color: #fff;
        }
        .alert {
            border-radius: 16px;
            border: none;
            padding: 1.1rem 1.6rem;
            font-size: 1.08rem;
            box-shadow: 0 2px 12px rgba(67,97,238,0.07);
            background: #f6f8fb;
            color: #23283e;
        }
        .alert-success {
            background: #e6f9f0;
            color: #1b7c4a;
        }
        .alert-danger {
            background: #ffeaea;
            color: #c0392b;
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
                width: var(--sidebar-collapsed-width) !important;
            }
            .main-content {
                margin-left: var(--sidebar-collapsed-width);
                width: calc(100vw - var(--sidebar-collapsed-width));
                padding: 1.2rem;
            }
        }
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 2.2rem;
            margin-bottom: 2.5rem;
        }
        .dashboard-card {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: flex-start;
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 2px 8px rgba(67,97,238,0.06);
            border: 1.5px solid #f0f1f6;
            padding: 2.1rem 1.6rem 1.3rem 1.6rem;
            position: relative;
            transition: box-shadow 0.18s, border 0.18s, transform 0.18s;
            min-height: 160px;
        }
        .dashboard-card:hover {
            box-shadow: 0 8px 24px rgba(67,97,238,0.10);
            border: 1.5px solid var(--primary-color);
            transform: translateY(-2px) scale(1.015);
        }
        .dashboard-card .icon {
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
        .dashboard-card .card-title {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.05rem;
            font-weight: 700;
            color: #23283e;
            margin-bottom: 0.3rem;
        }
        .dashboard-card .card-number {
            font-size: 2.1rem;
            font-weight: 800;
            color: var(--primary-color);
            margin-bottom: 0.3rem;
        }
        .dashboard-card .badge {
            font-size: 0.92rem;
            border-radius: 7px;
            padding: 0.28em 0.7em;
            font-weight: 500;
            background: #f2f6fc;
            color: var(--primary-color);
            border: none;
        }
        .dashboard-card .badge.text-success {
            color: #1b7c4a;
            background: #e6f9f0;
        }
        .dashboard-card .badge.text-info {
            color: #2274e0;
            background: #e9f4ff;
        }
        /* Tools Management Section */
        .tools-table th, .tools-table td {
            border: none;
            padding: 1.05rem 1.1rem;
            vertical-align: middle;
        }
        .tools-table th {
            background: #f6f8fb;
            color: #23283e;
            font-weight: 700;
            font-size: 1.03rem;
        }
        .tools-table tbody tr {
            background: #fff;
            border-radius: 12px;
        }
        .tools-table tbody tr:hover {
            background: #f2f6fc;
        }
        .tools-table .badge {
            font-size: 0.92rem;
            border-radius: 7px;
            padding: 0.28em 0.7em;
            font-weight: 500;
            background: #f2f6fc;
            color: var(--primary-color);
            border: none;
        }
        .tools-table .badge.text-success {
            color: #1b7c4a;
            background: #e6f9f0;
        }
        .tools-table .badge.text-danger {
            color: #c0392b;
            background: #ffeaea;
        }
        .tools-table .btn {
            border-radius: 999px !important;
            font-size: 0.98rem;
            padding: 0.4rem 1.1rem;
        }
        @media (max-width: 767.98px) {
            .dashboard-card {
                padding: 1rem 0.6rem 0.6rem 0.6rem;
            }
        }
        /* Management Section Cards */
        .mgmt-card {
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 2px 8px rgba(67,97,238,0.06);
            border: 1.5px solid #f0f1f6;
            margin-bottom: 0;
            padding: 0;
            overflow: hidden;
            transition: box-shadow 0.18s, border 0.18s, transform 0.18s;
        }
        .mgmt-card:hover {
            box-shadow: 0 8px 24px rgba(67,97,238,0.10);
            border: 1.5px solid var(--primary-color);
            transform: translateY(-2px) scale(1.01);
        }
        .mgmt-card .card-header {
            background: transparent;
            border-bottom: none;
            border-radius: 18px 18px 0 0 !important;
            padding: 1.1rem 2rem 0.5rem 2rem;
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            font-size: 1.08rem;
            color: #23283e;
            display: flex;
            align-items: center;
            justify-content: flex-end;
        }
        .mgmt-card .card-header .btn, .mgmt-card .card-header .btn-group .btn {
            font-size: 0.98rem;
            padding: 0.4rem 1.1rem;
        }
        .mgmt-card .card-body {
            padding: 1.3rem 2rem 1.5rem 2rem;
        }
        .mgmt-table th, .mgmt-table td {
            border: none;
            padding: 1.05rem 1.1rem;
            vertical-align: middle;
        }
        .mgmt-table th {
            background: #f6f8fb;
            color: #23283e;
            font-weight: 700;
            font-size: 1.03rem;
        }
        .mgmt-table tbody tr {
            background: #fff;
            border-radius: 12px;
        }
        .mgmt-table tbody tr:hover {
            background: #f2f6fc;
        }
        .mgmt-table .badge {
            font-size: 0.92rem;
            border-radius: 7px;
            padding: 0.28em 0.7em;
            font-weight: 500;
            background: #f2f6fc;
            color: var(--primary-color);
            border: none;
        }
        .mgmt-table .badge.text-success {
            color: #1b7c4a;
            background: #e6f9f0;
        }
        .mgmt-table .badge.text-danger {
            color: #c0392b;
            background: #ffeaea;
        }
        .mgmt-table .btn {
            border-radius: 999px !important;
            font-size: 0.98rem;
            padding: 0.4rem 1.1rem;
        }
        @media (max-width: 767.98px) {
            .section-block {
                padding: 1.1rem 0.7rem 0.7rem 0.7rem;
            }
            .mgmt-card .card-header, .mgmt-card .card-body {
                padding: 1rem 0.7rem 1rem 0.7rem;
            }
        }
        .server-section {
            background: #f8fafc;
            border-radius: 28px;
            margin-bottom: 2.8rem;
            padding: 2.5rem 2.5rem 1.5rem 2.5rem;
            box-shadow: 0 2px 8px rgba(67,97,238,0.04);
        }
        .server-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 2.2rem;
        }
        .server-header .section-title {
            display: flex;
            align-items: center;
            gap: 16px;
            font-family: 'Montserrat', sans-serif;
            font-size: 1.7rem;
            font-weight: 800;
            color: #23283e;
            letter-spacing: 0.5px;
        }
        .server-header .section-title i {
            font-size: 2.2rem;
            color: var(--primary-color);
        }
        .server-header .btn {
            font-size: 1.08rem;
            padding: 0.7rem 2.1rem;
            border-radius: 999px;
            font-weight: 700;
        }
        .server-card {
            background: #fff;
            border-radius: 22px;
            box-shadow: 0 4px 18px rgba(67,97,238,0.08);
            border: 1.5px solid #f0f1f6;
            padding: 0;
            overflow: hidden;
            transition: box-shadow 0.18s, border 0.18s, transform 0.18s;
        }
        .server-card:hover {
            box-shadow: 0 8px 32px rgba(67,97,238,0.13);
            border: 1.5px solid var(--primary-color);
            transform: translateY(-2px) scale(1.01);
        }
        .server-card .card-body {
            padding: 2.2rem 2.2rem 2rem 2.2rem;
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
            .server-section {
                padding: 1.2rem 0.7rem 0.7rem 0.7rem;
            }
            .server-card .card-body {
                padding: 1.2rem 0.7rem 1rem 0.7rem;
            }
        }
        .main-floating-card {
            background: rgba(255,255,255,0.85);
            border-radius: 36px;
            box-shadow: 0 12px 48px 0 rgba(67,97,238,0.13), 0 1.5px 8px 0 rgba(67,97,238,0.07);
            backdrop-filter: blur(8px);
            padding: 3.5rem 3.5rem 2.5rem 3.5rem;
            margin: 3.2rem 2.5vw 2.5rem 2.5vw;
            width: auto;
            max-width: 100%;
            transition: box-shadow 0.25s, border-radius 0.25s, padding 0.25s, margin 0.35s, width 0.35s;
        }
        .section-header {
            display: flex;
            align-items: center;
            gap: 16px;
            font-family: 'Montserrat', sans-serif;
            font-size: 1.7rem;
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
            background: #fff;
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
        @media (max-width: 1200px) {
            .main-floating-card {
                padding: 2.2rem 1.2rem 1.2rem 1.2rem;
                border-radius: 24px;
                margin: 2.2rem 1vw 1.2rem 1vw;
            }
            .section-header {
                font-size: 1.25rem;
                gap: 10px;
            }
        }
        @media (max-width: 991.98px) {
            .main-content {
                width: calc(100vw - var(--sidebar-collapsed-width));
                margin-left: var(--sidebar-collapsed-width);
            }
            .main-floating-card {
                padding: 1.2rem 0.5rem 0.7rem 0.5rem;
                border-radius: 16px;
                margin: 1.2rem 0.5vw 0.7rem 0.5vw;
            }
        }
        @media (max-width: 767.98px) {
            .main-content {
                width: 100vw;
                margin-left: 0;
            }
            .main-floating-card {
                padding: 0.5rem 0.1rem 0.5rem 0.1rem;
                border-radius: 10px;
                margin: 0.5rem 0.1vw 0.5rem 0.1vw;
            }
            .section-header {
                font-size: 1.05rem;
                gap: 7px;
            }
        }
        .section-header-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 2.2rem;
            gap: 1rem;
        }
        .section-header-row .section-header {
            margin-bottom: 0;
        }
        .section-header-row .btn {
            font-size: 1.08rem;
            padding: 0.7rem 2.1rem;
            border-radius: 999px;
            font-weight: 700;
        }
        .server-table-responsive {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        .server-table {
            min-width: 700px;
        }
        @media (max-width: 767.98px) {
            .server-table {
                min-width: 520px;
                font-size: 0.95rem;
            }
            .server-table th, .server-table td {
                padding: 0.7rem 0.5rem;
            }
        }
        .dashboard-management {
            margin-top: 2.5rem;
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
        }
        .dashboard-management .btn {
            font-size: 1.08rem;
            padding: 1.1rem 2.2rem;
            border-radius: 18px;
            font-weight: 700;
            box-shadow: 0 2px 8px rgba(67,97,238,0.07);
            display: flex;
            align-items: center;
            gap: 0.7rem;
        }
        .dashboard-management .btn i {
            font-size: 1.3rem;
        }
        .management-section {
            display: none;
            margin-top: 2.5rem;
        }
        .management-section.active {
            display: block;
        }
        .dashboard-section, .tools-section, .servers-section, .users-section {
            display: none;
        }
        .dashboard-section.active, .tools-section.active, .servers-section.active, .users-section.active {
            display: block;
        }
        .sidebar-logout {
            margin-top: auto;
            padding: 1.2rem 0 1.2rem 0;
            display: flex;
            justify-content: center;
        }
        .sidebar-logout .logout-link {
            display: flex;
            align-items: center;
            gap: 12px;
            width: 90%;
            padding: 0.9rem 1.2rem;
            border-radius: 10px;
            background: rgba(255,0,0,0.08);
            color: #c0392b;
            font-weight: bold;
            font-size: 1.08rem;
            text-decoration: none;
            transition: background 0.18s, color 0.18s;
            border: none;
            outline: none;
        }
        .sidebar-logout .logout-link:hover {
            background: rgba(255,0,0,0.16);
            color: #a83232;
        }
        .sidebar-logout .logout-link i {
            font-size: 1.3rem;
        }
        .sidebar.sidebar-collapsed .sidebar-logout .logout-link span {
            display: none;
        }
        .logs-section {
            display: none;
        }
        .logs-section.active {
            display: block;
        }
        .filter-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1.2rem;
            align-items: center;
        }
        .filter-bar input, .filter-bar select {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            border: 1px solid #e0e3ea;
            font-size: 1rem;
            min-width: 120px;
        }
        .filter-bar label {
            font-size: 0.98rem;
            color: #6c7a99;
            margin-right: 0.4rem;
        }
        .filter-bar .btn {
            padding: 0.5rem 1.2rem;
            border-radius: 8px;
            font-size: 1rem;
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
                    <a class="nav-link" href="#tools">
                        <i class="bi bi-tools"></i>
                        Tools
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#servers">
                        <i class="bi bi-hdd-stack"></i>
                        Servers
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#users">
                        <i class="bi bi-people"></i>
                        Users
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#logs">
                        <i class="bi bi-clipboard-data"></i>
                        Logs
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#settings">
                        <i class="bi bi-gear"></i>
                        Settings
                    </a>
                </li>
            </ul>
            <div class="sidebar-logout">
                <a href="logout.php" class="logout-link">
                    <i class="bi bi-box-arrow-right"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Main content -->
            <main class="main-content">
                <div class="main-floating-card">
                    <!-- Dashboard Overview Section -->
                    <div class="dashboard-section" id="dashboard-section">
                        <div class="section-header"><i class="bi bi-speedometer2"></i> Dashboard Overview</div>
                        <div class="dashboard-grid">
                            <div class="dashboard-card users">
                                <div class="icon"><i class="bi bi-people"></i></div>
                                <div class="card-title">Total Users</div>
                                <div class="card-number"><?php echo $total_users; ?></div>
                                <span class="badge bg-white text-primary">Active</span>
                            </div>
                            <div class="dashboard-card admins">
                                <div class="icon"><i class="bi bi-shield-check"></i></div>
                                <div class="card-title">Total Admins</div>
                                <div class="card-number"><?php echo $total_admins; ?></div>
                                <span class="badge bg-white text-info">System</span>
                            </div>
                            <div class="dashboard-card cookies">
                                <div class="icon"><i class="bi bi-cookie"></i></div>
                                <div class="card-title">Total Cookies</div>
                                <div class="card-number"><?php echo $total_cookies; ?></div>
                                <span class="badge bg-white text-success">Stored</span>
                            </div>
                            <div class="dashboard-card tools">
                                <div class="icon"><i class="bi bi-tools"></i></div>
                                <div class="card-title">Total Tools</div>
                                <div class="card-number"><?php echo $total_tools; ?></div>
                                <span class="badge bg-white text-info">Available</span>
                            </div>
                            <div class="dashboard-card servers">
                                <div class="icon"><i class="bi bi-hdd-stack"></i></div>
                                <div class="card-title">Total Servers</div>
                                <div class="card-number"><?php echo $total_servers; ?></div>
                                <span class="badge bg-white text-success">Running</span>
                            </div>
                        </div>
                        <!-- Dashboard Management Buttons -->
                        <div class="dashboard-management">
                            <button class="btn btn-outline-primary" id="dashboard-manage-tools"><i class="bi bi-tools"></i> Manage Tools</button>
                            <button class="btn btn-outline-primary" id="dashboard-manage-servers"><i class="bi bi-hdd-stack"></i> Manage Servers</button>
                            <button class="btn btn-outline-primary" id="dashboard-manage-users"><i class="bi bi-people"></i> Manage Users</button>
                            <button class="btn btn-outline-primary" id="dashboard-see-logs"><i class="bi bi-clipboard-data"></i> See Logs</button>
                        </div>
                    </div>

                    <!-- Tool Management Section -->
                    <div class="tools-section" id="tools-management-section">
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

                    <!-- Server Management Section -->
                    <div class="servers-section" id="servers-management-section">
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

                    <!-- Users Management Section -->
                    <div class="users-section" id="users-management-section">
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

                    <!-- Logs Management Section -->
                    <div class="logs-section" id="logs-management-section">
                        <div class="section-header-row">
                            <div class="section-header"><i class="bi bi-clipboard-data"></i> Logs</div>
                        </div>
                        <div class="server-table-responsive">
                            <table class="table server-table align-middle">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>User</th>
                                        <th>Action</th>
                                        <th>Details</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($logs_data as $log): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($log['created_at']))); ?></td>
                                        <td><?php echo htmlspecialchars($log['username'] ?? 'Unknown'); ?></td>
                                        <td><?php echo htmlspecialchars($log['action']); ?></td>
                                        <td><?php echo htmlspecialchars($log['details'] ?? ''); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
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
            </main>
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

        // Remove modal backdrop when modal is closed
        document.addEventListener('DOMContentLoaded', function() {
            const addToolModal = document.getElementById('addToolModal');
            addToolModal.addEventListener('hidden.bs.modal', function() {
                const backdrop = document.querySelector('.modal-backdrop');
                if (backdrop) {
                    backdrop.remove();
                }
            });
        });

        // Management section switching
        function showSection(sectionId) {
            document.querySelectorAll('.dashboard-section, .tools-section, .servers-section, .users-section, .logs-section').forEach(sec => sec.classList.remove('active'));
            if (sectionId) {
                document.getElementById(sectionId).classList.add('active');
            }
        }
        // Dashboard management buttons
        document.getElementById('dashboard-manage-tools').onclick = () => {
            showSection('tools-management-section');
            document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
            document.querySelectorAll('.nav-link').forEach(l => { if (l.textContent.includes('Tools')) l.classList.add('active'); });
        };
        document.getElementById('dashboard-manage-servers').onclick = () => {
            showSection('servers-management-section');
            document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
            document.querySelectorAll('.nav-link').forEach(l => { if (l.textContent.includes('Servers')) l.classList.add('active'); });
        };
        document.getElementById('dashboard-manage-users').onclick = () => {
            showSection('users-management-section');
            document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
            document.querySelectorAll('.nav-link').forEach(l => { if (l.textContent.includes('Users')) l.classList.add('active'); });
        };
        document.getElementById('dashboard-see-logs').onclick = () => {
            showSection('logs-management-section');
            document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
            document.querySelectorAll('.nav-link').forEach(l => { if (l.textContent.includes('Logs')) l.classList.add('active'); });
        };
        // Sidebar menu (example, you may need to update selectors to match your sidebar links)
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', function(e) {
                // Update active class
                document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
                this.classList.add('active');
                // Show correct section
                if (this.textContent.includes('Dashboard')) showSection('dashboard-section');
                else if (this.textContent.includes('Tools')) showSection('tools-management-section');
                else if (this.textContent.includes('Servers')) showSection('servers-management-section');
                else if (this.textContent.includes('Users')) showSection('users-management-section');
                else if (this.textContent.includes('Logs')) showSection('logs-management-section');
                else showSection(null);
            });
        });
        // Show dashboard by default
        showSection('dashboard-section');



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

            const formData = new FormData();
            formData.append('ajax_action', 'add_server');
            formData.append('server_name', serverName);
            formData.append('server_status', serverStatus);
            formData.append('tool_id', serverTool);
            formData.append('start_date', startDate);
            formData.append('end_date', endDate);
            formData.append('max_users', maxUsers);

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessage(data.message, 'success');
                    const addServerModal = bootstrap.Modal.getInstance(document.getElementById('addServerModal'));
                    addServerModal.hide();
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showMessage(data.message, 'danger');
                }
            })
            .catch(error => {
                showMessage('Error submitting form: ' + error.message, 'danger');
            });
        });

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

            const formData = new FormData();
            formData.append('ajax_action', 'add_user');
            formData.append('username', username);
            formData.append('email', email);
            formData.append('password', password);
            formData.append('user_type', userType);

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessage(data.message, 'success');
                    const addUserModal = bootstrap.Modal.getInstance(document.getElementById('addUserModal'));
                    addUserModal.hide();
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showMessage(data.message, 'danger');
                }
            })
            .catch(error => {
                showMessage('Error submitting form: ' + error.message, 'danger');
            });
        });

        // Delete Tool functionality
        document.addEventListener('click', function(e) {
            if (e.target.closest('.delete-tool')) {
                const button = e.target.closest('.delete-tool');
                const toolId = button.getAttribute('data-tool-id');
                
                if (confirm('Are you sure you want to delete this tool? This action cannot be undone.')) {
                    const formData = new FormData();
                    formData.append('ajax_action', 'delete_tool');
                    formData.append('tool_id', toolId);

                    fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showMessage(data.message, 'success');
                            setTimeout(() => {
                                window.location.reload();
                            }, 1000);
                        } else {
                            // If deletion failed due to dependencies, offer force delete
                            if (data.message.includes('associated server(s)')) {
                                if (confirm('This tool has associated servers. Do you want to force delete the tool and all its servers?')) {
                                    const forceFormData = new FormData();
                                    forceFormData.append('ajax_action', 'delete_tool');
                                    forceFormData.append('tool_id', toolId);
                                    forceFormData.append('force_delete', 'true');

                                    fetch(window.location.href, {
                                        method: 'POST',
                                        body: forceFormData
                                    })
                                    .then(response => response.json())
                                    .then(forceData => {
                                        if (forceData.success) {
                                            showMessage(forceData.message, 'success');
                                            setTimeout(() => {
                                                window.location.reload();
                                            }, 1000);
                                        } else {
                                            showMessage(forceData.message, 'danger');
                                        }
                                    })
                                    .catch(error => {
                                        showMessage('Error force deleting tool: ' + error.message, 'danger');
                                    });
                                }
                            } else {
                                showMessage(data.message, 'danger');
                            }
                        }
                    })
                    .catch(error => {
                        showMessage('Error deleting tool: ' + error.message, 'danger');
                    });
                }
            }
        });

        // Delete Server functionality
        document.addEventListener('click', function(e) {
            if (e.target.closest('.delete-server')) {
                const button = e.target.closest('.delete-server');
                const serverId = button.getAttribute('data-server-id');
                
                if (confirm('Are you sure you want to delete this server? This action cannot be undone.')) {
                    const formData = new FormData();
                    formData.append('ajax_action', 'delete_server');
                    formData.append('server_id', serverId);

                    fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showMessage(data.message, 'success');
                            setTimeout(() => {
                                window.location.reload();
                            }, 1000);
                        } else {
                            showMessage(data.message, 'danger');
                        }
                    })
                    .catch(error => {
                        showMessage('Error deleting server: ' + error.message, 'danger');
                    });
                }
            }
        });

        // Delete User functionality
        document.addEventListener('click', function(e) {
            if (e.target.closest('.delete-user')) {
                const button = e.target.closest('.delete-user');
                const userId = button.getAttribute('data-user-id');
                const username = button.getAttribute('data-username');
                
                if (confirm(`Are you sure you want to delete user "${username}"? This action cannot be undone.`)) {
                    const formData = new FormData();
                    formData.append('ajax_action', 'delete_user');
                    formData.append('user_id', userId);

                    fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showMessage(data.message, 'success');
                            setTimeout(() => {
                                window.location.reload();
                            }, 1000);
                        } else {
                            showMessage(data.message, 'danger');
                        }
                    })
                    .catch(error => {
                        showMessage('Error deleting user: ' + error.message, 'danger');
                    });
                }
            }
        });

        // Toggle Tool Status functionality
        document.addEventListener('click', function(e) {
            if (e.target.closest('.toggle-tool-status')) {
                const button = e.target.closest('.toggle-tool-status');
                const toolId = button.getAttribute('data-tool-id');
                const currentStatus = button.getAttribute('data-current-status');
                const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
                
                const formData = new FormData();
                formData.append('ajax_action', 'toggle_tool_status');
                formData.append('tool_id', toolId);
                formData.append('new_status', newStatus);

                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showMessage(data.message, 'success');
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        showMessage(data.message, 'danger');
                    }
                })
                .catch(error => {
                    showMessage('Error updating tool status: ' + error.message, 'danger');
                });
            }
        });

        // Toggle Server Status functionality
        document.addEventListener('click', function(e) {
            if (e.target.closest('.toggle-server-status')) {
                const button = e.target.closest('.toggle-server-status');
                const serverId = button.getAttribute('data-server-id');
                const currentStatus = button.getAttribute('data-current-status');
                const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
                
                const formData = new FormData();
                formData.append('ajax_action', 'toggle_server_status');
                formData.append('server_id', serverId);
                formData.append('new_status', newStatus);

                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showMessage(data.message, 'success');
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        showMessage(data.message, 'danger');
                    }
                })
                .catch(error => {
                    showMessage('Error updating server status: ' + error.message, 'danger');
                });
            }
        });

        // Reset forms when modals are closed
        document.getElementById('addServerModal').addEventListener('hidden.bs.modal', function() {
            document.getElementById('addServerForm').reset();
        });

        document.getElementById('addUserModal').addEventListener('hidden.bs.modal', function() {
            document.getElementById('addUserForm').reset();
        });
    </script>
</body>
</html> 