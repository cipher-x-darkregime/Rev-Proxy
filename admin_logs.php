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

// Get activity logs
$logs_query = $conn->query('
    SELECT al.created_at, u.username, al.action, al.details
    FROM activity_logs al
    LEFT JOIN users u ON al.user_id = u.id
    ORDER BY al.created_at DESC
    LIMIT 50
');
$logs_data = $logs_query->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Logs Section -->
<div class="logs-section" id="logs-section">
    <div class="section-header-row">
        <div class="section-header"><i class="bi bi-clipboard-data"></i> Activity Logs</div>
        <div class="header-buttons">
            <button class="btn btn-outline-primary" id="refresh-logs">
                <i class="bi bi-arrow-clockwise"></i> Refresh
            </button>
            <button class="btn btn-primary" id="export-logs">
                <i class="bi bi-download me-1"></i>
                Export Logs
            </button>
        </div>
    </div>
    <form class="filter-bar" id="logs-filter">
        <input type="text" placeholder="Username" data-filter-col="1">
        <input type="text" placeholder="Action" data-filter-col="2">
        <input type="text" placeholder="Details" data-filter-col="3">
        <input type="date" placeholder="Date" data-filter-col="0">
        <button type="reset" class="btn btn-outline-secondary">Clear</button>
    </form>
    <div class="server-table-responsive">
        <table class="table server-table align-middle" id="logs-table">
            <thead>
                <tr>
                    <th>Date/Time</th>
                    <th>Username</th>
                    <th>Action</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs_data as $log): ?>
                <tr>
                    <td><?php echo htmlspecialchars($log['created_at']); ?></td>
                    <td><?php echo htmlspecialchars($log['username'] ?? 'System'); ?></td>
                    <td>
                        <span class="badge text-primary">
                            <?php echo htmlspecialchars($log['action']); ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($log['details']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div> 