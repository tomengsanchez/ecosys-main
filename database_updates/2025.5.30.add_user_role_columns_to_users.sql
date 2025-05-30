ALTER TABLE `users`
ADD COLUMN `user_role` VARCHAR(50) NOT NULL DEFAULT 'user' COMMENT 'User role (e.g., admin, editor, user)' AFTER `display_name`;

-- Optional: Update your existing admin user (e.g., user_id = 1) to have the 'admin' role.
UPDATE `users`
SET `user_role` = 'admin'
WHERE `user_id` = 1; -- Or whichever user ID is your main administrator.

-- Optional: Add an index for the new column if you plan to query by role often.
ALTER TABLE `users`
ADD INDEX `idx_user_role` (`user_role`);
