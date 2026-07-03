-- Hostel Management System - Database Schema
-- Run this file in phpMyAdmin or MySQL CLI to create the database

CREATE DATABASE IF NOT EXISTS hostel_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE hostel_db;

-- Users table (admin & student login)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'student') NOT NULL DEFAULT 'student',
    status TINYINT(1) NOT NULL DEFAULT 1,
    approved TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Students profile
CREATE TABLE students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    roll_no VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    address TEXT,
    gender ENUM('Male', 'Female', 'Other') NOT NULL DEFAULT 'Male',
    course VARCHAR(100),
    year VARCHAR(50),
    guardian_name VARCHAR(100),
    guardian_phone VARCHAR(20),
    admission_date DATE,
    join_date DATE,
    status ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
    photo VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Rooms
CREATE TABLE rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_no VARCHAR(20) NOT NULL UNIQUE,
    floor VARCHAR(20),
    room_type ENUM('Single', 'Double', 'Triple', 'Dormitory') NOT NULL DEFAULT 'Double',
    capacity INT NOT NULL DEFAULT 2,
    occupancy INT NOT NULL DEFAULT 0,
    fee_per_month DECIMAL(10,2) NOT NULL DEFAULT 0,
    description TEXT,
    status ENUM('Available', 'Full', 'Maintenance') NOT NULL DEFAULT 'Available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Room allocations
CREATE TABLE room_allocations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    room_id INT NOT NULL,
    bed_no INT,
    allocation_date DATE NOT NULL,
    checkout_date DATE,
    status ENUM('Active', 'CheckedOut') NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Fees
CREATE TABLE fees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    total_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
    paid_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    due_amount DECIMAL(10,2) GENERATED ALWAYS AS (total_fee - paid_amount) STORED,
    payment_mode ENUM('Cash', 'Online', 'Cheque', 'DD') DEFAULT 'Cash',
    receipt_no VARCHAR(100),
    transaction_id VARCHAR(100) DEFAULT NULL,
    payment_date DATE,
    status ENUM('Pending', 'Partial', 'Paid') NOT NULL DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Attendance
CREATE TABLE attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    date DATE NOT NULL,
    status ENUM('Present', 'Absent') NOT NULL DEFAULT 'Present',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    UNIQUE KEY unique_attendance (student_id, date)
) ENGINE=InnoDB;

-- Complaints
CREATE TABLE complaints (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    category VARCHAR(100),
    description TEXT NOT NULL,
    attachment VARCHAR(255),
    status ENUM('Pending', 'Working', 'Resolved') NOT NULL DEFAULT 'Pending',
    admin_response TEXT,
    resolution_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Notices
CREATE TABLE notices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    priority ENUM('Normal', 'Urgent', 'Critical') NOT NULL DEFAULT 'Normal',
    publish_date DATE NOT NULL,
    expiry_date DATE,
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- Management staff
CREATE TABLE management_staff (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    designation VARCHAR(100) NOT NULL,
    description TEXT,
    icon VARCHAR(50) DEFAULT 'bi bi-person-badge',
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO management_staff (name, designation, description, icon, sort_order) VALUES
('Dr. K. Madhavi', 'Principal', 'YSR Engineering College, Proddatur', 'bi bi-person-badge', 1),
('Dr. A. Subba Rao', 'Warden', 'Hostel Administration', 'bi bi-shield-check', 2),
('Admin', 'Hostel Supervisor', 'Day-to-day management', 'bi bi-gear', 3);

-- Mess menu
CREATE TABLE mess_menu (
    id INT AUTO_INCREMENT PRIMARY KEY,
    day VARCHAR(20) NOT NULL,
    meal_type ENUM('Breakfast', 'Lunch', 'Evening Snacks', 'Dinner') NOT NULL,
    menu_items TEXT NOT NULL,
    date DATE,
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Contact messages
CREATE TABLE contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    subject VARCHAR(200),
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Gallery
CREATE TABLE gallery (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200),
    category VARCHAR(100),
    image VARCHAR(255) NOT NULL,
    status TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Activity log
CREATE TABLE activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Default admin user (password: password)
INSERT INTO users (username, email, password, role, status) VALUES
('admin', 'admin@hostel.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1);

-- Sample room data
INSERT INTO rooms (room_no, floor, room_type, capacity, occupancy, fee_per_month, description, status) VALUES
('101', 'Ground', 'Single', 1, 0, 5000.00, 'Single room with attached bathroom', 'Available'),
('102', 'Ground', 'Double', 2, 0, 4000.00, 'Double sharing room', 'Available'),
('103', 'Ground', 'Double', 2, 0, 4000.00, 'Double sharing room with balcony', 'Available'),
('201', 'First', 'Triple', 3, 0, 3000.00, 'Triple sharing standard room', 'Available'),
('202', 'First', 'Dormitory', 6, 0, 2000.00, 'Large dormitory with 6 beds', 'Available');

-- Sample mess menu
INSERT INTO mess_menu (day, meal_type, menu_items, date) VALUES
('Monday', 'Breakfast', 'Poha, Bread, Butter, Tea', '2026-06-30'),
('Monday', 'Lunch', 'Dal, Rice, Roti, Sabzi, Salad', '2026-06-30'),
('Monday', 'Evening Snacks', 'Samosa, Tea', '2026-06-30'),
('Monday', 'Dinner', 'Dal Makhani, Naan, Rice, Raita', '2026-06-30'),
('Tuesday', 'Breakfast', 'Aloo Paratha, Curd, Tea', '2026-07-01'),
('Tuesday', 'Lunch', 'Chole, Rice, Roti, Salad', '2026-07-01'),
('Tuesday', 'Evening Snacks', 'Vada Pav, Tea', '2026-07-01'),
('Tuesday', 'Dinner', 'Butter Chicken, Naan, Rice, Salad', '2026-07-01');

-- Sample notice
INSERT INTO notices (title, content, priority, publish_date, expiry_date, created_by) VALUES
('Welcome to Hostel Management System', 'This is the official hostel management portal. All students must check notices regularly.', 'Normal', '2026-06-30', '2026-12-31', 1);

-- Razorpay payment - add transaction_id column (run if upgrading existing DB)
-- ALTER TABLE fees ADD COLUMN transaction_id VARCHAR(100) DEFAULT NULL AFTER receipt_no;

-- Leaves
CREATE TABLE leaves (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    from_date DATE NOT NULL,
    to_date DATE NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('Pending', 'Approved', 'Rejected') NOT NULL DEFAULT 'Pending',
    admin_remark TEXT,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Vacated students archive
CREATE TABLE vacated_students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    original_id INT NOT NULL,
    original_user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    roll_no VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    address TEXT,
    gender ENUM('Male','Female','Other') NOT NULL DEFAULT 'Male',
    course VARCHAR(100),
    year VARCHAR(50),
    guardian_name VARCHAR(100),
    guardian_phone VARCHAR(20),
    admission_date DATE,
    join_date DATE,
    photo VARCHAR(255),
    last_room_no VARCHAR(20),
    vacate_reason TEXT NOT NULL,
    admin_remark TEXT,
    related_data LONGTEXT,
    vacated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Vacate requests
CREATE TABLE vacate_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('Pending', 'Approved', 'Rejected') NOT NULL DEFAULT 'Pending',
    admin_remark TEXT,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Room change requests
CREATE TABLE room_change_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    current_room_id INT NOT NULL,
    requested_room_id INT NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('Pending', 'Approved', 'Rejected') NOT NULL DEFAULT 'Pending',
    admin_remark TEXT,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (current_room_id) REFERENCES rooms(id) ON DELETE CASCADE,
    FOREIGN KEY (requested_room_id) REFERENCES rooms(id) ON DELETE CASCADE
) ENGINE=InnoDB;
