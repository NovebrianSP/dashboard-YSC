<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_admin();
require_once __DIR__ . '/../../config/database.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Metode tidak diizinkan.'); }
verify_csrf();
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id) { http_response_code(400); exit('ID jadwal tidak valid.'); }
$statement = $pdo->prepare('DELETE FROM jadwal_rapat WHERE id_rapat = :id');
$statement->execute(['id' => $id]);
flash('success', $statement->rowCount() ? 'Jadwal dan data terkait berhasil dihapus.' : 'Jadwal tidak ditemukan.');
redirect('/modules/jadwal/index.php');
