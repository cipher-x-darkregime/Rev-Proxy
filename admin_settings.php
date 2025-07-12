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

// Get latest cookie data
$stmt = $conn->query('SELECT cookie_data, updated_at FROM cookies ORDER BY updated_at DESC LIMIT 1');
$latest_cookies = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!-- Settings Section -->
<div class="settings-section" id="settings-section">
    <div class="section-header-row">
        <div class="section-header"><i class="bi bi-gear"></i> System Settings</div>
        <div class="header-buttons">
            <button class="btn btn-outline-primary" id="refresh-settings">
                <i class="bi bi-arrow-clockwise"></i> Refresh
            </button>
            <button class="btn btn-primary" id="save-settings">
                <i class="bi bi-check-lg me-1"></i>
                Save Settings
            </button>
        </div>
    </div>
    
    <!-- Cookie Management -->
    <div class="card mgmt-card">
        <div class="card-header">
            <h5><i class="bi bi-cookie"></i> Cookie Management</h5>
        </div>
        <div class="card-body">
            <form id="cookie-form">
                <div class="mb-3">
                    <label for="cookie_data" class="form-label">Cookie Data (JSON)</label>
                    <textarea class="form-control" id="cookie_data" name="cookie_data" rows="10" placeholder='{"cookie1": "value1", "cookie2": "value2"}'><?php echo htmlspecialchars($latest_cookies['cookie_data'] ?? ''); ?></textarea>
                    <div class="form-text">Enter cookie data in JSON format</div>
                </div>
                <button type="submit" class="btn btn-primary">Update Cookies</button>
            </form>
            <?php if ($latest_cookies): ?>
            <div class="mt-3">
                <small class="text-muted">Last updated: <?php echo htmlspecialchars($latest_cookies['updated_at']); ?></small>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- System Configuration -->
    <div class="card mgmt-card">
        <div class="card-header">
            <h5><i class="bi bi-sliders"></i> System Configuration</h5>
        </div>
        <div class="card-body">
            <form id="system-config-form">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="max_users_per_server" class="form-label">Max Users per Server</label>
                            <input type="number" class="form-control" id="max_users_per_server" name="max_users_per_server" value="100" min="1" max="1000">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="session_timeout" class="form-label">Session Timeout (minutes)</label>
                            <input type="number" class="form-control" id="session_timeout" name="session_timeout" value="30" min="5" max="480">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="log_retention_days" class="form-label">Log Retention (days)</label>
                            <input type="number" class="form-control" id="log_retention_days" name="log_retention_days" value="30" min="1" max="365">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="backup_frequency" class="form-label">Backup Frequency (days)</label>
                            <input type="number" class="form-control" id="backup_frequency" name="backup_frequency" value="7" min="1" max="30">
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Save Configuration</button>
            </form>
        </div>
    </div>
    
    <!-- Security Settings -->
    <div class="card mgmt-card">
        <div class="card-header">
            <h5><i class="bi bi-shield-check"></i> Security Settings</h5>
        </div>
        <div class="card-body">
            <form id="security-form">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="password_min_length" class="form-label">Minimum Password Length</label>
                            <input type="number" class="form-control" id="password_min_length" name="password_min_length" value="8" min="6" max="20">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="max_login_attempts" class="form-label">Max Login Attempts</label>
                            <input type="number" class="form-control" id="max_login_attempts" name="max_login_attempts" value="5" min="3" max="10">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="require_strong_password" name="require_strong_password" checked>
                                <label class="form-check-label" for="require_strong_password">
                                    Require Strong Password
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="enable_2fa" name="enable_2fa">
                                <label class="form-check-label" for="enable_2fa">
                                    Enable Two-Factor Authentication
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Save Security Settings</button>
            </form>
        </div>
    </div>
</div> 