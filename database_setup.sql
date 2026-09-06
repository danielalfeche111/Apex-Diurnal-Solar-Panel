-- Database setup for Apex Diurnal Solar Panels System
-- Consolidated Schema: Authentication, User Carts, and Commercial Consultation & RFQ Leads
-- Compatible with MySQL 5.7+, MySQL 8.0+, and MariaDB 10.3+

CREATE DATABASE IF NOT EXISTS solar_db;
USE solar_db;

-- -----------------------------------------------------------------------------
-- 1. USERS TABLE (Authentication)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create a default admin user for testing (password: admin123)
INSERT IGNORE INTO users (email, password_hash) VALUES
('admin@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- -----------------------------------------------------------------------------
-- 2. USER CARTS TABLE (Persistent cart items across devices)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS user_carts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id VARCHAR(50) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_product (user_id, product_id),
    KEY idx_user_carts_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------------------
-- 3. COMMERCIAL LEADS TABLE (Consultation Bookings & Corporate RFQ Quotes)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS commercial_leads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lead_type ENUM('consultation', 'rfq') NOT NULL DEFAULT 'consultation',
    company_name VARCHAR(255) NOT NULL,
    business_registration_type VARCHAR(100) NULL COMMENT 'Sole Proprietorship, Partnership, Corporation, Cooperative, Joint Venture',
    property_address TEXT NOT NULL,
    target_timeline ENUM('Immediate', 'Within 3 Months', '6+ Months') NULL COMMENT 'RFQ project completion timeline',
    facility_type ENUM('Manufacturing Plant', 'Commercial Building', 'Warehouse', 'Agricultural', 'School', 'House', 'House / Residential') NULL,
    power_supply VARCHAR(50) NULL DEFAULT 'Three-Phase Supply',
    facility_size DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Facility size in sqm',
    current_monthly_bill DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Monthly electricity bill PHP',
    estimated_system_size DECIMAL(6,2) DEFAULT NULL COMMENT 'Recommended system kW',
    estimated_installation_cost DECIMAL(12,2) DEFAULT NULL COMMENT 'PHP',
    estimated_annual_savings DECIMAL(12,2) DEFAULT NULL COMMENT 'PHP per year',
    estimated_payback_period DECIMAL(4,1) DEFAULT NULL COMMENT 'years',
    applicable_discounts LONGTEXT DEFAULT NULL COMMENT 'JSON array of applied volume discounts',
    estimated_installation_timeline VARCHAR(100) DEFAULT NULL,
    contact_person VARCHAR(255) NOT NULL,
    contact_title VARCHAR(255) DEFAULT NULL,
    corporate_email VARCHAR(255) NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    best_call_time ENUM('Morning', 'Afternoon', 'Anytime') NULL,
    preferred_date DATE NULL,
    preferred_time_slot ENUM('Morning', 'Afternoon') NULL,
    access_notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_commercial_leads_email (corporate_email),
    KEY idx_commercial_leads_created_at (created_at),
    KEY idx_commercial_leads_type (lead_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------------------
-- 4. IDEMPOTENT UPGRADE PROCEDURES (For safely updating existing installations)
-- -----------------------------------------------------------------------------
-- Ensure lead_type column exists
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='solar_db' AND TABLE_NAME='commercial_leads' AND COLUMN_NAME='lead_type');
SET @sql = IF(@col_exists=0, 'ALTER TABLE commercial_leads ADD COLUMN lead_type ENUM(\'consultation\', \'rfq\') NOT NULL DEFAULT \'consultation\' AFTER id', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Ensure business_registration_type column exists
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='solar_db' AND TABLE_NAME='commercial_leads' AND COLUMN_NAME='business_registration_type');
SET @sql = IF(@col_exists=0, 'ALTER TABLE commercial_leads ADD COLUMN business_registration_type VARCHAR(100) NULL AFTER company_name', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Ensure target_timeline column exists
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='solar_db' AND TABLE_NAME='commercial_leads' AND COLUMN_NAME='target_timeline');
SET @sql = IF(@col_exists=0, 'ALTER TABLE commercial_leads ADD COLUMN target_timeline ENUM(\'Immediate\', \'Within 3 Months\', \'6+ Months\') NULL AFTER property_address', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Ensure estimate columns exist
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='solar_db' AND TABLE_NAME='commercial_leads' AND COLUMN_NAME='facility_size');
SET @sql = IF(@col_exists=0, 'ALTER TABLE commercial_leads ADD COLUMN facility_size DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT \'Facility size in sqm\' AFTER power_supply', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='solar_db' AND TABLE_NAME='commercial_leads' AND COLUMN_NAME='current_monthly_bill');
SET @sql = IF(@col_exists=0, 'ALTER TABLE commercial_leads ADD COLUMN current_monthly_bill DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT \'Monthly electricity bill PHP\' AFTER facility_size', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='solar_db' AND TABLE_NAME='commercial_leads' AND COLUMN_NAME='estimated_system_size');
SET @sql = IF(@col_exists=0, 'ALTER TABLE commercial_leads ADD COLUMN estimated_system_size DECIMAL(6,2) DEFAULT NULL COMMENT \'Recommended system kW\' AFTER current_monthly_bill', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='solar_db' AND TABLE_NAME='commercial_leads' AND COLUMN_NAME='estimated_installation_cost');
SET @sql = IF(@col_exists=0, 'ALTER TABLE commercial_leads ADD COLUMN estimated_installation_cost DECIMAL(12,2) DEFAULT NULL COMMENT \'PHP\' AFTER estimated_system_size', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='solar_db' AND TABLE_NAME='commercial_leads' AND COLUMN_NAME='estimated_annual_savings');
SET @sql = IF(@col_exists=0, 'ALTER TABLE commercial_leads ADD COLUMN estimated_annual_savings DECIMAL(12,2) DEFAULT NULL COMMENT \'PHP per year\' AFTER estimated_installation_cost', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='solar_db' AND TABLE_NAME='commercial_leads' AND COLUMN_NAME='estimated_payback_period');
SET @sql = IF(@col_exists=0, 'ALTER TABLE commercial_leads ADD COLUMN estimated_payback_period DECIMAL(4,1) DEFAULT NULL COMMENT \'years\' AFTER estimated_annual_savings', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='solar_db' AND TABLE_NAME='commercial_leads' AND COLUMN_NAME='applicable_discounts');
SET @sql = IF(@col_exists=0, 'ALTER TABLE commercial_leads ADD COLUMN applicable_discounts LONGTEXT DEFAULT NULL AFTER estimated_payback_period', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='solar_db' AND TABLE_NAME='commercial_leads' AND COLUMN_NAME='estimated_installation_timeline');
SET @sql = IF(@col_exists=0, 'ALTER TABLE commercial_leads ADD COLUMN estimated_installation_timeline VARCHAR(100) DEFAULT NULL AFTER applicable_discounts', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Ensure consultation-specific columns are nullable for RFQ flow
ALTER TABLE commercial_leads 
    MODIFY facility_type ENUM('Manufacturing Plant', 'Commercial Building', 'Warehouse', 'Agricultural', 'School', 'House', 'House / Residential') NULL,
    MODIFY power_supply VARCHAR(50) NULL DEFAULT 'Three-Phase Supply',
    MODIFY best_call_time ENUM('Morning', 'Afternoon', 'Anytime') NULL,
    MODIFY preferred_date DATE NULL,
    MODIFY preferred_time_slot ENUM('Morning', 'Afternoon') NULL;