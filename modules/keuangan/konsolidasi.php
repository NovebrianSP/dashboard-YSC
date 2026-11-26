<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/finance.php';
require_once __DIR__ . '/../../config/database.php';

require_finance_access();

$currentYear = (int) date('Y');
$year = filter_input(INPUT_GET, 'tahun', FILTER_VALIDATE_INT);
$year = $year !== false && $year !== null && $year >= 2000 && $year <= 2100 ? $year : $currentYear;
$requestedMonth = filter_input(INPUT_GET, 'sampai', FILTER_VALIDATE_INT);
if ($requestedMonth !== false && $requestedMonth !== null && $requestedMonth >= 1 && $requestedMonth <= 12) {
    $month = $requestedMonth;
} else {
    $statement = $pdo->prepare('SELECT MAX(sampai_bulan) FROM keuangan_konsolidasi_sumber WHERE tahun = :tahun');
    $statement->execute(['tahun' => $year]);
    $lastSavedMonth = $statement->fetchColumn();
    $month = $lastSavedMonth !== false && $lastSavedMonth !== null
        ? (int) $lastSavedMonth
        : ($year === $currentYear ? (int) date('n') : 12);
}
$groups = [
    'penerimaan' => finance_income_sources(),
    'pengeluaran' => finance_expense_components(),
];
$defaultNotes = finance_consolidated_notes();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $year = filter_input(INPUT_POST, 'tahun', FILTER_VALIDATE_INT);
    $month = filter_input(INPUT_POST, 'sampai', FILTER_VALIDATE_INT);
    if ($year === false || $year === null || $year < 2000 || $year > 2100 || $month === false || $month === null || $month < 1 || $month > 12) {
        flash('danger', 'Tahun atau bulan laporan sumber tidak valid.');
        redirect('/modules/keuangan/konsolidasi.php');
    }

    $submittedAmounts = $_POST['realisasi'] ?? null;
    $submittedNotes = $_POST['keterangan'] ?? null;
    $amounts = [];
    $notes = [];
    $error = '';
    foreach ($groups as $type => $components) {
        foreach ($components as $key => $_label) {
            $amount = is_array($submittedAmounts) && isset($submittedAmounts[$type])
                ? finance_normalize_amount($submittedAmounts[$type][$key] ?? null)
                : null;
            $note = is_array($submittedNotes) && isset($submittedNotes[$type][$key])
                ? $submittedNotes[$type][$key]
                : null;
            if ($amount === null) {
                $error = 'Nilai laporan sumber harus berupa angka non-negatif dengan maksimal dua angka desimal.';
                break 2;
            }
            if (!is_string($note) || mb_strlen(trim($note)) > 500) {
                $error = 'Keterangan tiap komponen maksimal 500 karakter.';
                break 2;
            }
            $amounts[$type][$key] = $amount;
            $notes[$type][$key] = trim($note);
        }
    }
    if ($error !== '') {
        flash('danger', $error);
        redirect('/modules/keuangan/konsolidasi.php?tahun=' . (int) $year . '&sampai=' . (int) $month);
    }

    $pdo->beginTransaction();
    try {
        $statement = $pdo->prepare(
            'INSERT INTO keuangan_konsolidasi_sumber
                (tahun, sampai_bulan, jenis, komponen, jumlah, keterangan, diinput_oleh)
             VALUES
                (:tahun, :sampai_bulan, :jenis, :komponen, :jumlah, :keterangan, :user_id)
             ON DUPLICATE KEY UPDATE jumlah = VALUES(jumlah), keterangan = VALUES(keterangan),
                diinput_oleh = VALUES(diinput_oleh), updated_at = CURRENT_TIMESTAMP'
        );
        foreach ($amounts as $type => $components) {
            foreach ($components as $key => $amount) {
                $statement->execute([
                    'tahun' => $year,
                    'sampai_bulan' => $month,
                    'jenis' => $type,
                    'komponen' => $key,
                    'jumlah' => $amount,
                    'keterangan' => $notes[$type][$key],
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

    flash('success', 'Realisasi konsolidasi laporan sumber berhasil disimpan.');
    redirect('/modules/keuangan/konsolidasi.php?tahun=' . (int) $year . '&sampai=' . (int) $month);
}

$values = [];
foreach ($groups as $type => $components) {
    foreach ($components as $key => $_label) {
        $values[$type][$key] = ['jumlah' => '0.00', 'keterangan' => $defaultNotes[$type][$key]];
    }
}
$statement = $pdo->prepare(
    'SELECT jenis, komponen, jumlah, keterangan
     FROM keuangan_konsolidasi_sumber
     WHERE tahun = :tahun AND sampai_bulan = :sampai_bulan'
);
$statement->execute(['tahun' => $year, 'sampai_bulan' => $month]);
foreach ($statement->fetchAll() as $row) {
    if (isset($values[$row['jenis']][$row['komponen']])) {
        $values[$row['jenis']][$row['komponen']] = [
            'jumlah' => (string) $row['jumlah'],
            'keterangan' => (string) ($row['keterangan'] ?? ''),
        ];
    }
}
$monthNames = finance_month_names();

$pageTitle = 'Realisasi Konsolidasi';
require __DIR__ . '/../../includes/header.php';
?>
<div class="page-heading">
    <div>
        <p class="eyebrow">KEUANGAN</p>
        <h1>Realisasi dari Laporan Sumber</h1>
        <p>Catat angka konsolidasi secara terpisah dari input bulanan agar keduanya dapat direkonsiliasi tanpa penghitungan ganda.</p>
    </div>
</div>

<form class="filter-bar" method="get">
    <label class="sr-only" for="konsolidasi-tahun">Tahun</label>
    <input class="field-control sm:w-auto" id="konsolidasi-tahun" type="number" name="tahun" min="2000" max="2100" value="<?= (int) $year ?>" required>
    <label class="sr-only" for="konsolidasi-bulan">Sampai bulan</label>
    <select class="field-control sm:w-auto" id="konsolidasi-bulan" name="sampai">
        <?php foreach ($monthNames as $number => $name): ?>
            <option value="<?= (int) $number ?>" <?= $number === $month ? 'selected' : '' ?>>Kumulatif s.d. <?= h($name) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="button button-secondary" type="submit">Pilih Periode</button>
    <a class="button button-secondary" href="<?= h(app_url('/modules/keuangan/detail.php')) ?>?tahun=<?= (int) $year ?>">Lihat Rekap Terperinci</a>
</form>

<form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="tahun" value="<?= (int) $year ?>">
    <input type="hidden" name="sampai" value="<?= (int) $month ?>">
    <div class="mb-6 grid grid-cols-1 gap-4 lg:grid-cols-2">
        <?php foreach ($groups as $type => $components): ?>
            <section class="panel">
                <div class="panel-heading">
                    <h2><?= $type === 'penerimaan' ? 'Penerimaan' : 'Pengeluaran' ?> — <?= (int) $year ?> s.d. <?= h($monthNames[$month]) ?></h2>
                </div>
                <?php foreach ($components as $key => $label): ?>
                    <div class="mb-4">
                        <label class="field-label" for="<?= h($type . '-' . $key) ?>"><?= h($label) ?></label>
                        <div class="flex items-center gap-2">
                            <span aria-hidden="true">Rp</span>
                            <input class="field-control" id="<?= h($type . '-' . $key) ?>" type="number"
                                name="realisasi[<?= h($type) ?>][<?= h($key) ?>]" min="0" max="9999999999999.99"
                                step="0.01" inputmode="decimal" value="<?= h($values[$type][$key]['jumlah']) ?>" required>
                        </div>
                        <label class="field-label mt-2" for="keterangan-<?= h($type . '-' . $key) ?>">Keterangan</label>
                        <input class="field-control" id="keterangan-<?= h($type . '-' . $key) ?>" type="text"
                            name="keterangan[<?= h($type) ?>][<?= h($key) ?>]" maxlength="500"
                            value="<?= h($values[$type][$key]['keterangan']) ?>">
                    </div>
                <?php endforeach; ?>
            </section>
        <?php endforeach; ?>
    </div>
    <div class="flex flex-wrap gap-2">
        <button class="button button-primary" type="submit">Simpan Laporan Sumber</button>
        <a class="button button-secondary" href="<?= h(app_url('/modules/keuangan/index.php')) ?>?tahun=<?= (int) $year ?>&amp;sampai=<?= (int) $month ?>">Kembali ke Ringkasan</a>
    </div>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
