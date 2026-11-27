ALTER TABLE users
    ADD COLUMN failed_login_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER no_whatsapp,
    ADD COLUMN locked_at DATETIME NULL AFTER failed_login_attempts,
    ADD COLUMN password_reset_token CHAR(64) NULL AFTER locked_at,
    ADD COLUMN password_reset_expires_at DATETIME NULL AFTER password_reset_token,
    ADD UNIQUE KEY uq_users_password_reset_token (password_reset_token);
