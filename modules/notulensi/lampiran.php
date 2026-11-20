<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$statement = $pdo->prepare('SELECT n.file_lampiran,j.kategori_unit FROM notulensi n JOIN jadwal_rapat j ON j.id_rapat=n.id_rapat WHERE n.id_notulensi=:id AND ' . meeting_visibility_sql('j'));
$statement->execute(['id' => $id ?: 0]);
$attachment = $statement->fetch();
if (!$attachment || !$attachment['file_lampiran']) { http_response_code(404); exit('Lampiran tidak ditemukan.'); }
$filename = basename($attachment['file_lampiran']);
$path = __DIR__ . '/../../uploads/lampiran/' . $filename;
if (!is_file($path)) { http_response_code(404); exit('File lampiran tidak tersedia.'); }
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
if (!in_array($mime, ['application/pdf','image/jpeg','image/png'], true)) { http_response_code(415); exit('Tipe file tidak didukung.'); }
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('X-Content-Type-Options: nosniff');
header('Content-Length: ' . filesize($path));
readfile($path);
