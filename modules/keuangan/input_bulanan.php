<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/finance.php';
require_once __DIR__ . '/../../config/database.php';

require_finance_access();

$currentYear = (int) date('Y');
$year = filter_input(INPUT_GET, 'tahun', FILTER_VALIDATE_INT);
$year = $year !== false && $year !== null && $year >= 2000 && $year <= 2100 ? $year : $currentYear;
$month = filter_input(INPUT_GET, 'bulan', FILTER_VALIDATE_INT);
$month = $month !== false && $month !== null && $month >= 1 && $month <= 12
    ? $month
    : ($year === $currentYear ? (int) date('n') : 12);
$groups = [
    'penerimaan' => finance_income_sources(),
    'pengeluaran' => finance_expense_components(),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $year = filter_input(INPUT_POST, 'tahun', FILTER_VALIDATE_INT);
    $month = filter_input(INPUT_POST, 'bulan', FILTER_VALIDATE_INT);
    if ($year === false || $year === null || $year < 2000 || $year > 2100 || $month === false || $month === null || $month < 1 || $month > 12) {
        flash('danger', 'Tahun atau bulan tidak valid.');
        redirect('/modules/keuangan/input_bulanan.php');
    }

    $submitted = $_POST['realisasi'] ?? null;
    $amounts = [];
    $errors = [];
    foreach ($groups as $type => $components) {
        if (!is_array($submitted) || !isset($submitted[$type]) || !is_array($submitted[$type])) {
            $errors[] = 'Semua nilai realisasi wajib diisi.';
            break;
        }
        foreach ($components as $key => $_label) {
            $amount = finance_normalize_amount($submitted[$type][$key] ?? null);
            if ($amount === null) {
                $errors[] = 'Nilai realisasi harus berupa angka non-negatif dengan maksimal dua angka desimal.';
                break 2;
            }
            $amounts[$type][$key] = $amount;
        }
    }
    $submittedNote = $_POST['catatan'] ?? '';
    $note = is_string($submittedNote) ? trim($submittedNote) : '';
    if (!is_string($submittedNote)) {
        $errors[] = 'Catatan tidak valid.';
    }
    if (mb_strlen($note) > 500) {
        $errors[] = 'Catatan maksimal 500 karakter.';
    }
    if ($errors) {
        flash('danger', implode(' ', $errors));
        redirect('/modules/keuangan/input_bulanan.php?tahun=' . (int) $year . '&bulan=' . (int) $month);
    }

    $pdo->beginTransaction();
    try {
        $statement = $pdo->prepare(
            'INSERT INTO keuangan_periode (tahun, bulan, catatan, diinput_oleh)
             VALUES (:tahun, :bulan, :catatan, :user_id)
             ON DUPLICATE KEY UPDATE catatan = VALUES(catatan), diinput_oleh = VALUES(diinput_oleh), updated_at = CURRENT_TIMESTAMP'
        );
        $statement->execute([
            'tahun' => $year,
            'bulan' => $month,
            'catatan' => $note !== '' ? $note : null,
            'user_id' => current_user()['id_user'],
        ]);
        $statement = $pdo->prepare(
            'INSERT INTO keuangan_realisasi_bulanan (tahun, bulan, jenis, komponen, jumlah, diinput_oleh)
             VALUES (:tahun, :bulan, :jenis, :komponen, :jumlah, :user_id)
             ON DUPLICATE KEY UPDATE jumlah = VALUES(jumlah), diinput_oleh = VALUES(diinput_oleh), updated_at = CURRENT_TIMESTAMP'
        );
        foreach ($amounts as $type => $components) {
            foreach ($components as $key => $amount) {
                $statement->execute([
                    'tahun' => $year,
                    'bulan' => $month,
                    'jenis' => $type,
                    'komponen' => $key,
                    'jumlah' => $amount,
                    'user_id' => current_user()['id_user'],
                ]);
            }
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }

    flash('success', 'Realisasi bulanan berhasil disimpan.');
    redirect('/modules/keuangan/input_bulanan.php?tahun=' . (int) $year . '&bulan=' . (int) $month);
}

$amounts = ['penerimaan' => [], 'pengeluaran' => []];
$statement = $pdo->prepare(
    'SELECT jenis, komponen, jumlah FROM keuangan_realisasi_bulanan WHERE tahun = :tahun AND bulan = :bulan'
);
$statement->execute(['tahun' => $year, 'bulan' => $month]);
foreach ($statement->fetchAll() as $row) {
    if (isset($groups[$row['jenis']][$row['komponen']])) {
        $amounts[$row['jenis']][$row['komponen']] = (string) $row['jumlah'];
    }
}
$statement = $pdo->prepare('SELECT catatan, updated_at FROM keuangan_periode WHERE tahun = :tahun AND bulan = :bulan');
$statement->execute(['tahun' => $year, 'bulan' => $month]);
$period = $statement->fetch() ?: ['catatan' => '', 'updated_at' => null];
$monthNames = finance_month_names();

$pageTitle = 'Input Realisasi Bulanan';
require __DIR__ . '/../../includes/header.php';
?>
<div class="page-heading">
    <div>
        <p class="eyebrow">KEUANGAN</p>
        <h1>Input Realisasi Bulanan</h1>
        <p>Isi total bulanan per sumber penerimaan dan komponen pengeluaran, sesuai rekap konsolidasi.</p>
    </div>
</div>

<form class="filter-bar" method="get">
    <label class="sr-only" for="input-tahun">Tahun</label>
    <input class="field-control sm:w-auto" id="input-tahun" type="number" name="tahun" min="2000" max="2100" value="<?= (int) $year ?>" required>
    <label class="sr-only" for="input-bulan">Bulan</label>
    <select class="field-control sm:w-auto" id="input-bulan" name="bulan">
        <?php foreach ($monthNames as $number => $name): ?>
            <option value="<?= (int) $number ?>" <?= $number === $month ? 'selected' : '' ?>><?= h($name) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="button button-secondary" type="submit">Pilih Periode</button>
    <a class="button button-secondary" href="<?= h(app_url('/modules/keuangan/index.php')) ?>?tahun=<?= (int) $year ?>&amp;sampai=<?= (int) $month ?>">Kembali ke Ringkasan</a>
</form>

<?php if ($period['updated_at']): ?>
    <p class="field-note mb-4">Terakhir disimpan <?= h(format_date(substr($period['updated_at'], 0, 10))) ?>. Simpan kembali untuk mengganti seluruh nilai pada bulan ini.</p>
<?php endif; ?>

<form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="tahun" value="<?= (int) $year ?>">
    <input type="hidden" name="bulan" value="<?= (int) $month ?>">
    <div class="mb-6 grid grid-cols-1 gap-4 lg:grid-cols-2">
        <?php foreach ($groups as $type => $components): ?>
            <section class="panel">
                <div class="panel-heading">
                    <h2><?= $type === 'penerimaan' ? 'Penerimaan' : 'Pengeluaran' ?> — <?= h($monthNames[$month]) ?> <?= (int) $year ?></h2>
                </div>
                <?php foreach ($components as $key => $label): ?>
                    <div class="mb-4">
                        <label class="field-label" for="<?= h($type . '-' . $key) ?>"><?= h($label) ?></label>
                        <div class="flex items-center gap-2">
                            <span aria-hidden="true">Rp</span>
                            <input class="field-control" id="<?= h($type . '-' . $key) ?>" type="number" name="realisasi[<?= h($type) ?>][<?= h($key) ?>]"
                                min="0" max="9999999999999.99" step="0.01" inputmode="decimal"
                                value="<?= h($amounts[$type][$key] ?? '0.00') ?>" required>
                        </div>
                    </div>
                <?php endforeach; ?>
            </section>
        <?php endforeach; ?>
    </div>
    <section class="panel mb-5">
        <label class="field-label" for="catatan">Catatan sumber / konsolidasi (opsional)</label>
        <textarea class="field-control" id="catatan" name="catatan" maxlength="500" rows="3"><?= h($period['catatan'] ?? '') ?></textarea>
    </section>
    <div class="flex flex-wrap gap-2">
        <button class="button button-primary" type="submit">Simpan Realisasi Bulan</button>
        <a class="button button-secondary" href="<?= h(app_url('/modules/keuangan/index.php')) ?>?tahun=<?= (int) $year ?>&amp;sampai=<?= (int) $month ?>">Batal</a>
    </div>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
