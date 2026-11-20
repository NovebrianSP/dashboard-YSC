<aside class="sidebar">
    <a class="brand" href="<?= h(app_url('/dashboard.php')) ?>">
        <span class="brand-mark">SC</span>
        <span>Smart Cendekia<small>Manajemen Rapat</small></span>
    </a>
    <nav class="mt-4">
        <a class="nav-link" href="<?= h(app_url('/dashboard.php')) ?>">Ringkasan</a>
        <a class="nav-link" href="<?= h(app_url('/modules/jadwal/index.php')) ?>">Jadwal Rapat</a>
        <a class="nav-link" href="<?= h(app_url('/modules/notulensi/index.php')) ?>">Arsip Notulensi</a>
        <?php if (in_array(current_user()['role'] ?? '', ['admin', 'pimpinan'], true)): ?>
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
