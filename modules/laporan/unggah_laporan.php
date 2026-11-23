<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/monthly_reports.php';
require_monthly_report_access();
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Metode tidak diizinkan.');
}
verify_csrf();

$month = trim((string) ($_POST['bulan'] ?? ''));
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) || !valid_date($month . '-01')) {
    flash('danger', 'Pilih bulan laporan yang valid.');
    redirect('/modules/laporan/index.php');
}

try {
    $uploadedFile = upload_monthly_report($_FILES['file_laporan'] ?? []);
} catch (RuntimeException $exception) {
    flash('danger', $exception->getMessage());
    redirect('/modules/laporan/index.php');
}

try {
    $statement = $pdo->prepare(
        'INSERT INTO laporan_bulanan (bulan, nama_file, nama_asli, tipe_mime, ukuran_file, diunggah_oleh) '
        . 'VALUES (:bulan, :nama_file, :nama_asli, :tipe_mime, :ukuran_file, :diunggah_oleh)'
    );
    $statement->execute([
        'bulan' => $month . '-01',
        'nama_file' => $uploadedFile['filename'],
        'nama_asli' => $uploadedFile['original_name'],
        'tipe_mime' => $uploadedFile['mime'],
        'ukuran_file' => $uploadedFile['size'],
        'diunggah_oleh' => current_user()['id_user'],
    ]);
} catch (Throwable $exception) {
    delete_monthly_report_file($uploadedFile['filename']);
    throw $exception;
}

flash('success', 'Laporan bulanan berhasil diunggah.');
redirect('/modules/laporan/index.php');
