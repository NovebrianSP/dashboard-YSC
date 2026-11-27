<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/dap_import.php';
require_once __DIR__ . '/../../config/database.php';
require_admin();

const DAP_IMPORT_MAX_BYTES = 5 * 1024 * 1024;

$errors = [];
$warnings = [];
$sheetInput = $_POST['sheet'] ?? '';
$sheetName = is_string($sheetInput) ? trim($sheetInput) : '';
$defaultWeekStart = (new DateTimeImmutable('today'))->modify('monday this week');
$weekStartValue = trim((string) ($_POST['tanggal_mulai'] ?? $defaultWeekStart->format('Y-m-d')));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $weekStart = DateTimeImmutable::createFromFormat('!Y-m-d', $weekStartValue);
    if (
        $weekStart === false
        || $weekStart->format('Y-m-d') !== $weekStartValue
        || $weekStart->format('N') !== '1'
    ) {
        $errors[] = 'Tanggal mulai minggu harus berupa tanggal Senin yang valid.';
    }
    if (!is_string($sheetInput)) {
        $errors[] = 'Nama sheet tidak valid.';
    } elseif ($sheetName === '') {
        $warnings[] = 'Isi nama sheet terlebih dahulu sebelum mengimpor.';
    } elseif (mb_strlen($sheetName) > 100) {
        $errors[] = 'Nama sheet maksimal 100 karakter.';
    }

    $file = $_FILES['file_excel'] ?? null;
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        $errors[] = 'Pilih file template Excel (.xlsx) terlebih dahulu.';
    } elseif (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        $errors[] = in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
            ? 'Ukuran file melebihi batas yang diizinkan server.'
            : 'File gagal diunggah. Silakan coba lagi.';
    } elseif ((int) ($file['size'] ?? 0) > DAP_IMPORT_MAX_BYTES) {
        $errors[] = 'Ukuran file maksimal 5 MB.';
    } elseif (strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION)) !== 'xlsx') {
        $errors[] = 'Format file harus .xlsx. File .xls atau .csv tidak didukung.';
    } elseif (!is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
        $errors[] = 'File yang diunggah tidak valid.';
    }

    if (!$errors && !$warnings && $weekStart !== false) {
        $temporaryPath = (string) $file['tmp_name'];
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
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
                $import = dap_import_parse($temporaryPath, $sheetName, $weekStart);
                $result = dap_import_save($pdo, (int) current_user()['id_user'], $weekStart, $import);

                $activityCount = array_sum(array_map('count', $import['activities']));
                $message = $result['created']
                    ? 'DAP berhasil diimpor (' . count(array_filter($import['priorities']))
                        . ' prioritas, ' . $activityCount . ' aktivitas).'
                    : 'DAP untuk minggu tersebut sudah ada dengan isi yang sama; tidak ada perubahan.';
                $targetDates = array_column(dap_week_days($weekStart), 'date');
                if ($import['source_dates'] !== $targetDates) {
                    $message .= ' Tanggal header template berbeda; aktivitas dipetakan menurut urutan hari ke minggu tujuan.';
                    flash('warning', $message);
                } else {
                    flash('success', $message);
                }
                redirect('/modules/dap/detail.php?id=' . $result['id']);
            } catch (DapImportException $exception) {
                $errors = $exception->errors;
            } catch (PDOException $exception) {
                throw $exception;
            } catch (RuntimeException $exception) {
                $errors[] = 'File Excel tidak dapat diproses: ' . $exception->getMessage();
            }
        }
    }
}

$pageTitle = 'Impor DAP dari Excel';
require __DIR__ . '/../../includes/header.php';
?>
<div class="page-heading">
    <div>
        <p class="eyebrow">PERENCANAAN MINGGUAN</p>
        <h1>Impor DAP dari Excel</h1>
        <p>Unggah template DAP YSC untuk mengisi prioritas dan jadwal secara otomatis.</p>
    </div>
    <a class="button button-secondary" href="<?= h(app_url('/modules/dap/index.php')) ?>">Kembali ke DAP</a>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger" role="alert">
        <strong>File belum dapat diimpor.</strong>
        <ul class="mt-2">
            <?php foreach ($errors as $error): ?>
                <li>&bull; <?= h($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($warnings): ?>
    <div class="alert alert-warning" role="alert">
        <strong>Nama sheet diperlukan.</strong>
        <ul class="mt-2">
            <?php foreach ($warnings as $warning): ?>
                <li>&bull; <?= h($warning) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="panel form-panel mb-6">
    <?= csrf_field() ?>
    <div class="mb-4">
        <label class="field-label" for="file_excel">File template Excel (.xlsx, maksimal 5 MB)</label>
        <input class="field-control file:mr-3 file:rounded-md file:border-0 file:bg-blue-50 file:px-3 file:py-1 file:text-sm file:font-semibold file:text-blue-700"
            id="file_excel" type="file" name="file_excel"
            accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet">
        <span class="field-note">Jika validasi gagal, file Excel perlu dipilih ulang sebelum mencoba kembali.</span>
    </div>
    <div class="mb-4">
        <label class="field-label" for="sheet">Nama sheet</label>
        <input class="field-control" id="sheet" type="text" name="sheet" maxlength="100"
            value="<?= h($sheetName) ?>">
        <span class="field-note">Isi sesuai nama tab pada file Excel, termasuk jika hanya ada satu sheet.</span>
    </div>
    <div class="mb-5">
        <label class="field-label" for="tanggal_mulai">Minggu tujuan (tanggal Senin)</label>
        <input class="field-control" id="tanggal_mulai" type="date" name="tanggal_mulai"
            value="<?= h($weekStartValue) ?>" required>
        <span class="field-note">Kolom hari pada template dipetakan berurutan ke Senin–Sabtu pada minggu tujuan.</span>
    </div>
    <div class="flex flex-wrap gap-2">
        <button class="button button-primary" type="submit">Impor DAP</button>
        <a class="button button-secondary" href="<?= h(app_url('/modules/dap/index.php')) ?>">Batal</a>
    </div>
</form>

<section class="panel form-panel">
    <div class="panel-heading"><h2>Ketentuan file</h2></div>
    <ul class="space-y-3 text-sm text-slate-700">
        <li>&bull; Gunakan file Excel dengan susunan template DAP YSC (6 hari, prioritas, dan jadwal).</li>
        <li>&bull; Impor dibuat untuk akun admin yang sedang masuk. DAP yang sudah ada tidak akan ditimpa.</li>
        <li>&bull; Jika DAP minggu tujuan sudah ada dengan isi yang sama, proses tidak membuat duplikat.</li>
        <li>&bull; File diproses sementara dan tidak disimpan di server.</li>
    </ul>
</section>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
