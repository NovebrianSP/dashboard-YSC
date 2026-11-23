<?php
require_once __DIR__ . '/config/helpers.php';
require_once __DIR__ . '/config/database.php';

if (current_user()) {
    redirect('dashboard.php');
}

$error = ['email' => '', 'password' => ''];
$email = '';
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
        // Tahap 2: cek ke database.
        $statement = $pdo->prepare('SELECT id_user, nama_lengkap, jabatan, email, password, role FROM users WHERE email = :email LIMIT 1');
        $statement->execute(['email' => $email]);
        $user = $statement->fetch();

        if (!$user) {
            $error['email'] = 'Email salah.';
        } elseif (!password_verify($password, $user['password'])) {
            $error['password'] = 'Kata sandi salah.';
        } else {
            session_regenerate_id(true);
            unset($user['password']);
            $_SESSION['user'] = $user;
            redirect('dashboard.php');
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
    <link href="<?= h(app_url('/assets/css/app.css')) ?>" rel="stylesheet">
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
                        <?= $error['password'] ? 'aria-invalid="true" autofocus' : '' ?>
                        aria-describedby="password-error">
                    <p id="password-error" class="mt-1.5 text-xs text-red-600 <?= $error['password'] ? '' : 'hidden' ?>"><?= h($error['password']) ?></p>
                </div>
                <button class="w-full rounded-lg bg-blue-700 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 focus:outline-none focus:ring-4 focus:ring-blue-500/30 active:bg-blue-900" type="submit">Masuk</button>
            </form>
            <p class="mt-8 text-center text-xs text-slate-400">Smart Cendekia</p>
        </section>
    </main>
</body>

</html>