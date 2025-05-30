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
