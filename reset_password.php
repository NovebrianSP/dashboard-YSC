<?php
declare(strict_types=1);

require_once __DIR__ . '/config/helpers.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/password_reset.php';

$token = (string) ($_POST['token'] ?? $_GET['token'] ?? '');
$email = trim((string) ($_POST['email'] ?? $_GET['email'] ?? ''));
$error = '';
$notice = '';
$isResetRequest = $token === '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if ($isResetRequest) {
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Masukkan alamat email yang valid.';
        } else {
            $notice = 'Jika email tersebut terdaftar, tautan reset akan dikirim. Periksa kotak masuk dan folder spam.';
            $statement = $pdo->prepare('SELECT id_user FROM users WHERE email = :email LIMIT 1');
            $statement->execute(['email' => $email]);
            $userId = $statement->fetchColumn();
            if ($userId !== false) {
                $resetToken = create_password_reset_token($pdo, (int) $userId);
                try {
                    send_password_reset_email($email, $resetToken);
                } catch (RuntimeException $exception) {
                    error_log('Password reset email delivery failed: ' . $exception->getMessage());
                    $notice = '';
                    $error = 'Email reset tidak dapat dikirim saat ini. Silakan coba lagi nanti atau hubungi administrator.';
                }
            }
        }
    } else {
        $password = (string) ($_POST['password'] ?? '');
        $passwordConfirmation = (string) ($_POST['password_confirmation'] ?? '');

        if (!preg_match('/\A[a-f0-9]{64}\z/', $token)) {
            $error = 'Tautan reset tidak valid atau sudah kedaluwarsa. Minta tautan baru.';
        } elseif (strlen($password) < 10) {
            $error = 'Kata sandi baru minimal 10 karakter.';
        } elseif ($password !== $passwordConfirmation) {
            $error = 'Konfirmasi kata sandi tidak sama.';
        } else {
            $pdo->beginTransaction();
            try {
                $statement = $pdo->prepare(
                    'SELECT id_user FROM users
                     WHERE password_reset_token = :token
                       AND password_reset_expires_at > UTC_TIMESTAMP()
                     LIMIT 1 FOR UPDATE'
                );
                $statement->execute(['token' => hash('sha256', $token)]);
                $userId = $statement->fetchColumn();
                if ($userId === false) {
                    $pdo->rollBack();
                    $error = 'Tautan reset tidak valid atau sudah kedaluwarsa. Minta tautan baru.';
                } else {
                    $update = $pdo->prepare(
                        'UPDATE users
                         SET password = :password,
                             failed_login_attempts = 0,
                             locked_at = NULL,
                             password_reset_token = NULL,
                             password_reset_expires_at = NULL
                         WHERE id_user = :id'
                    );
                    $update->execute([
                        'password' => password_hash($password, PASSWORD_DEFAULT),
                        'id' => (int) $userId,
                    ]);
                    $pdo->commit();
                    flash('success', 'Kata sandi berhasil diubah. Silakan masuk dengan kata sandi baru.');
                    redirect('/login.php');
                }
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $exception;
            }
        }
    }
}

$tokenIsValid = false;
if (!$isResetRequest && preg_match('/\A[a-f0-9]{64}\z/', $token)) {
    $statement = $pdo->prepare(
        'SELECT 1 FROM users
         WHERE password_reset_token = :token
           AND password_reset_expires_at > UTC_TIMESTAMP()
         LIMIT 1'
    );
    $statement->execute(['token' => hash('sha256', $token)]);
    $tokenIsValid = $statement->fetchColumn() !== false;
    if (!$tokenIsValid && $error === '') {
        $error = 'Tautan reset tidak valid atau sudah kedaluwarsa. Minta tautan baru.';
    }
    if (!$tokenIsValid) {
        $isResetRequest = true;
    }
}

$message = take_flash();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Kata Sandi | Smart Cendekia</title>
    <link href="<?= h(asset_url('/assets/css/app.css')) ?>" rel="stylesheet">
    <link href="<?= h(asset_url('/assets/css/dark.css')) ?>" rel="stylesheet">
</head>
<body class="min-h-screen bg-slate-100 font-sans text-slate-800 antialiased">
    <main class="flex min-h-screen items-center justify-center bg-gradient-to-br from-slate-100 via-blue-50 to-indigo-100 px-4 py-10 sm:px-6">
        <section class="w-full max-w-md rounded-2xl border border-white/80 bg-white p-7 shadow-xl shadow-slate-900/10 sm:p-10">
            <h1 class="text-center text-2xl font-bold tracking-tight text-slate-900">
                <?= $isResetRequest ? 'Reset kata sandi' : 'Buat kata sandi baru' ?>
            </h1>
            <p class="mb-8 mt-2 text-center text-sm leading-6 text-slate-500">
                <?= $isResetRequest ? 'Masukkan email akun untuk menerima tautan reset.' : 'Tautan reset berlaku selama satu jam.' ?>
            </p>

            <?php if ($message): ?>
                <div class="mb-5 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800" role="status"><?= h($message['message']) ?></div>
            <?php endif; ?>
            <?php if ($notice !== ''): ?>
                <div class="mb-5 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800" role="status"><?= h($notice) ?></div>
            <?php endif; ?>
            <?php if ($error !== ''): ?>
                <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert"><?= h($error) ?></div>
            <?php endif; ?>

            <?php if ($isResetRequest): ?>
                <form method="post" class="space-y-5">
                    <?= csrf_field() ?>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700" for="email">Email</label>
                        <input class="block w-full rounded-lg border border-slate-300 bg-white px-3.5 py-3 text-sm text-slate-900 shadow-sm outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/15"
                            type="email" name="email" id="email" autocomplete="email" required value="<?= h($email) ?>">
                    </div>
                    <button class="w-full rounded-lg bg-blue-700 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800" type="submit">Kirim tautan reset</button>
                </form>
            <?php elseif ($tokenIsValid): ?>
                <form method="post" class="space-y-5">
                    <?= csrf_field() ?>
                    <input type="hidden" name="token" value="<?= h($token) ?>">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700" for="password">Kata sandi baru</label>
                        <input class="block w-full rounded-lg border border-slate-300 bg-white px-3.5 py-3 text-sm text-slate-900 shadow-sm outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/15"
                            type="password" name="password" id="password" autocomplete="new-password" minlength="10" required>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700" for="password_confirmation">Ulangi kata sandi baru</label>
                        <input class="block w-full rounded-lg border border-slate-300 bg-white px-3.5 py-3 text-sm text-slate-900 shadow-sm outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/15"
                            type="password" name="password_confirmation" id="password_confirmation" autocomplete="new-password" minlength="10" required>
                    </div>
                    <button class="w-full rounded-lg bg-blue-700 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800" type="submit">Simpan kata sandi</button>
                </form>
            <?php endif; ?>

            <p class="mt-8 text-center text-sm"><a class="font-medium text-blue-700 hover:text-blue-800" href="<?= h(app_url('/login.php')) ?>">Kembali ke halaman masuk</a></p>
        </section>
    </main>
</body>
</html>
