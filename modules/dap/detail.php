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
$isOwner = (int) $dap['id_user'] === (int) current_user()['id_user'];
$pageTitle = 'Detail DAP';
require __DIR__ . '/../../includes/header.php';
?>
<div class="page-heading">
    <div>
        <p class="eyebrow">PERENCANAAN MINGGUAN</p>
        <h1>Daily Activities Plan</h1>
        <p><?= h($dap['nama_lengkap']) ?> · <?= h($dap['jabatan']) ?></p>
        <p><?= h(format_date($weekStart->format('Y-m-d'))) ?> – <?= h(format_date($weekEnd->format('Y-m-d'))) ?></p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a class="button button-secondary" href="<?= h(app_url('/modules/dap/download.php')) ?>?id=<?= (int) $dap['id_dap'] ?>">Download Excel</a>
        <a class="button button-secondary" href="<?= h(app_url('/modules/dap/cetak.php')) ?>?id=<?= (int) $dap['id_dap'] ?>">Cetak / PDF</a>
        <?php if ($isOwner): ?>
            <a class="button button-primary" href="<?= h(app_url('/modules/dap/edit.php')) ?>?id=<?= (int) $dap['id_dap'] ?>">Edit DAP</a>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/_priorities.php'; ?>
<?php $dapEditable = false; ?>
<?php require __DIR__ . '/_schedule.php'; ?>
<a class="button button-secondary" href="<?= h(app_url('/modules/dap/index.php')) ?>">Kembali ke daftar DAP</a>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
