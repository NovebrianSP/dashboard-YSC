<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/monthly_reports.php';
require_monthly_report_access();
require_once __DIR__ . '/../../config/database.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$statement = $pdo->prepare('SELECT nama_file, nama_asli, tipe_mime, ukuran_file FROM laporan_bulanan WHERE id_laporan = :id');
$statement->execute(['id' => $id ?: 0]);
$report = $statement->fetch();
if (!$report) {
    http_response_code(404);
    exit('Laporan bulanan tidak ditemukan.');
}

$filename = (string) $report['nama_file'];
if (basename($filename) !== $filename || !preg_match('/^[a-f0-9]{32}\.(doc|docx|pdf)$/', $filename)) {
    http_response_code(404);
    exit('File laporan tidak tersedia.');
}
$path = __DIR__ . '/../../uploads/laporan_bulanan/' . $filename;
if (!is_file($path)) {
    http_response_code(404);
    exit('File laporan tidak tersedia.');
}

$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$mimeTypes = [
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'pdf' => 'application/pdf',
];
$originalName = (string) $report['nama_asli'];
$originalName = preg_replace('/[\x00-\x1F\x7F]/', '', $originalName) ?? '';
if ($originalName === '') {
    $originalName = 'laporan.' . $extension;
}

header('Content-Type: ' . $mimeTypes[$extension]);
header('Content-Disposition: attachment; filename="laporan.' . $extension . '"; filename*=UTF-8\'\'' . rawurlencode($originalName));
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
readfile($path);
