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
?>

<!-- Tool Management Section -->
<div class="tools-section" id="tools-management-section">
    <div class="section-header-row">
        <div class="section-header"><i class="bi bi-tools"></i> Tool Management</div>
        <div class="header-buttons">
            <button class="btn btn-outline-primary" id="refresh-tools">
                <i class="bi bi-arrow-clockwise"></i> Refresh
            </button>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addToolModal">
                <i class="bi bi-plus-lg me-1"></i>
                Add Tool
            </button>
        </div>
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