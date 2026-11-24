<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'httponly' => true,
        'secure' => $https,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function app_url(string $path): string
{
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $basePath = preg_replace('~/(?:modules|config|includes|uploads)/.*$~', '', $scriptName);
    if ($basePath === $scriptName) {
        $basePath = dirname($scriptName);
    }
    $basePath = str_replace('\\', '/', $basePath);
    if ($basePath === '/' || $basePath === '.' || $basePath === '\\') {
        $basePath = '';
    }

    return rtrim($basePath, '/') . '/' . ltrim($path, '/');
}

function redirect(string $url): void
{
    if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
        $url = app_url($url);
    }
    header('Location: ' . $url);
    exit;
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_admin(): bool
{
    return (current_user()['role'] ?? '') === 'admin';
}

function require_login(): void
{
    if (!current_user()) {
        flash('warning', 'Silakan masuk terlebih dahulu.');
        redirect('/login.php');
    }
}

function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        exit('Anda tidak memiliki izin untuk melakukan tindakan ini.');
    }
}

function can_create_meeting(): bool
{
    return in_array(current_user()['role'] ?? '', ['admin', 'pimpinan'], true);
}

function require_meeting_create_access(): void
{
    require_login();
    if (!can_create_meeting()) {
        http_response_code(403);
        exit('Hanya admin dan pimpinan yang dapat membuat jadwal rapat.');
    }
}

function can_change_meeting_status(): bool
{
    return in_array(current_user()['role'] ?? '', ['admin', 'pimpinan'], true);
}

function require_meeting_status_access(): void
{
    require_login();
    if (!can_change_meeting_status()) {
        http_response_code(403);
        exit('Hanya admin dan pimpinan yang dapat mengubah status rapat.');
    }
}

function can_access_category(string $category): bool
{
    $role = current_user()['role'] ?? '';
    return match ($role) {
        'admin', 'pimpinan' => true,
        'unit_sdit' => in_array($category, ['sdit', 'semua'], true),
        'unit_smpit' => in_array($category, ['smpit', 'semua'], true),
        default => false,
    };
}

function require_category_access(string $category): void
{
    if (!can_access_category($category)) {
        http_response_code(403);
        exit('Data ini tidak tersedia untuk unit Anda.');
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        exit('Permintaan tidak valid atau sesi telah kedaluwarsa. Silakan muat ulang halaman.');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function take_flash(): ?array
{
    $message = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $message;
}

function format_date(?string $date): string
{
    if (!$date) {
        return '-';
    }
    $months = [
        1 => 'Januari',
        'Februari',
        'Maret',
        'April',
        'Mei',
        'Juni',
        'Juli',
        'Agustus',
        'September',
        'Oktober',
        'November',
        'Desember',
    ];
    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return '-';
    }
    return date('j', $timestamp) . ' ' . $months[(int) date('n', $timestamp)] . ' ' . date('Y', $timestamp);
}

function valid_date(string $date): bool
{
    $parsed = DateTime::createFromFormat('!Y-m-d', $date);
    return $parsed !== false && $parsed->format('Y-m-d') === $date;
}

function sanitize_rich_text(string $html): string
{
    $html = preg_replace('~<(script|style|iframe|object|svg|math)\b[^>]*>.*?</\1\s*>~is', '', $html) ?? '';
    $html = strip_tags($html, '<p><br><strong><em><u><ol><ul><li><h1><h2><h3><blockquote>');
    $allowed = ['p', 'br', 'strong', 'em', 'u', 'ol', 'ul', 'li', 'h1', 'h2', 'h3', 'blockquote'];
    return preg_replace_callback(
        '~</?([a-z0-9]+)\b[^>]*>~i',
        static function (array $matches) use ($allowed): string {
            $tag = strtolower($matches[1]);
            if (!in_array($tag, $allowed, true)) {
                return '';
            }
            return str_starts_with($matches[0], '</') ? "</{$tag}>" : "<{$tag}>";
        },
        $html
    ) ?? '';
}

function category_label(string $category): string
{
    return [
        'yayasan' => 'Yayasan',
        'sdit' => 'SD IT',
        'smpit' => 'SMP IT',
        'semua' => 'Semua Unit',
    ][$category] ?? $category;
}

function status_label(string $status): string
{
    return [
        'terjadwal' => 'Terjadwal',
        'selesai' => 'Selesai',
        'dibatalkan' => 'Dibatalkan',
        'pending' => 'Pending',
        'proses' => 'Sedang Proses',
    ][$status] ?? ucfirst($status);
}

function upload_attachment(array $file): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Lampiran gagal diunggah.');
    }
    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new RuntimeException('Ukuran lampiran maksimal 5 MB.');
    }

    $allowed = [
        'pdf' => ['application/pdf'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
    ];
    $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    if (!isset($allowed[$extension])) {
        throw new RuntimeException('Format lampiran harus PDF, JPG, atau PNG.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
    if (!in_array($mime, $allowed[$extension], true)) {
        throw new RuntimeException('Tipe file lampiran tidak sesuai dengan ekstensinya.');
    }

    $directory = dirname(__DIR__) . '/uploads/lampiran';
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('Folder lampiran tidak dapat dibuat.');
    }
    $filename = bin2hex(random_bytes(16)) . '.' . $extension;
    if (!move_uploaded_file((string) $file['tmp_name'], $directory . '/' . $filename)) {
        throw new RuntimeException('Lampiran tidak dapat disimpan.');
    }
    return $filename;
}

function delete_attachment(?string $filename): void
{
    if (!$filename || basename($filename) !== $filename) {
        return;
    }
    $path = dirname(__DIR__) . '/uploads/lampiran/' . $filename;
    if (is_file($path) && !unlink($path)) {
        error_log('Unable to delete attachment: ' . $filename);
    }
}

function meeting_visibility_sql(string $alias = 'j'): string
{
    return match (current_user()['role'] ?? '') {
        'unit_sdit' => "{$alias}.kategori_unit IN ('sdit', 'semua')",
        'unit_smpit' => "{$alias}.kategori_unit IN ('smpit', 'semua')",
        default => '1 = 1',
    };
}

function safe_return_path(string $path, string $fallback): string
{
    return str_starts_with($path, '/') && !str_starts_with($path, '//') ? $path : $fallback;
}

function meeting_invite_text(array $meeting): string
{
    return implode("\n", [
        '*UNDANGAN RAPAT*',
        '',
        'Agenda: ' . $meeting['judul_rapat'],
        'Tanggal: ' . format_date($meeting['tanggal_rapat']),
        'Waktu: ' . substr($meeting['waktu_mulai'], 0, 5) . ' WIB',
        'Tempat: ' . $meeting['lokasi'],
        'Unit: ' . category_label($meeting['kategori_unit']),
        '',
        'Mohon kehadirannya. Terima kasih.',
        'Yayasan Smart Cendekia',
    ]);
}

function normalize_wa_number(string $number): string
{
    $digits = preg_replace('/\D+/', '', $number) ?? '';
    if (str_starts_with($digits, '0')) {
        $digits = '62' . substr($digits, 1);
    }
    return $digits;
}

function wa_clean(string $text): string
{
    // Variabel template WhatsApp tidak boleh berisi baris baru atau spasi berlebih.
    return trim(preg_replace('/\s+/', ' ', $text) ?? '');
}

function send_whatsapp_template(string $to, array $params): bool
{
    $configFile = __DIR__ . '/whatsapp.php';
    if (!is_file($configFile)) {
        error_log('config/whatsapp.php belum dibuat.');
        return false;
    }
    $cfg = require $configFile;

    $payload = [
        'messaging_product' => 'whatsapp',
        'to' => normalize_wa_number($to),
        'type' => 'template',
        'template' => [
            'name' => $cfg['template'],
            'language' => ['code' => 'id'],
            'components' => [[
                'type' => 'body',
                'parameters' => array_map(
                    static fn(string $p): array => ['type' => 'text', 'text' => wa_clean($p)],
                    $params
                ),
            ]],
        ],
    ];

    $ch = curl_init("https://graph.facebook.com/{$cfg['version']}/{$cfg['phone_number_id']}/messages");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $cfg['token'],
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    ]);
    
    $response = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);


    if ($code < 200 || $code >= 300) {
        error_log('WhatsApp API gagal (' . $code . '): ' . (string) $response);
        return false;
    }
    return true;
}

function asset_url(string $path): string
{
    $file = dirname(__DIR__) . '/' . ltrim($path, '/');
    $version = is_file($file) ? (string) filemtime($file) : (string) time();
    return app_url($path) . '?v=' . $version;
}

function format_rupiah(float|int|string $amount): string
{
    return 'Rp ' . number_format((float) $amount, 0, ',', '.');
}

function can_view_finance(): bool
{
    return in_array(current_user()['role'] ?? '', ['admin', 'pimpinan'], true);
}

function require_finance_access(): void
{
    require_login();
    if (!can_view_finance()) {
        http_response_code(403);
        exit('Hanya admin dan pimpinan yang dapat melihat laporan keuangan.');
    }
}

function pos_label(string $pos): string
{
    return [
        'yayasan' => 'Yayasan',
        'kantin' => 'Kantin',
        'donasi' => 'Donasi',
    ][$pos] ?? ucfirst($pos);
}