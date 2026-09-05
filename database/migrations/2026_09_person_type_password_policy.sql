-- Lost & Found: person type + demo password upgrade
-- Run this ONCE on the existing lost_and_found database.

USE lost_and_found;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS person_type ENUM('student','faculty','staff') NOT NULL DEFAULT 'student' AFTER role;

-- Existing demo/user classifications.
UPDATE users
SET person_type = 'student'
WHERE email IN ('john.doe@uni.edu', 'sara.khan@uni.edu');

UPDATE users
SET person_type = 'staff', batch = NULL
WHERE role = 'admin' OR email = 'admin@uni.edu';

-- New demo credentials (all passwords are at least 8 characters):
-- john.doe@uni.edu / demo1234
-- sara.khan@uni.edu / demo1234
-- admin@uni.edu / admin1234

UPDATE users
SET password_hash = '$2y$12$eEsuCkIl6CPuK2ZL/a2uouWJ15Epqx5ld/tjgrmIj4Iavq8oEn8HC'
WHERE email IN ('john.doe@uni.edu', 'sara.khan@uni.edu');

UPDATE users
SET password_hash = '$2y$12$iM0aBf2XFPrNyr18ZXLvnOXUYsqxJOF28JUOIeiYGZMTjjdR3Ttqu'
WHERE email = 'admin@uni.edu';
