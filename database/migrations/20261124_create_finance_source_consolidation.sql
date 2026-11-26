CREATE TABLE IF NOT EXISTS keuangan_konsolidasi_sumber (
    tahun SMALLINT UNSIGNED NOT NULL,
    sampai_bulan TINYINT UNSIGNED NOT NULL,
    jenis ENUM('penerimaan', 'pengeluaran') NOT NULL,
    komponen VARCHAR(30) NOT NULL,
    jumlah DECIMAL(15,2) UNSIGNED NOT NULL DEFAULT 0.00,
    keterangan VARCHAR(500) NULL,
    diinput_oleh INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (tahun, sampai_bulan, jenis, komponen),
    INDEX idx_keuangan_konsolidasi_tahun_jenis (tahun, jenis),
    CONSTRAINT fk_keuangan_konsolidasi_user FOREIGN KEY (diinput_oleh) REFERENCES users(id_user) ON DELETE SET NULL
) ENGINE=InnoDB;
