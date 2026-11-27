<?php
declare(strict_types=1);

function create_password_reset_token(PDO $pdo, int $userId): string
{
    $token = bin2hex(random_bytes(32));
    $statement = $pdo->prepare(
        'UPDATE users
         SET password_reset_token = :token, password_reset_expires_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 HOUR)
         WHERE id_user = :id'
    );
    $statement->execute([
        'token' => hash('sha256', $token),
        'id' => $userId,
    ]);

    return $token;
}

function send_password_reset_email(string $email, string $token): void
{
    $appUrl = trim((string) getenv('APP_URL'));
    $mailFrom = trim((string) getenv('MAIL_FROM'));
    $appUrlParts = parse_url($appUrl);
    if (
        !$appUrlParts
        || !in_array($appUrlParts['scheme'] ?? '', ['http', 'https'], true)
        || empty($appUrlParts['host'])
        || isset($appUrlParts['user'])
        || isset($appUrlParts['pass'])
        || isset($appUrlParts['query'])
        || isset($appUrlParts['fragment'])
        || !filter_var($mailFrom, FILTER_VALIDATE_EMAIL)
    ) {
        throw new RuntimeException('APP_URL atau MAIL_FROM tidak dikonfigurasi dengan benar.');
    }

    $resetUrl = rtrim($appUrl, '/') . app_url('/reset_password.php?token=' . rawurlencode($token));
    $body = implode(PHP_EOL, [
        'Kami menerima permintaan untuk mereset kata sandi akun Dashboard Smart Cendekia.',
        'Buka tautan berikut dalam satu jam untuk membuat kata sandi baru:',
        '',
        $resetUrl,
        '',
        'Jika Anda tidak meminta reset kata sandi, abaikan email ini.',
    ]);
    $headers = implode("\r\n", [
        'From: ' . $mailFrom,
        'Content-Type: text/plain; charset=UTF-8',
        'X-Mailer: PHP/' . PHP_VERSION,
    ]);

    if (!mail($email, 'Reset kata sandi Dashboard Smart Cendekia', $body, $headers)) {
        throw new RuntimeException('Fungsi mail() gagal mengirim email reset.');
    }
}
