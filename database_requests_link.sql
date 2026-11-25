-- =====================================================================
-- สคริปต์สำหรับนำเข้าบน Plesk: ระบบคำขอและอนุมัติ + เชื่อมต่อ dashboard_member
-- =====================================================================
-- วิธีใช้งาน:
-- 1. รันสคริปต์นี้ในฐานข้อมูลใหม่ (ระบบคำขอและอนุมัติ) ผ่าน phpMyAdmin
-- 2. หากชื่อฐานข้อมูล dashboard_member บน Plesk มี prefix (เช่น u123_dashboard_member)
--    ให้แก้ไขชื่อตรง "dashboard_member" ให้ตรงกับชื่อจริงบน Plesk
-- =====================================================================

SET FOREIGN_KEY_CHECKS=0;

-- 1. ตารางคำขอ (Requests)
CREATE TABLE IF NOT EXISTS `requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_no` varchar(50) NOT NULL,
  `requester_id` int(11) DEFAULT NULL,
  `employee_id` varchar(50) NOT NULL,
  `title` varchar(20) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `department` varchar(100) NOT NULL,
  `position` varchar(100) NOT NULL,
  `property` varchar(100) NOT NULL,
  `hotel_id` int(2) DEFAULT NULL,
  `level_id` int(2) DEFAULT NULL,
  `manager_name` varchar(100) NOT NULL,
  `it_staff_id` int(11) DEFAULT NULL,
  `it_staff_name` varchar(100) DEFAULT NULL,
  `it_effective_date` date DEFAULT NULL,
  `it_expiry_date` date DEFAULT NULL,
  `it_deleted_date` date DEFAULT NULL,
  `it_notes` text DEFAULT NULL,
  `it_signature` longtext DEFAULT NULL,
  `it_completed_at` timestamp NULL DEFAULT NULL,
  `systems_data` longtext NOT NULL COMMENT 'JSON string of selected systems and options',
  `policy_agreed` tinyint(1) NOT NULL DEFAULT 1,
  `requester_signature` longtext DEFAULT NULL,
  `status` enum('pending_dept_head','pending_hotel_manager','pending_it_manager','pending_it_action','pending_hr','completed','rejected') NOT NULL DEFAULT 'pending_dept_head',
  `current_approver_role` varchar(50) DEFAULT 'dept_head',
  `rejected_by` varchar(100) DEFAULT NULL,
  `reject_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `request_no` (`request_no`),
  KEY `idx_status` (`status`),
  KEY `idx_request_no` (`request_no`),
  KEY `idx_emp_id` (`employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. ตารางการอนุมัติ (Approvals)
CREATE TABLE IF NOT EXISTS `approvals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` int(11) NOT NULL,
  `approver_role` enum('dept_head','hotel_manager','it_manager') NOT NULL,
  `approver_id` int(11) DEFAULT NULL,
  `approver_name` varchar(100) NOT NULL,
  `action` enum('approved','rejected') NOT NULL,
  `signature` longtext DEFAULT NULL COMMENT 'Base64 PNG signature',
  `comment` text DEFAULT NULL,
  `signed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `request_id` (`request_id`),
  CONSTRAINT `approvals_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. สร้าง VIEW เสมือน เชื่อมตรงไปยังฐานข้อมูล dashboard_member
CREATE OR REPLACE VIEW `ex_user` AS SELECT * FROM `dashboard_member`.`ex_user`;
CREATE OR REPLACE VIEW `ex_login` AS SELECT * FROM `dashboard_member`.`ex_login`;
CREATE OR REPLACE VIEW `ex_department` AS SELECT * FROM `dashboard_member`.`ex_department`;
CREATE OR REPLACE VIEW `ex_hotel` AS SELECT * FROM `dashboard_member`.`ex_hotel`;
CREATE OR REPLACE VIEW `ex_level` AS SELECT * FROM `dashboard_member`.`ex_level`;
CREATE OR REPLACE VIEW `ex_property` AS SELECT * FROM `dashboard_member`.`ex_property`;
CREATE OR REPLACE VIEW `ex_title` AS SELECT * FROM `dashboard_member`.`ex_title`;

SET FOREIGN_KEY_CHECKS=1;
