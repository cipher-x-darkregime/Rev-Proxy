<?php
require_once 'config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        echo 'UNAUTHORIZED';
        exit();
    } else {
        header('Location: admin_login.php');
        exit();
    }
}

$conn = getDBConnection();

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
?>

<!-- User Management Section -->
<div class="users-section" id="users-management-section">
    <div class="section-header-row">
        <div class="section-header"><i class="bi bi-people"></i> User Management</div>
        <div class="header-buttons">
            <button class="btn btn-outline-primary" id="refresh-users">
                <i class="bi bi-arrow-clockwise"></i> Refresh
            </button>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="bi bi-plus-lg me-1"></i>
                Add User
            </button>
        </div>
    </div>
    <form class="filter-bar" id="users-filter">
        <input type="text" placeholder="User ID" data-filter-col="0">
        <input type="text" placeholder="Username" data-filter-col="1">
        <input type="text" placeholder="Email" data-filter-col="2">
        <select data-filter-col="3">
            <option value="">User Type</option>
            <option value="admin">Admin</option>
            <option value="user">User</option>
        </select>
        <select data-filter-col="4">
            <option value="">Status</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
        </select>
        <button type="reset" class="btn btn-outline-secondary">Clear</button>
    </form>
    <div class="server-table-responsive">
        <table class="table server-table align-middle" id="users-table">
            <thead>
                <tr>
                    <th>User ID</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>User Type</th>
                    <th>Status</th>
                    <th>Last Login</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users_data as $user): ?>
                <tr>
                    <td><?php echo htmlspecialchars($user['id']); ?></td>
                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td>
                        <span class="badge <?php echo $user['user_type'] === 'admin' ? 'text-info' : 'text-primary'; ?>">
                            <?php echo ucfirst(htmlspecialchars($user['user_type'])); ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge <?php echo $user['status'] === 'active' ? 'text-success' : 'text-danger'; ?>">
                            <?php echo ucfirst(htmlspecialchars($user['status'])); ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($user['last_login'] ?? 'Never'); ?></td>
                    <td>
                        <button class="btn btn-outline-primary btn-sm" title="Edit"><i class="bi bi-pencil"></i></button>
                        <button class="btn btn-danger btn-sm delete-user" title="Delete User" data-user-id="<?php echo $user['id']; ?>"><i class="bi bi-trash"></i></button>
                        <button class="btn btn-secondary btn-sm" title="Check Logs"><i class="bi bi-clipboard-data"></i></button>
                        <button class="btn btn-info btn-sm" title="Reset Password"><i class="bi bi-key"></i></button>
                        <button class="btn btn-warning btn-sm" title="Check Activity"><i class="bi bi-activity"></i></button>
                        <button class="btn btn-<?php echo $user['status'] === 'active' ? 'success' : 'warning'; ?> btn-sm toggle-user-status" 
                                title="<?php echo $user['status'] === 'active' ? 'Deactivate' : 'Activate'; ?> User" 
                                data-user-id="<?php echo $user['id']; ?>" 
                                data-current-status="<?php echo $user['status']; ?>">
                            <i class="bi bi-<?php echo $user['status'] === 'active' ? 'check-circle' : 'x-circle'; ?>"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div> 