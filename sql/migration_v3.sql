-- V3 Migration: Hostel ERP Upgrade - Boys/Girls Hostel, Maintenance, Emergency, Analytics
USE hostel_db;

-- ============================================================
-- 1. Add hostel_type to wardens (boys/girls)
-- ============================================================
ALTER TABLE `wardens` ADD COLUMN IF NOT EXISTS `hostel_type` enum('boys','girls') DEFAULT NULL AFTER `shift`;

-- ============================================================
-- 2. Add hostel_type to students
-- ============================================================
ALTER TABLE `students` ADD COLUMN IF NOT EXISTS `hostel_type` enum('boys','girls') DEFAULT NULL AFTER `status`;

-- ============================================================
-- 3. Maintenance requests table
-- ============================================================
CREATE TABLE IF NOT EXISTS `maintenance_requests` (
  `id` int PRIMARY KEY AUTO_INCREMENT,
  `student_id` int DEFAULT NULL,
  `room_id` int DEFAULT NULL,
  `category` enum('Electrical','Plumbing','Furniture','Internet','Cleaning','Painting','Water Supply','Carpentry','Other') NOT NULL DEFAULT 'Other',
  `priority` enum('Low','Medium','High','Emergency') DEFAULT 'Medium',
  `description` text NOT NULL,
  `photos` text,
  `status` enum('Pending','Assigned','In Progress','Resolved','Closed','Rejected') NOT NULL DEFAULT 'Pending',
  `assigned_to` int DEFAULT NULL,
  `assigned_date` datetime DEFAULT NULL,
  `completed_date` datetime DEFAULT NULL,
  `completion_remarks` text,
  `escalated_to` int DEFAULT NULL,
  `escalated_reason` text,
  `escalated_at` datetime DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 4. Room maintenance history
-- ============================================================
CREATE TABLE IF NOT EXISTS `room_maintenance_history` (
  `id` int PRIMARY KEY AUTO_INCREMENT,
  `room_id` int NOT NULL,
  `problem` varchar(500) NOT NULL,
  `solution` text,
  `category` varchar(100) DEFAULT NULL,
  `completed_by` varchar(100) DEFAULT NULL,
  `remarks` text,
  `cost` decimal(10,2) DEFAULT 0,
  `repair_date` date NOT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 5. Emergency reports table
-- ============================================================
CREATE TABLE IF NOT EXISTS `emergency_reports` (
  `id` int PRIMARY KEY AUTO_INCREMENT,
  `category` enum('Medical Emergency','Fire','Security Issue','Power Failure','Water Leakage','Violence','Accident','Other') NOT NULL DEFAULT 'Other',
  `priority` enum('Low','Medium','High','Critical') NOT NULL DEFAULT 'Critical',
  `description` text NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `reporter_name` varchar(100) DEFAULT NULL,
  `reporter_phone` varchar(20) DEFAULT NULL,
  `reporter_id` int DEFAULT NULL,
  `assigned_to` int DEFAULT NULL,
  `status` enum('New','In Progress','Resolved','Closed') NOT NULL DEFAULT 'New',
  `resolution` text,
  `resolved_at` datetime DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`reporter_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 6. Student timeline events
-- ============================================================
CREATE TABLE IF NOT EXISTS `student_timeline` (
  `id` int PRIMARY KEY AUTO_INCREMENT,
  `student_id` int NOT NULL,
  `event_type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text,
  `reference_id` int DEFAULT NULL,
  `reference_table` varchar(50) DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 7. Backup history
-- ============================================================
CREATE TABLE IF NOT EXISTS `backup_history` (
  `id` int PRIMARY KEY AUTO_INCREMENT,
  `filename` varchar(255) NOT NULL,
  `filepath` varchar(500) NOT NULL,
  `filesize` bigint DEFAULT 0,
  `type` enum('manual','automatic') NOT NULL DEFAULT 'manual',
  `created_by` int DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 8. Student digital IDs
-- ============================================================
CREATE TABLE IF NOT EXISTS `student_digital_ids` (
  `id` int PRIMARY KEY AUTO_INCREMENT,
  `student_id` int NOT NULL UNIQUE,
  `id_number` varchar(50) NOT NULL UNIQUE,
  `qr_code` varchar(500) DEFAULT NULL,
  `blood_group` varchar(5) DEFAULT NULL,
  `emergency_contact` varchar(20) DEFAULT NULL,
  `emergency_name` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `issued_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 9. Emergency logs for timeline
-- ============================================================
CREATE TABLE IF NOT EXISTS `emergency_logs` (
  `id` int PRIMARY KEY AUTO_INCREMENT,
  `emergency_id` int NOT NULL,
  `action` varchar(100) NOT NULL,
  `performed_by` int DEFAULT NULL,
  `role` varchar(50) DEFAULT NULL,
  `remarks` text,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`emergency_id`) REFERENCES `emergency_reports` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 10. Indexes for performance
-- ============================================================
CREATE INDEX IF NOT EXISTS idx_students_hostel ON students(hostel_type);
CREATE INDEX IF NOT EXISTS idx_wardens_hostel ON wardens(hostel_type);
CREATE INDEX IF NOT EXISTS idx_maintenance_status ON maintenance_requests(status);
CREATE INDEX IF NOT EXISTS idx_maintenance_priority ON maintenance_requests(priority);
CREATE INDEX IF NOT EXISTS idx_emergency_status ON emergency_reports(status);
CREATE INDEX IF NOT EXISTS idx_student_timeline ON student_timeline(student_id, created_at);
