<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/finance.php';
require_once __DIR__ . '/../../config/database.php';

require_finance_access();

$currentYear = (int) date('Y');
$year = filter_input(INPUT_GET, 'tahun', FILTER_VALIDATE_INT);
$year = $year !== false && $year !== null && $year >= 2000 && $year <= 2100 ? $year : $currentYear;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $year = filter_input(INPUT_POST, 'tahun', FILTER_VALIDATE_INT);
    if ($year === false || $year === null || $year < 2000 || $year > 2100) {
        flash('danger', 'Tahun saldo tidak valid.');
        redirect('/modules/keuangan/saldo.php');
    }

    $openingBalance = finance_normalize_signed_amount($_POST['saldo_awal'] ?? null);
    $closingInput = $_POST['saldo_akhir_laporan'] ?? '';
    $closingBalance = $closingInput === '' ? null : finance_normalize_signed_amount($closingInput);
    $submittedNote = $_POST['catatan'] ?? '';
    $note = is_string($submittedNote) ? trim($submittedNote) : '';

    if ($openingBalance === null || ($closingInput !== '' && $closingBalance === null)) {
        flash('danger', 'Saldo harus berupa angka dengan maksimal dua angka desimal.');
        redirect('/modules/keuangan/saldo.php?tahun=' . (int) $year);
    }
    if (!is_string($submittedNote) || mb_strlen($note) > 500) {
        flash('danger', 'Catatan saldo maksimal 500 karakter.');
        redirect('/modules/keuangan/saldo.php?tahun=' . (int) $year);
    }

    $statement = $pdo->prepare(
        'INSERT INTO keuangan_saldo_tahunan (tahun, saldo_awal, saldo_akhir_laporan, catatan, diinput_oleh)
         VALUES (:tahun, :saldo_awal, :saldo_akhir, :catatan, :user_id)
         ON DUPLICATE KEY UPDATE saldo_awal = VALUES(saldo_awal), saldo_akhir_laporan = VALUES(saldo_akhir_laporan),
             catatan = VALUES(catatan), diinput_oleh = VALUES(diinput_oleh), updated_at = CURRENT_TIMESTAMP'
    );
    $statement->execute([
        'tahun' => $year,
        'saldo_awal' => $openingBalance,
        'saldo_akhir' => $closingBalance,
        'catatan' => $note !== '' ? $note : null,
        'user_id' => current_user()['id_user'],
    ]);

    flash('success', 'Saldo tahunan berhasil disimpan.');
    redirect('/modules/keuangan/saldo.php?tahun=' . (int) $year);
}

$statement = $pdo->prepare('SELECT saldo_awal, saldo_akhir_laporan, catatan FROM keuangan_saldo_tahunan WHERE tahun = :tahun');
$statement->execute(['tahun' => $year]);
$balances = $statement->fetch() ?: [
    'saldo_awal' => '0.00',
    'saldo_akhir_laporan' => null,
    'catatan' => '',
];

$pageTitle = 'Saldo Tahunan';
require __DIR__ . '/../../includes/header.php';
?>
<div class="page-heading">
    <div>
        <p class="eyebrow">KEUANGAN</p>
        <h1>Saldo Tahunan</h1>
        <p>Catat saldo awal dan saldo akhir sesuai laporan untuk membantu rekonsiliasi arus kas tahunan.</p>
    </div>
</div>

<form class="filter-bar" method="get">
    <label class="field-label mb-0" for="saldo-tahun">Tahun</label>
    <input class="field-control sm:w-auto" id="saldo-tahun" type="number" name="tahun" min="2000" max="2100" value="<?= (int) $year ?>" required>
    <button class="button button-secondary" type="submit">Pilih Tahun</button>
    <a class="button button-secondary" href="<?= h(app_url('/modules/keuangan/index.php')) ?>?tahun=<?= (int) $year ?>">Kembali ke Ringkasan</a>
</form>

<form method="post" class="panel form-panel">
    <?= csrf_field() ?>
    <input type="hidden" name="tahun" value="<?= (int) $year ?>">
    <h2 class="mb-1 text-lg font-bold text-slate-900">Posisi Saldo <?= (int) $year ?></h2>
    <p class="mb-5 text-sm text-slate-500">Saldo akhir laporan bersifat opsional sampai laporan tahunan tersedia. Nilai dapat positif atau negatif.</p>
    <div class="mb-4">
        <label class="field-label" for="saldo-awal">Saldo awal tahun</label>
        <div class="flex items-center gap-2">
            <span aria-hidden="true">Rp</span>
            <input class="field-control" id="saldo-awal" type="number" name="saldo_awal" min="-9999999999999.99" max="9999999999999.99" step="0.01" inputmode="decimal" value="<?= h((string) $balances['saldo_awal']) ?>" required>
        </div>
    </div>
    <div class="mb-4">
        <label class="field-label" for="saldo-akhir">Saldo akhir menurut laporan (opsional)</label>
        <div class="flex items-center gap-2">
            <span aria-hidden="true">Rp</span>
            <input class="field-control" id="saldo-akhir" type="number" name="saldo_akhir_laporan" min="-9999999999999.99" max="9999999999999.99" step="0.01" inputmode="decimal"
                value="<?= h($balances['saldo_akhir_laporan'] === null ? '' : (string) $balances['saldo_akhir_laporan']) ?>">
        </div>
    </div>
    <div class="mb-5">
        <label class="field-label" for="saldo-catatan">Catatan (opsional)</label>
        <textarea class="field-control" id="saldo-catatan" name="catatan" maxlength="500" rows="3"><?= h($balances['catatan'] ?? '') ?></textarea>
    </div>
    <div class="flex flex-wrap gap-2">
        <button class="button button-primary" type="submit">Simpan Saldo</button>
        <a class="button button-secondary" href="<?= h(app_url('/modules/keuangan/index.php')) ?>?tahun=<?= (int) $year ?>">Batal</a>
    </div>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
