<header class="topbar">
    <button class="button button-secondary button-sm lg:hidden" type="button" data-sidebar-toggle aria-label="Buka menu">☰</button>
    <div class="ml-auto text-right">
        <strong><?= h(current_user()['nama_lengkap'] ?? '') ?></strong>
        <span class="block text-sm text-slate-500"><?= h(current_user()['jabatan'] ?? '') ?> · <?= h(current_user()['role'] ?? '') ?></span>
    </div>
    <form method="post" action="<?= h(app_url('/logout.php')) ?>">
        <?= csrf_field() ?>
        <button class="button button-danger button-sm" type="submit">Keluar</button>
    </form>
</header>
