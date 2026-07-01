-- HFST Fire Safety ERP Database Schema & Seed Data (PostgreSQL / Supabase compatible)

-- Drop tables if they exist to allow clean re-runs
DROP TABLE IF EXISTS invoices CASCADE;
DROP TABLE IF EXISTS delivery_challans CASCADE;
DROP TABLE IF EXISTS quote_versions CASCADE;
DROP TABLE IF EXISTS quotes CASCADE;
DROP TABLE IF EXISTS stock_ledger CASCADE;
DROP TABLE IF EXISTS batches CASCADE;
DROP TABLE IF EXISTS serialized_assets CASCADE;
DROP TABLE IF EXISTS warehouses CASCADE;
DROP TABLE IF EXISTS items CASCADE;
DROP TABLE IF EXISTS projects CASCADE;

-- 1. Projects
CREATE TABLE projects (
    id VARCHAR(100) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    client_name VARCHAR(255) NOT NULL,
    location TEXT NOT NULL,
    starting_date DATE,
    is_refilling_project BOOLEAN DEFAULT FALSE,
    is_refilling_reminders BOOLEAN DEFAULT FALSE,
    contact_person VARCHAR(255),
    contact_number VARCHAR(100),
    supplied_items TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 2. Items
CREATE TABLE items (
    id VARCHAR(100) PRIMARY KEY,
    sku VARCHAR(100) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    type VARCHAR(50) NOT NULL CHECK (type IN ('Serialized', 'BatchManaged', 'Standard', 'KitAssembly')),
    unit VARCHAR(50) DEFAULT 'Pcs',
    initial_quantity DECIMAL(12, 4) DEFAULT 0.0000,
    average_consumption_rate DECIMAL(12, 4) DEFAULT 0.0000,
    lead_time_days INTEGER DEFAULT 30,
    selling_price DECIMAL(15, 4) DEFAULT 0.0000,
    purchase_price DECIMAL(15, 4) DEFAULT 0.0000,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 3. Warehouses
CREATE TABLE warehouses (
    id VARCHAR(100) PRIMARY KEY,
    name VARCHAR(255) UNIQUE NOT NULL,
    location TEXT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 4. Serialized Assets (NFPA 72 Panels, etc.)
CREATE TABLE serialized_assets (
    serial_number VARCHAR(100) PRIMARY KEY,
    item_id VARCHAR(100) NOT NULL REFERENCES items(id) ON DELETE RESTRICT,
    status VARCHAR(50) DEFAULT 'In-Stock',
    warranty_expiry_date DATE,
    project_id VARCHAR(100) REFERENCES projects(id) ON DELETE RESTRICT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 5. Batches / Lots (Clean Agents, Powders, etc.)
CREATE TABLE batches (
    batch_number VARCHAR(100) PRIMARY KEY,
    item_id VARCHAR(100) NOT NULL REFERENCES items(id) ON DELETE RESTRICT,
    qty DECIMAL(12, 4) DEFAULT 0.0000,
    manufacturing_date DATE NOT NULL,
    expiry_date DATE NOT NULL,
    hydrostatic_test_due_date DATE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 6. Stock Ledger (Double-Entry Log)
CREATE TABLE stock_ledger (
    id VARCHAR(100) PRIMARY KEY,
    item_id VARCHAR(100) NOT NULL REFERENCES items(id) ON DELETE RESTRICT,
    source_warehouse_id VARCHAR(100) REFERENCES warehouses(id) ON DELETE RESTRICT,
    dest_warehouse_id VARCHAR(100) REFERENCES warehouses(id) ON DELETE RESTRICT,
    qty DECIMAL(12, 4) NOT NULL,
    type VARCHAR(50) NOT NULL, -- e.g., 'Stock In', 'Stock Out', 'Transfer'
    date DATE NOT NULL,
    serial VARCHAR(100), -- References serial_number if item is Serialized
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 7. Quotes
CREATE TABLE quotes (
    id VARCHAR(100) PRIMARY KEY,
    project_id VARCHAR(100) NOT NULL REFERENCES projects(id) ON DELETE RESTRICT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 8. Quote Versions (Negotiation Tree)
CREATE TABLE quote_versions (
    id VARCHAR(100) PRIMARY KEY,
    quote_id VARCHAR(100) NOT NULL REFERENCES quotes(id) ON DELETE RESTRICT,
    version_number INTEGER NOT NULL,
    status VARCHAR(50) NOT NULL CHECK (status IN ('Draft', 'Submitted', 'Approved', 'Rejected', 'Expired', 'Superseded', 'Converted')),
    total DECIMAL(15, 4) NOT NULL,
    vat DECIMAL(15, 4) NOT NULL,
    date DATE NOT NULL,
    notes TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (quote_id, version_number)
);

-- 9. Delivery Challans
CREATE TABLE delivery_challans (
    id VARCHAR(100) PRIMARY KEY,
    project_id VARCHAR(100) NOT NULL REFERENCES projects(id) ON DELETE RESTRICT,
    item_id VARCHAR(100) NOT NULL REFERENCES items(id) ON DELETE RESTRICT,
    status VARCHAR(50) NOT NULL,
    shipped_qty DECIMAL(12, 4) NOT NULL,
    invoiced_qty DECIMAL(12, 4) NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 10. Invoices (Outstanding Payments)
CREATE TABLE invoices (
    id VARCHAR(100) PRIMARY KEY,
    project_id VARCHAR(100) REFERENCES projects(id) ON DELETE RESTRICT,
    challan_id VARCHAR(100) REFERENCES delivery_challans(id) ON DELETE RESTRICT,
    qty DECIMAL(12, 4) NOT NULL,
    price DECIMAL(15, 4) NOT NULL,
    vat_challan VARCHAR(100),
    status VARCHAR(50) NOT NULL,
    payment_method VARCHAR(100) DEFAULT 'Cash',
    payment_details TEXT,
    item_sku VARCHAR(100) REFERENCES items(sku),
    date DATE NOT NULL,
    due DATE NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);


-- ==========================================
-- SEED DATA INJECTION
-- ==========================================

-- Seed Projects
INSERT INTO projects (id, name, client_name, location) VALUES
('proj-1', 'High-Rise Tower Fire Sprinkler installation', 'DHAKA METROPOLIS DEVELOPERS LTD', 'Uttara, Dhaka'),
('proj-2', 'Bashundhara City mall Alarm Retrofit', 'BASHUNDHARA GROUP', 'Baridhara, Dhaka');

-- Seed Items
INSERT INTO items (id, sku, name, type, unit, initial_quantity, average_consumption_rate, lead_time_days, selling_price, purchase_price) VALUES
('item-1', 'FM200-CYL-120L', 'FM200 Clean Agent Cylinder 120L', 'BatchManaged', 'Pcs', 0.0000, 1.5000, 45, 125000.0000, 95000.0000),
('item-2', 'PANEL-NFX-72', 'Addressable Fire Alarm Control Panel', 'Serialized', 'Pcs', 0.0000, 0.2000, 60, 350000.0000, 280000.0000),
('item-3', 'SPRINK-HD-12', 'HD Brass Fire Sprinkler Head 1/2"', 'Standard', 'Pcs', 0.0000, 10.0000, 15, 750.0000, 450.0000);

-- Seed Warehouses
INSERT INTO warehouses (id, name, location, is_active) VALUES
('wh-1', 'Uttara Central Depot', 'Uttara, Dhaka', TRUE),
('wh-2', 'Chittagong Port Transit Warehouse', 'Halishahar, Chittagong', TRUE);

-- Seed Serialized Assets
INSERT INTO serialized_assets (serial_number, item_id, status, warranty_expiry_date, project_id) VALUES
('SN-NFX-9081', 'item-2', 'Installed', '2027-06-24', 'proj-1'),
('SN-NFX-9082', 'item-2', 'In-Stock', '2027-06-24', NULL),
('SN-NFX-9083', 'item-2', 'In-Stock', '2027-06-24', NULL);

-- Seed Batches
INSERT INTO batches (batch_number, item_id, qty, manufacturing_date, expiry_date, hydrostatic_test_due_date) VALUES
('BAT-FM200-01', 'item-1', 99.0000, '2025-01-10', '2030-01-10', '2029-06-15'),
('BAT-FM200-02', 'item-1', 50.0000, '2026-02-15', '2031-02-15', '2026-07-10');

-- Seed Stock Ledger
INSERT INTO stock_ledger (id, item_id, source_warehouse_id, dest_warehouse_id, qty, type, date, serial) VALUES
('l-1', 'item-1', NULL, 'wh-1', 100.0000, 'Stock In', '2026-06-24', NULL),
('l-2', 'item-2', NULL, 'wh-1', 15.0000, 'Stock In', '2026-06-24', NULL),
('l-3', 'item-2', 'wh-1', NULL, 1.0000, 'Stock Out', '2026-06-24', 'SN-NFX-9081');

-- Seed Quotes
INSERT INTO quotes (id, project_id) VALUES
('Q-2026-001', 'proj-1');

-- Seed Quote Versions
INSERT INTO quote_versions (id, quote_id, version_number, status, total, vat, date, notes) VALUES
('qv-1', 'Q-2026-001', 1, 'Superseded', 4500000.0000, 675000.0000, '2026-06-01', 'Initial client proposal estimate'),
('qv-2', 'Q-2026-001', 2, 'Superseded', 4200000.0000, 630000.0000, '2026-06-10', 'Discounted price offered'),
('qv-3', 'Q-2026-001', 3, 'Approved', 4150000.0000, 622500.0000, '2026-06-15', 'Final agreed contract rates');

-- Seed Delivery Challans
INSERT INTO delivery_challans (id, project_id, item_id, status, shipped_qty, invoiced_qty) VALUES
('CHALLAN-2026-001', 'proj-1', 'item-1', 'Delivered', 10.0000, 9.0000);

-- Seed Invoices
INSERT INTO invoices (id, project_id, challan_id, qty, price, vat_challan, status, date, due) VALUES
('INV-2026-001', 'proj-1', 'CHALLAN-2026-001', 6.0000, 125000.0000, 'NBR Mushak-6.3 #9081', 'Paid', '2026-06-16', '2026-07-16'),
('INV-2026-002', 'proj-1', 'CHALLAN-2026-001', 3.0000, 125000.0000, 'NBR Mushak-6.3 #9110', 'Unpaid', '2026-06-20', '2026-07-20');
