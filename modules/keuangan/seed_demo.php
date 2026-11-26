<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/modules/keuangan/transaksi.php');
}
verify_csrf();

$action = (string) ($_POST['action'] ?? '');

if ($action === 'hapus') {
    $deleted = $pdo->exec('DELETE FROM transaksi_keuangan WHERE is_demo = 1');
    flash('success', "Data demo dihapus ({$deleted} baris). Data asli tidak tersentuh.");
    redirect('/modules/keuangan/transaksi.php');
}
if ($action !== 'buat') {
    redirect('/modules/keuangan/transaksi.php');
}

mt_srand(2026);

// Pembulatan ke puluhan ribu, dengan variasi acak +/- $pct dari nilai dasar.
$vary = static fn(int $base, float $pct): int =>
    (int) (round($base * (1 + (mt_rand(-100, 100) / 100) * $pct) / 10000) * 10000);
$chance = static fn(int $percent): bool => mt_rand(1, 100) <= $percent;

$today = new DateTimeImmutable('today');
$firstThis = $today->modify('first day of this month');
$by = (int) (current_user()['id_user'] ?? 0) ?: null;

$pdo->beginTransaction();
try {
    $pdo->exec('DELETE FROM transaksi_keuangan WHERE is_demo = 1');
    $insert = $pdo->prepare(
        'INSERT INTO transaksi_keuangan (tanggal, jenis, kategori, pos, keterangan, jumlah, is_demo, dibuat_oleh)
         VALUES (:tanggal, :jenis, :kategori, :pos, :ket, :jumlah, 1, :by)'
    );
    $add = static function (DateTimeImmutable $date, string $jenis, string $kategori, string $pos, string $ket, int $amount)
        use ($insert, $today, $by): void {
        if ($date > $today) {
            return; // tidak membuat transaksi di masa depan
        }
        $insert->execute([
            'tanggal' => $date->format('Y-m-d'), 'jenis' => $jenis, 'kategori' => $kategori,
            'pos' => $pos, 'ket' => $ket, 'jumlah' => $amount, 'by' => $by,
        ]);
    };

    for ($i = 11; $i >= 0; $i--) {
        $month = $firstThis->modify("-{$i} month");
        $label = $month->format('m/Y');
        $on = static fn(int $day): DateTimeImmutable =>
            $month->setDate((int) $month->format('Y'), (int) $month->format('n'), $day);

        // ===== YAYASAN =====
        $add($on(10), 'pemasukan', 'Kontribusi Unit', 'yayasan', "Kontribusi unit pendidikan {$label}", $vary(60000000, 0.08));
        if ($chance(30)) {
            $add($on(14), 'pemasukan', 'Hibah', 'yayasan', "Hibah {$label}", $vary(25000000, 0.4));
        }
        if ($chance(50)) {
            $add($on(20), 'pemasukan', 'Sewa Aset', 'yayasan', "Sewa aset yayasan {$label}", $vary(4000000, 0.2));
        }
        $add($on(25), 'pengeluaran', 'Gaji & Honor', 'yayasan', "Gaji dan honor pengurus {$label}", $vary(26000000, 0.03));
        $add($on(15), 'pengeluaran', 'Operasional', 'yayasan', "Biaya operasional {$label}", $vary(9000000, 0.2));
        $add($on(5), 'pengeluaran', 'Listrik & Air', 'yayasan', "Tagihan listrik dan air {$label}", $vary(3500000, 0.15));
        if ($chance(25)) {
            $add($on(22), 'pengeluaran', 'Pemeliharaan', 'yayasan', "Pemeliharaan gedung {$label}", $vary(12000000, 0.5));
        }
        if ($chance(12)) {
            $add($on(18), 'pengeluaran', 'Pembangunan', 'yayasan', "Pembangunan fasilitas {$label}", $vary(30000000, 0.4));
        }

        // ===== KANTIN =====
        foreach ([7, 14, 21, 28] as $week => $day) {
            $n = $week + 1;
            $add($on($day), 'pemasukan', 'Penjualan', 'kantin', "Penjualan minggu ke-{$n} {$label}", $vary(14000000, 0.2));
            $add($on($day), 'pengeluaran', 'Bahan Baku', 'kantin', "Belanja bahan baku minggu ke-{$n} {$label}", $vary(8000000, 0.2));
        }
        if ($chance(40)) {
            $add($on(16), 'pemasukan', 'Pesanan Katering', 'kantin', "Pesanan katering acara {$label}", $vary(6000000, 0.5));
        }
        $add($on(25), 'pengeluaran', 'Gaji Karyawan', 'kantin', "Gaji karyawan kantin {$label}", $vary(9000000, 0.03));
        $add($on(6), 'pengeluaran', 'Gas & Listrik', 'kantin', "Gas dan listrik kantin {$label}", $vary(1800000, 0.15));
        if ($chance(20)) {
            $add($on(19), 'pengeluaran', 'Peralatan', 'kantin', "Peralatan dapur {$label}", $vary(3500000, 0.5));
        }

        // ===== DONASI =====
        $donations = mt_rand(3, 6);
        for ($d = 1; $d <= $donations; $d++) {
            $add($on(mt_rand(1, 28)), 'pemasukan', 'Donasi Umum', 'donasi', "Donasi masuk {$label}", $vary(4000000, 0.7));
        }
        $add($on(9), 'pemasukan', 'Infaq', 'donasi', "Infaq jamaah {$label}", $vary(8000000, 0.3));
        if ($chance(30)) {
            $add($on(21), 'pemasukan', 'Wakaf', 'donasi', "Wakaf {$label}", $vary(15000000, 0.5));
        }
        $add($on(24), 'pengeluaran', 'Beasiswa', 'donasi', "Penyaluran beasiswa {$label}", $vary(12000000, 0.1));
        if ($chance(45)) {
            $add($on(17), 'pengeluaran', 'Bantuan Sosial', 'donasi', "Bantuan sosial {$label}", $vary(6000000, 0.4));
        }
        if ($chance(10)) {
            $add($on(27), 'pengeluaran', 'Pembangunan', 'donasi', "Dana pembangunan dari wakaf {$label}", $vary(20000000, 0.3));
        }
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}

flash('success', 'Data demo berhasil dibuat untuk 12 bulan terakhir.');
redirect('/modules/keuangan/transaksi.php');