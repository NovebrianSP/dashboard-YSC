<?php
declare(strict_types=1);

function can_access_monthly_reports(): bool
{
    return in_array(current_user()['role'] ?? '', ['admin', 'pimpinan'], true);
}

function require_monthly_report_access(): void
{
    require_login();
    if (!can_access_monthly_reports()) {
        http_response_code(403);
        exit('Arsip laporan bulanan hanya tersedia untuk admin dan pimpinan.');
    }
}

function validate_monthly_report_file(string $path, string $extension): string
{
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
    if (!is_string($mime)) {
        throw new RuntimeException('Jenis file laporan tidak dapat diperiksa.');
    }

    if ($extension === 'pdf') {
        $handle = fopen($path, 'rb');
        $signature = $handle ? fread($handle, 5) : false;
        if ($handle) {
            fclose($handle);
        }
        if ($mime !== 'application/pdf' || $signature !== '%PDF-') {
            throw new RuntimeException('Isi file tidak sesuai dengan format PDF.');
        }
        return 'application/pdf';
    }

    if ($extension === 'doc') {
        $handle = fopen($path, 'rb');
        $signature = $handle ? fread($handle, 8) : false;
        if ($handle) {
            fclose($handle);
        }
        $oleSignature = hex2bin('D0CF11E0A1B11AE1');
        if ($signature !== $oleSignature || !in_array($mime, ['application/msword', 'application/x-ole-storage'], true)) {
            throw new RuntimeException('Isi file tidak sesuai dengan format DOC.');
        }
        return 'application/msword';
    }

    if ($extension === 'docx') {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('Ekstensi PHP ZipArchive diperlukan untuk memeriksa file DOCX.');
        }
        $archive = new ZipArchive();
        if ($archive->open($path) !== true) {
            throw new RuntimeException('File DOCX tidak valid.');
        }
        $isDocument = $archive->locateName('[Content_Types].xml') !== false
            && $archive->locateName('word/document.xml') !== false;
        $archive->close();
        if (!$isDocument || !in_array($mime, [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip',
            'application/octet-stream',
        ], true)) {
            throw new RuntimeException('Isi file tidak sesuai dengan format DOCX.');
        }
        return 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    }

    throw new RuntimeException('Format laporan harus DOC, DOCX, atau PDF.');
}

function upload_monthly_report(array $file): array
{
    if (!isset($file['error']) || !is_int($file['error'])) {
        throw new RuntimeException('Data file laporan tidak valid.');
    }
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('Pilih file laporan yang akan diunggah.');
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('File laporan gagal diunggah.');
    }
    if (!isset($file['size'], $file['name'], $file['tmp_name'])
        || !is_int($file['size'])
        || !is_string($file['name'])
        || !is_string($file['tmp_name'])) {
        throw new RuntimeException('Data file laporan tidak valid.');
    }
    if ($file['size'] > 10 * 1024 * 1024) {
        throw new RuntimeException('Ukuran file laporan maksimal 10 MB.');
    }

    $temporaryPath = $file['tmp_name'];
    if ($temporaryPath === '' || !is_uploaded_file($temporaryPath)) {
        throw new RuntimeException('File unggahan tidak valid.');
    }

    $originalName = basename(str_replace('\\', '/', $file['name']));
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($extension, ['doc', 'docx', 'pdf'], true)) {
        throw new RuntimeException('Format laporan harus DOC, DOCX, atau PDF.');
    }
    $mime = validate_monthly_report_file($temporaryPath, $extension);
    $originalName = preg_replace('/[\x00-\x1F\x7F]/', '', $originalName) ?? '';
    $originalName = substr($originalName, 0, 240);
    if ($originalName === '') {
        throw new RuntimeException('Nama file laporan tidak valid.');
    }

    $directory = dirname(__DIR__) . '/uploads/laporan_bulanan';
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('Folder arsip laporan bulanan tidak dapat dibuat.');
    }
    $filename = bin2hex(random_bytes(16)) . '.' . $extension;
    if (!move_uploaded_file($temporaryPath, $directory . '/' . $filename)) {
        throw new RuntimeException('File laporan tidak dapat disimpan.');
    }

    return [
        'filename' => $filename,
        'original_name' => $originalName,
        'mime' => $mime,
        'size' => $file['size'],
    ];
}

function delete_monthly_report_file(string $filename): void
{
    if (basename($filename) !== $filename || !preg_match('/^[a-f0-9]{32}\.(doc|docx|pdf)$/', $filename)) {
        return;
    }
    $path = dirname(__DIR__) . '/uploads/laporan_bulanan/' . $filename;
    if (is_file($path) && !unlink($path)) {
        error_log('Unable to delete monthly report file: ' . $filename);
    }
}
