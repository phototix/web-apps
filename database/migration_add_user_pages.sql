CREATE TABLE IF NOT EXISTS user_pages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    token VARCHAR(80) NOT NULL,
    title VARCHAR(150) DEFAULT NULL,
    is_public TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY user_pages_token_unique (token),
    KEY user_pages_user_id_index (user_id),
    CONSTRAINT user_pages_user_id_fk FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
);
