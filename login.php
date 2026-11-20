<?php
require_once __DIR__ . '/config/helpers.php';
require_once __DIR__ . '/config/database.php';

if (current_user()) {
    redirect('dashboard.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $statement = $pdo->prepare('SELECT id_user, nama_lengkap, jabatan, email, password, role FROM users WHERE email = :email LIMIT 1');
    $statement->execute(['email' => $email]);
    $user = $statement->fetch();
    if ($user && password_verify($password, $user['password'])) {
        session_regenerate_id(true);
        unset($user['password']);
        $_SESSION['user'] = $user;
        redirect('dashboard.php');
    }
    $error = 'Email atau kata sandi tidak sesuai.';
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
    <link href="<?= h(app_url('/assets/css/app.css')) ?>" rel="stylesheet">
</head>
<body class="min-h-screen bg-slate-100 font-sans text-slate-800 antialiased">
<main class="flex min-h-screen items-center justify-center bg-gradient-to-br from-slate-100 via-blue-50 to-indigo-100 px-4 py-10 sm:px-6">
    <section class="w-full max-w-md rounded-2xl border border-white/80 bg-white p-7 shadow-xl shadow-slate-900/10 sm:p-10" aria-labelledby="login-title">
        <div class="mx-auto mb-6 flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-100 text-lg font-extrabold tracking-wide text-blue-700">SC</div>
        <h1 id="login-title" class="text-center text-2xl font-bold tracking-tight text-slate-900">Masuk ke Dashboard</h1>
        <p class="mb-8 mt-2 text-center text-sm leading-6 text-slate-500">Manajemen Rapat Yayasan Smart Cendekia</p>

        <?php if ($error): ?>
            <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert"><?= h($error) ?></div>
        <?php endif; ?>
        <?php if ($message): ?>
            <div class="mb-5 rounded-lg border px-4 py-3 text-sm <?= h($flashStyle) ?>" role="status"><?= h($message['message']) ?></div>
        <?php endif; ?>

        <form method="post" class="space-y-5">
        <?= csrf_field() ?>
            <div>
                <label class="mb-2 block text-sm font-medium text-slate-700" for="email">Email</label>
                <input class="block w-full rounded-lg border border-slate-300 bg-white px-3.5 py-3 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-blue-500 focus:ring-4 focus:ring-blue-500/15" type="email" name="email" id="email" autocomplete="username" required autofocus>
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-slate-700" for="password">Kata sandi</label>
                <input class="block w-full rounded-lg border border-slate-300 bg-white px-3.5 py-3 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-blue-500 focus:ring-4 focus:ring-blue-500/15" type="password" name="password" id="password" autocomplete="current-password" required>
            </div>
            <button class="w-full rounded-lg bg-blue-700 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 focus:outline-none focus:ring-4 focus:ring-blue-500/30 active:bg-blue-900" type="submit">Masuk</button>
        </form>
        <p class="mt-8 text-center text-xs text-slate-400">Smart Cendekia</p>
    </section>
</main>
</body>
</html>