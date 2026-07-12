USE abdu_mart;

ALTER TABLE users
    ADD COLUMN google_id VARCHAR(255) NULL UNIQUE AFTER email,
    ADD COLUMN email_verified_at TIMESTAMP NULL DEFAULT NULL AFTER phone,
    MODIFY password_hash VARCHAR(255) NULL DEFAULT NULL;

CREATE TABLE IF NOT EXISTS settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS auth_tokens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    token_hash VARCHAR(64) NOT NULL,
    purpose ENUM('signup', 'password_reset') NOT NULL,
    payload JSON NULL,
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email_purpose (email, purpose),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB;

ALTER TABLE orders
    ADD COLUMN confirmation_email_sent_at TIMESTAMP NULL DEFAULT NULL AFTER updated_at;
