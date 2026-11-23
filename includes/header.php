<?php
$pageTitle = $pageTitle ?? 'Dashboard';
$flashMessage = take_flash();
$flashStyle = match ($flashMessage['type'] ?? '') {
    'success' => 'alert-success',
    'warning' => 'alert-warning',
    'danger', 'error' => 'alert-danger',
    default => 'alert-info',
};
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($pageTitle) ?> | Smart Cendekia</title>
    <link rel="icon" type="image/png" href="<?= h(app_url('/assets/logo_ysc-1-removebg-preview.png?v=3')) ?>">
    <meta name="theme-color" content="#142b4a">
    <link href="<?= h(app_url('/assets/css/app.css')) ?>" rel="stylesheet">
    <?php if (in_array($pageTitle, ['Buat Notulensi', 'Edit Notulensi'], true)): ?>
        <link href="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.snow.css" rel="stylesheet">
    <?php endif; ?>
</head>
<body>
<div class="app-shell">
    <?php require __DIR__ . '/sidebar.php'; ?>
    <main class="main-content">
        <?php require __DIR__ . '/topbar.php'; ?>
        <div class="content-area">
            <?php if ($flashMessage): ?>
                <div class="alert <?= h($flashStyle) ?> flex items-start justify-between gap-4" role="alert">
                    <span><?= h($flashMessage['message']) ?></span>
                    <button class="alert-close" type="button" data-alert-dismiss aria-label="Tutup">×</button>
                </div>
            <?php endif; ?>
