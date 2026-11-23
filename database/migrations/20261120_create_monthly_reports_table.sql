CREATE TABLE IF NOT EXISTS laporan_bulanan (
    id_laporan INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bulan DATE NOT NULL,
    nama_file VARCHAR(64) NOT NULL UNIQUE,
    nama_asli VARCHAR(240) NOT NULL,
    tipe_mime VARCHAR(100) NOT NULL,
    ukuran_file INT UNSIGNED NOT NULL,
    diunggah_oleh INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_laporan_bulanan_bulan (bulan, created_at),
    CONSTRAINT fk_laporan_bulanan_user FOREIGN KEY (diunggah_oleh) REFERENCES users(id_user) ON DELETE SET NULL
) ENGINE=InnoDB;
