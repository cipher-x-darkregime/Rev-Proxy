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

// Get servers data
$servers_query = $conn->query('
    SELECT s.id, s.name, s.status, s.current_users, s.start_date, s.end_date,
           t.name as tool_name
    FROM servers s
    LEFT JOIN tools t ON s.tool_id = t.id
    ORDER BY s.id
');
$servers_data = $servers_query->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Server Management Section -->
<div class="servers-section" id="servers-management-section">
    <div class="section-header-row">
        <div class="section-header"><i class="bi bi-hdd-stack"></i> Server Management</div>
        <div class="header-buttons">
            <button class="btn btn-outline-primary" id="refresh-servers">
                <i class="bi bi-arrow-clockwise"></i> Refresh
            </button>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addServerModal">
                <i class="bi bi-plus-lg me-1"></i>
                Add Server
            </button>
        </div>
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
                    <th>Current Users</th>
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
                    <td><?php echo htmlspecialchars($server['current_users'] ?? 0); ?></td>
                    <td><?php echo htmlspecialchars($server['tool_name'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($server['start_date'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($server['end_date'] ?? 'N/A'); ?></td>
                    <td>
                        <button class="btn btn-outline-primary btn-sm" title="Configure"><i class="bi bi-gear"></i></button>
                        <button class="btn btn-danger btn-sm delete-server" title="Delete Server" data-server-id="<?php echo $server['id']; ?>"><i class="bi bi-trash"></i></button>
                        <button class="btn btn-secondary btn-sm" title="Check Logs"><i class="bi bi-clipboard-data"></i></button>
                        <button class="btn btn-info btn-sm" title="Add User"><i class="bi bi-plus-circle"></i></button>
                        <button class="btn btn-warning btn-sm" title="Check Users"><i class="bi bi-people"></i></button>
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