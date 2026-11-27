<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/finance.php';

$visibility = meeting_visibility_sql('j');
$statement = $pdo->prepare("SELECT COUNT(*) FROM jadwal_rapat j WHERE {$visibility} AND YEAR(j.tanggal_rapat) = YEAR(CURRENT_DATE()) AND MONTH(j.tanggal_rapat) = MONTH(CURRENT_DATE()) AND j.status_rapat = 'terjadwal'");
$statement->execute();
$meetingsThisMonth = (int) $statement->fetchColumn();
$statement = $pdo->prepare("SELECT COUNT(*) FROM notulensi n JOIN jadwal_rapat j ON j.id_rapat = n.id_rapat WHERE {$visibility}");
$statement->execute();
$minutesCount = (int) $statement->fetchColumn();
$statement = $pdo->prepare("SELECT j.* FROM jadwal_rapat j WHERE {$visibility} AND j.status_rapat = 'terjadwal' AND j.tanggal_rapat >= CURRENT_DATE() ORDER BY j.tanggal_rapat, j.waktu_mulai LIMIT 1");
$statement->execute();
$upcoming = $statement->fetchAll();
$nextMeeting = $upcoming[0] ?? null;

// DAP minggu ini. Aturan akses sama dengan menu Daily Activities Plan di sidebar (admin dan pimpinan).
$dapVisible = in_array(current_user()['role'] ?? '', ['admin', 'pimpinan'], true);
$dapSubmitters = [];
$dapEligible = 0;
$dapWeekStart = '';
$dapWeekEnd = '';
if ($dapVisible) {
    // Minggu berjalan: Senin sampai Minggu (zona waktu WIB).
    $weekStart = new DateTimeImmutable('monday this week', new DateTimeZone('Asia/Jakarta'));
    $dapWeekStart = $weekStart->format('Y-m-d');
    $dapWeekEnd = $weekStart->modify('+6 days')->format('Y-m-d');

    $statement = $pdo->prepare(
        "SELECT u.id_user, u.nama_lengkap, MAX(d.submitted_at) AS submitted_at
         FROM dap d
         JOIN users u ON u.id_user = d.id_user
         WHERE d.status = 'submitted' AND d.tanggal_mulai BETWEEN :mulai AND :akhir
         GROUP BY u.id_user, u.nama_lengkap
         ORDER BY submitted_at ASC"
    );
    $statement->execute(['mulai' => $dapWeekStart, 'akhir' => $dapWeekEnd]);
    $dapSubmitters = $statement->fetchAll();

    $dapEligible = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('admin', 'pimpinan')")->fetchColumn();
}
$dapCount = count($dapSubmitters);

$financeVisible = can_view_finance();
$dashboardYear = (int) date('Y');
$financeMonths = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
$financeMonthly = array_fill(1, 12, ['penerimaan' => 0.0, 'pengeluaran' => 0.0]);
$financeMax = 0.0;
if ($financeVisible) {
    // Laporan terbaru hasil impor Excel; urutannya sama dengan tab pertama di halaman Laporan Keuangan.
    $latestReport = $pdo->query(
        'SELECT id_laporan, tahun
         FROM keuangan_laporan
         ORDER BY tahun DESC, sampai_bulan DESC, created_at DESC, id_laporan DESC
         LIMIT 1'
    )->fetch();

    if ($latestReport) {
        $dashboardYear = (int) $latestReport['tahun'];
        $statement = $pdo->prepare(
            'SELECT bulan, jenis, SUM(jumlah) AS total
             FROM keuangan_laporan_realisasi
             WHERE id_laporan = :id
             GROUP BY bulan, jenis'
        );
        $statement->execute(['id' => $latestReport['id_laporan']]);
        foreach ($statement->fetchAll() as $row) {
            $financeMonthly[(int) $row['bulan']][$row['jenis']] = (float) $row['total'];
            $financeMax = max($financeMax, (float) $row['total']);
        }
    }
}

$financeScaleMax = $financeMax > 0 ? $financeMax : 1;
$formatCompactAmount = static function (float $amount): string {
    if ($amount >= 1000000000) {
        return number_format($amount / 1000000000, 1, ',', '.') . ' M';
    }
    if ($amount >= 1000000) {
        return number_format($amount / 1000000, 0, ',', '.') . ' jt';
    }
    if ($amount >= 1000) {
        return number_format($amount / 1000, 0, ',', '.') . ' rb';
    }
    return number_format($amount, 0, ',', '.');
};
$formatExactRupiah = static function (float $amount): string {
    $formatted = number_format($amount, 2, ',', '.');
    return 'Rp ' . (preg_replace('/,00$/', '', $formatted) ?? $formatted);
};

$pageTitle = 'Ringkasan';
require __DIR__ . '/includes/header.php';
?>
<div class="page-heading">
    <div>
        <p class="eyebrow">OVERVIEW</p>
        <h1>Ringkasan Dashboard</h1>
        <p class="text-slate-500">Pantau jadwal rapat, arsip notulensi, dan ringkasan keuangan.</p>
    </div>
    <?php if (can_create_meeting()): ?><a href="<?= h(app_url('/modules/rapat/tambah.php')) ?>" class="button button-primary">+ Buat Jadwal</a><?php endif; ?>
</div>
<div class="mb-4 grid grid-cols-1 gap-3 md:grid-cols-3">
    <div class="stat-card"><span>Rapat terjadwal bulan ini</span><strong><?= $meetingsThisMonth ?></strong><a href="<?= h(app_url('/modules/rapat/index.php')) ?>">Lihat jadwal →</a></div>
    <div class="stat-card"><span>Arsip notulensi</span><strong><?= $minutesCount ?></strong><a href="<?= h(app_url('/modules/rapat/index.php#arsip-notulensi')) ?>">Lihat arsip →</a></div>
    <?php if ($dapVisible): ?>
        <div class="stat-card">
            <span>DAP minggu ini</span>
            <strong><?= $dapCount ?></strong>
            <span>dari <?= $dapEligible ?> pengguna sudah mengisi</span>
            <span class="text-xs text-slate-400"><?= h(format_date($dapWeekStart)) ?> &ndash; <?= h(format_date($dapWeekEnd)) ?></span>
            <?php if ($dapSubmitters): ?>
                <ul class="mt-2 text-sm text-slate-700">
                    <?php foreach (array_slice($dapSubmitters, 0, 5) as $person): ?>
                        <li>&bull; <?= h($person['nama_lengkap']) ?></li>
                    <?php endforeach; ?>
                    <?php if ($dapCount > 5): ?>
                        <li class="text-slate-500">+<?= $dapCount - 5 ?> lainnya</li>
                    <?php endif; ?>
                </ul>
            <?php else: ?>
                <span class="mt-2">Belum ada yang mengisi minggu ini.</span>
            <?php endif; ?>
            <a class="mt-2" href="<?= h(app_url('/modules/dap/index.php')) ?>">Lihat DAP →</a>
        </div>
    <?php else: ?>
        <div class="stat-card">
            <span>Jadwal terdekat</span>
            <?php if ($nextMeeting): ?>
                <strong class="text-lg"><?= h($nextMeeting['judul_rapat']) ?></strong>
                <span><?= h(format_date($nextMeeting['tanggal_rapat'])) ?> · <?= h(substr($nextMeeting['waktu_mulai'], 0, 5)) ?></span>
                <a href="<?= h(app_url('/modules/rapat/index.php')) ?>">Lihat detail →</a>
            <?php else: ?>
                <strong class="text-lg">Belum ada</strong>
                <span>Tidak ada jadwal mendatang.</span>
                <a href="<?= h(app_url('/modules/rapat/index.php')) ?>">Lihat jadwal →</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
<?php if ($financeVisible): ?>
    <section class="panel">
        <div class="panel-heading">
            <h2>Rekap Penerimaan vs Pengeluaran per Bulan (<?= $dashboardYear ?>)</h2>
            <a href="<?= h(app_url('/modules/keuangan/index.php')) ?>">Laporan keuangan</a>
        </div>
        <div style="overflow-x:auto">
            <svg viewBox="0 0 1000 390" role="img" aria-labelledby="finance-chart-title finance-chart-description"
                style="display:block;width:100%;min-width:720px;height:auto">
                <title id="finance-chart-title">Rekap penerimaan dan pengeluaran tahun <?= $dashboardYear ?></title>
                <desc id="finance-chart-description">Grafik batang berkelompok yang membandingkan penerimaan dan pengeluaran untuk setiap bulan.</desc>
                <?php
                $plotLeft = 100;
                $plotTop = 24;
                $plotWidth = 870;
                $plotHeight = 300;
                $groupWidth = $plotWidth / 12;
                $barWidth = 15;
                for ($tick = 0; $tick <= 4; $tick++):
                    $value = $financeScaleMax * $tick / 4;
                    $y = $plotTop + $plotHeight - ($plotHeight * $tick / 4);
                ?>
                    <line x1="<?= $plotLeft ?>" y1="<?= number_format($y, 2, '.', '') ?>" x2="<?= $plotLeft + $plotWidth ?>" y2="<?= number_format($y, 2, '.', '') ?>" stroke="#cbd5e1" stroke-dasharray="<?= $tick === 0 ? '0' : '4 4' ?>" />
                    <text x="<?= $plotLeft - 12 ?>" y="<?= number_format($y + 4, 2, '.', '') ?>" text-anchor="end" fill="#64748b" font-size="12"><?= h($formatCompactAmount($value)) ?></text>
                <?php endfor; ?>
                <?php foreach ($financeMonths as $index => $monthLabel): ?>
                    <?php
                    $month = $index + 1;
                    $center = $plotLeft + ($index * $groupWidth) + ($groupWidth / 2);
                    $income = $financeMonthly[$month]['penerimaan'];
                    $expense = $financeMonthly[$month]['pengeluaran'];
                    $incomeHeight = $plotHeight * $income / $financeScaleMax;
                    $expenseHeight = $plotHeight * $expense / $financeScaleMax;
                    ?>
                    <rect x="<?= number_format($center - $barWidth - 2, 2, '.', '') ?>" y="<?= number_format($plotTop + $plotHeight - $incomeHeight, 2, '.', '') ?>"
                        width="<?= $barWidth ?>" height="<?= number_format($incomeHeight, 2, '.', '') ?>" rx="2" fill="#22c55e">
                        <title><?= h($monthLabel . ' — Penerimaan: ' . $formatExactRupiah($income)) ?></title>
                    </rect>
                    <rect x="<?= number_format($center + 2, 2, '.', '') ?>" y="<?= number_format($plotTop + $plotHeight - $expenseHeight, 2, '.', '') ?>"
                        width="<?= $barWidth ?>" height="<?= number_format($expenseHeight, 2, '.', '') ?>" rx="2" fill="#ef4444">
                        <title><?= h($monthLabel . ' — Pengeluaran: ' . $formatExactRupiah($expense)) ?></title>
                    </rect>
                    <text x="<?= number_format($center, 2, '.', '') ?>" y="<?= $plotTop + $plotHeight + 24 ?>" text-anchor="middle" fill="#64748b" font-size="12"><?= h($monthLabel) ?></text>
                <?php endforeach; ?>
            </svg>
        </div>
        <div class="mt-4 flex flex-wrap gap-4 text-sm">
            <span><span aria-hidden="true" style="display:inline-block;width:12px;height:12px;background:#22c55e;margin-right:6px"></span>Penerimaan</span>
            <span><span aria-hidden="true" style="display:inline-block;width:12px;height:12px;background:#ef4444;margin-right:6px"></span>Pengeluaran</span>
            <?php if ($financeMax <= 0): ?><span class="text-slate-500">Belum ada realisasi keuangan tahun ini.</span><?php endif; ?>
        </div>
        <p class="field-note mt-2">Sumbu vertikal dalam rupiah. Arahkan kursor ke batang untuk melihat nilai rinci.</p>
    </section>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>