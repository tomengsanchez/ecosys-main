-- Manually hash a password in PHP: echo password_hash("password123", PASSWORD_DEFAULT);
-- Let's say the output is: $2y$10$abcdefghijklmnopqrstuv
INSERT INTO `users` (`user_login`, `user_pass`, `user_nicename`, `user_email`, `user_registered`, `user_status`, `display_name`)
VALUES
('testuser', '$2y$10$abcdefghijklmnopqrstuv', 'testuser', 'test@example.com', NOW(), 0, 'Test User');