CREATE TABLE IF NOT EXISTS keuangan_periode (
    tahun SMALLINT UNSIGNED NOT NULL,
    bulan TINYINT UNSIGNED NOT NULL,
    catatan VARCHAR(500) NULL,
    diinput_oleh INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (tahun, bulan),
    CONSTRAINT fk_keuangan_periode_user FOREIGN KEY (diinput_oleh) REFERENCES users(id_user) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS keuangan_realisasi_bulanan (
    tahun SMALLINT UNSIGNED NOT NULL,
    bulan TINYINT UNSIGNED NOT NULL,
    jenis ENUM('penerimaan', 'pengeluaran') NOT NULL,
    komponen VARCHAR(30) NOT NULL,
    jumlah DECIMAL(15,2) UNSIGNED NOT NULL DEFAULT 0.00,
    diinput_oleh INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (tahun, bulan, jenis, komponen),
    INDEX idx_keuangan_realisasi_tahun_jenis (tahun, jenis),
    CONSTRAINT fk_keuangan_realisasi_periode FOREIGN KEY (tahun, bulan) REFERENCES keuangan_periode(tahun, bulan) ON DELETE CASCADE,
    CONSTRAINT fk_keuangan_realisasi_user FOREIGN KEY (diinput_oleh) REFERENCES users(id_user) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS keuangan_target_tahunan (
    id_target INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tahun SMALLINT UNSIGNED NOT NULL,
    sumber VARCHAR(30) NOT NULL,
    jumlah DECIMAL(15,2) UNSIGNED NOT NULL DEFAULT 0.00,
    diinput_oleh INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_keuangan_target_tahun_sumber (tahun, sumber),
    CONSTRAINT fk_keuangan_target_user FOREIGN KEY (diinput_oleh) REFERENCES users(id_user) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS keuangan_saldo_tahunan (
    tahun SMALLINT UNSIGNED NOT NULL PRIMARY KEY,
    saldo_awal DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    saldo_akhir_laporan DECIMAL(15,2) NULL,
    catatan VARCHAR(500) NULL,
    diinput_oleh INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_keuangan_saldo_user FOREIGN KEY (diinput_oleh) REFERENCES users(id_user) ON DELETE SET NULL
) ENGINE=InnoDB;
