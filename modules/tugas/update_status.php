<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Metode tidak diizinkan.'); }
verify_csrf();
$taskId = filter_input(INPUT_POST, 'id_tugas', FILTER_VALIDATE_INT);
$status = (string) ($_POST['status_tugas'] ?? '');
$returnTo = safe_return_path((string) ($_POST['return_to'] ?? ''), '/modules/rapat/index.php#arsip-notulensi');
if (!$taskId || !in_array($status, ['pending','proses','selesai'], true)) { http_response_code(400); exit('Data status tugas tidak valid.'); }
$statement = $pdo->prepare('SELECT t.penanggung_jawab,j.kategori_unit FROM tindak_lanjut t JOIN notulensi n ON n.id_notulensi=t.id_notulensi JOIN jadwal_rapat j ON j.id_rapat=n.id_rapat WHERE t.id_tugas=:id');
$statement->execute(['id' => $taskId]);
$task = $statement->fetch();
if (!$task) { http_response_code(404); exit('Tugas tidak ditemukan.'); }
if (!can_access_category($task['kategori_unit'])) { http_response_code(403); exit('Tugas ini tidak tersedia untuk unit Anda.'); }
if (!is_admin() && strcasecmp((string) current_user()['nama_lengkap'], $task['penanggung_jawab']) !== 0) {
    http_response_code(403);
    exit('Hanya admin atau penanggung jawab tugas yang dapat memperbarui status.');
}
$statement = $pdo->prepare('UPDATE tindak_lanjut SET status_tugas=:status WHERE id_tugas=:id');
$statement->execute(['status'=>$status,'id'=>$taskId]);
flash('success', 'Status tindak lanjut berhasil diperbarui.');
redirect($returnTo);
