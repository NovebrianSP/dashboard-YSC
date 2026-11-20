<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/dap.php';
require_dap_access();
require_once __DIR__ . '/../../config/database.php';

$errors = [];
$currentDate = new DateTimeImmutable('today');
$defaultWeekStart = $currentDate->modify('monday this week');
$weekStartValue = trim((string) ($_POST['tanggal_mulai'] ?? $_GET['week_start'] ?? $defaultWeekStart->format('Y-m-d')));
$priorities = array_fill(0, 10, '');
$priorityErrors = [];
$weekStart = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$priorities, $priorityErrors] = dap_collect_priorities($_POST['pekerjaan_prioritas'] ?? null);
}
if (valid_date($weekStartValue)) {
    $candidateWeekStart = new DateTimeImmutable($weekStartValue);
    if ($candidateWeekStart->format('N') === '1') {
        $weekStart = $candidateWeekStart;
    }
}
$dapDays = $weekStart ? dap_week_days($weekStart) : [];
[$activityMap, $activityErrors] = dap_collect_activities($_POST['activities'] ?? [], $dapDays);

if ($weekStart) {
    $existingStatement = $pdo->prepare('SELECT id_dap FROM dap WHERE id_user = :user_id AND tanggal_mulai = :week_start');
    $existingStatement->execute([
        'user_id' => current_user()['id_user'],
        'week_start' => $weekStart->format('Y-m-d'),
    ]);
    $existingId = $existingStatement->fetchColumn();
    if ($existingId) {
        redirect('/modules/dap/edit.php?id=' . (int) $existingId);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!$weekStart) {
        $errors[] = 'Tanggal mulai minggu harus merupakan tanggal Senin yang valid.';
    }
    $errors = array_merge($errors, $priorityErrors, $activityErrors);

    if (!$errors) {
        $pdo->beginTransaction();
        try {
            $statement = $pdo->prepare(
                "INSERT INTO dap (id_user, tanggal_mulai, pekerjaan_prioritas, status) "
                . "VALUES (:user_id, :week_start, :priorities, 'submitted')"
            );
            $statement->execute([
                'user_id' => current_user()['id_user'],
                'week_start' => $weekStart->format('Y-m-d'),
                'priorities' => dap_priorities_to_storage($priorities),
            ]);
            $dapId = (int) $pdo->lastInsertId();
            dap_save_activities($pdo, $dapId, $activityMap);
            $pdo->commit();
            flash('success', 'DAP berhasil disubmit.');
            redirect('/modules/dap/detail.php?id=' . $dapId);
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }
}

$pageTitle = 'Buat DAP';
require __DIR__ . '/../../includes/header.php';
?>
<div class="page-heading">
    <div>
        <p class="eyebrow">PERENCANAAN MINGGUAN</p>
        <h1>Buat Daily Activities Plan</h1>
        <p>Pilih tanggal Senin sebagai awal minggu, lalu isi prioritas dan aktivitas.</p>
    </div>
</div>
<?php if ($errors): ?>
    <div class="alert alert-danger" role="alert"><?= h(implode(' ', $errors)) ?></div>
<?php endif; ?>
<form method="get" class="panel mb-5 flex flex-wrap items-end gap-3">
    <div class="w-full max-w-sm">
        <label class="field-label" for="week_start">Tanggal mulai minggu (Senin)</label>
        <input class="field-control" type="date" id="week_start" name="week_start" value="<?= h($weekStartValue) ?>" required>
    </div>
    <button class="button button-secondary" type="submit">Tampilkan Minggu</button>
</form>
<form method="post" class="space-y-5">
    <?= csrf_field() ?>
    <input type="hidden" name="tanggal_mulai" value="<?= h($weekStartValue) ?>">
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

    <?php if ($dapDays): ?>
        <p class="text-sm text-slate-500">Slot aktivitas tersedia Senin–Jumat pukul 07.30–15.30 dan Sabtu pukul 07.30–13.00. Slot yang tidak digunakan boleh dikosongkan.</p>
        <?php $dapEditable = true; ?>
        <?php require __DIR__ . '/_schedule.php'; ?>
    <?php else: ?>
        <p class="text-sm text-slate-500">Pilih tanggal Senin yang valid untuk menampilkan jadwal minggu tersebut.</p>
    <?php endif; ?>

    <div class="flex flex-wrap gap-2">
        <button class="button button-primary" type="submit">Submit DAP</button>
        <a class="button button-secondary" href="<?= h(app_url('/modules/dap/index.php')) ?>">Batal</a>
    </div>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
