<aside class="sidebar">
    <a class="brand" href="<?= h(app_url('/dashboard.php')) ?>">
        <span class="brand-mark"><img src="<?= h(app_url('/assets/logo_ysc-1-removebg-preview.png')) ?>" alt="Logo Smart Cendekia"></span>
        <span>Smart Cendekia<small>Manajemen Rapat</small></span>
    </a>
    <nav class="mt-4">
        <a class="nav-link" href="<?= h(app_url('/dashboard.php')) ?>">Ringkasan</a>
        <a class="nav-link" href="<?= h(app_url('/modules/rapat/index.php')) ?>">Rapat dan Notulensi</a>
        <?php if (in_array(current_user()['role'] ?? '', ['admin', 'pimpinan'], true)): ?>
            <a class="nav-link" href="<?= h(app_url('/modules/laporan/index.php')) ?>">Arsip Laporan Bulanan</a>
            <a class="nav-link" href="<?= h(app_url('/modules/dap/index.php')) ?>">Daily Activities Plan</a>
        <?php endif; ?>
        <?php if (is_admin()): ?>
            <a class="nav-link" href="<?= h(app_url('/modules/users/index.php')) ?>">Manajemen Pengguna</a>
        <?php endif; ?>
    </nav>
    <div class="sidebar-bottom">
        <span>Sistem internal Yayasan</span>
    </div>
</aside>
