CREATE DATABASE IF NOT EXISTS ownera_test;
USE ownera_test;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    user_type ENUM('admin', 'user') NOT NULL DEFAULT 'user'
);

CREATE TABLE IF NOT EXISTS cookies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cookie_data TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT,
    FOREIGN KEY (updated_by) REFERENCES users(id)
);

-- Insert default admin user (password: Admin@123)
INSERT INTO users (username, password, email, user_type) 
VALUES ('admin', '$2a$10$WhsTQ7fFZlAQ1aPRf3sxyOH59m5xWwr9Rsof6pOwWKJ1Dz22kp2di', 'admin@example.com', 'admin');

-- Insert default regular user (password: User@123)
INSERT INTO users (username, password, email, user_type) 
VALUES ('user', '$2a$10$2.3Ty7US6EG0U04DD70Ho.DLnxh0peBXX7xRTH1K6XijrGfwxVRuC', 'user@example.com', 'user'); 