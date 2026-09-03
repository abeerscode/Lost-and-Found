USE lost_and_found;
ALTER TABLE users
    ADD COLUMN batch VARCHAR(30) DEFAULT NULL AFTER department,
    ADD COLUMN profile_photo VARCHAR(255) DEFAULT NULL AFTER batch;

UPDATE users SET batch = '2022' WHERE email IN ('john.doe@uni.edu', 'sara.khan@uni.edu') AND batch IS NULL;
UPDATE users SET batch = 'Staff' WHERE email = 'admin@uni.edu' AND batch IS NULL;
