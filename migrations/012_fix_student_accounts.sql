-- Migration 012: Fix student accounts for testing
-- Creates Jack account and updates john_doe password to Student1234

-- Create Jack student account with password Jack1234
INSERT INTO users (username, email, password_hash, full_name, role, is_active, created_at)
VALUES ('Jack', 'jack@student.local', '$2y$10$aa/Ljzb8Bc433lEY.dYnA.wWbJewI3l1J2XDDJmg7AgcQsHa9kXJw2', 'Jack Student', 'student', 1, NOW())
ON DUPLICATE KEY UPDATE 
    password_hash = '$2y$10$aa/Ljzb8Bc433lEY.dYnA.wWbJewI3l1J2XDDJmg7AgcQsHa9kXJw2',
    is_active = 1;

-- Update john_doe password to Student1234 (from student123)
UPDATE users 
SET password_hash = '$2y$10$2RnbttlfV2s1wJna9J1q7uizf.IhGCVDh5D2xD1VUhsvvsuspARQO'
WHERE username = 'john_doe';

-- Display student accounts
SELECT user_id, username, email, full_name, role, is_active 
FROM users 
WHERE role = 'student'
ORDER BY username;
