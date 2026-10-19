-- Complete Apex Diurnal schema. Applied by Doctrine's baseline migration.
-- Ordered by foreign-key dependencies; no records are dropped or replaced.

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

CREATE TABLE IF NOT EXISTS carts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    status ENUM('active', 'converted', 'abandoned') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    KEY idx_carts_user_status (user_id, status),
    KEY idx_carts_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

CREATE TABLE IF NOT EXISTS commercial_leads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    lead_type ENUM('consultation', 'rfq') NOT NULL DEFAULT 'consultation',
    company_name VARCHAR(255) NOT NULL,
    business_registration_type VARCHAR(100) NULL COMMENT 'Sole Proprietorship, Partnership, Corporation, Cooperative, Joint Venture',
    property_address TEXT NOT NULL,
    target_timeline ENUM('Immediate', 'Within 3 Months', '6+ Months') NULL COMMENT 'RFQ project completion timeline',
    facility_type ENUM('Manufacturing Plant', 'Commercial Building', 'Warehouse', 'Agricultural', 'School', 'House', 'House / Residential', 'Commercial') NULL,
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
    best_call_time VARCHAR(50) NULL,
    preferred_date DATE NULL,
    preferred_time_slot VARCHAR(50) NULL,
    access_notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_commercial_leads_email (corporate_email),
    KEY idx_commercial_leads_created_at (created_at),
    KEY idx_commercial_leads_type (lead_type),
    KEY idx_commercial_leads_user_id (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

CREATE TABLE IF NOT EXISTS service_bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_reference VARCHAR(30) NOT NULL UNIQUE,
    order_id INT NULL,
    customer_name VARCHAR(255) NOT NULL,
    customer_email VARCHAR(255) NOT NULL,
    customer_phone VARCHAR(50) NOT NULL,
    service_type ENUM('consultation', 'installation', 'maintenance') NOT NULL DEFAULT 'consultation',
    preferred_date DATE NOT NULL,
    preferred_time_slot VARCHAR(50) NOT NULL,
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

CREATE TABLE IF NOT EXISTS quote_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    lead_id INT NULL,
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
    applicable_discounts LONGTEXT DEFAULT NULL COMMENT 'JSON array',
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
    KEY idx_quote_requests_user_id (user_id),
    KEY idx_quote_requests_lead_id (lead_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (lead_id) REFERENCES commercial_leads(id) ON DELETE SET NULL,
    FOREIGN KEY (quoted_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
