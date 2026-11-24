<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

require_finance_access();
define('KEUANGAN_PARTIAL', true);

$posList = ['yayasan', 'kantin', 'donasi'];

$years = $pdo->query('SELECT DISTINCT YEAR(tanggal) AS y FROM transaksi_keuangan ORDER BY y DESC')->fetchAll(PDO::FETCH_COLUMN);
$year = filter_input(INPUT_GET, 'tahun', FILTER_VALIDATE_INT) ?: (int) ($years[0] ?? date('Y'));
$pos = (string) ($_GET['pos'] ?? '');
if (!in_array($pos, $posList, true)) {
    $pos = '';
}

// Total per pos (tidak terpengaruh filter pos, dipakai untuk kartu ringkasan)
$statement = $pdo->prepare('SELECT pos, jenis, SUM(jumlah) AS total FROM transaksi_keuangan WHERE YEAR(tanggal) = :tahun GROUP BY pos, jenis');
$statement->execute(['tahun' => $year]);
$perPos = [];
foreach ($posList as $p) {
    $perPos[$p] = ['pemasukan' => 0.0, 'pengeluaran' => 0.0];
}
foreach ($statement->fetchAll() as $row) {
    $perPos[$row['pos']][$row['jenis']] = (float) $row['total'];
}

// Query berikut mengikuti filter tahun dan pos
$where = 'YEAR(tanggal) = :tahun';
$params = ['tahun' => $year];
if ($pos !== '') {
    $where .= ' AND pos = :pos';
    $params['pos'] = $pos;
}

$statement = $pdo->prepare("SELECT MONTH(tanggal) AS bulan, jenis, SUM(jumlah) AS total FROM transaksi_keuangan WHERE {$where} GROUP BY bulan, jenis");
$statement->execute($params);
$monthly = ['pemasukan' => array_fill(0, 12, 0.0), 'pengeluaran' => array_fill(0, 12, 0.0)];
foreach ($statement->fetchAll() as $row) {
    $monthly[$row['jenis']][(int) $row['bulan'] - 1] = (float) $row['total'];
}

// Komposisi: tanpa filter = pemasukan per pos, dengan filter = pengeluaran per kategori
if ($pos === '') {
    $compositionTitle = 'Sumber Pemasukan per Pos';
    $composition = [];
    foreach ($posList as $p) {
        $composition[pos_label($p)] = $perPos[$p]['pemasukan'];
    }
} else {
    $compositionTitle = 'Pengeluaran ' . pos_label($pos) . ' per Kategori';
    $statement = $pdo->prepare("SELECT kategori, SUM(jumlah) AS total FROM transaksi_keuangan WHERE {$where} AND jenis = 'pengeluaran' GROUP BY kategori ORDER BY total DESC");
    $statement->execute($params);
    $composition = [];
    foreach ($statement->fetchAll() as $row) {
        $composition[$row['kategori']] = (float) $row['total'];
    }
}

// Pencarian dan pemuatan bertahap (infinite scroll): hanya memengaruhi tabel.
$q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
$tableWhere = $where;
$tableParams = $params;
if ($q !== '') {
    $like = '%' . addcslashes($q, '\\%_') . '%'; // % dan _ yang diketik dianggap huruf biasa
    $tableWhere .= ' AND (keterangan LIKE :q1 OR kategori LIKE :q2 OR pos LIKE :q3)';
    $tableParams += ['q1' => $like, 'q2' => $like, 'q3' => $like];
}

$perBatch = 15; // jumlah baris per pemuatan
$isAjax = ($_GET['ajax'] ?? '') === 'rows';
$offset = $isAjax ? max(0, (int) filter_input(INPUT_GET, 'mulai', FILTER_VALIDATE_INT)) : 0;

$statement = $pdo->prepare("SELECT COUNT(*) FROM transaksi_keuangan WHERE {$tableWhere}");
$statement->execute($tableParams);
$totalTransactions = (int) $statement->fetchColumn();

$statement = $pdo->prepare("SELECT * FROM transaksi_keuangan WHERE {$tableWhere} ORDER BY tanggal DESC, id_transaksi DESC LIMIT {$perBatch} OFFSET {$offset}");
$statement->execute($tableParams);
$recent = $statement->fetchAll();
$hasMore = $offset + count($recent) < $totalTransactions;

// Permintaan gulir: kirim baris berikutnya sebagai JSON, lalu selesai.
if ($isAjax) {
    ob_start();
    require __DIR__ . '/_baris_transaksi.php';
    $html = (string) ob_get_clean();

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'html' => $html,
        'count' => count($recent),
        'total' => $totalTransactions,
        'hasMore' => $hasMore,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Tautan halaman: mempertahankan filter tahun dan pos
$pageUrl = static function (int $target) use ($year, $pos): string {
    $query = ['tahun' => $year];
    if ($pos !== '') {
        $query['pos'] = $pos;
    }
    $query['halaman'] = $target;
    return '?' . http_build_query($query) . '#transaksi';
};

$demoCount = (int) $pdo->query('SELECT COUNT(*) FROM transaksi_keuangan WHERE is_demo = 1')->fetchColumn();
$totalRows = (int) $pdo->query('SELECT COUNT(*) FROM transaksi_keuangan')->fetchColumn();

// Ringkasan khusus pos yang dipilih
$summary = null;
if ($pos !== '') {
    $in = $perPos[$pos]['pemasukan'];
    $out = $perPos[$pos]['pengeluaran'];
    $balance = $in - $out;
    $ratio = $in > 0 ? ($pos === 'kantin' ? $balance / $in : $out / $in) * 100 : 0;
    $ratioLabel = [
        'kantin' => 'Margin Laba',
        'donasi' => 'Dana Tersalurkan',
        'yayasan' => 'Rasio Pengeluaran',
    ][$pos];
    $balanceLabel = [
        'kantin' => 'Laba Bersih',
        'donasi' => 'Sisa Dana Donasi',
        'yayasan' => 'Saldo',
    ][$pos];
    $summary = compact('in', 'out', 'balance', 'ratio', 'ratioLabel', 'balanceLabel');
}

$chartData = [
    'bulan' => ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'],
    'pemasukan' => $monthly['pemasukan'],
    'pengeluaran' => $monthly['pengeluaran'],
    'komposisiLabel' => array_keys($composition),
    'komposisiNilai' => array_values($composition),
];

$pageTitle = 'Laporan Keuangan';
require __DIR__ . '/../../includes/header.php';
?>
<div class="page-heading">
    <div>
        <p class="eyebrow">KEUANGAN</p>
        <h1>Laporan Keuangan</h1>
        <p>Pemantauan keuangan Yayasan, Kantin, dan Donasi.</p>
    </div>
</div>

<?php if ($demoCount > 0): ?>
    <div class="alert alert-warning" role="status">
        <strong>DATA DEMO.</strong> Angka di halaman ini hanya contoh dan bukan data keuangan yang sebenarnya.
        <?php if (is_admin()): ?>
            <div class="mt-2 flex flex-wrap gap-2">
                <form class="inline" method="post" action="<?= h(app_url('/modules/keuangan/seed_demo.php')) ?>">
                    <?= csrf_field() ?><input type="hidden" name="action" value="buat">
                    <button class="button button-secondary button-sm">Buat ulang data demo</button>
                </form>
                <form class="inline" method="post" action="<?= h(app_url('/modules/keuangan/seed_demo.php')) ?>" data-confirm="Hapus semua data demo? Data asli tidak ikut terhapus.">
                    <?= csrf_field() ?><input type="hidden" name="action" value="hapus">
                    <button class="button button-danger button-sm">Hapus data demo</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($totalRows === 0): ?>
    <div class="panel">
        <p class="empty-state">Belum ada data keuangan.</p>
        <?php if (is_admin()): ?>
            <form method="post" action="<?= h(app_url('/modules/keuangan/seed_demo.php')) ?>">
                <?= csrf_field() ?><input type="hidden" name="action" value="buat">
                <button class="button button-primary">Buat data demo</button>
            </form>
        <?php endif; ?>
    </div>
<?php else: ?>
    <form class="filter-bar" method="get">
        <select class="field-control sm:w-auto" name="tahun" aria-label="Tahun">
            <?php foreach ($years as $y): ?>
                <option value="<?= (int) $y ?>" <?= (int) $y === $year ? 'selected' : '' ?>><?= (int) $y ?></option>
            <?php endforeach; ?>
        </select>
        <select class="field-control sm:w-auto" name="pos" aria-label="Pos">
            <option value="">Semua pos</option>
            <?php foreach ($posList as $option): ?>
                <option value="<?= h($option) ?>" <?= $pos === $option ? 'selected' : '' ?>><?= h(pos_label($option)) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="button button-secondary">Terapkan</button>
    </form>

    <?php if ($summary === null): ?>
        <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-3">
            <?php foreach ($posList as $p): ?>
                <?php $balance = $perPos[$p]['pemasukan'] - $perPos[$p]['pengeluaran']; ?>
                <div class="stat-card">
                    <span><?= h(pos_label($p)) ?> &middot; <?= (int) $year ?></span>
                    <strong class="<?= $balance >= 0 ? 'text-green-800' : 'text-red-800' ?>"><?= h(format_rupiah($balance)) ?></strong>
                    <span>Masuk <?= h(format_rupiah($perPos[$p]['pemasukan'])) ?></span>
                    <span>Keluar <?= h(format_rupiah($perPos[$p]['pengeluaran'])) ?></span>
                    <a class="mt-2" href="?tahun=<?= (int) $year ?>&amp;pos=<?= h($p) ?>">Lihat detail</a>
                </div>
            <?php endforeach; ?>
        </div>
        <p class="field-note mb-6">Saldo tiap pos ditampilkan terpisah. Dana donasi umumnya terikat peruntukannya sehingga tidak dijumlahkan dengan saldo operasional.</p>
    <?php else: ?>
        <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-4">
            <div class="stat-card"><span>Pemasukan <?= (int) $year ?></span><strong class="text-green-800"><?= h(format_rupiah($summary['in'])) ?></strong></div>
            <div class="stat-card"><span>Pengeluaran <?= (int) $year ?></span><strong class="text-red-800"><?= h(format_rupiah($summary['out'])) ?></strong></div>
            <div class="stat-card"><span><?= h($summary['balanceLabel']) ?></span><strong class="<?= $summary['balance'] >= 0 ? 'text-green-800' : 'text-red-800' ?>"><?= h(format_rupiah($summary['balance'])) ?></strong></div>
            <div class="stat-card"><span><?= h($summary['ratioLabel']) ?></span><strong><?= h(number_format($summary['ratio'], 1, ',', '.')) ?>%</strong></div>
        </div>
    <?php endif; ?>

    <div class="mb-6 grid grid-cols-1 gap-4 lg:grid-cols-12">
        <section class="panel lg:col-span-7">
            <div class="panel-heading">
                <h2>Pemasukan dan Pengeluaran per Bulan</h2>
            </div>
            <div style="height:300px"><canvas id="chart-bulanan"></canvas></div>
        </section>
        <section class="panel lg:col-span-5">
            <div class="panel-heading">
                <h2><?= h($compositionTitle) ?></h2>
            </div>
            <div style="height:300px"><canvas id="chart-komposisi"></canvas></div>
        </section>
    </div>

    <section class="panel" id="transaksi">
        <div class="panel-heading">
            <h2>Catatan Transaksi</h2>
        </div>
        <div class="mb-4">
            <input class="field-control" type="search" id="cari-transaksi" value="<?= h($q) ?>"
                placeholder="Cari keterangan, kategori, atau pos..." autocomplete="off"
                aria-label="Cari transaksi" aria-controls="tx-scroll">
        </div>
        <p class="mb-2 text-sm text-slate-500" id="transaksi-info" aria-live="polite"></p>

        <div class="table-container tx-scroll" id="tx-scroll" tabindex="0" role="region" aria-label="Daftar transaksi"
            data-offset="<?= count($recent) ?>" data-total="<?= (int) $totalTransactions ?>" data-has-more="<?= $hasMore ? '1' : '0' ?>">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Keterangan</th>
                        <th>Kategori</th>
                        <th>Pos</th>
                        <th class="text-right">Jumlah</th>
                    </tr>
                </thead>
                <tbody id="tx-body"><?php require __DIR__ . '/_baris_transaksi.php'; ?></tbody>
            </table>
            <div id="tx-sentinel" style="height:1px" aria-hidden="true"></div>
            <div id="tx-status" class="py-3 text-center text-sm text-slate-500" aria-live="polite"></div>
        </div>
    </section>

    <script type="application/json" id="finance-data">
        <?= json_encode($chartData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>
    </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
    <script>
        (function() {
            var data = JSON.parse(document.getElementById('finance-data').textContent);
            var charts = [];
            var rupiah = function(v) {
                return 'Rp ' + Number(v).toLocaleString('id-ID');
            };
            var short = function(v) {
                return v >= 1e9 ? (v / 1e9).toFixed(1) + ' M' : v >= 1e6 ? (v / 1e6).toFixed(0) + ' jt' : v;
            };

            function build() {
                charts.forEach(function(c) {
                    c.destroy();
                });
                charts = [];
                var dark = document.documentElement.dataset.theme === 'dark';
                Chart.defaults.color = dark ? '#a3b1c6' : '#475569';
                Chart.defaults.borderColor = dark ? '#243247' : '#e2e8f0';

                charts.push(new Chart(document.getElementById('chart-bulanan'), {
                    type: 'bar',
                    data: {
                        labels: data.bulan,
                        datasets: [{
                                label: 'Pemasukan',
                                data: data.pemasukan,
                                backgroundColor: '#22c55e'
                            },
                            {
                                label: 'Pengeluaran',
                                data: data.pengeluaran,
                                backgroundColor: '#ef4444'
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                ticks: {
                                    callback: short
                                }
                            }
                        },
                        plugins: {
                            tooltip: {
                                callbacks: {
                                    label: function(c) {
                                        return c.dataset.label + ': ' + rupiah(c.parsed.y);
                                    }
                                }
                            }
                        }
                    }
                }));

                charts.push(new Chart(document.getElementById('chart-komposisi'), {
                    type: 'doughnut',
                    data: {
                        labels: data.komposisiLabel,
                        datasets: [{
                            data: data.komposisiNilai,
                            backgroundColor: ['#3b82f6', '#f59e0b', '#8b5cf6', '#14b8a6', '#ef4444', '#64748b'],
                            borderColor: dark ? '#111a2c' : '#ffffff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(c) {
                                        return c.label + ': ' + rupiah(c.parsed);
                                    }
                                }
                            }
                        }
                    }
                }));
            }

            build();
            // Gambar ulang grafik saat tema diganti.
            new MutationObserver(build).observe(document.documentElement, {
                attributes: true,
                attributeFilter: ['data-theme']
            });
        })();
    </script>

    <script>
        (function() {
            var input = document.getElementById('cari-transaksi');
            var box = document.getElementById('transaksi-hasil');
            if (!input || !box) return;

            var timer = null;
            var controller = null;

            function load(params) {
                if (controller) controller.abort(); // batalkan permintaan lama yang belum selesai
                var mine = controller = new AbortController();

                var request = new URLSearchParams(params.toString());
                request.set('ajax', '1');
                box.setAttribute('aria-busy', 'true');
                box.style.opacity = '.6';

                fetch(location.pathname + '?' + request.toString(), {
                        signal: mine.signal,
                        credentials: 'same-origin'
                    })
                    .then(function(res) {
                        // Sesi habis: server mengalihkan ke halaman login.
                        if (res.redirected) {
                            location.reload();
                            return null;
                        }
                        if (!res.ok) throw new Error('HTTP ' + res.status);
                        return res.text();
                    })
                    .then(function(html) {
                        if (html === null) return;
                        box.innerHTML = html;
                        // Simpan keadaan di alamat agar tetap ada saat halaman dimuat ulang.
                        history.replaceState(null, '', location.pathname + '?' + params.toString() + '#transaksi');
                    })
                    .catch(function(err) {
                        if (err.name === 'AbortError') return;
                        box.innerHTML = '<p class="empty-state">Pencarian gagal. Periksa koneksi lalu coba lagi.</p>';
                    })
                    .finally(function() {
                        if (controller === mine) {
                            box.removeAttribute('aria-busy');
                            box.style.opacity = '';
                        }
                    });
            }

            // Mengetik: tunggu 300 ms sejak ketikan terakhir, lalu cari mulai dari halaman 1.
            input.addEventListener('input', function() {
                clearTimeout(timer);
                timer = setTimeout(function() {
                    var params = new URLSearchParams(location.search);
                    params.delete('ajax');
                    params.delete('halaman');
                    var value = input.value.trim();
                    if (value) {
                        params.set('q', value);
                    } else {
                        params.delete('q');
                    }
                    load(params);
                }, 300);
            });

            // Klik nomor halaman: ambil lewat fetch, tanpa memuat ulang seluruh halaman.
            box.addEventListener('click', function(e) {
                var link = e.target.closest('nav a[href]');
                if (!link) return;
                e.preventDefault();
                var params = new URL(link.href, location.href).searchParams;
                params.delete('ajax');
                load(params);
                document.getElementById('transaksi').scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            });
        })();
    </script>

    <script>
        (function() {
            var input = document.getElementById('cari-transaksi');
            var scroller = document.getElementById('tx-scroll');
            var body = document.getElementById('tx-body');
            var info = document.getElementById('transaksi-info');
            var status = document.getElementById('tx-status');
            var sentinel = document.getElementById('tx-sentinel');
            if (!input || !scroller || !body || !info || !status || !sentinel) return;

            var state = {
                q: input.value.trim(),
                offset: Number(scroller.dataset.offset) || 0,
                total: Number(scroller.dataset.total) || 0,
                hasMore: scroller.dataset.hasMore === '1',
                loading: false,
                failed: false
            };
            var controller = null;
            var lastReset = false;
            var fmt = function(n) {
                return Number(n).toLocaleString('id-ID');
            };

            function render() {
                if (state.total === 0) {
                    info.textContent = '';
                } else {
                    info.textContent = 'Menampilkan ' + fmt(state.offset) + ' dari ' + fmt(state.total) + ' transaksi' +
                        (state.q ? ' untuk “' + state.q + '”' : '');
                }
                if (state.failed) return; // pesan galat dikelola showError()
                if (state.loading) {
                    status.textContent = 'Memuat...';
                } else if (!state.hasMore && state.total > 0) {
                    status.textContent = 'Semua transaksi sudah ditampilkan.';
                } else {
                    status.textContent = '';
                }
            }

            function showError() {
                status.textContent = '';
                var message = document.createElement('span');
                message.textContent = 'Gagal memuat data. ';
                var retry = document.createElement('button');
                retry.type = 'button';
                retry.className = 'button button-secondary button-sm';
                retry.textContent = 'Coba lagi';
                retry.addEventListener('click', function() {
                    load(lastReset);
                });
                status.append(message, retry);
            }

            async function load(reset) {
                if (!reset && (state.loading || !state.hasMore)) return;

                if (controller) controller.abort(); // batalkan permintaan lama (mis. saat pencarian berubah)
                var mine = controller = new AbortController();
                lastReset = reset;
                state.loading = true;
                state.failed = false;
                render();

                var params = new URLSearchParams(location.search);
                params.delete('halaman');
                params.set('ajax', 'rows');
                params.set('mulai', String(reset ? 0 : state.offset));
                if (state.q) {
                    params.set('q', state.q);
                } else {
                    params.delete('q');
                }

                try {
                    var res = await fetch(location.pathname + '?' + params.toString(), {
                        signal: mine.signal,
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json'
                        }
                    });
                    // Sesi habis: server mengalihkan ke halaman login.
                    if (res.redirected) {
                        location.reload();
                        return;
                    }
                    if (!res.ok) throw new Error('HTTP ' + res.status);
                    var data = await res.json();

                    if (reset) {
                        body.innerHTML = data.html;
                        state.offset = data.count;
                        scroller.scrollTop = 0;
                    } else {
                        body.insertAdjacentHTML('beforeend', data.html);
                        state.offset += data.count;
                    }
                    state.total = data.total;
                    state.hasMore = data.hasMore;
                } catch (err) {
                    if (err.name === 'AbortError') return;
                    state.failed = true;
                    showError();
                } finally {
                    if (controller === mine) {
                        controller = null;
                        state.loading = false;
                        render();
                        recheck();
                    }
                }
            }

            // Pemicu: sentinel di dasar kotak gulir mendekati area yang terlihat.
            var observer = new IntersectionObserver(function(entries) {
                if (entries[0].isIntersecting) load(false);
            }, {
                root: scroller,
                rootMargin: '0px 0px 120px 0px'
            });
            observer.observe(sentinel);

            // Pengamat hanya aktif saat keadaan berubah. Jika setelah memuat sentinel masih terlihat
            // (baris belum memenuhi kotak), pasang ulang agar pemeriksaan dijalankan lagi.
            function recheck() {
                if (!state.hasMore || state.failed) return;
                observer.unobserve(sentinel);
                observer.observe(sentinel);
            }

            // Mengetik: tunggu 300 ms sejak ketikan terakhir, lalu muat ulang dari awal.
            var timer = null;
            input.addEventListener('input', function() {
                clearTimeout(timer);
                timer = setTimeout(function() {
                    var value = input.value.trim();
                    if (value === state.q) return;
                    state.q = value;

                    var params = new URLSearchParams(location.search);
                    params.delete('halaman');
                    if (value) {
                        params.set('q', value);
                    } else {
                        params.delete('q');
                    }
                    history.replaceState(null, '', location.pathname + '?' + params.toString() + '#transaksi');

                    load(true);
                }, 300);
            });

            render();
        })();
    </script>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/footer.php'; ?>