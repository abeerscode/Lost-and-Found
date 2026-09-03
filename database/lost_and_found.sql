-- Lost & Found — clean demo database
CREATE DATABASE IF NOT EXISTS lost_and_found CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE lost_and_found;
SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS post_status_log, password_resets, notifications, messages, comments, claims, posts, categories, users;
SET FOREIGN_KEY_CHECKS=1;

CREATE TABLE users (
 id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, university_id VARCHAR(50) DEFAULT NULL,
 email VARCHAR(150) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL,
 role ENUM('user','admin') NOT NULL DEFAULT 'user', phone VARCHAR(30) DEFAULT NULL,
 department VARCHAR(100) DEFAULT NULL, batch VARCHAR(30) DEFAULT NULL, profile_photo VARCHAR(255) DEFAULT NULL, account_status ENUM('active','suspended','banned') NOT NULL DEFAULT 'active',
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
CREATE TABLE categories (
 id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(50) NOT NULL UNIQUE, is_high_value_default TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB;
CREATE TABLE posts (
 id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, type ENUM('lost','found') NOT NULL,
 title VARCHAR(150) NOT NULL, description TEXT NOT NULL, category_id INT NOT NULL, location VARCHAR(150) NOT NULL,
 item_datetime DATETIME NOT NULL, photo_url VARCHAR(255) DEFAULT NULL,
 status ENUM('open','claimed','resolved') NOT NULL DEFAULT 'open', is_high_value TINYINT(1) NOT NULL DEFAULT 0,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB;
CREATE FULLTEXT INDEX idx_posts_search ON posts(title,description);
CREATE TABLE claims (
 id INT AUTO_INCREMENT PRIMARY KEY, post_id INT NOT NULL, claimant_id INT NOT NULL, proof_description TEXT NOT NULL,
 status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending', verified_by_admin INT DEFAULT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(post_id) REFERENCES posts(id) ON DELETE CASCADE, FOREIGN KEY(claimant_id) REFERENCES users(id) ON DELETE CASCADE,
 FOREIGN KEY(verified_by_admin) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
CREATE TABLE comments (
 id INT AUTO_INCREMENT PRIMARY KEY, post_id INT NOT NULL, user_id INT NOT NULL, message TEXT NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(post_id) REFERENCES posts(id) ON DELETE CASCADE, FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE messages (
 id INT AUTO_INCREMENT PRIMARY KEY, sender_id INT NOT NULL, receiver_id INT NOT NULL, post_id INT DEFAULT NULL,
 content TEXT NOT NULL, is_read TINYINT(1) NOT NULL DEFAULT 0, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(sender_id) REFERENCES users(id) ON DELETE CASCADE, FOREIGN KEY(receiver_id) REFERENCES users(id) ON DELETE CASCADE,
 FOREIGN KEY(post_id) REFERENCES posts(id) ON DELETE SET NULL
) ENGINE=InnoDB;
CREATE TABLE notifications (
 id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, type VARCHAR(50) NOT NULL, message VARCHAR(255) NOT NULL,
 link VARCHAR(255) DEFAULT NULL, is_read TINYINT(1) NOT NULL DEFAULT 0, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE password_resets (
 id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, token VARCHAR(64) NOT NULL UNIQUE, expires_at DATETIME NOT NULL,
 used TINYINT(1) NOT NULL DEFAULT 0, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE post_status_log (
 id INT AUTO_INCREMENT PRIMARY KEY, post_id INT NOT NULL, old_status VARCHAR(20) DEFAULT NULL, new_status VARCHAR(20) NOT NULL,
 changed_by INT DEFAULT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(post_id) REFERENCES posts(id) ON DELETE CASCADE, FOREIGN KEY(changed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO categories(name,is_high_value_default) VALUES
('Electronics',1),('Documents',1),('Bags',0),('Keys',0),('Accessories',0),('Clothing',0),('Others',0);

-- Demo credentials:
-- john.doe@uni.edu / demo123
-- sara.khan@uni.edu / demo123
-- admin@uni.edu / admin123
INSERT INTO users(name,university_id,email,password_hash,role,phone,department,batch) VALUES
('John Doe','CSE-220101','john.doe@uni.edu','$2y$12$73o.c4UzCdq/PIxJ0q1DGul99mE0nEMHF/3LgVxuwlm.MX3GlWmCu','user','01710000001','Computer Science & Engineering','2022'),
('Sara Khan','BBA-220205','sara.khan@uni.edu','$2y$12$73o.c4UzCdq/PIxJ0q1DGul99mE0nEMHF/3LgVxuwlm.MX3GlWmCu','user','01710000002','Business Administration','2022'),
('System Administrator','ADMIN-001','admin@uni.edu','$2y$12$lHzGGgO4FwN9JnBK1vS7AOJU42zHMUB86Gl68oY3XsRJrC0ZQpMbq','admin','01700000000','IT Administration','Staff');

INSERT INTO posts(user_id,type,title,description,category_id,location,item_datetime,photo_url,status,is_high_value,created_at) VALUES
(1,'lost','Fossil Black Watch','Black Fossil wrist watch with a leather strap. I may have left it close to the library reading area.',5,'University Library, 2nd Floor','2026-09-01 10:30:00','demo/black-watch.jpg','open',0,'2026-09-01 11:00:00'),
(2,'found','Black Jansport Backpack','Black Jansport backpack found near the Student Union. Several notebooks were inside.',3,'Student Union','2026-09-01 12:10:00','demo/black-backpack.jpg','open',0,'2026-09-01 12:30:00'),
(2,'found','Apple AirPods Pro','White Apple AirPods Pro with charging case found close to the Engineering Building.',1,'Engineering Building','2026-08-31 15:45:00','demo/airpods-pro.jpg','open',1,'2026-08-31 16:10:00'),
(1,'lost','Car Keys','Set of three keys attached to a brown leather keychain.',4,'Parking Lot B','2026-08-31 18:20:00','demo/car-keys.jpg','open',0,'2026-08-31 18:40:00'),
(1,'found','iPhone 14 Pro','Black iPhone 14 Pro found on a cafeteria table. The phone is locked and has a dark protective case.',1,'University Cafeteria','2026-08-30 13:20:00','demo/iphone-14-pro.jpg','open',1,'2026-08-30 13:40:00'),
(2,'lost','Brown Notebook','Brown hardcover notebook containing handwritten lecture notes.',7,'Classroom 204','2026-08-30 11:00:00','demo/brown-notebook.jpg','open',0,'2026-08-30 11:20:00'),
(2,'found','Blue Hydro Flask','Blue Hydro Flask water bottle found beside the university gym lockers.',7,'University Gym','2026-08-29 17:40:00','demo/blue-bottle.jpg','open',0,'2026-08-29 18:00:00'),
(1,'lost','Ray-Ban Sunglasses','Black Ray-Ban sunglasses with a black protective case.',5,'Central Lawn','2026-08-29 14:15:00','demo/sunglasses.jpg','open',0,'2026-08-29 14:35:00'),
(2,'found','University ID Card','Student identification card found near the main entrance of the university library.',2,'Main Library Entrance','2026-08-28 09:25:00','demo/student-id.jpg','open',1,'2026-08-28 09:45:00'),
(1,'lost','Brown Leather Wallet','Brown leather wallet containing several cards. The owner can identify the cards inside.',5,'Sports Complex','2026-08-27 19:10:00','demo/brown-wallet.jpg','open',1,'2026-08-27 19:30:00'),
(2,'found','Silver MacBook Air','Silver MacBook Air found in a classroom after an afternoon lecture.',1,'Engineering Building, Room 305','2026-08-27 16:30:00','demo/macbook-air.jpg','open',1,'2026-08-27 16:50:00'),
(1,'found','USB-C Laptop Charger','Black USB-C laptop charger found underneath a desk in Computer Lab 3.',1,'Computer Lab 3','2026-08-26 13:00:00','demo/usb-c-charger.jpg','resolved',0,'2026-08-26 13:20:00'),
(2,'lost','Scientific Calculator','Black Casio scientific calculator. My name is written on a small sticker on the back.',1,'Science Building, Room 402','2026-08-25 12:30:00','demo/calculator.jpg','open',0,'2026-08-25 13:00:00'),
(1,'found','Green Umbrella','Compact dark green umbrella found beside the entrance after the afternoon rain.',7,'Main Building Entrance','2026-08-24 17:20:00','demo/green-umbrella.jpg','open',0,'2026-08-24 17:40:00'),
(2,'lost','Student Bus Card','University transport card inside a transparent plastic holder.',2,'University Bus Stop','2026-08-23 08:15:00','demo/bus-card.jpg','claimed',1,'2026-08-23 08:40:00');

INSERT INTO messages(sender_id,receiver_id,post_id,content,is_read,created_at) VALUES
(2,1,1,'Hi John, I saw your post about the black watch you lost near the library.',1,'2026-09-02 10:35:00'),
(1,2,1,'Hi Sara. Yes, I am still looking for it. Did you find one?',1,'2026-09-02 10:36:00'),
(2,1,1,'I found a black watch near the library entrance yesterday morning.',1,'2026-09-02 10:37:00'),
(1,2,1,'That might be mine. Can you tell me what the strap looks like?',1,'2026-09-02 10:38:00'),
(2,1,1,'It has a black leather strap and a silver buckle.',1,'2026-09-02 10:39:00'),
(1,2,1,'That sounds like mine. There should also be a small scratch on the side.',1,'2026-09-02 10:40:00'),
(2,1,1,'Yes, I can see a small scratch on the side.',1,'2026-09-02 10:41:00'),
(1,2,1,'Great. When would you be available to meet?',1,'2026-09-02 10:42:00'),
(2,1,1,'I will be on campus tomorrow around 3 PM. We can meet at the library front desk.',1,'2026-09-02 10:43:00'),
(1,2,1,'Perfect. That works for me. Thank you!',1,'2026-09-02 10:44:00'),
(2,1,1,'No problem. See you tomorrow.',0,'2026-09-02 10:45:00'),
(2,1,5,'Hi John, is the iPhone you found still available?',1,'2026-08-31 09:10:00'),
(1,2,5,'Yes, I still have it. Do you think it belongs to someone you know?',1,'2026-08-31 09:12:00'),
(2,1,5,'Possibly. I will ask my classmate to confirm the wallpaper and case.',1,'2026-08-31 09:14:00'),
(1,2,5,'Sure. Ask them to message me with identifying details.',1,'2026-08-31 09:16:00');

INSERT INTO comments(post_id,user_id,message,created_at) VALUES
(3,1,'Was this found near the main entrance or inside the Engineering Building?','2026-09-01 17:10:00'),
(3,2,'It was close to the ground-floor entrance.','2026-09-01 17:18:00'),
(7,1,'Was the bottle found inside the locker room?','2026-08-30 10:15:00'),
(7,2,'Yes, beside the last row of lockers.','2026-08-30 10:22:00');
INSERT INTO claims(post_id,claimant_id,proof_description,status,verified_by_admin,created_at) VALUES
(9,1,'I believe this card belongs to one of my classmates. I can provide the university ID information privately.','pending',NULL,'2026-09-02 09:20:00'),
(15,1,'The transport card has a small blue sticker on the back and the card number ends in 74.','approved',3,'2026-08-24 11:00:00');
INSERT INTO notifications(user_id,type,message,link,is_read,created_at) VALUES
(1,'message','Sara Khan sent you a message about your Fossil Black Watch.','/messages/conversation.php?with=2&post_id=1',0,'2026-09-02 10:45:00'),
(1,'claim','Your claim for the University ID Card has been submitted.','/posts/view.php?id=9',1,'2026-09-02 09:20:00'),
(2,'message','John Doe replied to your conversation about the Fossil Black Watch.','/messages/conversation.php?with=1&post_id=1',1,'2026-09-02 10:44:00'),
(2,'post','Your Apple AirPods Pro report is now visible.','/posts/view.php?id=3',0,'2026-08-31 16:10:00');
INSERT INTO post_status_log(post_id,old_status,new_status,changed_by,created_at) VALUES
(12,'open','resolved',1,'2026-08-28 14:00:00'),(15,'open','claimed',3,'2026-08-24 13:10:00');
