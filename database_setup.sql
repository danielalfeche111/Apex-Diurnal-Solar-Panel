-- =============================================================================
-- APEX DIURNAL SOLAR PANELS - UNIFIED MASTER DATABASE SETUP
-- Consolidated & Connected Relational Schema
-- Compatible with MySQL 5.7+, MySQL 8.0+, and MariaDB 10.3+
-- All 14 tables fully interconnected with foreign keys, seed data, and triggers
-- =============================================================================

CREATE DATABASE IF NOT EXISTS solar_db;
USE solar_db;

SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- 1. USERS TABLE (Customer Authentication & Profiles)
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

-- Default customer user for testing (password: admin123)
INSERT IGNORE INTO users (email, password_hash) VALUES
('admin@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- -----------------------------------------------------------------------------
-- 2. ADMIN USERS TABLE (Admin Authentication & Roles)
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
-- 3. PRODUCTS TABLE (Catalog Items & Hardware Specifications)
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
-- 4. INVENTORY TABLE (Real-Time Stock Tracking)
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
-- 5. INVENTORY TRANSACTIONS TABLE (Stock Movement Audit Trail)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS inventory_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id VARCHAR(50) NOT NULL,
    change_amount INT NOT NULL,
    transaction_type ENUM('order_deduction', 'cancellation_restock', 'manual_adjustment', 'restock') NOT NULL,
    reference_id VARCHAR(50) NULL COMMENT 'Order number or adjustment reference',
    notes TEXT NULL,
    created_by INT NULL COMMENT 'Admin User ID who performed action',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_inv_tx_product (product_id),
    KEY idx_inv_tx_type (transaction_type),
    KEY idx_inv_tx_created_by (created_by),
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------------------
-- 6. CARTS TABLE (Persistent Multi-Device Shopping Carts)
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
-- 7. CART ITEMS TABLE (Granular Cart Line Items & Snapshots)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cart_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cart_id INT NOT NULL,
    product_id VARCHAR(50) NOT NULL,
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
-- 8. USER CARTS TABLE (Legacy Compatibility Cart Items)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS user_carts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id VARCHAR(50) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_product (user_id, product_id),
    KEY idx_user_carts_user_id (user_id),
    KEY idx_user_carts_product_id (product_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------------------
-- 9. ORDERS TABLE (Customer Purchases & Lifecycle Progression)
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
-- 10. ORDER ITEMS TABLE (Purchased Line Items)
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
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------------------
-- 11. ORDER STATUS HISTORY TABLE (Order Timeline Audit Trail)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS order_status_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    status ENUM('pending', 'client_confirmed', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded') NOT NULL,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    changed_by INT NULL COMMENT 'Admin/staff ID who updated status',
    notes TEXT NULL,
    KEY idx_order_status_order_id (order_id),
    KEY idx_order_status_changed_at (changed_at),
    KEY idx_order_status_changed_by (changed_by),
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------------------
-- 12. COMMERCIAL LEADS TABLE (Consultation Bookings & RFQ Site Audits)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS commercial_leads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL COMMENT 'Registered Customer ID',
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
    KEY idx_commercial_leads_user_id (user_id),
    KEY idx_commercial_leads_email (corporate_email),
    KEY idx_commercial_leads_created_at (created_at),
    KEY idx_commercial_leads_type (lead_type),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------------------
-- 13. QUOTE REQUESTS TABLE (Commercial Grid Inquiries & RFQ Quotations)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS quote_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL COMMENT 'Registered Customer ID',
    lead_id INT NULL COMMENT 'Originating Commercial Lead ID',
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
    quoted_by INT NULL COMMENT 'Admin Staff ID',
    quoted_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_quote_requests_user_id (user_id),
    KEY idx_quote_requests_lead_id (lead_id),
    KEY idx_quote_requests_status (status),
    KEY idx_quote_requests_email (email),
    KEY idx_quote_requests_num (quote_number),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (lead_id) REFERENCES commercial_leads(id) ON DELETE SET NULL,
    FOREIGN KEY (quoted_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------------------
-- 14. SERVICE BOOKINGS TABLE (Consultation & Installation Appointments)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS service_bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL COMMENT 'Registered Customer ID',
    booking_reference VARCHAR(30) NOT NULL UNIQUE,
    order_id INT NULL COMMENT 'Associated Hardware Order ID',
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
    KEY idx_service_bookings_user_id (user_id),
    KEY idx_service_bookings_order_id (order_id),
    KEY idx_service_bookings_date (preferred_date),
    KEY idx_service_bookings_status (status),
    KEY idx_service_bookings_ref (booking_reference),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- -----------------------------------------------------------------------------
-- 15. ORDER STATUS TRIGGERS (Automatic Lifecycle Progression Logging)
-- -----------------------------------------------------------------------------
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

-- -----------------------------------------------------------------------------
-- 16. IDEMPOTENT UPGRADE PROCEDURES (Safely upgrades existing databases)
-- -----------------------------------------------------------------------------
-- commercial_leads columns
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='solar_db' AND TABLE_NAME='commercial_leads' AND COLUMN_NAME='user_id');
SET @sql = IF(@col_exists=0, 'ALTER TABLE commercial_leads ADD COLUMN user_id INT NULL AFTER id, ADD CONSTRAINT fk_commercial_leads_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- quote_requests columns
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='solar_db' AND TABLE_NAME='quote_requests' AND COLUMN_NAME='user_id');
SET @sql = IF(@col_exists=0, 'ALTER TABLE quote_requests ADD COLUMN user_id INT NULL AFTER id, ADD CONSTRAINT fk_quote_requests_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='solar_db' AND TABLE_NAME='quote_requests' AND COLUMN_NAME='lead_id');
SET @sql = IF(@col_exists=0, 'ALTER TABLE quote_requests ADD COLUMN lead_id INT NULL AFTER user_id, ADD CONSTRAINT fk_quote_requests_lead FOREIGN KEY (lead_id) REFERENCES commercial_leads(id) ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- service_bookings columns
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='solar_db' AND TABLE_NAME='service_bookings' AND COLUMN_NAME='user_id');
SET @sql = IF(@col_exists=0, 'ALTER TABLE service_bookings ADD COLUMN user_id INT NULL AFTER id, ADD CONSTRAINT fk_service_bookings_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- users profile columns
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='solar_db' AND TABLE_NAME='users' AND COLUMN_NAME='full_name');
SET @sql = IF(@col_exists=0, 'ALTER TABLE users ADD COLUMN full_name VARCHAR(255) NULL AFTER email, ADD COLUMN phone VARCHAR(50) NULL AFTER full_name, ADD COLUMN street_address VARCHAR(255) NULL AFTER phone, ADD COLUMN city VARCHAR(100) NULL AFTER street_address, ADD COLUMN province VARCHAR(100) NULL AFTER city, ADD COLUMN postal_code VARCHAR(20) NULL AFTER province, ADD COLUMN is_default_shipping BOOLEAN DEFAULT TRUE AFTER postal_code, ADD COLUMN is_default_billing BOOLEAN DEFAULT TRUE AFTER is_default_shipping', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;