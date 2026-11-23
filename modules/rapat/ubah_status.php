<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

require_meeting_status_access();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/modules/rapat/index.php');
}
verify_csrf();

// Pertahankan filter yang sedang aktif (hanya key yang diizinkan).
parse_str((string) ($_POST['return_query'] ?? ''), $query);
$query = array_intersect_key($query, array_flip(['status', 'unit', 'q', 'bulan']));
$back = '/modules/rapat/index.php' . ($query ? '?' . http_build_query($query) : '');

$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
$status = (string) ($_POST['status'] ?? '');

if (!$id || !in_array($status, ['terjadwal', 'selesai', 'dibatalkan'], true)) {
    flash('danger', 'Data perubahan status tidak valid.');
    redirect($back);
}

$statement = $pdo->prepare('UPDATE jadwal_rapat SET status_rapat = :status WHERE id_rapat = :id');
$statement->execute(['status' => $status, 'id' => $id]);

if ($statement->rowCount() > 0) {
    flash('success', 'Status rapat diubah menjadi ' . status_label($status) . '.');
} else {
    flash('warning', 'Tidak ada perubahan pada status rapat.');
}
redirect($back);