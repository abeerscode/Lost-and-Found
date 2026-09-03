USE lost_and_found;

ALTER TABLE comments
    ADD COLUMN parent_id INT DEFAULT NULL AFTER user_id,
    ADD INDEX idx_comments_parent_id (parent_id),
    ADD CONSTRAINT fk_comments_parent
        FOREIGN KEY (parent_id) REFERENCES comments(id)
        ON DELETE CASCADE;
