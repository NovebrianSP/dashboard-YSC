<?php
declare(strict_types=1);

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$normalizedPath = strtolower(str_replace('\\', '/', rawurldecode($requestPath)));
if ($normalizedPath === '/uploads/lampiran' || str_starts_with($normalizedPath, '/uploads/lampiran/')) {
    http_response_code(404);
    exit;
}

$target = realpath(__DIR__ . DIRECTORY_SEPARATOR . ltrim($requestPath, '/\\'));
$protectedDirectory = realpath(__DIR__ . '/uploads/lampiran');
if ($target && $protectedDirectory) {
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
