<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/finance.php';
require_once __DIR__ . '/../../config/finance_import.php';
require_once __DIR__ . '/../../config/database.php';

require_finance_access();

const IMPOR_MAX_BYTES = 5 * 1024 * 1024;
const IMPOR_TEMPLATE_PATH = '/assets/templates/Dashboard_Capaian_Keuangan.xlsx';

$errors = [];
$duplicate = null;
$titleInput = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $titleInput = mb_substr(trim((string) ($_POST['judul'] ?? '')), 0, 150);
    $file = $_FILES['laporan'] ?? null;

    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        $errors[] = 'Pilih file Excel (.xlsx) terlebih dahulu.';
    } elseif ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
            ? 'Ukuran file melebihi batas yang diizinkan server.'
            : 'File gagal diunggah. Silakan coba lagi.';
    } elseif ((int) $file['size'] > IMPOR_MAX_BYTES) {
        $errors[] = 'Ukuran file maksimal 5 MB.';
    } elseif (strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION)) !== 'xlsx') {
        $errors[] = 'Format file harus .xlsx. File .xls atau .csv tidak didukung.';
    } elseif (!is_uploaded_file((string) $file['tmp_name'])) {
        $errors[] = 'File tidak valid.';
    } else {
        $tmp = (string) $file['tmp_name'];
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
        $allowedMime = [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/zip',
            'application/x-zip-compressed',
            'application/octet-stream',
        ];

        if (!in_array($mime, $allowedMime, true)) {
            $errors[] = 'Tipe file tidak dikenali sebagai berkas Excel (.xlsx).';
        } else {
            try {
                $data = finance_import_parse($tmp);
                $hash = (string) hash_file('sha256', $tmp);

                $statement = $pdo->prepare('SELECT id_laporan, judul FROM keuangan_laporan WHERE hash_file = :hash LIMIT 1');
                $statement->execute(['hash' => $hash]);
                $duplicate = $statement->fetch() ?: null;

                if ($duplicate === null) {
                    $title = $titleInput !== ''
                        ? $titleInput
                        : finance_import_default_title($data['tahun'], $data['sampai_bulan']);
                    $originalName = mb_substr(
                        preg_replace('/[\x00-\x1F\x7F]/u', '', basename((string) $file['name'])) ?? '',
                        0,
                        240
                    );
                    $userId = (int) (current_user()['id_user'] ?? 0) ?: null;

                    $reportId = finance_import_save($pdo, $data, $title, $originalName, $hash, $userId);
                    flash('success', 'Laporan "' . $title . '" berhasil diimpor dan ditampilkan pada tab baru.');
                    redirect('/modules/keuangan/index.php?laporan=' . $reportId);
                }
                $errors[] = 'File ini sudah pernah diimpor, sehingga tidak dibuat tab baru.';
            } catch (FinanceImportException $exception) {
                $errors = $exception->errors;
            } catch (PDOException $exception) {
                if ((string) $exception->getCode() === '23000') {
                    $errors[] = 'File ini sudah pernah diimpor.';
                } else {
                    throw $exception;
                }
            }
        }
    }
}

$templateFile = dirname(__DIR__, 2) . IMPOR_TEMPLATE_PATH;
$pageTitle = 'Impor Laporan Keuangan';
require __DIR__ . '/../../includes/header.php';
?>
<div class="page-heading">
    <div>
        <p class="eyebrow">KEUANGAN</p>
        <h1>Impor Laporan Keuangan</h1>
        <p>Unggah file Excel Dashboard Capaian Keuangan. Setiap impor menjadi tab laporan tersendiri.</p>
    </div>
    <a class="button button-secondary" href="<?= h(app_url('/modules/keuangan/index.php')) ?>">Kembali ke Laporan</a>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger" role="alert">
        <strong>File belum dapat diimpor.</strong>
        <ul class="mt-2">
            <?php foreach ($errors as $error): ?>
                <li>&bull; <?= h($error) ?></li>
            <?php endforeach; ?>
        </ul>
        <?php if ($duplicate): ?>
            <p class="mt-2"><a href="<?= h(app_url('/modules/keuangan/index.php')) ?>?laporan=<?= (int) $duplicate['id_laporan'] ?>">Buka tab &ldquo;<?= h($duplicate['judul']) ?>&rdquo;</a></p>
        <?php endif; ?>
    </div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="panel form-panel mb-6">
    <?= csrf_field() ?>
    <div class="mb-4">
        <label class="field-label" for="laporan">File Excel (.xlsx, maksimal 5 MB)</label>
        <input class="field-control file:mr-3 file:rounded-md file:border-0 file:bg-blue-50 file:px-3 file:py-1 file:text-sm file:font-semibold file:text-blue-700"
            id="laporan" type="file" name="laporan"
            accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
    </div>
    <div class="mb-5">
        <label class="field-label" for="judul">Judul tab (opsional)</label>
        <input class="field-control" id="judul" type="text" name="judul" maxlength="150"
            value="<?= h($titleInput) ?>" placeholder="Contoh: Laporan 2026 s.d. Oktober">
        <span class="field-note">Jika dikosongkan, judul dibuat otomatis dari tahun dan bulan terakhir yang terisi.</span>
    </div>
    <div class="flex flex-wrap gap-2">
        <button class="button button-primary" type="submit">Impor Laporan</button>
        <a class="button button-secondary" href="<?= h(app_url('/modules/keuangan/index.php')) ?>">Batal</a>
    </div>
</form>

<section class="panel form-panel">
    <div class="panel-heading"><h2>Ketentuan file</h2></div>
    <ul class="space-y-4 text-sm text-slate-700">
        <li>&bull; Gunakan template <strong>Dashboard Capaian Keuangan</strong> tanpa mengubah nama sheet maupun letak kolom dan barisnya.
            <?php if (is_file($templateFile)): ?>
                <a href="<?= h(app_url(IMPOR_TEMPLATE_PATH)) ?>" download>Unduh template</a>.
            <?php endif; ?>
        </li>
        <li>&bull; Sheet yang dibaca: <em>Dashboard</em>, <em>Rekap Penerimaan</em>, <em>Rekap Pengeluaran</em>, dan <em>Target Penerimaan</em>.</li>
        <li>&bull; Tahun diambil dari judul sheet, misalnya &ldquo;REKAP PENERIMAAN 2026&rdquo;.</li>
        <li>&bull; Nilai harus berupa angka bukan negatif (sel kosong dianggap 0). Saldo awal dan saldo akhir boleh negatif.</li>
        <li>&bull; Saldo akhir menurut laporan bernilai 0 dianggap belum diisi.</li>
        <li>&bull; File yang isinya persis sama dengan yang pernah diimpor akan ditolak. File Excel tidak disimpan di server, hanya angka hasil bacanya.</li>
        <li>&bull; Simpan file di Excel sebelum diunggah agar nilai rumusnya ikut tersimpan.</li>
    </ul>
</section>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
