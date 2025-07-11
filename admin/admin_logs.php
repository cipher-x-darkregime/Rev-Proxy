<?php
require_once __DIR__ . '/../core/config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: admin_login.php');
    exit();
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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs - Admin Dashboard</title>
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
                        <a class="nav-link active" href="/Rev-Proxy/admin/admin_logs.php">
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
                <h1>Activity Logs</h1>
                <p>View system activity and user actions.</p>
            </div>

            <!-- Activity Logs Section -->
            <div class="logs-section">
                <div class="section-header-row">
                    <div class="section-header"><i class="bi bi-clipboard-data"></i> Activity Logs</div>
                    <button class="btn btn-outline-secondary" onclick="exportLogs()">
                        <i class="bi bi-download me-1"></i>
                        Export Logs
                    </button>
                </div>
                <div class="server-table-responsive">
                    <table class="table server-table align-middle">
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Details</th>
                                <th>IP Address</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs_data as $log): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($log['timestamp']); ?></td>
                                <td><?php echo htmlspecialchars($log['username']); ?></td>
                                <td><?php echo htmlspecialchars($log['action']); ?></td>
                                <td><?php echo htmlspecialchars($log['details']); ?></td>
                                <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                                <td>
                                    <span class="badge <?php echo $log['status'] === 'success' ? 'text-success' : 'text-danger'; ?>">
                                        <?php echo ucfirst(htmlspecialchars($log['status'])); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/admin-scripts.js"></script>
    <script>
        // Export logs functionality
        function exportLogs() {
            // Create a CSV content
            const table = document.querySelector('.server-table');
            const rows = Array.from(table.querySelectorAll('tr'));
            
            let csvContent = "data:text/csv;charset=utf-8,";
            
            // Add headers
            const headers = Array.from(rows[0].querySelectorAll('th')).map(th => th.textContent);
            csvContent += headers.join(',') + '\n';
            
            // Add data rows
            for (let i = 1; i < rows.length; i++) {
                const cells = Array.from(rows[i].querySelectorAll('td')).map(td => {
                    // Escape commas and quotes in cell content
                    let content = td.textContent.replace(/"/g, '""');
                    return `"${content}"`;
                });
                csvContent += cells.join(',') + '\n';
            }
            
            // Create download link
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "activity_logs.csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            showMessage('Logs exported successfully!', 'success');
        }
    </script>
</body>
</html> 