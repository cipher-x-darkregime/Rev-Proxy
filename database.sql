CREATE DATABASE IF NOT EXISTS ownera_test;
USE ownera_test;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    user_type ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active'
);

-- Add status column if it doesn't exist (for existing databases)
ALTER TABLE users ADD COLUMN IF NOT EXISTS status ENUM('active', 'inactive') NOT NULL DEFAULT 'active';

CREATE TABLE IF NOT EXISTS cookies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cookie_data TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT,
    FOREIGN KEY (updated_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS tools (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    domain VARCHAR(255),
    directory VARCHAR(255),
    user_limit INT DEFAULT 5,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS servers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    tool_id INT,
    start_date DATE,
    end_date DATE,
    max_users INT DEFAULT 100,
    current_users INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT,
    FOREIGN KEY (tool_id) REFERENCES tools(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS server_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    server_id INT,
    user_id INT,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (server_id) REFERENCES servers(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    UNIQUE KEY unique_server_user (server_id, user_id)
);

CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Insert default admin user (password: Admin@123)
INSERT INTO users (username, password, email, user_type) 
VALUES ('admin', '$2a$10$4vzmWSqHQeoKMRbIz8rQU.zDzEuoKlZBkyZxZBoCAbEq4ANjhYnI6', 'admin@example.com', 'admin')
ON DUPLICATE KEY UPDATE status = 'active';

-- Insert default regular user (password: User@123)
INSERT INTO users (username, password, email, user_type) 
VALUES ('user', '$2a$10$Wljwx1r2IDhfUxG/gr0Og.eabEokLFO7EXHabqDpytYWcJjQjPxWC', 'user@example.com', 'user')
ON DUPLICATE KEY UPDATE status = 'active';

-- Insert sample tools
INSERT INTO tools (name, status, domain, directory, user_limit, created_by) VALUES
('Tool Alpha', 'active', 'alpha.example.com', '/tools/alpha', 10, 1),
('Tool Beta', 'inactive', 'beta.example.com', '/tools/beta', 5, 1),
('Tool Gamma', 'active', 'gamma.example.com', '/tools/gamma', 15, 1);

-- Insert sample servers
INSERT INTO servers (name, status, tool_id, start_date, end_date, max_users, current_users, created_by) VALUES
('Server 1', 'active', 1, '2024-06-01', '2024-12-01', 150, 87, 1),
('Server 2', 'active', 2, '2024-05-15', '2024-11-15', 120, 34, 1);

-- Insert sample activity logs
INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES
(1, 'Login', 'Successful login', '192.168.1.100'),
(1, 'Added Tool', 'Tool Alpha', '192.168.1.100'),
(2, 'Login', 'Successful login', '192.168.1.101'),
(1, 'Deleted Server', 'Server 2', '192.168.1.100'); 