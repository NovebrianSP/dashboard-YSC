<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/finance.php';
require_once __DIR__ . '/../../config/database.php';

require_finance_access();

$currentYear = (int) date('Y');
$year = filter_input(INPUT_GET, 'tahun', FILTER_VALIDATE_INT);
$year = $year !== false && $year !== null && $year >= 2000 && $year <= 2100 ? $year : $currentYear;
$sources = finance_income_sources();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $year = filter_input(INPUT_POST, 'tahun', FILTER_VALIDATE_INT);
    if ($year === false || $year === null || $year < 2000 || $year > 2100) {
        flash('danger', 'Tahun target tidak valid.');
        redirect('/modules/keuangan/target.php');
    }

    $submitted = $_POST['target'] ?? null;
    $targets = [];
    foreach ($sources as $key => $_label) {
        $amount = is_array($submitted) ? finance_normalize_amount($submitted[$key] ?? null) : null;
        if ($amount === null) {
            flash('danger', 'Target harus berupa angka non-negatif dengan maksimal dua angka desimal.');
            redirect('/modules/keuangan/target.php?tahun=' . (int) $year);
        }
        $targets[$key] = $amount;
    }

    $pdo->beginTransaction();
    try {
        $statement = $pdo->prepare(
            'INSERT INTO keuangan_target_tahunan (tahun, sumber, jumlah, diinput_oleh)
             VALUES (:tahun, :sumber, :jumlah, :user_id)
             ON DUPLICATE KEY UPDATE jumlah = VALUES(jumlah), diinput_oleh = VALUES(diinput_oleh), updated_at = CURRENT_TIMESTAMP'
        );
        foreach ($targets as $source => $amount) {
            $statement->execute([
                'tahun' => $year,
                'sumber' => $source,
                'jumlah' => $amount,
                'user_id' => current_user()['id_user'],
            ]);
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }

    flash('success', 'Target penerimaan tahunan berhasil disimpan.');
    redirect('/modules/keuangan/target.php?tahun=' . (int) $year);
}

$targets = [];
$statement = $pdo->prepare('SELECT sumber, jumlah FROM keuangan_target_tahunan WHERE tahun = :tahun');
$statement->execute(['tahun' => $year]);
foreach ($statement->fetchAll() as $row) {
    if (isset($sources[$row['sumber']])) {
        $targets[$row['sumber']] = (string) $row['jumlah'];
    }
}

$pageTitle = 'Target Penerimaan';
require __DIR__ . '/../../includes/header.php';
?>
<div class="page-heading">
    <div>
        <p class="eyebrow">KEUANGAN</p>
        <h1>Target Penerimaan Tahunan</h1>
        <p>Tetapkan target untuk tiap sumber penerimaan. Capaian dihitung otomatis dari realisasi bulanan.</p>
    </div>
</div>

<form class="filter-bar" method="get">
    <label class="field-label mb-0" for="target-tahun">Tahun</label>
    <input class="field-control sm:w-auto" id="target-tahun" type="number" name="tahun" min="2000" max="2100" value="<?= (int) $year ?>" required>
    <button class="button button-secondary" type="submit">Pilih Tahun</button>
    <a class="button button-secondary" href="<?= h(app_url('/modules/keuangan/index.php')) ?>?tahun=<?= (int) $year ?>">Kembali ke Ringkasan</a>
</form>

<form method="post" class="panel form-panel">
    <?= csrf_field() ?>
    <input type="hidden" name="tahun" value="<?= (int) $year ?>">
    <h2 class="mb-1 text-lg font-bold text-slate-900">Target <?= (int) $year ?></h2>
    <p class="mb-5 text-sm text-slate-500">Masukkan target tahunan dalam rupiah. Isi 0 jika sumber tersebut tidak memiliki target.</p>
    <?php foreach ($sources as $key => $label): ?>
        <div class="mb-4">
            <label class="field-label" for="target-<?= h($key) ?>"><?= h($label) ?></label>
            <div class="flex items-center gap-2">
                <span aria-hidden="true">Rp</span>
                <input class="field-control" id="target-<?= h($key) ?>" type="number" name="target[<?= h($key) ?>]"
                    min="0" max="9999999999999.99" step="0.01" inputmode="decimal"
                    value="<?= h($targets[$key] ?? '0.00') ?>" required>
            </div>
        </div>
    <?php endforeach; ?>
    <div class="mt-5 flex flex-wrap gap-2">
        <button class="button button-primary" type="submit">Simpan Target</button>
        <a class="button button-secondary" href="<?= h(app_url('/modules/keuangan/index.php')) ?>?tahun=<?= (int) $year ?>">Batal</a>
    </div>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
