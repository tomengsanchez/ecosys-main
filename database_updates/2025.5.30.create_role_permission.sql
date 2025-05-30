-- Step 1: Create the 'role_permissions' table
-- This table will store the link between a role_name (e.g., 'admin', 'editor')
-- and a capability_key (e.g., 'MANAGE_USERS').
CREATE TABLE `role_permissions` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_name` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `capability_key` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_capability_unique` (`role_name`, `capability_key`), -- Ensures a capability is not assigned twice to the same role
  INDEX `idx_role_name` (`role_name`),
  INDEX `idx_capability_key` (`capability_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 2: Seed initial permissions for the 'admin' role.
-- This assumes your CAPABILITIES are defined in config.php.
-- You would run these INSERT statements after the table is created.
-- This script will need to be adapted if your capabilities change.

-- For 'admin' role, grant all capabilities defined in your CAPABILITIES constant.
-- You'll need to manually list them here or generate these INSERTs programmatically
-- based on your config.php CAPABILITIES array if you run this via a script.
-- Example (replace with your actual capabilities from config.php):
INSERT INTO `role_permissions` (`role_name`, `capability_key`) VALUES
('admin', 'ACCESS_ADMIN_PANEL'),
('admin', 'MANAGE_USERS'),
('admin', 'MANAGE_ROLES_PERMISSIONS'),
('admin', 'MANAGE_DEPARTMENTS'),
('admin', 'MANAGE_SITE_SETTINGS'),
('admin', 'VIEW_REPORTS'),
('admin', 'MANAGE_OPEN_OFFICE_RESERVATIONS'),
('admin', 'MANAGE_IT_REQUESTS'),
('admin', 'MANAGE_RAP_CALENDAR'),
('admin', 'MANAGE_SES_DATA');

-- Example for an 'editor' role (if you want to pre-seed it)
-- INSERT INTO `role_permissions` (`role_name`, `capability_key`) VALUES
-- ('editor', 'ACCESS_ADMIN_PANEL'),
-- ('editor', 'VIEW_REPORTS'),
-- ('editor', 'MANAGE_RAP_CALENDAR');

