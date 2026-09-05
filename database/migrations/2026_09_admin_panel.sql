USE lost_and_found;

CREATE TABLE IF NOT EXISTS admin_activity (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT DEFAULT NULL,
    action_type VARCHAR(60) NOT NULL,
    target_type VARCHAR(40) DEFAULT NULL,
    target_id INT DEFAULT NULL,
    description VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_admin_activity_created (created_at),
    INDEX idx_admin_activity_target (target_type, target_id),
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
