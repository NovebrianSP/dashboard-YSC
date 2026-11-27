<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/dap.php';
require_dap_access();
require_once __DIR__ . '/../../config/database.php';

$statement = $pdo->query(
    "SELECT d.id_dap, d.id_user, d.tanggal_mulai, d.submitted_at, u.nama_lengkap, u.jabatan "
    . "FROM dap d JOIN users u ON u.id_user = d.id_user "
    . "WHERE d.status = 'submitted' ORDER BY d.tanggal_mulai DESC, u.nama_lengkap"
);
$plans = $statement->fetchAll();
$currentUserId = (int) current_user()['id_user'];
$pageTitle = 'Daily Activities Plan';
require __DIR__ . '/../../includes/header.php';
?>
<div class="page-heading">
    <div>
        <p class="eyebrow">PERENCANAAN MINGGUAN</p>
        <h1>Daily Activities Plan</h1>
        <p>Lihat DAP yang sudah disubmit atau buat rencana mingguan Anda.</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <?php if (is_admin()): ?>
            <a class="button button-secondary" href="<?= h(app_url('/modules/dap/impor.php')) ?>">Impor dari Excel</a>
        <?php endif; ?>
        <a class="button button-primary" href="<?= h(app_url('/modules/dap/tambah.php')) ?>">+ Buat DAP</a>
    </div>
</div>
<div class="table-container">
    <table class="data-table">
        <thead><tr><th>Minggu</th><th>Pemilik</th><th>Disubmit</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php foreach ($plans as $plan): ?>
            <?php
            $weekStart = new DateTimeImmutable($plan['tanggal_mulai']);
            $weekEnd = $weekStart->modify('+5 days');
            $ownedByCurrentUser = (int) $plan['id_user'] === $currentUserId;
            ?>
            <tr>
                <td><?= h(format_date($weekStart->format('Y-m-d'))) ?> – <?= h(format_date($weekEnd->format('Y-m-d'))) ?></td>
                <td><strong><?= h($plan['nama_lengkap']) ?></strong><div class="text-sm text-slate-500"><?= h($plan['jabatan']) ?></div></td>
                <td><?= h(format_date($plan['submitted_at'])) ?></td>
                <td class="whitespace-nowrap">
                    <a class="button button-secondary button-sm" href="<?= h(app_url('/modules/dap/detail.php')) ?>?id=<?= (int) $plan['id_dap'] ?>">Lihat</a>
                    <?php if ($ownedByCurrentUser): ?>
                        <a class="button button-secondary button-sm" href="<?= h(app_url('/modules/dap/edit.php')) ?>?id=<?= (int) $plan['id_dap'] ?>">Edit</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$plans): ?>
            <tr><td colspan="4" class="py-8 text-center text-slate-500">Belum ada DAP yang disubmit.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
