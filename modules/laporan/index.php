<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/monthly_reports.php';
require_monthly_report_access();
require_once __DIR__ . '/../../config/database.php';

$statement = $pdo->query(
    'SELECT r.id_laporan, r.bulan, r.nama_file, r.nama_asli, r.ukuran_file, r.created_at, u.nama_lengkap '
    . 'FROM laporan_bulanan r LEFT JOIN users u ON u.id_user = r.diunggah_oleh '
    . 'ORDER BY r.bulan DESC, r.created_at DESC'
);
$monthlyReportGroups = [];
foreach ($statement->fetchAll() as $report) {
    $monthlyReportGroups[$report['bulan']][] = $report;
}
$indonesianMonths = [
    1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
];
$pageTitle = 'Arsip Laporan Bulanan';
require __DIR__ . '/../../includes/header.php';
?>
<div class="page-heading">
    <div>
        <p class="eyebrow">DOKUMENTASI</p>
        <h1>Arsip Laporan Bulanan</h1>
        <p class="text-slate-500">Unggah, lihat, dan unduh laporan bulanan.</p>
    </div>
</div>
<div class="grid grid-cols-1 items-start gap-5 xl:grid-cols-3">
    <section class="panel xl:col-span-2" aria-labelledby="monthly-archive-title">
        <div class="panel-heading">
            <div>
                <h2 id="monthly-archive-title">Laporan Tersimpan</h2>
                <p class="mt-1 text-sm text-slate-500">Laporan dikelompokkan berdasarkan bulan.</p>
            </div>
        </div>
        <?php if ($monthlyReportGroups): ?>
            <div class="monthly-archive-list">
                <?php foreach ($monthlyReportGroups as $reportMonth => $reports): ?>
                    <?php
                    $reportDate = new DateTimeImmutable($reportMonth);
                    $reportMonthLabel = $indonesianMonths[(int) $reportDate->format('n')] . ' ' . $reportDate->format('Y');
                    ?>
                    <section class="monthly-report-group">
                        <h3 class="monthly-report-heading">
                            <?= h($reportMonthLabel) ?>
                            <span class="monthly-archive-count"><?= count($reports) ?></span>
                        </h3>
                        <ul class="monthly-report-list">
                            <?php foreach ($reports as $report): ?>
                                <?php $extension = strtolower(pathinfo($report['nama_file'], PATHINFO_EXTENSION)); ?>
                                <li class="monthly-report-item">
                                    <div class="min-w-0">
                                        <strong class="block break-words text-sm"><?= h($report['nama_asli']) ?></strong>
                                        <span class="text-xs text-slate-500">
                                            <?= h($report['nama_lengkap'] ?: 'Pengguna tidak tersedia') ?>
                                            · <?= h(format_date($report['created_at'])) ?>
                                            · <?= h(number_format((int) $report['ukuran_file'] / 1024, 0, ',', '.')) ?> KB
                                        </span>
                                    </div>
                                    <div class="flex shrink-0 items-center gap-2">
                                        <?php if ($extension !== 'doc'): ?>
                                            <a class="button button-primary button-sm" href="<?= h(app_url('/modules/laporan/lihat_laporan.php')) ?>?id=<?= (int) $report['id_laporan'] ?>" target="_blank" rel="noopener">Lihat</a>
                                        <?php else: ?>
                                            <span class="text-xs text-slate-500">DOC perlu diunduh</span>
                                        <?php endif; ?>
                                        <a class="button button-secondary button-sm" href="<?= h(app_url('/modules/laporan/unduh_laporan.php')) ?>?id=<?= (int) $report['id_laporan'] ?>">Unduh</a>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </section>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="empty-state">Belum ada file laporan bulanan.</p>
        <?php endif; ?>
    </section>
    <section class="panel" aria-labelledby="upload-report-title">
        <div class="panel-heading">
            <div>
                <h2 id="upload-report-title">Submit Laporan</h2>
                <p class="mt-1 text-sm text-slate-500">DOC, DOCX, atau PDF; maksimal 10 MB per file.</p>
            </div>
        </div>
        <form action="<?= h(app_url('/modules/laporan/unggah_laporan.php')) ?>" method="post" enctype="multipart/form-data" class="space-y-4">
            <?= csrf_field() ?>
            <div>
                <label class="field-label" for="bulan_laporan">Bulan laporan</label>
                <input class="field-control" type="month" id="bulan_laporan" name="bulan" value="<?= h(date('Y-m')) ?>" required>
            </div>
            <div>
                <label class="field-label" for="file_laporan">File laporan</label>
                <input class="field-control" type="file" id="file_laporan" name="file_laporan" accept=".doc,.docx,.pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/pdf" required>
            </div>
            <button class="button button-primary" type="submit">Unggah Laporan</button>
        </form>
    </section>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
