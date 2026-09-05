USE lost_and_found;

-- ============================================================
-- LARGE DEMO ENHANCEMENT
-- Safe to run on the previously imported large demo database.
-- Adds local demo profile-photo paths, gives Abeer a richer report
-- history, adjusts replies on his reports, and populates his
-- notification feed.
-- ============================================================

-- ------------------------------------------------------------
-- 1) PROFILE PHOTOS FOR ALL SEEDED ACCOUNTS
-- Files are included under uploads/profiles/ in the project.
-- ------------------------------------------------------------
UPDATE users SET profile_photo = 'profiles/user_01.jpg' WHERE id = 1;
UPDATE users SET profile_photo = 'profiles/user_02.jpg' WHERE id = 2;
UPDATE users SET profile_photo = 'profiles/user_03.jpg' WHERE id = 3;
UPDATE users SET profile_photo = 'profiles/user_04.jpg' WHERE id = 4;
UPDATE users SET profile_photo = 'profiles/user_05.jpg' WHERE id = 5;
UPDATE users SET profile_photo = 'profiles/user_06.jpg' WHERE id = 6;
UPDATE users SET profile_photo = 'profiles/user_07.jpg' WHERE id = 7;
UPDATE users SET profile_photo = 'profiles/user_08.jpg' WHERE id = 8;
UPDATE users SET profile_photo = 'profiles/user_09.jpg' WHERE id = 9;
UPDATE users SET profile_photo = 'profiles/user_10.jpg' WHERE id = 10;
UPDATE users SET profile_photo = 'profiles/user_11.jpg' WHERE id = 11;
UPDATE users SET profile_photo = 'profiles/user_12.jpg' WHERE id = 12;
UPDATE users SET profile_photo = 'profiles/user_13.jpg' WHERE id = 13;
UPDATE users SET profile_photo = 'profiles/user_14.jpg' WHERE id = 14;
UPDATE users SET profile_photo = 'profiles/user_15.jpg' WHERE id = 15;
UPDATE users SET profile_photo = 'profiles/user_16.jpg' WHERE id = 16;
UPDATE users SET profile_photo = 'profiles/user_17.jpg' WHERE id = 17;
UPDATE users SET profile_photo = 'profiles/user_18.jpg' WHERE id = 18;
UPDATE users SET profile_photo = 'profiles/user_19.jpg' WHERE id = 19;
UPDATE users SET profile_photo = 'profiles/user_20.jpg' WHERE id = 20;
UPDATE users SET profile_photo = 'profiles/user_21.jpg' WHERE id = 21;

-- ------------------------------------------------------------
-- 2) MAKE MD. ABEER HASAN THE PRIMARY / ACTIVE DEMO USER
-- Abeer becomes the owner of 10 reports across different
-- categories, types and states.
-- ------------------------------------------------------------
UPDATE posts SET user_id = 1 WHERE id IN (1,2,6,11,16,17,23,24,28,35);

-- Ensure a useful state/type mix for Abeer's dashboard.
UPDATE posts SET type = 'lost',  status = 'open'     WHERE id = 1;  -- laptop
UPDATE posts SET type = 'found', status = 'open'     WHERE id = 2;  -- earbuds
UPDATE posts SET type = 'lost',  status = 'open'     WHERE id = 6;  -- admit card folder
UPDATE posts SET type = 'lost',  status = 'open'     WHERE id = 11; -- backpack
UPDATE posts SET type = 'found', status = 'open'     WHERE id = 16; -- keys
UPDATE posts SET type = 'found', status = 'claimed'  WHERE id = 17; -- key set
UPDATE posts SET type = 'lost',  status = 'claimed'  WHERE id = 23; -- wristwatch
UPDATE posts SET type = 'found', status = 'resolved' WHERE id = 24; -- sunglasses
UPDATE posts SET type = 'found', status = 'resolved' WHERE id = 28; -- scarf
UPDATE posts SET type = 'found', status = 'open'     WHERE id = 35; -- umbrella

-- When the large seed was imported before this migration, nested
-- owner replies on these reports belong to the old owners. Make the
-- owner-side replies consistent with Abeer being the report owner.
UPDATE comments c
JOIN comments parent_comment ON parent_comment.id = c.parent_id
SET c.user_id = 1
WHERE c.parent_id IS NOT NULL
  AND c.post_id IN (1,2,6,11,16,17,23,24,28,35);

-- ------------------------------------------------------------
-- 3) CLEAN + REBUILD ABEER'S NOTIFICATION FEED
-- This only touches notifications belonging to Abeer.
-- ------------------------------------------------------------
DELETE FROM notifications WHERE user_id = 1;

INSERT INTO notifications
(user_id, type, message, link, is_read, created_at)
VALUES
(1,'message','John Doe replied in your conversation about Silver Dell Laptop.','/messages/conversation.php?with=2&post_id=1',0,'2026-09-05 02:56:00'),
(1,'message','Sara Khan sent you a new message about Wireless Earbuds in Black Case.','/messages/conversation.php?with=3&post_id=2',0,'2026-09-05 02:49:00'),
(1,'message','Nusrat Jahan replied about Black Smartphone.','/messages/conversation.php?with=4&post_id=3',1,'2026-09-05 02:42:00'),
(1,'message','Tahmid Rahman sent you more details about USB-C Charging Cable.','/messages/conversation.php?with=5&post_id=4',0,'2026-09-05 02:35:00'),
(1,'message','Rafiul Islam responded regarding Scientific Calculator.','/messages/conversation.php?with=6&post_id=5',1,'2026-09-05 02:28:00'),
(1,'message','Afsana Mimi sent a follow-up about Exam Admit Card Folder.','/messages/conversation.php?with=7&post_id=6',0,'2026-09-05 02:21:00'),
(1,'message','Imran Hossain messaged you about Blue Academic Document File.','/messages/conversation.php?with=8&post_id=7',1,'2026-09-05 02:14:00'),
(1,'message','Tasmia Rahman replied in the White Canvas Tote Bag thread.','/messages/conversation.php?with=9&post_id=12',0,'2026-09-05 02:07:00'),
(1,'message','Nahid Hasan sent an update about Research Notes Packet.','/messages/conversation.php?with=10&post_id=10',1,'2026-09-05 02:00:00'),
(1,'message','Dr. Farhana Islam replied about the Black Campus Backpack.','/messages/conversation.php?with=11&post_id=11',0,'2026-09-05 01:53:00'),
(1,'message','Mr. Rezaul Karim sent you a message about Keys with Blue Ring.','/messages/conversation.php?with=16&post_id=16',0,'2026-09-05 01:46:00'),
(1,'message','Ms. Shahana Akter replied about Wallet and Key Set.','/messages/conversation.php?with=17&post_id=17',1,'2026-09-05 01:39:00'),
(1,'post','Your report “Silver Dell Laptop” received a new comment.','/posts/view.php?id=1',0,'2026-09-05 01:30:00'),
(1,'post','Your report “Wireless Earbuds in Black Case” has new activity.','/posts/view.php?id=2',1,'2026-09-05 01:21:00'),
(1,'post','Your report “Exam Admit Card Folder” received a new comment.','/posts/view.php?id=6',0,'2026-09-05 01:12:00'),
(1,'claim','Someone submitted a claim for “Black Wristwatch”.','/posts/view.php?id=23',0,'2026-09-05 01:03:00'),
(1,'status','“Black Sunglasses” has been marked as resolved.','/posts/view.php?id=24',1,'2026-09-05 00:54:00'),
(1,'status','“Grey Wool Scarf” has been marked as resolved after verification.','/posts/view.php?id=28',0,'2026-09-05 00:45:00'),
(1,'post','Your report “Yellow Umbrella” has new activity.','/posts/view.php?id=35',0,'2026-09-05 00:36:00');

-- ------------------------------------------------------------
-- 4) STATUS HISTORY FOR ABEER'S CLAIMED / RESOLVED REPORTS
-- Avoid duplicates if this migration is accidentally run twice.
-- ------------------------------------------------------------
DELETE FROM post_status_log WHERE post_id IN (17,23,24,28);

INSERT INTO post_status_log
(post_id, old_status, new_status, changed_by, created_at)
VALUES
(17,'open','claimed',1,'2026-09-04 13:00:00'),
(23,'open','claimed',1,'2026-09-03 14:30:00'),
(24,'open','resolved',1,'2026-09-02 16:10:00'),
(28,'open','resolved',1,'2026-09-01 12:20:00');

-- ------------------------------------------------------------
-- QUICK CHECK
-- Expected Abeer result: 10 reports with lost/found and
-- open/claimed/resolved representation, plus notifications.
-- ------------------------------------------------------------
SELECT
    u.name,
    u.profile_photo,
    COUNT(p.id) AS reports,
    SUM(p.type = 'lost') AS lost_reports,
    SUM(p.type = 'found') AS found_reports,
    SUM(p.status = 'claimed') AS claimed_reports,
    SUM(p.status = 'resolved') AS resolved_reports
FROM users u
LEFT JOIN posts p ON p.user_id = u.id
WHERE u.id = 1
GROUP BY u.id;

SELECT COUNT(*) AS abeer_notifications
FROM notifications
WHERE user_id = 1;
