<aside class="sidebar">

    <script>
        (function() {
            const sidebar = document.querySelector('.sidebar');
            if (!sidebar) return;

            const desktop = window.matchMedia('(min-width: 64rem)');
            const close = () => sidebar.classList.remove('is-open');

            // Overlay dibuat lewat JS, gayanya inline agar tidak tergantung app.css.
            const overlay = document.createElement('div');
            overlay.setAttribute('aria-hidden', 'true');
            overlay.style.cssText =
                'position:fixed;inset:0;z-index:30;background:rgba(15,23,42,.5);display:none';
            document.body.appendChild(overlay);

            // Tampilkan overlay hanya saat sidebar terbuka di layar kecil.
            const sync = () => {
                const open = sidebar.classList.contains('is-open') && !desktop.matches;
                overlay.style.display = open ? 'block' : 'none';
            };
            new MutationObserver(sync).observe(sidebar, {
                attributes: true,
                attributeFilter: ['class'],
            });
            desktop.addEventListener('change', () => {
                if (desktop.matches) close();
                sync();
            });

            // Klik di luar sidebar menutup sidebar.
            overlay.addEventListener('click', close);

            // Tombol Escape menutup sidebar.
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') close();
            });

            // Memilih menu menutup sidebar.
            sidebar.querySelectorAll('.nav-link, .brand').forEach((link) => {
                link.addEventListener('click', close);
            });

            sync();
        })();
    </script>

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