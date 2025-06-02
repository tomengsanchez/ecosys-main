-- Main table for all content types (similar to WordPress's wp_posts)
CREATE TABLE `objects` (
  `object_id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `object_author` BIGINT(20) UNSIGNED NOT NULL DEFAULT 0, -- Corresponds to a user ID (you'll need a users table)
  `object_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `object_date_gmt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `object_content` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `object_title` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `object_excerpt` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `object_status` VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'publish', -- e.g., 'publish', 'draft', 'pending', 'private', 'trash'
  `comment_status` VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open', -- 'open', 'closed'
  `ping_status` VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open', -- 'open', 'closed' (for trackbacks/pingbacks)
  `object_password` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '', -- For password-protected entries
  `object_name` VARCHAR(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '', -- URL-friendly slug
  `object_modified` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `object_modified_gmt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `object_parent` BIGINT(20) UNSIGNED NOT NULL DEFAULT 0, -- For hierarchical post types (e.g., pages)
  `guid` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '', -- Globally Unique Identifier (can be a permalink)
  `menu_order` INT(11) NOT NULL DEFAULT 0, -- For ordering items
  `object_type` VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'post', -- KEY: 'post', 'page', 'product', 'event', 'your_custom_type'
  `object_mime_type` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '', -- For attachments
  PRIMARY KEY (`object_id`),
  KEY `object_type_status_date` (`object_type`, `object_status`, `object_date`, `object_id`),
  KEY `object_parent` (`object_parent`),
  KEY `object_author` (`object_author`),
  KEY `object_name` (`object_name`(191)) -- Index prefix for utf8mb4 compatibility with older MySQL versions
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Metadata for objects (similar to WordPress's wp_postmeta)
-- This table allows you to add custom fields to any object without altering the `objects` table structure.
CREATE TABLE `objectmeta` (
  `meta_id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `object_id` BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
  `meta_key` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_value` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`meta_id`),
  KEY `object_id` (`object_id`),
  KEY `meta_key` (`meta_key`(191)) -- Index prefix for utf8mb4 compatibility
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Terms table (for categories, tags, etc. - similar to WordPress's wp_terms)
CREATE TABLE `terms` (
  `term_id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `slug` VARCHAR(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `term_group` BIGINT(10) NOT NULL DEFAULT 0, -- For grouping terms (rarely used by default in WP)
  PRIMARY KEY (`term_id`),
  KEY `slug` (`slug`(191)), -- Index prefix
  KEY `name` (`name`(191)) -- Index prefix
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Term Taxonomy table (defines the type of term - category, tag, custom taxonomy - similar to WordPress's wp_term_taxonomy)
-- This table links terms to a specific taxonomy (e.g. 'category', 'tag', 'product_color').
CREATE TABLE `term_taxonomy` (
  `term_taxonomy_id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `term_id` BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
  `taxonomy` VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '', -- e.g., 'category', 'post_tag', 'product_category'
  `description` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `parent` BIGINT(20) UNSIGNED NOT NULL DEFAULT 0, -- For hierarchical taxonomies (e.g., parent category)
  `count` BIGINT(20) NOT NULL DEFAULT 0, -- Number of objects associated with this term in this taxonomy
  PRIMARY KEY (`term_taxonomy_id`),
  UNIQUE KEY `term_id_taxonomy` (`term_id`, `taxonomy`), -- Ensures a term is unique within a taxonomy
  KEY `taxonomy` (`taxonomy`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Term Relationships table (links objects to terms via term_taxonomy - similar to WordPress's wp_term_relationships)
-- This is the junction table connecting objects to their terms (categories, tags, etc.).
CREATE TABLE `term_relationships` (
  `object_id` BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
  `term_taxonomy_id` BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
  `term_order` INT(11) NOT NULL DEFAULT 0, -- Order of terms for an object (rarely used by default)
  PRIMARY KEY (`object_id`, `term_taxonomy_id`), -- Composite primary key
  KEY `term_taxonomy_id` (`term_taxonomy_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional: Users table (if you need user accounts)
-- This is a simplified version. WordPress's user system is more complex.
CREATE TABLE `users` (
  `user_id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_login` VARCHAR(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `user_pass` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '', -- Store hashed passwords!
  `user_nicename` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '', -- Display name
  `user_email` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `user_url` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `user_registered` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `user_status` INT(11) NOT NULL DEFAULT 0, -- 0 = active, 1 = inactive/banned
  `display_name` VARCHAR(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`user_id`),
  KEY `user_login_key` (`user_login`),
  KEY `user_nicename` (`user_nicename`),
  KEY `user_email` (`user_email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional: User Meta table (similar to WordPress's wp_usermeta)
-- For storing additional information about users.
CREATE TABLE `usermeta` (
  `umeta_id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
  `meta_key` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_value` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`umeta_id`),
  KEY `user_id` (`user_id`),
  KEY `meta_key` (`meta_key`(191)) -- Index prefix
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional: Options table (for global site settings - similar to WordPress's wp_options)
CREATE TABLE `options` (
  `option_id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `option_name` VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '', -- Max length 191 for unique key with utf8mb4
  `option_value` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `autoload` VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'yes', -- 'yes' or 'no' (whether to load on every page)
  PRIMARY KEY (`option_id`),
  UNIQUE KEY `option_name` (`option_name`) -- Option names must be unique
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

ALTER TABLE `users`
ADD COLUMN `user_role` VARCHAR(50) NOT NULL DEFAULT 'user' COMMENT 'User role (e.g., admin, editor, user)' AFTER `display_name`;

-- Optional: Update your existing admin user (e.g., user_id = 1) to have the 'admin' role.
UPDATE `users`
SET `user_role` = 'admin'
WHERE `user_id` = 1; -- Or whichever user ID is your main administrator.

-- Optional: Add an index for the new column if you plan to query by role often.
ALTER TABLE `users`
ADD INDEX `idx_user_role` (`user_role`);


-- Step 1: Create the new 'departments' table
CREATE TABLE `departments` (
  `department_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `department_name` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `department_description` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`department_id`),
  UNIQUE KEY `department_name_unique` (`department_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 2: Add a 'department_id' column to the 'users' table
ALTER TABLE `users`
ADD COLUMN `department_id` INT(11) UNSIGNED DEFAULT NULL AFTER `user_role`,
ADD INDEX `idx_department_id` (`department_id`);

-- Step 3: Add a foreign key constraint to link users.department_id to departments.department_id
-- This ensures data integrity. If a department is deleted, you can decide what happens to users in it.
-- ON DELETE SET NULL means if a department is deleted, the user's department_id will become NULL.
-- You could also use ON DELETE RESTRICT to prevent deletion of a department if users are in it.
ALTER TABLE `users`
ADD CONSTRAINT `fk_users_department`
FOREIGN KEY (`department_id`) REFERENCES `departments`(`department_id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- Optional: Insert some sample departments to get started
INSERT INTO `departments` (`department_name`, `department_description`) VALUES
('Human Resources', 'Handles all employee-related matters.'),
('Engineering', 'Responsible for software development and infrastructure.'),
('Sales & Marketing', 'Drives business growth and customer outreach.'),
('General Administration', 'Default department for unassigned users.');

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

INSERT INTO `role_permissions` (`role_name`, `capability_key`) VALUES ('admin', 'MANAGE_ROOMS');
-- Add for other roles as needed, e.g.:
-- INSERT INTO `role_permissions` (`role_name`, `capability_key`) VALUES ('editor', 'MANAGE_ROOMS');


-- Ensure your 'admin' role_key exists in the 'roles' table.
-- These are examples; adjust 'admin' or other role_keys as needed.

-- For an 'admin' role, grant all new room reservation capabilities:
INSERT INTO `role_permissions` (`role_name`, `capability_key`) VALUES
('admin', 'CREATE_ROOM_RESERVATIONS'),
('admin', 'EDIT_OWN_ROOM_RESERVATIONS'), -- If you plan to implement this
('admin', 'CANCEL_OWN_ROOM_RESERVATIONS'),
('admin', 'VIEW_ALL_ROOM_RESERVATIONS'),
('admin', 'APPROVE_DENY_ROOM_RESERVATIONS'),
('admin', 'EDIT_ANY_ROOM_RESERVATION'),   -- If you plan to implement this
('admin', 'DELETE_ANY_ROOM_RESERVATION'); -- If you plan to implement this

-- For a standard 'user' role, grant basic capabilities:
INSERT INTO `role_permissions` (`role_name`, `capability_key`) VALUES
('user', 'CREATE_ROOM_RESERVATIONS'),
('user', 'CANCEL_OWN_ROOM_RESERVATIONS');
-- Optionally, 'user' might also get 'EDIT_OWN_ROOM_RESERVATIONS' if you implement that feature for users.

-- For an 'editor' or 'manager' role, you might give a subset:
-- INSERT INTO `role_permissions` (`role_name`, `capability_key`) VALUES
-- ('editor', 'CREATE_ROOM_RESERVATIONS'),
-- ('editor', 'CANCEL_OWN_ROOM_RESERVATIONS'),
-- ('editor', 'VIEW_ALL_ROOM_RESERVATIONS'); -- If they need to see all but not approve

-- Important: Ensure these capability keys match exactly what's in your config.php
-- and that the role_name (e.g., 'admin', 'user') matches the role_key in your 'roles' table.
-- Avoid duplicate entries. If a role already has a capability, these inserts might fail
-- or you might want to use INSERT IGNORE or an UPDATE ON DUPLICATE KEY statement
-- depending on your database setup and desired behavior.
