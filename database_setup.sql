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
    full_name VARCHAR(255) NULL,
    phone VARCHAR(50) NULL,
    street_address VARCHAR(255) NULL,
    city VARCHAR(100) NULL,
    province VARCHAR(100) NULL,
    postal_code VARCHAR(20) NULL,
    is_default_shipping BOOLEAN DEFAULT TRUE,
    is_default_billing BOOLEAN DEFAULT TRUE,
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

-- Active and historic cart sessions with status lifecycle
CREATE TABLE IF NOT EXISTS carts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    status ENUM('active', 'converted', 'abandoned') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    KEY idx_carts_user_status (user_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Granular cart line items linked to active carts
CREATE TABLE IF NOT EXISTS cart_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cart_id INT NOT NULL,
    product_id VARCHAR(50) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    price_at_addition DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (cart_id) REFERENCES carts(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_cart_product (cart_id, product_id),
    KEY idx_cart_items_cart (cart_id)
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

-- Ensure users profile fields exist
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='solar_db' AND TABLE_NAME='users' AND COLUMN_NAME='full_name');
SET @sql = IF(@col_exists=0, 'ALTER TABLE users ADD COLUMN full_name VARCHAR(255) NULL AFTER email, ADD COLUMN phone VARCHAR(50) NULL AFTER full_name, ADD COLUMN street_address VARCHAR(255) NULL AFTER phone, ADD COLUMN city VARCHAR(100) NULL AFTER street_address, ADD COLUMN province VARCHAR(100) NULL AFTER city, ADD COLUMN postal_code VARCHAR(20) NULL AFTER province, ADD COLUMN is_default_shipping BOOLEAN DEFAULT TRUE AFTER postal_code, ADD COLUMN is_default_billing BOOLEAN DEFAULT TRUE AFTER is_default_shipping', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------------------------------
-- 5. ADMIN USERS TABLE (Admin Authentication & Roles)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    role ENUM('superadmin', 'manager', 'staff') DEFAULT 'staff',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_admin_users_username (username),
    KEY idx_admin_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default superadmin: username=admin, password=admin123
INSERT IGNORE INTO admin_users (username, password_hash, email, role) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@apexdiurnal.com', 'superadmin'),
('admin_testing', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@gmail.com', 'superadmin');

-- -----------------------------------------------------------------------------
-- 6. PRODUCTS TABLE (Catalog Items & Classification)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS products (
    id VARCHAR(50) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    type ENUM('physical', 'service', 'custom') NOT NULL DEFAULT 'physical',
    base_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    description TEXT,
    image VARCHAR(255),
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO products (id, name, type, base_price, description, image) VALUES
('residential-arrays', 'Residential Arrays', 'physical', 8120.00, 'High-efficiency monocrystalline solar panels engineered for residential rooftops.', 'assets/images/residential arrays.png'),
('advanced-solar-inverter', 'Advanced Solar Inverter', 'physical', 25200.00, 'Pure sine-wave hybrid smart inverter with 98.4% peak grid conversion efficiency.', 'assets/images/advance power inverter.png'),
('commercial-grids', 'Commercial Grids', 'custom', 0.00, 'Utility-scale commercial solar panel grid installations for industrial and corporate facilities.', 'assets/images/commercial grids.png'),
('installation-booking', 'Professional Installation Booking', 'service', 8400.00, 'Certified master technician site assessment, 3D solar layout modeling & turnkey mounting.', 'assets/images/product-booking.png');

-- -----------------------------------------------------------------------------
-- 7. INVENTORY TABLE (Real-Time Stock Tracking)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id VARCHAR(50) NOT NULL UNIQUE,
    sku VARCHAR(50) NOT NULL UNIQUE,
    current_stock INT NOT NULL DEFAULT 0,
    min_stock_level INT NOT NULL DEFAULT 5,
    max_stock_level INT NOT NULL DEFAULT 100,
    reorder_point INT NOT NULL DEFAULT 10,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO inventory (product_id, sku, current_stock, min_stock_level, max_stock_level, reorder_point) VALUES
('residential-arrays', 'APX-MOD-RES01', 45, 5, 100, 10),
('advanced-solar-inverter', 'APX-INV-SMT02', 28, 5, 50, 8);

-- -----------------------------------------------------------------------------
-- 8. INVENTORY TRANSACTIONS TABLE (Audit Trail for Stock Movement)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS inventory_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id VARCHAR(50) NOT NULL,
    change_amount INT NOT NULL,
    transaction_type ENUM('order_deduction', 'cancellation_restock', 'manual_adjustment', 'restock') NOT NULL,
    reference_id VARCHAR(50) NULL COMMENT 'Order number or adjustment reason',
    notes TEXT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_inv_tx_product (product_id),
    KEY idx_inv_tx_type (transaction_type),
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------------------
-- 9. ORDERS TABLE (Customer Purchases & Order Lifecycle)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(30) NOT NULL UNIQUE,
    user_id INT NULL,
    customer_name VARCHAR(255) NOT NULL,
    customer_email VARCHAR(255) NOT NULL,
    customer_phone VARCHAR(50) NOT NULL,
    property_type VARCHAR(50) NOT NULL DEFAULT 'Residential',
    payment_method VARCHAR(100) NOT NULL,
    status ENUM('pending', 'client_confirmed', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded') DEFAULT 'pending',
    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    tax_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    shipping_address TEXT NOT NULL,
    billing_address TEXT,
    notes TEXT,
    installation_head VARCHAR(255) NULL,
    installation_date DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_orders_number (order_number),
    KEY idx_orders_user_id (user_id),
    KEY idx_orders_status (status),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------------------
-- 10. ORDER ITEMS TABLE (Line Items for Orders)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id VARCHAR(50) NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    KEY idx_order_items_order_id (order_id),
    KEY idx_order_items_product_id (product_id),
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------------------
-- 11. SERVICE BOOKINGS TABLE (Consultation & Installation Appointments)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS service_bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_reference VARCHAR(30) NOT NULL UNIQUE,
    order_id INT NULL,
    customer_name VARCHAR(255) NOT NULL,
    customer_email VARCHAR(255) NOT NULL,
    customer_phone VARCHAR(50) NOT NULL,
    service_type ENUM('consultation', 'installation', 'maintenance') NOT NULL DEFAULT 'consultation',
    preferred_date DATE NOT NULL,
    preferred_time_slot ENUM('morning', 'afternoon') NOT NULL,
    status ENUM('pending', 'confirmed', 'completed', 'cancelled') DEFAULT 'pending',
    address TEXT,
    access_notes TEXT,
    assigned_technician VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_service_bookings_date (preferred_date),
    KEY idx_service_bookings_status (status),
    KEY idx_service_bookings_ref (booking_reference),
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------------------
-- 12. QUOTE REQUESTS TABLE (Commercial Grid Inquiries & RFQ Ticketing)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS quote_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quote_number VARCHAR(30) NOT NULL UNIQUE,
    company_name VARCHAR(255) NOT NULL,
    contact_person VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(50) NOT NULL,
    facility_type VARCHAR(100) NULL,
    facility_size DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    current_monthly_bill DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    target_timeline ENUM('Immediate', 'Within 3 Months', '6+ Months') NULL,
    estimated_system_size DECIMAL(6,2) NULL,
    estimated_installation_cost DECIMAL(12,2) NULL,
    estimated_annual_savings DECIMAL(12,2) NULL,
    estimated_payback_period DECIMAL(4,1) NULL,
    installation_address TEXT,
    access_notes TEXT,
    installation_head VARCHAR(255) NULL,
    installation_date DATE NULL,
    status ENUM('new', 'pending', 'client_confirmed', 'confirmed', 'in_progress', 'completed', 'cancelled', 'reviewed', 'quoted', 'accepted', 'rejected', 'expired') DEFAULT 'new',
    admin_notes TEXT,
    quoted_amount DECIMAL(12,2) NULL,
    quoted_by INT NULL,
    quoted_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_quote_requests_status (status),
    KEY idx_quote_requests_email (email),
    KEY idx_quote_requests_num (quote_number),
    FOREIGN KEY (quoted_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------------------
-- 13. SHOPPING CARTS TABLE (Persistent Multi-Device User Carts)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS carts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    status ENUM('active', 'converted', 'abandoned') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_carts_user_status (user_id, status),
    KEY idx_carts_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------------------
-- 14. CART ITEMS TABLE (Persistent Cart Line Items & Price Snapshotting)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cart_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cart_id INT NOT NULL,
    product_id VARCHAR(100) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    price_at_addition DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_cart_product (cart_id, product_id),
    KEY idx_cart_items_cart (cart_id),
    KEY idx_cart_items_product (product_id),
    FOREIGN KEY (cart_id) REFERENCES carts(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------------------
-- 15. ORDER STATUS HISTORY TABLE & TRIGGERS (Lifecycle Tracking)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS order_status_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    status ENUM('pending', 'client_confirmed', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded') NOT NULL,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    changed_by INT NULL COMMENT 'Admin/user ID who made the change',
    notes TEXT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    KEY idx_order_status_order_id (order_id),
    KEY idx_order_status_changed_at (changed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DELIMITER //

CREATE TRIGGER IF NOT EXISTS order_status_insert
AFTER INSERT ON orders
FOR EACH ROW
BEGIN
    INSERT INTO order_status_history (order_id, status, changed_by, notes)
    VALUES (NEW.id, NEW.status, NULL, 'Order placed');
END//

CREATE TRIGGER IF NOT EXISTS order_status_update
AFTER UPDATE ON orders
FOR EACH ROW
BEGIN
    IF NEW.status <> OLD.status THEN
        INSERT INTO order_status_history (order_id, status, changed_by, notes)
        VALUES (
            NEW.id, 
            NEW.status,
            COALESCE(@current_admin_id, (SELECT id FROM admin_users WHERE username = SUBSTRING_INDEX(USER(), '@', 1)), NULL),
            CONCAT('Status changed from ', OLD.status, ' to ', NEW.status)
        );
    END IF;
END//

DELIMITER ;