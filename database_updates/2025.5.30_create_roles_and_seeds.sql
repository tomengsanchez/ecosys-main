-- Step 1: Create the 'roles' table
CREATE TABLE `roles` (
  `role_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_key` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Unique key for the role (e.g., admin, editor, user)',
  `role_name` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Display name for the role (e.g., Administrator, Editor)',
  `role_description` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_system_role` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'True if this role is critical and cannot be deleted (e.g., admin)',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`role_id`),
  UNIQUE KEY `role_key_unique` (`role_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 2: Seed initial roles
-- It's crucial that 'admin' role exists and is marked as a system role.
INSERT INTO `roles` (`role_key`, `role_name`, `role_description`, `is_system_role`) VALUES
('admin', 'Administrator', 'Has full system access and all permissions.', TRUE),
('editor', 'Editor', 'Can manage content and specific sections.', FALSE),
('user', 'User', 'Standard user with basic access.', FALSE);

-- Step 3: Update 'role_permissions' table to use 'role_key' if it was using role names directly.
-- If your 'role_permissions.role_name' was already designed to store keys like 'admin', 'editor',
-- then this step might not be necessary, or it's just a confirmation.
-- The 'role_name' column in 'role_permissions' should store the 'role_key' from the 'roles' table.

-- Step 4: Ensure the 'users' table's 'user_role' column stores these role_keys.
-- Example: If a user was 'admin', their 'user_role' in the 'users' table should be 'admin'.
-- This should align with the `role_key` in the new `roles` table.
-- No ALTER TABLE needed for `users.user_role` if it already stores these keys.

INSERT INTO `role_permissions` (`role_name`, `capability_key`) VALUES ('admin', 'MANAGE_ROLES');
