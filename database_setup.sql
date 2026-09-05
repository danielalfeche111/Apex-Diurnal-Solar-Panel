-- Database setup for Apex Diurnal Solar Panels Authentication System
-- Run this in your MySQL/MariaDB database to create the required tables

CREATE DATABASE IF NOT EXISTS solar_db;
USE solar_db;

-- Users table for authentication
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create a default admin user for testing (password: admin123)
INSERT IGNORE INTO users (email, password_hash) VALUES
('admin@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'); -- password: admin123

-- Optional: User carts table for persistent carts across devices
CREATE TABLE IF NOT EXISTS user_carts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id VARCHAR(50) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_product (user_id, product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Indexes for better performance
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_user_carts_user_id ON user_carts(user_id);