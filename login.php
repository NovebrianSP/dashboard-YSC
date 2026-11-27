<?php
require_once __DIR__ . '/config/helpers.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/password_reset.php';

if (current_user()) {
    redirect('dashboard.php');
}

$error = ['email' => '', 'password' => ''];
$email = '';
$isLocked = false;
$emailDeliveryFailed = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    // Tahap 1: cek isian/format. Berhenti di error pertama.
    if ($email === '') {
        $error['email'] = 'Email wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error['email'] = 'Format email tidak valid.';
    } elseif ($password === '') {
        $error['password'] = 'Kata sandi wajib diisi.';
    } else {
        $resetToken = null;
        $pdo->beginTransaction();
        try {
            $statement = $pdo->prepare(
                'SELECT id_user, nama_lengkap, jabatan, email, password, role, failed_login_attempts, locked_at
                 FROM users WHERE email = :email LIMIT 1 FOR UPDATE'
            );
            $statement->execute(['email' => $email]);
            $user = $statement->fetch();

            if (!$user || $user['locked_at'] !== null) {
                $isLocked = $user && $user['locked_at'] !== null;
                $error['password'] = $isLocked
                    ? 'Akun dikunci setelah tiga kali gagal masuk. Gunakan tautan reset kata sandi.'
                    : 'Email atau kata sandi salah.';
                $pdo->commit();
            } elseif (!password_verify($password, $user['password'])) {
                $failedAttempts = (int) $user['failed_login_attempts'] + 1;
                if ($failedAttempts >= 3) {
                    $isLocked = true;
                    $resetToken = create_password_reset_token($pdo, (int) $user['id_user']);
                    $update = $pdo->prepare(
                        'UPDATE users
                         SET failed_login_attempts = 3, locked_at = UTC_TIMESTAMP()
                         WHERE id_user = :id'
                    );
                    $error['password'] = 'Akun dikunci setelah tiga kali gagal masuk. Tautan reset kata sandi telah dikirim ke email akun.';
                } else {
                    $update = $pdo->prepare(
                        'UPDATE users SET failed_login_attempts = :attempts WHERE id_user = :id'
                    );
                    $update->bindValue(':attempts', $failedAttempts, PDO::PARAM_INT);
                    $error['password'] = 'Email atau kata sandi salah.';
                }
                $update->bindValue(':id', (int) $user['id_user'], PDO::PARAM_INT);
                $update->execute();
                $pdo->commit();
            } else {
                $update = $pdo->prepare(
                    'UPDATE users SET failed_login_attempts = 0 WHERE id_user = :id'
                );
                $update->execute(['id' => (int) $user['id_user']]);
                $pdo->commit();
                session_regenerate_id(true);
                unset($user['password'], $user['failed_login_attempts'], $user['locked_at']);
                $_SESSION['user'] = $user;
                redirect('dashboard.php');
            }
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        if ($resetToken !== null) {
            try {
                send_password_reset_email($email, $resetToken);
            } catch (RuntimeException $exception) {
                error_log('Password reset email delivery failed: ' . $exception->getMessage());
                $emailDeliveryFailed = true;
                $error['password'] = 'Akun dikunci setelah tiga kali gagal masuk, tetapi email reset gagal dikirim. Coba minta tautan reset kembali.';
            }
        }
    }
} elseif (isset($_GET['email']) && is_string($_GET['email'])) {
    $email = trim($_GET['email']);
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $statement = $pdo->prepare('SELECT locked_at FROM users WHERE email = :email LIMIT 1');
        $statement->execute(['email' => $email]);
        $lockedAt = $statement->fetchColumn();
        $isLocked = $lockedAt !== false && $lockedAt !== null;
        if ($isLocked) {
            $error['password'] = 'Akun dikunci setelah tiga kali gagal masuk. Gunakan tautan reset kata sandi.';
        }
    }
}

$message = take_flash();
$flashStyle = match ($message['type'] ?? '') {
    'success' => 'border-green-200 bg-green-50 text-green-800',
    'warning' => 'border-amber-200 bg-amber-50 text-amber-800',
    'danger', 'error' => 'border-red-200 bg-red-50 text-red-800',
    default => 'border-blue-200 bg-blue-50 text-blue-800',
};
?>
<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk | Smart Cendekia</title>
    <link rel="icon" type="image/png" href="<?= h(app_url('/assets/logo_ysc-1-removebg-preview.png?v=3')) ?>">
    <meta name="theme-color" content="#142b4a">
    <script>
        (function() {
            try {
                var t = localStorage.getItem('theme');
                if (!t) t = matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                document.documentElement.dataset.theme = t;
            } catch (e) {}
        })();
    </script>
    <link href="<?= h(asset_url('/assets/css/app.css')) ?>" rel="stylesheet">
    <link href="<?= h(asset_url('/assets/css/dark.css')) ?>" rel="stylesheet">
</head>

<body class="min-h-screen bg-slate-100 font-sans text-slate-800 antialiased">
    <main class="flex min-h-screen items-center justify-center bg-gradient-to-br from-slate-100 via-blue-50 to-indigo-100 px-4 py-10 sm:px-6">
        <section class="w-full max-w-md rounded-2xl border border-white/80 bg-white p-7 shadow-xl shadow-slate-900/10 sm:p-10" aria-labelledby="login-title">
            <span class="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-2xl"><img src="<?= h(app_url('/assets/logo_ysc-1-removebg-preview.png')) ?>" alt="Logo Smart Cendekia"></span>
            <h1 id="login-title" class="text-center text-2xl font-bold tracking-tight text-slate-900">Masuk ke Dashboard</h1>
            <p class="mb-8 mt-2 text-center text-sm leading-6 text-slate-500">Manajemen Rapat Yayasan Smart Cendekia</p>

            <?php if ($message): ?>
                <div class="mb-5 rounded-lg border px-4 py-3 text-sm <?= h($flashStyle) ?>" role="status"><?= h($message['message']) ?></div>
            <?php endif; ?>

            <form method="post" class="space-y-5">
                <?= csrf_field() ?>
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700" for="email">Email</label>
                    <input class="block w-full rounded-lg border border-slate-300 bg-white px-3.5 py-3 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-blue-500 focus:ring-4 focus:ring-blue-500/15"
                        type="email" name="email" id="email" autocomplete="username" required
                        value="<?= h($email) ?>"
                        <?= $error['email'] ? 'aria-invalid="true" autofocus' : (!$error['password'] ? 'autofocus' : '') ?>
                        aria-describedby="email-error">
                    <p id="email-error" class="mt-1.5 text-xs text-red-600 <?= $error['email'] ? '' : 'hidden' ?>"><?= h($error['email']) ?></p>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700" for="password">Kata sandi</label>
                    <input class="block w-full rounded-lg border border-slate-300 bg-white px-3.5 py-3 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-blue-500 focus:ring-4 focus:ring-blue-500/15"
                        type="password" name="password" id="password" autocomplete="current-password" required
                        <?= $isLocked ? 'disabled' : '' ?>
                        <?= $error['password'] ? 'aria-invalid="true" autofocus' : '' ?>
                        aria-describedby="password-error">
                    <p id="password-error" class="mt-1.5 text-xs text-red-600 <?= $error['password'] ? '' : 'hidden' ?>"><?= h($error['password']) ?></p>
                    <?php if ($emailDeliveryFailed): ?>
                        <p class="mt-2 text-xs text-amber-700">Email reset tidak dapat dikirim saat ini. Silakan coba minta tautan reset kembali.</p>
                    <?php endif; ?>
                </div>
                <?php if ($isLocked): ?>
                    <a class="block w-full rounded-lg bg-blue-700 px-4 py-3 text-center text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 focus:outline-none focus:ring-4 focus:ring-blue-500/30"
                        href="<?= h(app_url('/reset_password.php?email=' . rawurlencode($email))) ?>">Reset kata sandi melalui email</a>
                <?php else: ?>
                    <button class="w-full rounded-lg bg-blue-700 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 focus:outline-none focus:ring-4 focus:ring-blue-500/30 active:bg-blue-900" type="submit">Masuk</button>
                    <p class="text-center text-sm"><a class="font-medium text-blue-700 hover:text-blue-800" href="<?= h(app_url('/reset_password.php' . ($email !== '' ? '?email=' . rawurlencode($email) : ''))) ?>">Lupa kata sandi?</a></p>
                <?php endif; ?>
            </form>
            <p class="mt-8 text-center text-xs text-slate-400">Smart Cendekia</p>
        </section>
    </main>

    <button type="button" id="theme-toggle" class="theme-fab" aria-pressed="false" aria-label="Ganti ke mode gelap" title="Ganti ke mode gelap">
        <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" />
        </svg>
        <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="12" cy="12" r="5" />
            <path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42" />
        </svg>
    </button>

    <script src="<?= h(app_url('/assets/js/script.js')) ?>"></script>
    </script>
</body>

</html>