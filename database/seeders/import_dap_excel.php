<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../../config/dap_import.php';

if ($argc !== 5) {
    fwrite(
        STDERR,
        "Penggunaan: php database/seeders/import_dap_excel.php <file.xlsx> <sheet> <email-admin> <tanggal-senin-YYYY-MM-DD>\n"
    );
    exit(1);
}

$filePath = realpath($argv[1]);
$sheetName = trim($argv[2]);
$email = trim($argv[3]);
$weekStartValue = trim($argv[4]);
$weekStart = DateTimeImmutable::createFromFormat('!Y-m-d', $weekStartValue);

if (
    $filePath === false
    || !is_file($filePath)
    || filesize($filePath) > 5 * 1024 * 1024
    || strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) !== 'xlsx'
) {
    fwrite(STDERR, "File .xlsx tidak ditemukan atau ukurannya melebihi 5 MB.\n");
    exit(1);
}
if ($sheetName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Nama sheet dan email admin harus valid.\n");
    exit(1);
}
if (
    $weekStart === false
    || $weekStart->format('Y-m-d') !== $weekStartValue
    || $weekStart->format('N') !== '1'
) {
    fwrite(STDERR, "Tanggal mulai minggu harus berupa tanggal Senin dengan format YYYY-MM-DD.\n");
    exit(1);
}

require_once __DIR__ . '/../../config/database.php';

try {
    $import = dap_import_parse($filePath, $sheetName, $weekStart);
    $userStatement = $pdo->prepare(
        "SELECT id_user FROM users WHERE email = :email AND role = 'admin' LIMIT 1"
    );
    $userStatement->execute(['email' => $email]);
    $userId = $userStatement->fetchColumn();
    if ($userId === false) {
        throw new RuntimeException('Akun admin dengan email tersebut tidak ditemukan.');
    }

    $targetDates = array_column(dap_week_days($weekStart), 'date');
    if ($import['source_dates'] !== $targetDates) {
        fwrite(
            STDERR,
            'Catatan: tanggal header sheet (' . implode(', ', $import['source_dates'])
                . ') berbeda dari minggu target. Aktivitas dipetakan menurut urutan hari ke '
                . $weekStart->format('Y-m-d') . ".\n"
        );
    }

    $result = dap_import_save($pdo, (int) $userId, $weekStart, $import);
    if (!$result['created']) {
        fwrite(STDOUT, "DAP sudah ada dengan isi yang sama; tidak ada perubahan.\n");
        exit(0);
    }

    $activityCount = array_sum(array_map('count', $import['activities']));
    fwrite(
        STDOUT,
        'DAP berhasil diimpor untuk akun ' . $email . ' minggu ' . $weekStart->format('Y-m-d')
            . ' (ID ' . $result['id'] . ', ' . count(array_filter($import['priorities']))
            . ' prioritas, ' . $activityCount . " aktivitas).\n"
    );
} catch (Throwable $exception) {
    fwrite(STDERR, 'Impor DAP gagal: ' . $exception->getMessage() . "\n");
    exit(1);
}
