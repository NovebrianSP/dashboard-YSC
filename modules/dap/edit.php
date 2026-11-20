<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/dap.php';
require_dap_access();
require_once __DIR__ . '/../../config/database.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$statement = $pdo->prepare('SELECT * FROM dap WHERE id_dap = :id AND id_user = :user_id AND status = :status');
$statement->execute([
    'id' => $id ?: 0,
    'user_id' => current_user()['id_user'],
    'status' => 'submitted',
]);
$dap = $statement->fetch();
if (!$dap) {
    http_response_code(404);
    exit('DAP tidak ditemukan atau Anda bukan pemiliknya.');
}

$weekStart = new DateTimeImmutable($dap['tanggal_mulai']);
$dapDays = dap_week_days($weekStart);
$statement = $pdo->prepare('SELECT tanggal, waktu_mulai, aktivitas FROM dap_activities WHERE id_dap = :id');
$statement->execute(['id' => $dap['id_dap']]);
$activityMap = dap_activity_map($statement->fetchAll());
$priorities = dap_priorities_from_storage($dap['pekerjaan_prioritas']);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    [$priorities, $priorityErrors] = dap_collect_priorities($_POST['pekerjaan_prioritas'] ?? []);
    [$activityMap, $activityErrors] = dap_collect_activities($_POST['activities'] ?? [], $dapDays);
    $errors = array_merge($errors, $priorityErrors, $activityErrors);

    if (!$errors) {
        $pdo->beginTransaction();
        try {
            $statement = $pdo->prepare(
                'UPDATE dap SET pekerjaan_prioritas = :priorities WHERE id_dap = :id AND id_user = :user_id'
            );
            $statement->execute([
                'priorities' => dap_priorities_to_storage($priorities),
                'id' => $dap['id_dap'],
                'user_id' => current_user()['id_user'],
            ]);
            $statement = $pdo->prepare('DELETE FROM dap_activities WHERE id_dap = :id');
            $statement->execute(['id' => $dap['id_dap']]);
            dap_save_activities($pdo, (int) $dap['id_dap'], $activityMap);
            $pdo->commit();
            flash('success', 'DAP berhasil diperbarui.');
            redirect('/modules/dap/detail.php?id=' . (int) $dap['id_dap']);
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }
}

$pageTitle = 'Edit DAP';
require __DIR__ . '/../../includes/header.php';
?>
<div class="page-heading">
    <div>
        <p class="eyebrow">PERENCANAAN MINGGUAN</p>
        <h1>Edit Daily Activities Plan</h1>
        <p>Minggu mulai <?= h(format_date($weekStart->format('Y-m-d'))) ?>. DAP hanya dapat diedit oleh pemiliknya.</p>
    </div>
</div>
<?php if ($errors): ?>
    <div class="alert alert-danger" role="alert"><?= h(implode(' ', $errors)) ?></div>
<?php endif; ?>
<form method="post" class="space-y-5">
    <?= csrf_field() ?>
    <section class="panel">
        <h2 class="mb-1 text-lg font-bold text-slate-900">Pekerjaan Prioritas</h2>
        <p class="mb-4 text-sm text-slate-500">Isi hingga 10 pekerjaan prioritas untuk minggu ini. Setidaknya satu isian wajib diisi.</p>
        <div class="dap-priority-inputs">
            <?php foreach ($priorities as $index => $priority): ?>
                <label class="dap-priority-row">
                    <span><?= $index + 1 ?></span>
                    <input class="field-control" type="text" name="pekerjaan_prioritas[<?= $index ?>]" maxlength="500" value="<?= h($priority) ?>" placeholder="Pekerjaan prioritas <?= $index + 1 ?>">
                </label>
            <?php endforeach; ?>
        </div>
    </section>
    <?php $dapEditable = true; ?>
    <?php require __DIR__ . '/_schedule.php'; ?>
    <div class="flex flex-wrap gap-2">
        <button class="button button-primary" type="submit">Simpan Perubahan</button>
        <a class="button button-secondary" href="<?= h(app_url('/modules/dap/detail.php')) ?>?id=<?= (int) $dap['id_dap'] ?>">Batal</a>
    </div>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
