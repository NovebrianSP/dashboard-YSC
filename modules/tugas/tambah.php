<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_admin();
require_once __DIR__ . '/../../config/database.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Metode tidak diizinkan.'); }
verify_csrf();
$minuteId = filter_input(INPUT_POST, 'id_notulensi', FILTER_VALIDATE_INT);
$description = trim((string) ($_POST['deskripsi_tugas'] ?? ''));
$pic = trim((string) ($_POST['penanggung_jawab'] ?? ''));
$deadline = trim((string) ($_POST['tenggat_waktu'] ?? ''));
if (!$minuteId || $description === '' || $pic === '' || !valid_date($deadline)) {
    flash('danger', 'Lengkapi deskripsi, penanggung jawab, dan tenggat waktu yang valid.');
    redirect('/modules/notulensi/index.php');
}
$statement = $pdo->prepare('SELECT id_rapat FROM notulensi WHERE id_notulensi=:id');
$statement->execute(['id' => $minuteId]);
if (!$statement->fetch()) { http_response_code(404); exit('Notulensi tidak ditemukan.'); }
$statement = $pdo->prepare('INSERT INTO tindak_lanjut (id_notulensi,deskripsi_tugas,penanggung_jawab,tenggat_waktu) VALUES (:id,:description,:pic,:deadline)');
$statement->execute(['id'=>$minuteId,'description'=>$description,'pic'=>$pic,'deadline'=>$deadline]);
flash('success', 'Tindak lanjut berhasil ditambahkan.');
redirect('/modules/notulensi/detail.php?id=' . $minuteId);
