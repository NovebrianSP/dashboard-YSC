CREATE TABLE IF NOT EXISTS dap (
    id_dap INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_user INT UNSIGNED NOT NULL,
    tanggal_mulai DATE NOT NULL,
    pekerjaan_prioritas TEXT NOT NULL,
    status ENUM('submitted') NOT NULL DEFAULT 'submitted',
    submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_dap_user_week (id_user, tanggal_mulai),
    INDEX idx_dap_status_week (status, tanggal_mulai),
    CONSTRAINT fk_dap_user FOREIGN KEY (id_user) REFERENCES users(id_user) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS dap_activities (
    id_aktivitas INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_dap INT UNSIGNED NOT NULL,
    tanggal DATE NOT NULL,
    waktu_mulai TIME NOT NULL,
    aktivitas TEXT NOT NULL,
    UNIQUE KEY uq_dap_activity_slot (id_dap, tanggal, waktu_mulai),
    CONSTRAINT fk_dap_activities_dap FOREIGN KEY (id_dap) REFERENCES dap(id_dap) ON DELETE CASCADE
) ENGINE=InnoDB;
