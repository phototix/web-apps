CREATE DATABASE IF NOT EXISTS erp_ezy_chat CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE erp_ezy_chat;

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('superadmin', 'admin', 'users') NOT NULL DEFAULT 'users',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login_at TIMESTAMP NULL DEFAULT NULL,
    expiry_date DATE NULL,
    agent_contacts TEXT NULL,
    file_handling_category_assignment INT NOT NULL DEFAULT 1,
    INDEX idx_expiry_date (expiry_date)
);
