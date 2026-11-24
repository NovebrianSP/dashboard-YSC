(function () {
    var root = document.documentElement;
    var btn = document.getElementById('theme-toggle');

    function apply(theme, save) {
        root.dataset.theme = theme;
        if (save) {
            try { localStorage.setItem('theme', theme); } catch (e) {}
        }
        if (btn) {
            var dark = theme === 'dark';
            var label = dark ? 'Ganti ke mode terang' : 'Ganti ke mode gelap';
            btn.setAttribute('aria-pressed', dark ? 'true' : 'false');
            btn.setAttribute('aria-label', label);
            btn.setAttribute('title', label);
        }
    }

    apply(root.dataset.theme || 'light', false);

    if (btn) {
        btn.addEventListener('click', function () {
            apply(root.dataset.theme === 'dark' ? 'light' : 'dark', true);
        });
    }

    // Ikuti perubahan tema sistem, kecuali pengguna sudah memilih sendiri.
    matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function (e) {
        try { if (localStorage.getItem('theme')) return; } catch (err) {}
        apply(e.matches ? 'dark' : 'light', false);
    });
})();