<?php
declare(strict_types=1);

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$normalizedPath = strtolower(str_replace('\\', '/', rawurldecode($requestPath)));
$protectedUploadPaths = ['/uploads/lampiran', '/uploads/laporan_bulanan'];
foreach ($protectedUploadPaths as $protectedUploadPath) {
    if (str_contains($normalizedPath, $protectedUploadPath . '/')
        || str_ends_with($normalizedPath, $protectedUploadPath)) {
        http_response_code(404);
        exit;
    }
}

$target = realpath(__DIR__ . DIRECTORY_SEPARATOR . ltrim($requestPath, '/\\'));
$protectedDirectories = [
    realpath(__DIR__ . '/uploads/lampiran'),
    realpath(__DIR__ . '/uploads/laporan_bulanan'),
];
foreach ($protectedDirectories as $protectedDirectory) {
    if (!$target || !$protectedDirectory) {
        continue;
    }
    $targetPath = strtolower(str_replace('\\', '/', $target));
    $protectedPath = strtolower(str_replace('\\', '/', $protectedDirectory));
    if (str_starts_with($targetPath, $protectedPath . '/')) {
        http_response_code(404);
        exit;
    }
}

if ($requestPath !== '/' && $target && is_file($target)) {
    return false;
}

require __DIR__ . '/index.php';
