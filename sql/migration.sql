-- Migration: New tables for enhanced features
-- Run this to add new tables without affecting existing ones

USE hostel_db;

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int PRIMARY KEY AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','success','warning','danger') DEFAULT 'info',
  `link` varchar(500) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `hostel_events` (
  `id` int PRIMARY KEY AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text,
  `event_date` date NOT NULL,
  `event_time` time DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `image` varchar(500) DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `testimonials` (
  `id` int PRIMARY KEY AUTO_INCREMENT,
  `student_id` int DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `rating` tinyint DEFAULT 5,
  `photo` varchar(500) DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `faq` (
  `id` int PRIMARY KEY AUTO_INCREMENT,
  `question` text NOT NULL,
  `answer` text NOT NULL,
  `category` varchar(100) DEFAULT 'General',
  `sort_order` int DEFAULT 0,
  `status` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `visitor_logs` (
  `id` int PRIMARY KEY AUTO_INCREMENT,
  `visitor_name` varchar(255) NOT NULL,
  `contact` varchar(50) DEFAULT NULL,
  `purpose` varchar(255) DEFAULT NULL,
  `student_id` int DEFAULT NULL,
  `check_in` datetime NOT NULL,
  `check_out` datetime DEFAULT NULL,
  `id_proof` varchar(255) DEFAULT NULL,
  `remarks` text,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `maintenance_requests` (
  `id` int PRIMARY KEY AUTO_INCREMENT,
  `room_id` int DEFAULT NULL,
  `student_id` int DEFAULT NULL,
  `issue_type` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `priority` enum('Low','Medium','High','Emergency') DEFAULT 'Medium',
  `status` enum('Pending','In Progress','Completed','Cancelled') DEFAULT 'Pending',
  `assigned_to` int DEFAULT NULL,
  `completion_date` datetime DEFAULT NULL,
  `remarks` text,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `inventory` (
  `id` int PRIMARY KEY AUTO_INCREMENT,
  `item_name` varchar(255) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `quantity` int DEFAULT 0,
  `unit` varchar(50) DEFAULT 'piece',
  `condition_status` enum('Good','Fair','Poor','Damaged') DEFAULT 'Good',
  `room_id` int DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `purchase_cost` decimal(10,2) DEFAULT 0,
  `notes` text,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `lost_found` (
  `id` int PRIMARY KEY AUTO_INCREMENT,
  `item_name` varchar(255) NOT NULL,
  `description` text,
  `category` varchar(100) DEFAULT NULL,
  `status` enum('Lost','Found','Claimed','Returned') DEFAULT 'Lost',
  `location` varchar(255) DEFAULT NULL,
  `date_reported` date DEFAULT NULL,
  `reported_by` int DEFAULT NULL,
  `claimed_by` int DEFAULT NULL,
  `photo` varchar(500) DEFAULT NULL,
  `remarks` text,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`reported_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`claimed_by`) REFERENCES `students` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `student_feedback` (
  `id` int PRIMARY KEY AUTO_INCREMENT,
  `student_id` int NOT NULL,
  `category` varchar(100) NOT NULL,
  `rating` tinyint NOT NULL,
  `feedback` text,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `meal_ratings` (
  `id` int PRIMARY KEY AUTO_INCREMENT,
  `student_id` int NOT NULL,
  `menu_id` int DEFAULT NULL,
  `meal_date` date NOT NULL,
  `meal_type` varchar(50) NOT NULL,
  `rating` tinyint NOT NULL,
  `feedback` text,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` int PRIMARY KEY AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempted_at` timestamp DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` int PRIMARY KEY AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT 0,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add new columns to students table if they don't exist
ALTER TABLE `students` ADD COLUMN IF NOT EXISTS `emergency_contact` varchar(50) DEFAULT NULL AFTER `guardian_phone`;
ALTER TABLE `students` ADD COLUMN IF NOT EXISTS `emergency_contact_name` varchar(255) DEFAULT NULL AFTER `guardian_phone`;
ALTER TABLE `students` ADD COLUMN IF NOT EXISTS `blood_group` varchar(10) DEFAULT NULL AFTER `gender`;
ALTER TABLE `students` ADD COLUMN IF NOT EXISTS `medical_info` text DEFAULT NULL AFTER `blood_group`;
ALTER TABLE `students` ADD COLUMN IF NOT EXISTS `department` varchar(100) DEFAULT NULL AFTER `course`;
ALTER TABLE `students` ADD COLUMN IF NOT EXISTS `admission_year` year DEFAULT NULL AFTER `join_date`;
ALTER TABLE `students` ADD COLUMN IF NOT EXISTS `documents` text DEFAULT NULL AFTER `photo`;
ALTER TABLE `students` ADD COLUMN IF NOT EXISTS `id_card` varchar(500) DEFAULT NULL AFTER `photo`;

-- Add indexes for performance
CREATE INDEX IF NOT EXISTS idx_notifications_user ON notifications(user_id, is_read);
CREATE INDEX IF NOT EXISTS idx_notifications_created ON notifications(created_at);
CREATE INDEX IF NOT EXISTS idx_events_date ON hostel_events(event_date);
CREATE INDEX IF NOT EXISTS idx_visitor_checkin ON visitor_logs(check_in);
CREATE INDEX IF NOT EXISTS idx_maintenance_status ON maintenance_requests(status);
CREATE INDEX IF NOT EXISTS idx_login_attempts ON login_attempts(username, ip_address, attempted_at);
CREATE INDEX IF NOT EXISTS idx_feedback_student ON student_feedback(student_id);
CREATE INDEX IF NOT EXISTS idx_meal_ratings_date ON meal_ratings(meal_date);
