<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/database.php';

$email = getenv('ADMIN_EMAIL') ?: '';
$password = getenv('ADMIN_PASSWORD') ?: '';
$name = getenv('ADMIN_NAME') ?: 'Administrator';
$jobTitle = getenv('ADMIN_JOB_TITLE') ?: 'Administrator Sistem';
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 10) {
    fwrite(STDERR, "Set ADMIN_EMAIL yang valid dan ADMIN_PASSWORD minimal 10 karakter sebelum menjalankan script.\n");
    exit(1);
}
if ((int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0) {
    fwrite(STDERR, "Tabel users sudah berisi akun. Script ini hanya dapat membuat admin pertama.\n");
    exit(1);
}
$statement = $pdo->prepare('INSERT INTO users (nama_lengkap,jabatan,email,password,role) VALUES (:name,:job,:email,:password,:role)');
$statement->execute([
    'name' => $name,
    'job' => $jobTitle,
    'email' => $email,
    'password' => password_hash($password, PASSWORD_DEFAULT),
    'role' => 'admin',
]);
fwrite(STDOUT, "Akun admin pertama berhasil dibuat untuk {$email}.\n");
