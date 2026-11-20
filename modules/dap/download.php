<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/dap.php';
require_dap_access();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/dap_excel.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$statement = $pdo->prepare(
    "SELECT d.*, u.nama_lengkap, u.jabatan FROM dap d "
    . "JOIN users u ON u.id_user = d.id_user "
    . "WHERE d.id_dap = :id AND d.status = 'submitted'"
);
$statement->execute(['id' => $id ?: 0]);
$dap = $statement->fetch();
if (!$dap) {
    http_response_code(404);
    exit('DAP tidak ditemukan.');
}

$weekStart = new DateTimeImmutable($dap['tanggal_mulai']);
$days = dap_week_days($weekStart);
$priorities = dap_priorities_from_storage($dap['pekerjaan_prioritas']);
$statement = $pdo->prepare('SELECT tanggal, waktu_mulai, aktivitas FROM dap_activities WHERE id_dap = :id');
$statement->execute(['id' => $dap['id_dap']]);
$activities = dap_activity_map($statement->fetchAll());
$content = dap_build_xlsx($dap, $days, $priorities, $activities);
$filename = 'DAP-' . $weekStart->format('Y-m-d') . '-' . (int) $dap['id_dap'] . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($content));
header('X-Content-Type-Options: nosniff');
echo $content;
