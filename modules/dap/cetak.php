<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/dap.php';
require_dap_access();
require_once __DIR__ . '/../../config/database.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$statement = $pdo->prepare(
    "SELECT d.*, u.nama_lengkap, u.jabatan FROM dap d "
    . "JOIN users u ON u.id_user = d.id_user "
    . "WHERE d.id_dap = :id AND d.status = 'submitted'"
);
$statement->execute(['id' => $id ?: 0]);
$dap = $statement->fetch();
if (!$dap) {
    http_response_code(404);
    exit('DAP tidak ditemukan.');
}

$weekStart = new DateTimeImmutable($dap['tanggal_mulai']);
$weekEnd = $weekStart->modify('+5 days');
$dapDays = dap_week_days($weekStart);
$dapPriorities = dap_priorities_from_storage($dap['pekerjaan_prioritas']);
$statement = $pdo->prepare('SELECT tanggal, waktu_mulai, aktivitas FROM dap_activities WHERE id_dap = :id');
$statement->execute(['id' => $dap['id_dap']]);
$activityMap = dap_activity_map($statement->fetchAll());
$pageTitle = 'Cetak DAP';
require __DIR__ . '/../../includes/header.php';
?>
<div class="print-actions">
    <button class="button button-primary" type="button" onclick="window.print()">Cetak / Simpan PDF</button>
    <a class="button button-secondary" href="<?= h(app_url('/modules/dap/detail.php')) ?>?id=<?= (int) $dap['id_dap'] ?>">Kembali</a>
</div>
<article class="panel print-document dap-print-document mx-auto w-full">
    <header class="mb-6 border-b border-slate-300 pb-4 text-center">
        <p class="font-bold">YAYASAN SMART CENDEKIA</p>
        <h1 class="text-2xl font-bold text-slate-900">DAILY ACTIVITIES PLAN</h1>
        <p class="mt-2 text-sm"><?= h($dap['nama_lengkap']) ?> · <?= h($dap['jabatan']) ?></p>
        <p class="text-sm"><?= h(format_date($weekStart->format('Y-m-d'))) ?> – <?= h(format_date($weekEnd->format('Y-m-d'))) ?></p>
    </header>
    <?php require __DIR__ . '/_priorities.php'; ?>
    <?php $dapEditable = false; ?>
    <?php require __DIR__ . '/_schedule.php'; ?>
</article>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
