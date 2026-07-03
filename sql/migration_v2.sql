-- V2 Migration: Warden Module, RBAC, Escalation Workflow
USE hostel_db;

-- 1. Update users table role ENUM to include warden
ALTER TABLE `users` MODIFY COLUMN `role` enum('admin','warden','student') NOT NULL DEFAULT 'student';

-- 2. Create wardens table
CREATE TABLE IF NOT EXISTS `wardens` (
  `id` int PRIMARY KEY AUTO_INCREMENT,
  `user_id` int NOT NULL UNIQUE,
  `name` varchar(255) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `photo` varchar(500) DEFAULT NULL,
  `shift` varchar(50) DEFAULT 'Day',
  `status` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Add attendance columns
ALTER TABLE `attendance` ADD COLUMN IF NOT EXISTS `time` time DEFAULT NULL AFTER `date`;
ALTER TABLE `attendance` ADD COLUMN IF NOT EXISTS `status` enum('Present','Absent','Late','On Leave','Medical Leave','Weekend Leave','Outside Hostel') NOT NULL DEFAULT 'Present' AFTER `date`;
ALTER TABLE `attendance` ADD COLUMN IF NOT EXISTS `taken_by` int DEFAULT NULL AFTER `status`;
ALTER TABLE `attendance` ADD COLUMN IF NOT EXISTS `taken_role` varchar(20) DEFAULT NULL AFTER `taken_by`;
ALTER TABLE `attendance` ADD COLUMN IF NOT EXISTS `remarks` text DEFAULT NULL AFTER `taken_role`;
ALTER TABLE `attendance` ADD COLUMN IF NOT EXISTS `is_locked` tinyint(1) DEFAULT 0 AFTER `remarks`;

-- 4. Update complaints table
ALTER TABLE `complaints` ADD COLUMN IF NOT EXISTS `assigned_to` int DEFAULT NULL AFTER `student_id`;
ALTER TABLE `complaints` ADD COLUMN IF NOT EXISTS `assigned_role` varchar(20) DEFAULT NULL AFTER `assigned_to`;
ALTER TABLE `complaints` ADD COLUMN IF NOT EXISTS `priority` enum('Low','Medium','High','Emergency') DEFAULT 'Medium' AFTER `status`;
ALTER TABLE `complaints` ADD COLUMN IF NOT EXISTS `escalated_to` int DEFAULT NULL AFTER `priority`;
ALTER TABLE `complaints` ADD COLUMN IF NOT EXISTS `escalated_by` int DEFAULT NULL AFTER `escalated_to`;
ALTER TABLE `complaints` ADD COLUMN IF NOT EXISTS `escalation_reason` text DEFAULT NULL AFTER `escalated_by`;
ALTER TABLE `complaints` ADD COLUMN IF NOT EXISTS `escalated_at` datetime DEFAULT NULL AFTER `escalation_reason`;
ALTER TABLE `complaints` ADD COLUMN IF NOT EXISTS `resolved_by` int DEFAULT NULL AFTER `escalated_at`;
ALTER TABLE `complaints` ADD COLUMN IF NOT EXISTS `resolved_at` datetime DEFAULT NULL AFTER `resolved_by`;
ALTER TABLE `complaints` ADD COLUMN IF NOT EXISTS `resolution_notes` text DEFAULT NULL AFTER `resolved_at`;
ALTER TABLE `complaints` ADD COLUMN IF NOT EXISTS `auto_escalated` tinyint(1) DEFAULT 0 AFTER `resolution_notes`;

-- 5. Create complaint_logs table
CREATE TABLE IF NOT EXISTS `complaint_logs` (
  `id` int PRIMARY KEY AUTO_INCREMENT,
  `complaint_id` int NOT NULL,
  `action` varchar(100) NOT NULL,
  `performed_by` int DEFAULT NULL,
  `role` varchar(20) DEFAULT NULL,
  `remarks` text,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Update leaves table
ALTER TABLE `leaves` ADD COLUMN IF NOT EXISTS `warden_approved` tinyint(1) DEFAULT NULL AFTER `status`;
ALTER TABLE `leaves` ADD COLUMN IF NOT EXISTS `warden_id` int DEFAULT NULL AFTER `warden_approved`;
ALTER TABLE `leaves` ADD COLUMN IF NOT EXISTS `warden_remark` text DEFAULT NULL AFTER `warden_id`;
ALTER TABLE `leaves` ADD COLUMN IF NOT EXISTS `warden_action_at` datetime DEFAULT NULL AFTER `warden_remark`;
ALTER TABLE `leaves` ADD COLUMN IF NOT EXISTS `is_escalated` tinyint(1) DEFAULT 0 AFTER `warden_action_at`;
ALTER TABLE `leaves` ADD COLUMN IF NOT EXISTS `leave_type` enum('Regular','Emergency','Medical','Weekend') DEFAULT 'Regular' AFTER `reason`;
ALTER TABLE `leaves` ADD COLUMN IF NOT EXISTS `return_date` date DEFAULT NULL AFTER `to_date`;

-- 7. Create room_inspections table
CREATE TABLE IF NOT EXISTS `room_inspections` (
  `id` int PRIMARY KEY AUTO_INCREMENT,
  `room_id` int NOT NULL,
  `inspection_date` date NOT NULL,
  `inspector` varchar(255) DEFAULT NULL,
  `cleanliness_rating` tinyint DEFAULT NULL,
  `furniture_condition` tinyint DEFAULT NULL,
  `electrical_status` tinyint DEFAULT NULL,
  `plumbing_status` tinyint DEFAULT NULL,
  `damages` text,
  `photos` text,
  `remarks` text,
  `result` enum('Pass','Fail','Needs Improvement') DEFAULT 'Pass',
  `created_by` int DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Create daily_reports table
CREATE TABLE IF NOT EXISTS `daily_reports` (
  `id` int PRIMARY KEY AUTO_INCREMENT,
  `report_date` date NOT NULL,
  `attendance_summary` text,
  `complaints_summary` text,
  `visitors_summary` text,
  `sick_students` text,
  `maintenance_issues` text,
  `discipline_cases` text,
  `important_notes` text,
  `submitted_by` int DEFAULT NULL,
  `submitted_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. Create discipline_records table
CREATE TABLE IF NOT EXISTS `discipline_records` (
  `id` int PRIMARY KEY AUTO_INCREMENT,
  `student_id` int NOT NULL,
  `room_id` int DEFAULT NULL,
  `warning_type` enum('Verbal','Written','Final','Fine') DEFAULT 'Verbal',
  `misconduct_type` varchar(100) DEFAULT NULL,
  `fine_amount` decimal(10,2) DEFAULT 0,
  `action_taken` text,
  `remarks` text,
  `attachments` text,
  `recorded_by` int DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10. Create medical_records table
CREATE TABLE IF NOT EXISTS `medical_records` (
  `id` int PRIMARY KEY AUTO_INCREMENT,
  `student_id` int NOT NULL,
  `disease` varchar(255) DEFAULT NULL,
  `symptoms` text,
  `medicine` text,
  `doctor` varchar(255) DEFAULT NULL,
  `hospital` varchar(255) DEFAULT NULL,
  `visit_date` date NOT NULL,
  `emergency_contact` varchar(50) DEFAULT NULL,
  `remarks` text,
  `recorded_by` int DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 11. Create night_roll_call table
CREATE TABLE IF NOT EXISTS `night_roll_call` (
  `id` int PRIMARY KEY AUTO_INCREMENT,
  `student_id` int NOT NULL,
  `date` date NOT NULL,
  `status` enum('Present','Absent','Outside','Leave','Late Return') NOT NULL DEFAULT 'Present',
  `remarks` text,
  `recorded_by` int DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_night_roll_student_date` (`student_id`,`date`),
  FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 12. Create audit_logs table
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `role` varchar(20) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `module` varchar(100) DEFAULT NULL,
  `description` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 13. Update visitor_logs table
ALTER TABLE `visitor_logs` ADD COLUMN IF NOT EXISTS `photo` varchar(500) DEFAULT NULL AFTER `id_proof`;
ALTER TABLE `visitor_logs` ADD COLUMN IF NOT EXISTS `government_id` varchar(255) DEFAULT NULL AFTER `photo`;
ALTER TABLE `visitor_logs` ADD COLUMN IF NOT EXISTS `warden_approved` tinyint(1) DEFAULT NULL AFTER `government_id`;
ALTER TABLE `visitor_logs` ADD COLUMN IF NOT EXISTS `status` enum('Pending','Approved','Rejected','Checked In','Checked Out') DEFAULT 'Pending' AFTER `warden_approved`;

-- 14. Indexes for performance
CREATE INDEX IF NOT EXISTS idx_attendance_locked ON attendance(is_locked);
CREATE INDEX IF NOT EXISTS idx_attendance_taken ON attendance(taken_by);
CREATE INDEX IF NOT EXISTS idx_complaints_priority ON complaints(priority);
CREATE INDEX IF NOT EXISTS idx_complaints_escalated ON complaints(escalated_to);
CREATE INDEX IF NOT EXISTS idx_complaints_assigned ON complaints(assigned_to);
CREATE INDEX IF NOT EXISTS idx_audit_logs_user ON audit_logs(user_id);
CREATE INDEX IF NOT EXISTS idx_audit_logs_action ON audit_logs(action, created_at);
CREATE INDEX IF NOT EXISTS idx_night_roll_date ON night_roll_call(date);
CREATE INDEX IF NOT EXISTS idx_discipline_student ON discipline_records(student_id);
CREATE INDEX IF NOT EXISTS idx_medical_student ON medical_records(student_id);
CREATE INDEX IF NOT EXISTS idx_inspection_room ON room_inspections(room_id, inspection_date);
