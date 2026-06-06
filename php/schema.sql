-- VendorBridge Database Schema
CREATE DATABASE IF NOT EXISTS vendorbridge CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE vendorbridge;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','procurement_officer','vendor','manager') NOT NULL DEFAULT 'vendor',
    company VARCHAR(150),
    phone VARCHAR(20),
    country VARCHAR(80),
    avatar VARCHAR(255),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Vendors table
CREATE TABLE IF NOT EXISTS vendors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    company_name VARCHAR(150) NOT NULL,
    contact_person VARCHAR(100),
    email VARCHAR(150) NOT NULL,
    phone VARCHAR(20),
    gst_number VARCHAR(20),
    category VARCHAR(80),
    address TEXT,
    country VARCHAR(80),
    rating DECIMAL(3,2) DEFAULT 0.00,
    status ENUM('active','inactive','pending') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- RFQs table
CREATE TABLE IF NOT EXISTS rfqs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rfq_number VARCHAR(30) UNIQUE NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    category VARCHAR(80),
    deadline DATE NOT NULL,
    budget DECIMAL(15,2),
    status ENUM('draft','open','closed','cancelled') DEFAULT 'open',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- RFQ items
CREATE TABLE IF NOT EXISTS rfq_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rfq_id INT NOT NULL,
    item_name VARCHAR(200) NOT NULL,
    description TEXT,
    quantity DECIMAL(10,2) NOT NULL,
    unit VARCHAR(30),
    FOREIGN KEY (rfq_id) REFERENCES rfqs(id) ON DELETE CASCADE
);

-- RFQ vendor assignments
CREATE TABLE IF NOT EXISTS rfq_vendors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rfq_id INT NOT NULL,
    vendor_id INT NOT NULL,
    invited_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (rfq_id, vendor_id),
    FOREIGN KEY (rfq_id) REFERENCES rfqs(id) ON DELETE CASCADE,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE
);

-- Quotations
CREATE TABLE IF NOT EXISTS quotations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quotation_number VARCHAR(30) UNIQUE NOT NULL,
    rfq_id INT NOT NULL,
    vendor_id INT NOT NULL,
    total_amount DECIMAL(15,2) NOT NULL,
    delivery_days INT,
    validity_days INT DEFAULT 30,
    notes TEXT,
    status ENUM('submitted','under_review','selected','rejected') DEFAULT 'submitted',
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (rfq_id) REFERENCES rfqs(id),
    FOREIGN KEY (vendor_id) REFERENCES vendors(id)
);

-- Quotation line items
CREATE TABLE IF NOT EXISTS quotation_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quotation_id INT NOT NULL,
    rfq_item_id INT,
    item_name VARCHAR(200) NOT NULL,
    quantity DECIMAL(10,2),
    unit_price DECIMAL(12,2) NOT NULL,
    total_price DECIMAL(15,2) NOT NULL,
    FOREIGN KEY (quotation_id) REFERENCES quotations(id) ON DELETE CASCADE,
    FOREIGN KEY (rfq_item_id) REFERENCES rfq_items(id) ON DELETE SET NULL
);

-- Approvals
CREATE TABLE IF NOT EXISTS approvals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quotation_id INT NOT NULL,
    approver_id INT NOT NULL,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    remarks TEXT,
    action_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (quotation_id) REFERENCES quotations(id),
    FOREIGN KEY (approver_id) REFERENCES users(id)
);

-- Purchase Orders
CREATE TABLE IF NOT EXISTS purchase_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    po_number VARCHAR(30) UNIQUE NOT NULL,
    quotation_id INT NOT NULL,
    rfq_id INT NOT NULL,
    vendor_id INT NOT NULL,
    subtotal DECIMAL(15,2) NOT NULL,
    tax_percent DECIMAL(5,2) DEFAULT 18.00,
    tax_amount DECIMAL(15,2),
    total_amount DECIMAL(15,2) NOT NULL,
    delivery_date DATE,
    status ENUM('pending','confirmed','delivered','cancelled') DEFAULT 'pending',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (quotation_id) REFERENCES quotations(id),
    FOREIGN KEY (rfq_id) REFERENCES rfqs(id),
    FOREIGN KEY (vendor_id) REFERENCES vendors(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Invoices
CREATE TABLE IF NOT EXISTS invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(30) UNIQUE NOT NULL,
    po_id INT NOT NULL,
    vendor_id INT NOT NULL,
    subtotal DECIMAL(15,2),
    cgst DECIMAL(15,2) DEFAULT 0,
    sgst DECIMAL(15,2) DEFAULT 0,
    igst DECIMAL(15,2) DEFAULT 0,
    total_amount DECIMAL(15,2) NOT NULL,
    due_date DATE,
    status ENUM('draft','sent','paid','overdue') DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (po_id) REFERENCES purchase_orders(id),
    FOREIGN KEY (vendor_id) REFERENCES vendors(id)
);

-- Activity Logs
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50),
    entity_id INT,
    description TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ===== SEED DATA =====

-- Users (password: Password123 hashed)
INSERT INTO users (name, email, password, role, company, phone, country) VALUES
('Admin User', 'admin@vendorbridge.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'VendorBridge Corp', '+91-9000000001', 'India'),
('Arjun Mehta', 'officer@vendorbridge.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'procurement_officer', 'VendorBridge Corp', '+91-9000000002', 'India'),
('Priya Sharma', 'manager@vendorbridge.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'manager', 'VendorBridge Corp', '+91-9000000003', 'India'),
('TechCore Ltd', 'vendor1@techcore.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'vendor', 'TechCore Ltd', '+91-9000000004', 'India'),
('Infra Supplies Co', 'vendor2@infrasupplies.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'vendor', 'Infra Supplies Co', '+91-9000000005', 'India'),
('FurnPro India', 'vendor3@furnpro.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'vendor', 'FurnPro India', '+91-9000000006', 'India');

-- Vendors
INSERT INTO vendors (user_id, company_name, contact_person, email, phone, gst_number, category, address, country, rating, status) VALUES
(4, 'TechCore Ltd', 'Raj Patel', 'vendor1@techcore.com', '+91-9000000004', '22AAAAA0000A1Z5', 'IT Hardware', '301, Tech Park, Ahmedabad', 'India', 4.5, 'active'),
(5, 'Infra Supplies Co', 'Meena Joshi', 'vendor2@infrasupplies.com', '+91-9000000005', '24BBBBB0000B1Z3', 'Furniture', '12, Industrial Area, Surat', 'India', 4.2, 'active'),
(6, 'FurnPro India', 'Vikram Singh', 'vendor3@furnpro.com', '+91-9000000006', '27CCCCC0000C1Z1', 'Furniture', '45, Maker Marg, Mumbai', 'India', 3.8, 'active'),
(NULL, 'Stationary World', 'Anita Rao', 'statworld@example.com', '+91-9000000007', '29DDDDD0000D1Z9', 'Stationery', '22, MG Road, Bangalore', 'India', 4.0, 'active');

-- RFQs
INSERT INTO rfqs (rfq_number, title, description, category, deadline, budget, status, created_by) VALUES
('RFQ-2025-001', 'Office Furniture Procurement Q2', 'Procurement of office chairs, tables and storage units for new floor', 'Furniture', '2025-07-15', 185000.00, 'open', 2),
('RFQ-2025-002', 'IT Hardware Refresh', 'Laptops, monitors and networking equipment for engineering team', 'IT Hardware', '2025-07-20', 450000.00, 'open', 2),
('RFQ-2025-003', 'Annual Stationery Supply', 'Pens, paper, files and other office stationery for full year', 'Stationery', '2025-07-10', 35000.00, 'closed', 2);

-- RFQ Items
INSERT INTO rfq_items (rfq_id, item_name, description, quantity, unit) VALUES
(1, 'Office Chairs', 'Ergonomic office chairs with lumbar support', 20, 'Nos'),
(1, 'Work Desks', 'L-shaped work desks 1.5m x 1.2m', 10, 'Nos'),
(1, 'Storage Cabinets', '3-door storage cabinets with lock', 5, 'Nos'),
(2, 'Laptops', 'Core i7, 16GB RAM, 512GB SSD', 15, 'Nos'),
(2, 'Monitors', '27 inch 4K display monitors', 20, 'Nos'),
(2, 'Network Switch', '48-port gigabit managed switch', 2, 'Nos'),
(3, 'A4 Paper Reams', '80gsm A4 size paper', 100, 'Reams'),
(3, 'Pens Box', 'Blue ballpoint pens', 50, 'Box');

-- RFQ Vendor Assignments
INSERT INTO rfq_vendors (rfq_id, vendor_id) VALUES
(1, 2),(1, 3),
(2, 1),
(3, 4);

-- Quotations
INSERT INTO quotations (quotation_number, rfq_id, vendor_id, total_amount, delivery_days, validity_days, notes, status) VALUES
('QT-2025-001', 1, 2, 172000.00, 14, 30, 'Premium quality furniture with 2yr warranty. Free installation included.', 'selected'),
('QT-2025-002', 1, 3, 165000.00, 21, 30, 'Budget friendly options available. Delivery in 3 weeks.', 'rejected'),
('QT-2025-003', 2, 1, 438000.00, 7, 30, 'Latest models with 3yr onsite warranty. Express delivery possible.', 'submitted'),
('QT-2025-004', 3, 4, 32500.00, 3, 30, 'All items in stock. Ready for immediate dispatch.', 'submitted');

-- Quotation Items
INSERT INTO quotation_items (quotation_id, rfq_item_id, item_name, quantity, unit_price, total_price) VALUES
(1, 1, 'Office Chairs', 20, 4500.00, 90000.00),
(1, 2, 'Work Desks', 10, 7000.00, 70000.00),
(1, 3, 'Storage Cabinets', 5, 2400.00, 12000.00),
(2, 1, 'Office Chairs', 20, 4000.00, 80000.00),
(2, 2, 'Work Desks', 10, 6500.00, 65000.00),
(2, 3, 'Storage Cabinets', 5, 4000.00, 20000.00),
(3, 4, 'Laptops', 15, 24000.00, 360000.00),
(3, 5, 'Monitors', 20, 3200.00, 64000.00),
(3, 6, 'Network Switch', 2, 7000.00, 14000.00),
(4, 7, 'A4 Paper Reams', 100, 280.00, 28000.00),
(4, 8, 'Pens Box', 50, 90.00, 4500.00);

-- Approvals
INSERT INTO approvals (quotation_id, approver_id, status, remarks, action_at) VALUES
(1, 3, 'approved', 'Quality and pricing are acceptable. Proceed with PO.', '2025-06-01 10:30:00'),
(2, 3, 'rejected', 'Delivery timeline too long. Quality concerns raised.', '2025-06-01 10:35:00'),
(3, 3, 'pending', NULL, NULL);

-- Purchase Orders
INSERT INTO purchase_orders (po_number, quotation_id, rfq_id, vendor_id, subtotal, tax_percent, tax_amount, total_amount, delivery_date, status, created_by) VALUES
('PO-2025-001', 1, 1, 2, 172000.00, 18.00, 30960.00, 202960.00, '2025-06-20', 'confirmed', 2);

-- Invoices
INSERT INTO invoices (invoice_number, po_id, vendor_id, subtotal, cgst, sgst, igst, total_amount, due_date, status) VALUES
('INV-2025-001', 1, 2, 172000.00, 15480.00, 15480.00, 0.00, 202960.00, '2025-07-05', 'sent');

-- Activity Logs
INSERT INTO activity_logs (user_id, action, entity_type, entity_id, description) VALUES
(2, 'RFQ_CREATED', 'rfq', 1, 'Created RFQ: Office Furniture Procurement Q2'),
(2, 'RFQ_CREATED', 'rfq', 2, 'Created RFQ: IT Hardware Refresh'),
(4, 'QUOTATION_SUBMITTED', 'quotation', 1, 'Infra Supplies Co submitted quotation QT-2025-001'),
(6, 'QUOTATION_SUBMITTED', 'quotation', 2, 'FurnPro India submitted quotation QT-2025-002'),
(3, 'QUOTATION_APPROVED', 'quotation', 1, 'Manager approved QT-2025-001 for Office Furniture RFQ'),
(2, 'PO_GENERATED', 'purchase_order', 1, 'Purchase Order PO-2025-001 generated from approved quotation'),
(2, 'INVOICE_GENERATED', 'invoice', 1, 'Invoice INV-2025-001 generated from PO-2025-001');
