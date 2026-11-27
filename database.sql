-- CREATE DATABASE IF NOT EXISTS smartcendekia
--     CHARACTER SET utf8mb4
--     COLLATE utf8mb4_unicode_ci;
-- USE smartcendekia;

-- ============================================================
-- Pengguna
-- ============================================================

CREATE TABLE IF NOT EXISTS users (
    id_user INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nama_lengkap VARCHAR(100) NOT NULL,
    jabatan VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'pimpinan', 'unit_sdit', 'unit_smpit') NOT NULL,
    no_whatsapp VARCHAR(20) NULL,
    failed_login_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    locked_at DATETIME NULL,
    password_reset_token CHAR(64) NULL,
    password_reset_expires_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_password_reset_token (password_reset_token)
) ENGINE=InnoDB;

-- ============================================================
-- Rapat dan notulensi
-- ============================================================

CREATE TABLE IF NOT EXISTS jadwal_rapat (
    id_rapat INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    judul_rapat VARCHAR(200) NOT NULL,
    tanggal_rapat DATE NOT NULL,
    waktu_mulai TIME NOT NULL,
    lokasi VARCHAR(150) NOT NULL,
    kategori_unit ENUM('yayasan', 'sdit', 'smpit', 'semua') NOT NULL,
    status_rapat ENUM('terjadwal', 'selesai', 'dibatalkan') NOT NULL DEFAULT 'terjadwal',
    deskripsi_agenda TEXT NULL,
    dibuat_oleh INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_jadwal_tanggal_status (tanggal_rapat, status_rapat),
    INDEX idx_jadwal_kategori (kategori_unit),
    CONSTRAINT fk_jadwal_user FOREIGN KEY (dibuat_oleh) REFERENCES users(id_user) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notulensi (
    id_notulensi INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_rapat INT UNSIGNED NOT NULL UNIQUE,
    daftar_hadir TEXT NOT NULL,
    isi_pembahasan LONGTEXT NOT NULL,
    keputusan_final TEXT NOT NULL,
    file_lampiran VARCHAR(255) NULL,
    dibuat_oleh INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notulensi_rapat FOREIGN KEY (id_rapat) REFERENCES jadwal_rapat(id_rapat) ON DELETE CASCADE,
    CONSTRAINT fk_notulensi_user FOREIGN KEY (dibuat_oleh) REFERENCES users(id_user) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tindak_lanjut (
    id_tugas INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_notulensi INT UNSIGNED NOT NULL,
    deskripsi_tugas TEXT NOT NULL,
    penanggung_jawab VARCHAR(100) NOT NULL,
    tenggat_waktu DATE NOT NULL,
    status_tugas ENUM('pending', 'proses', 'selesai') NOT NULL DEFAULT 'pending',
    INDEX idx_tugas_status_deadline (status_tugas, tenggat_waktu),
    CONSTRAINT fk_tugas_notulensi FOREIGN KEY (id_notulensi) REFERENCES notulensi(id_notulensi) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- Daily Activities Plan
-- ============================================================

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

-- ============================================================
-- Arsip laporan bulanan (berkas unggahan)
-- ============================================================

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

-- ============================================================
-- Laporan keuangan (hasil impor file Excel)
-- Setiap impor menjadi satu laporan, yaitu satu tab di halaman Laporan Keuangan.
-- ============================================================

CREATE TABLE IF NOT EXISTS keuangan_laporan (
    id_laporan INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    judul VARCHAR(150) NOT NULL,
    tahun SMALLINT UNSIGNED NOT NULL,
    sampai_bulan TINYINT UNSIGNED NOT NULL,
    saldo_awal DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    saldo_akhir_laporan DECIMAL(15,2) NULL,
    nama_file_asli VARCHAR(240) NOT NULL,
    hash_file CHAR(64) NOT NULL,
    diimpor_oleh INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_keuangan_laporan_hash (hash_file),
    INDEX idx_keuangan_laporan_urut (tahun, sampai_bulan, created_at),
    CONSTRAINT fk_keuangan_laporan_user FOREIGN KEY (diimpor_oleh) REFERENCES users(id_user) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS keuangan_laporan_realisasi (
    id_laporan INT UNSIGNED NOT NULL,
    bulan TINYINT UNSIGNED NOT NULL,
    jenis ENUM('penerimaan', 'pengeluaran') NOT NULL,
    komponen VARCHAR(30) NOT NULL,
    jumlah DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (id_laporan, bulan, jenis, komponen),
    CONSTRAINT fk_laporan_realisasi_laporan FOREIGN KEY (id_laporan) REFERENCES keuangan_laporan(id_laporan) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS keuangan_laporan_konsolidasi (
    id_laporan INT UNSIGNED NOT NULL,
    jenis ENUM('penerimaan', 'pengeluaran') NOT NULL,
    komponen VARCHAR(30) NOT NULL,
    jumlah DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    keterangan VARCHAR(500) NULL,
    PRIMARY KEY (id_laporan, jenis, komponen),
    CONSTRAINT fk_laporan_konsolidasi_laporan FOREIGN KEY (id_laporan) REFERENCES keuangan_laporan(id_laporan) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS keuangan_laporan_target (
    id_laporan INT UNSIGNED NOT NULL,
    sumber VARCHAR(30) NOT NULL,
    jumlah DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    catatan VARCHAR(500) NULL,
    PRIMARY KEY (id_laporan, sumber),
    CONSTRAINT fk_laporan_target_laporan FOREIGN KEY (id_laporan) REFERENCES keuangan_laporan(id_laporan) ON DELETE CASCADE
) ENGINE=InnoDB;
