-- Mainsystem Installation Data SQL Script
-- This script populates essential initial data.
-- Run this AFTER running install_table.sql

-- --------------------------------------------------------
-- Default Roles
-- --------------------------------------------------------
INSERT IGNORE INTO `roles` (`role_key`, `role_name`, `role_description`, `is_system_role`, `created_at`) VALUES
('admin', 'Administrator', 'Has full system access and all permissions.', TRUE, NOW()),
('editor', 'Editor', 'Can manage content and specific sections.', FALSE, NOW()),
('user', 'User', 'Standard user with basic access.', FALSE, NOW());

-- --------------------------------------------------------
-- Default Admin User
-- --------------------------------------------------------
INSERT IGNORE INTO `users` (`user_id`, `user_login`, `user_pass`, `user_nicename`, `user_email`, `user_registered`, `user_status`, `display_name`, `user_role`) VALUES
(1, 'admin', '$2y$10$DcqROgHtfmKP96yjbg1VGeEnWa8APzrpxFcGHf5SoZZ4iEAf5bNSe', 'admin', 'admin@example.com', NOW(), 0, 'Site Administrator', 'admin');

-- --------------------------------------------------------
-- Default Site Options
-- These should align with defaults in config.php or desired initial values.
-- --------------------------------------------------------
INSERT IGNORE INTO `options` (`option_name`, `option_value`, `autoload`) VALUES
('site_name', 'Mainsystem', 'yes'),
('site_tagline', 'Your Company Core System', 'yes'),
('admin_email', 'admin@example.com', 'yes'), -- General admin email
('items_per_page', '10', 'yes'),
('site_description', 'Welcome to the Mainsystem application.', 'yes'),
('maintenance_mode', 'off', 'yes'),
('site_debug_mode', 'off', 'yes'), -- Added for debug monitor
('site_time_format', 'Y-m-d H:i', 'yes'), 
('site_email_notifications_enabled', 'on', 'yes'), 
('site_email_from', 'noreply@example.com', 'yes'), 
('site_admin_email_notifications', 'admin@example.com', 'yes');

-- --------------------------------------------------------
-- Default Role Permissions
-- Assign all defined capabilities to the 'admin' role.
-- Basic capabilities for 'user' and 'editor' roles.
-- This list should match all keys from the CAPABILITIES constant in config.php for the admin role.
-- --------------------------------------------------------

-- Admin Role Permissions (should have all capabilities)
INSERT IGNORE INTO `role_permissions` (`role_name`, `capability_key`) VALUES
('admin', 'ACCESS_ADMIN_PANEL'),
('admin', 'MANAGE_USERS'),
('admin', 'MANAGE_ROLES'),
('admin', 'MANAGE_ROLES_PERMISSIONS'),
('admin', 'MANAGE_DEPARTMENTS'),
('admin', 'MANAGE_SITE_SETTINGS'),
('admin', 'VIEW_REPORTS'),
('admin', 'VIEW_SYSTEM_INFO'), -- Added System Info Capability

('admin', 'MANAGE_ROOMS'), 
('admin', 'VIEW_ROOMS'),
('admin', 'CREATE_ROOMS'),
('admin', 'EDIT_ROOMS'),
('admin', 'DELETE_ROOMS'),

('admin', 'CREATE_ROOM_RESERVATIONS'),
('admin', 'EDIT_OWN_ROOM_RESERVATIONS'),
('admin', 'CANCEL_OWN_ROOM_RESERVATIONS'),
('admin', 'VIEW_ALL_ROOM_RESERVATIONS'),
('admin', 'APPROVE_DENY_ROOM_RESERVATIONS'),
('admin', 'EDIT_ANY_ROOM_RESERVATION'),
('admin', 'DELETE_ANY_ROOM_RESERVATION'),

('admin', 'VIEW_VEHICLES'),
('admin', 'CREATE_VEHICLES'),
('admin', 'EDIT_VEHICLES'),
('admin', 'DELETE_VEHICLES'),

('admin', 'CREATE_VEHICLE_RESERVATIONS'),
('admin', 'EDIT_OWN_VEHICLE_RESERVATIONS'),
('admin', 'CANCEL_OWN_VEHICLE_RESERVATIONS'),
('admin', 'VIEW_ALL_VEHICLE_RESERVATIONS'),
('admin', 'APPROVE_DENY_VEHICLE_RESERVATIONS'),
('admin', 'EDIT_ANY_VEHICLE_RESERVATION'),
('admin', 'DELETE_ANY_VEHICLE_RESERVATION'),

('admin', 'MANAGE_IT_REQUESTS'),
('admin', 'MANAGE_RAP_CALENDAR'),
('admin', 'MANAGE_SES_DATA'),
('admin', 'MANAGE_DTR'),
('admin', 'MANAGE_ASSETS');

-- User Role Permissions (basic access)
INSERT IGNORE INTO `role_permissions` (`role_name`, `capability_key`) VALUES
('user', 'CREATE_ROOM_RESERVATIONS'), 
('user', 'CANCEL_OWN_ROOM_RESERVATIONS'), 
('user', 'VIEW_ROOMS'), 
('user', 'VIEW_VEHICLES'), 
('user', 'CREATE_VEHICLE_RESERVATIONS'),
('user', 'CANCEL_OWN_VEHICLE_RESERVATIONS');

-- Editor Role Permissions (example - adjust as needed)
INSERT IGNORE INTO `role_permissions` (`role_name`, `capability_key`) VALUES
('editor', 'VIEW_ROOMS'),
('editor', 'CREATE_ROOM_RESERVATIONS'),
('editor', 'CANCEL_OWN_ROOM_RESERVATIONS'),
('editor', 'VIEW_ALL_ROOM_RESERVATIONS'), -- Example: Editor can see all room reservations
('editor', 'APPROVE_DENY_ROOM_RESERVATIONS'), -- Example: Editor can approve/deny room reservations
('editor', 'VIEW_VEHICLES'),
('editor', 'CREATE_VEHICLE_RESERVATIONS'),
('editor', 'CANCEL_OWN_VEHICLE_RESERVATIONS'),
('editor', 'VIEW_REPORTS'); 

-- --------------------------------------------------------
-- Sample Departments
-- --------------------------------------------------------
INSERT IGNORE INTO `departments` (`department_name`, `department_description`, `created_at`) VALUES
('General Management', 'Oversees all company operations.', NOW()),
('Human Resources', 'Handles employee relations, recruitment, and benefits.', NOW()),
('IT Department', 'Manages technology infrastructure and support.', NOW()),
('Finance', 'Manages company finances and accounting.', NOW()),
('Operations', 'Handles day-to-day operational activities.', NOW());

-- Assign the default admin user (ID 1) to a department (e.g., General Management)
-- Assuming 'General Management' gets ID 1 if it's the first.
UPDATE `users` SET `department_id` = (SELECT `department_id` FROM `departments` WHERE `department_name` = 'General Management' LIMIT 1) WHERE `user_id` = 1;


-- --------------------------------------------------------
-- Sample Room
-- --------------------------------------------------------
INSERT INTO `objects` (`object_author`, `object_date`, `object_date_gmt`, `object_content`, `object_title`, `object_excerpt`, `object_status`, `comment_status`, `ping_status`, `object_password`, `object_name`, `object_modified`, `object_modified_gmt`, `object_parent`, `guid`, `menu_order`, `object_type`, `object_mime_type`) VALUES
(1, NOW(), NOW(), 'A standard meeting room with a projector and whiteboard, suitable for up to 10 people.', 'Conference Room Alpha', 'Standard meeting room for 10.', 'available', 'closed', 'closed', '', 'conference-room-alpha', NOW(), NOW(), 0, '', 0, 'room', '');

-- For simplicity, if this is the VERY FIRST object in the 'objects' table, its ID will be 1.
INSERT INTO `objectmeta` (`object_id`, `meta_key`, `meta_value`) VALUES
(1, 'room_capacity', '10'),
(1, 'room_location', 'Building A, 2nd Floor'),
(1, 'room_equipment', 'Projector, Whiteboard, Conference Phone');

-- --------------------------------------------------------
-- Sample Vehicle
-- --------------------------------------------------------
INSERT INTO `objects` (`object_author`, `object_date`, `object_date_gmt`, `object_content`, `object_title`, `object_excerpt`, `object_status`, `comment_status`, `ping_status`, `object_password`, `object_name`, `object_modified`, `object_modified_gmt`, `object_parent`, `guid`, `menu_order`, `object_type`, `object_mime_type`) VALUES
(1, NOW(), NOW(), 'Company van for general use and transport. Regularly maintained.', 'Toyota HiAce - Van 01', 'Company van for transport.', 'available', 'closed', 'closed', '', 'toyota-hiace-van-01', NOW(), NOW(), 0, '', 0, 'vehicle', '');

-- Assuming the vehicle object is the second object inserted (ID will likely be 2)
-- If you run this script on an empty DB, the first room object would be ID 1, first vehicle object ID 2
SET @last_vehicle_id = LAST_INSERT_ID(); -- More reliable way to get ID of last insert

INSERT INTO `objectmeta` (`object_id`, `meta_key`, `meta_value`) VALUES
(@last_vehicle_id, 'vehicle_plate_number', 'XYZ 789'),
(@last_vehicle_id, 'vehicle_make', 'Toyota'),
(@last_vehicle_id, 'vehicle_model', 'HiAce Commuter'),
(@last_vehicle_id, 'vehicle_year', '2022'),
(@last_vehicle_id, 'vehicle_capacity', '12'),
(@last_vehicle_id, 'vehicle_type', 'Van'),
(@last_vehicle_id, 'vehicle_fuel_type', 'Diesel');


SELECT 'Installation data script completed.' AS Status;
