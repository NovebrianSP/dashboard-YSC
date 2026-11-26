<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/finance.php';
require_once __DIR__ . '/../../config/database.php';

require_finance_access();

// Hapus satu laporan hasil impor (khusus admin).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    require_admin();
    $deleteId = filter_input(INPUT_POST, 'id_laporan', FILTER_VALIDATE_INT);
    if ($deleteId) {
        $statement = $pdo->prepare('DELETE FROM keuangan_laporan WHERE id_laporan = :id');
        $statement->execute(['id' => $deleteId]);
        if ($statement->rowCount() > 0) {
            flash('success', 'Laporan berhasil dihapus.');
        } else {
            flash('warning', 'Laporan tidak ditemukan atau sudah dihapus.');
        }
    }
    redirect('/modules/keuangan/index.php');
}

$incomeSources = finance_income_sources();
$expenseComponents = finance_expense_components();
$monthNames = finance_month_names();

$reports = $pdo->query(
    'SELECT l.*, u.nama_lengkap
     FROM keuangan_laporan l
     LEFT JOIN users u ON u.id_user = l.diimpor_oleh
     ORDER BY l.tahun DESC, l.sampai_bulan DESC, l.created_at DESC, l.id_laporan DESC'
)->fetchAll();

$selectedId = filter_input(INPUT_GET, 'laporan', FILTER_VALIDATE_INT) ?: 0;
$report = null;
foreach ($reports as $candidate) {
    if ((int) $candidate['id_laporan'] === $selectedId) {
        $report = $candidate;
        break;
    }
}
if ($report === null && $reports) {
    $report = $reports[0];
}

$sections = [
    'ringkasan' => 'Ringkasan',
    'penerimaan' => 'Rekap Penerimaan',
    'pengeluaran' => 'Rekap Pengeluaran',
    'target' => 'Target Penerimaan',
];
$section = (string) ($_GET['bagian'] ?? 'ringkasan');
if (!isset($sections[$section])) {
    $section = 'ringkasan';
}

$rp = static fn(float $value): string => format_rupiah($value);
$dash = static fn(float $value): string => $value == 0.0 ? '-' : format_rupiah($value);
$pct = static fn(float $value): string => number_format($value, 1, ',', '.') . '%';

// Nilai awal agar variabel selalu terdefinisi; diisi ulang bila ada laporan yang dipilih.
$reportId = 0;
$monthly = [];
$inputTotals = ['penerimaan' => [], 'pengeluaran' => []];
$monthIncome = [];
$monthExpense = [];
$consolidation = ['penerimaan' => [], 'pengeluaran' => []];
$targets = [];
$incomeTotal = 0.0;
$expenseTotal = 0.0;
$surplus = 0.0;
$targetTotal = 0.0;
$targetPercent = 0.0;
$inputCoverage = 0.0;
$openingBalance = 0.0;
$reportedClosing = null;
$estimatedClosing = 0.0;
$chartData = [];

if ($report) {
    $reportId = (int) $report['id_laporan'];

    // Realisasi bulanan: [bulan][jenis][komponen] => jumlah
    $monthly = [];
    for ($month = 1; $month <= 12; $month++) {
        $monthly[$month] = [
            'penerimaan' => array_fill_keys(array_keys($incomeSources), 0.0),
            'pengeluaran' => array_fill_keys(array_keys($expenseComponents), 0.0),
        ];
    }
    $statement = $pdo->prepare('SELECT bulan, jenis, komponen, jumlah FROM keuangan_laporan_realisasi WHERE id_laporan = :id');
    $statement->execute(['id' => $reportId]);
    foreach ($statement->fetchAll() as $row) {
        $bulan = (int) $row['bulan'];
        if (isset($monthly[$bulan][$row['jenis']]) && array_key_exists($row['komponen'], $monthly[$bulan][$row['jenis']])) {
            $monthly[$bulan][$row['jenis']][$row['komponen']] = (float) $row['jumlah'];
        }
    }

    $inputTotals = [
        'penerimaan' => array_fill_keys(array_keys($incomeSources), 0.0),
        'pengeluaran' => array_fill_keys(array_keys($expenseComponents), 0.0),
    ];
    $monthIncome = array_fill(1, 12, 0.0);
    $monthExpense = array_fill(1, 12, 0.0);
    $filledIncomeCells = 0;
    for ($month = 1; $month <= 12; $month++) {
        foreach ($monthly[$month]['penerimaan'] as $key => $amount) {
            $inputTotals['penerimaan'][$key] += $amount;
            $monthIncome[$month] += $amount;
            if ($amount > 0) {
                $filledIncomeCells++;
            }
        }
        foreach ($monthly[$month]['pengeluaran'] as $key => $amount) {
            $inputTotals['pengeluaran'][$key] += $amount;
            $monthExpense[$month] += $amount;
        }
    }
    $inputCoverage = $filledIncomeCells / (12 * count($incomeSources)) * 100;

    // Laporan sumber (kumulatif). Bila tidak ada, dipakai total input bulanan.
    $consolidation = [];
    foreach (['penerimaan', 'pengeluaran'] as $type) {
        foreach ($inputTotals[$type] as $key => $amount) {
            $consolidation[$type][$key] = ['jumlah' => $amount, 'keterangan' => ''];
        }
    }
    $statement = $pdo->prepare('SELECT jenis, komponen, jumlah, keterangan FROM keuangan_laporan_konsolidasi WHERE id_laporan = :id');
    $statement->execute(['id' => $reportId]);
    foreach ($statement->fetchAll() as $row) {
        if (isset($consolidation[$row['jenis']][$row['komponen']])) {
            $consolidation[$row['jenis']][$row['komponen']] = [
                'jumlah' => (float) $row['jumlah'],
                'keterangan' => (string) ($row['keterangan'] ?? ''),
            ];
        }
    }

    $targets = [];
    foreach ($incomeSources as $key => $_label) {
        $targets[$key] = ['jumlah' => 0.0, 'catatan' => ''];
    }
    $statement = $pdo->prepare('SELECT sumber, jumlah, catatan FROM keuangan_laporan_target WHERE id_laporan = :id');
    $statement->execute(['id' => $reportId]);
    foreach ($statement->fetchAll() as $row) {
        if (isset($targets[$row['sumber']])) {
            $targets[$row['sumber']] = ['jumlah' => (float) $row['jumlah'], 'catatan' => (string) ($row['catatan'] ?? '')];
        }
    }

    $incomeTotal = (float) array_sum(array_column($consolidation['penerimaan'], 'jumlah'));
    $expenseTotal = (float) array_sum(array_column($consolidation['pengeluaran'], 'jumlah'));
    $surplus = $incomeTotal - $expenseTotal;
    $targetTotal = (float) array_sum(array_column($targets, 'jumlah'));
    $targetPercent = $targetTotal > 0 ? $incomeTotal / $targetTotal * 100 : 0.0;

    $openingBalance = (float) $report['saldo_awal'];
    $reportedClosing = $report['saldo_akhir_laporan'] !== null ? (float) $report['saldo_akhir_laporan'] : null;
    $estimatedClosing = $openingBalance + $incomeTotal - $expenseTotal;

    $chartData = [
        'bulan' => array_map(static fn(string $name): string => mb_substr($name, 0, 3), array_values($monthNames)),
        'penerimaan' => array_values($monthIncome),
        'pengeluaran' => array_values($monthExpense),
        'sumber' => array_values($incomeSources),
        'target' => array_map(static fn(array $item): float => $item['jumlah'], array_values($targets)),
        'realisasi' => array_map(static fn(array $item): float => $item['jumlah'], array_values($consolidation['penerimaan'])),
    ];
}

$pageTitle = 'Laporan Keuangan';
require __DIR__ . '/../../includes/header.php';
?>
<style>
    .kl-tabs { display: flex; gap: .25rem; overflow-x: auto; margin-bottom: 1rem; border-bottom: 1px solid var(--color-slate-200); }
    .kl-tab { flex: 0 0 auto; max-width: 18rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; padding: .625rem 1rem; border-bottom: 2px solid transparent; font-size: .875rem; font-weight: 600; color: var(--color-slate-500); text-decoration: none; }
    .kl-tab:hover { color: var(--color-slate-800); text-decoration: none; }
    .kl-tab.is-active { border-bottom-color: var(--color-blue-700); color: var(--color-blue-700); }
    .kl-bar { height: .5rem; min-width: 6rem; overflow: hidden; border-radius: 9999px; background: var(--color-slate-100); }
    .kl-bar > span { display: block; height: 100%; border-radius: 9999px; background: #22c55e; }
</style>

<div class="page-heading">
    <div>
        <p class="eyebrow">KEUANGAN</p>
        <h1>Laporan Keuangan</h1>
        <p>Setiap file Excel yang diimpor menjadi satu tab laporan.</p>
    </div>
    <a class="button button-primary" href="<?= h(app_url('/modules/keuangan/impor.php')) ?>">+ Impor Laporan</a>
</div>

<?php if (!$report): ?>
    <div class="panel">
        <p class="empty-state">Belum ada laporan yang diimpor. Unggah file Excel Dashboard Capaian Keuangan untuk menampilkan laporan.</p>
        <a class="button button-primary" href="<?= h(app_url('/modules/keuangan/impor.php')) ?>">Impor Laporan Pertama</a>
    </div>
<?php else: ?>
    <nav class="kl-tabs" aria-label="Laporan keuangan yang diimpor">
        <?php foreach ($reports as $item): ?>
            <?php $isActive = (int) $item['id_laporan'] === $reportId; ?>
            <a class="kl-tab<?= $isActive ? ' is-active' : '' ?>" title="<?= h($item['judul']) ?>"
                href="<?= h('?laporan=' . (int) $item['id_laporan'] . '&bagian=' . $section) ?>"<?= $isActive ? ' aria-current="page"' : '' ?>><?= h($item['judul']) ?></a>
        <?php endforeach; ?>
    </nav>

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <p class="field-note" style="margin-top:0">
            Data tahun <?= (int) $report['tahun'] ?> sampai <?= h($monthNames[(int) $report['sampai_bulan']] ?? '') ?>.
            Sumber file: <?= h($report['nama_file_asli']) ?>, diimpor <?= h(format_date(substr((string) $report['created_at'], 0, 10))) ?>
            pukul <?= h(substr((string) $report['created_at'], 11, 5)) ?><?= $report['nama_lengkap'] ? ' oleh ' . h($report['nama_lengkap']) : '' ?>.
        </p>
        <?php if (is_admin()): ?>
            <form method="post" data-confirm="Hapus laporan &quot;<?= h($report['judul']) ?>&quot;? Tab ini beserta datanya akan dihapus. File Excel Anda tidak terpengaruh.">
                <?= csrf_field() ?>
                <input type="hidden" name="id_laporan" value="<?= $reportId ?>">
                <button class="button button-danger button-sm" type="submit">Hapus Laporan Ini</button>
            </form>
        <?php endif; ?>
    </div>

    <div class="mb-6 flex flex-wrap gap-2" role="navigation" aria-label="Bagian laporan">
        <?php foreach ($sections as $key => $label): ?>
            <a class="button button-sm <?= $key === $section ? 'button-primary' : 'button-secondary' ?>"
                href="<?= h('?laporan=' . $reportId . '&bagian=' . $key) ?>"<?= $key === $section ? ' aria-current="page"' : '' ?>><?= h($label) ?></a>
        <?php endforeach; ?>
    </div>

    <?php if ($section === 'ringkasan'): ?>
        <div class="mb-4 grid grid-cols-1 gap-4 md:grid-cols-3">
            <div class="stat-card"><span>Penerimaan</span><strong class="text-green-800"><?= h($rp($incomeTotal)) ?></strong></div>
            <div class="stat-card"><span>Pengeluaran</span><strong class="text-red-800"><?= h($rp($expenseTotal)) ?></strong></div>
            <div class="stat-card">
                <span><?= $surplus >= 0 ? 'Surplus' : 'Defisit' ?></span>
                <strong class="<?= $surplus >= 0 ? 'text-green-800' : 'text-red-800' ?>"><?= h($rp($surplus)) ?></strong>
            </div>
        </div>

        <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
            <div class="stat-card"><span>Saldo awal</span><strong><?= h($rp($openingBalance)) ?></strong></div>
            <div class="stat-card">
                <span>Saldo akhir menurut laporan</span>
                <strong><?= $reportedClosing !== null ? h($rp($reportedClosing)) : 'Belum diinput' ?></strong>
                <span>Estimasi dari data: <?= h($rp($estimatedClosing)) ?></span>
                <?php if ($reportedClosing !== null): ?>
                    <span>Selisih: <?= h($rp($reportedClosing - $estimatedClosing)) ?></span>
                <?php endif; ?>
            </div>
            <div class="stat-card"><span>Capaian input bulanan</span><strong><?= h($pct($inputCoverage)) ?></strong><span>Sel penerimaan terisi dari <?= 12 * count($incomeSources) ?></span></div>
            <div class="stat-card">
                <span>Capaian target penerimaan</span>
                <strong><?= $targetTotal > 0 ? h($pct($targetPercent)) : '-' ?></strong>
                <span>Target <?= h($rp($targetTotal)) ?></span>
            </div>
        </div>

        <div class="mb-6 grid grid-cols-1 gap-4 lg:grid-cols-2">
            <section class="panel">
                <div class="panel-heading"><h2>Ringkasan Sumber Penerimaan</h2></div>
                <div class="table-container">
                    <table class="data-table">
                        <thead><tr><th>Sumber</th><th class="text-right">Realisasi</th><th class="text-right">Porsi</th></tr></thead>
                        <tbody>
                            <?php foreach ($incomeSources as $key => $label): ?>
                                <?php $amount = $consolidation['penerimaan'][$key]['jumlah']; ?>
                                <tr>
                                    <td><?= h($label) ?></td>
                                    <td class="text-right whitespace-nowrap"><?= h($rp($amount)) ?></td>
                                    <td class="text-right whitespace-nowrap"><?= $incomeTotal > 0 ? h($pct($amount / $incomeTotal * 100)) : '-' ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr>
                                <th>Total</th>
                                <th class="text-right whitespace-nowrap"><?= h($rp($incomeTotal)) ?></th>
                                <th class="text-right"><?= $incomeTotal > 0 ? '100,0%' : '-' ?></th>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
            <section class="panel">
                <div class="panel-heading"><h2>Ringkasan Pengeluaran</h2></div>
                <div class="table-container">
                    <table class="data-table">
                        <thead><tr><th>Komponen</th><th class="text-right">Realisasi</th></tr></thead>
                        <tbody>
                            <?php foreach ($expenseComponents as $key => $label): ?>
                                <tr>
                                    <td><?= h($label) ?></td>
                                    <td class="text-right whitespace-nowrap"><?= h($rp($consolidation['pengeluaran'][$key]['jumlah'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr>
                                <th>Total</th>
                                <th class="text-right whitespace-nowrap"><?= h($rp($expenseTotal)) ?></th>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <div class="mb-6 grid grid-cols-1 gap-4 lg:grid-cols-2">
            <section class="panel">
                <div class="panel-heading"><h2>Penerimaan vs Pengeluaran per Bulan</h2></div>
                <div style="height:320px" id="wadah-grafik-bulanan"><canvas id="grafik-bulanan" role="img" aria-label="Grafik batang penerimaan dan pengeluaran per bulan"></canvas></div>
            </section>
            <section class="panel">
                <div class="panel-heading"><h2>Target vs Realisasi Penerimaan per Sumber</h2></div>
                <?php if ($targetTotal <= 0): ?>
                    <p class="empty-state">Target penerimaan pada laporan ini belum diisi.</p>
                <?php else: ?>
                    <div style="height:320px" id="wadah-grafik-target"><canvas id="grafik-target" role="img" aria-label="Grafik batang target dan realisasi penerimaan per sumber"></canvas></div>
                <?php endif; ?>
            </section>
        </div>

        <section class="panel mb-6">
            <div class="panel-heading"><h2>Tren Bulanan <?= (int) $report['tahun'] ?></h2></div>
            <div class="table-container">
                <table class="data-table">
                    <thead><tr><th>Bulan</th><th class="text-right">Penerimaan</th><th class="text-right">Pengeluaran</th><th class="text-right">Surplus / Defisit</th></tr></thead>
                    <tbody>
                        <?php foreach ($monthNames as $number => $name): ?>
                            <?php $net = $monthIncome[$number] - $monthExpense[$number]; ?>
                            <tr>
                                <td><?= h($name) ?></td>
                                <td class="text-right whitespace-nowrap"><?= h($dash($monthIncome[$number])) ?></td>
                                <td class="text-right whitespace-nowrap"><?= h($dash($monthExpense[$number])) ?></td>
                                <td class="text-right whitespace-nowrap <?= $net < 0 ? 'text-red-800' : '' ?>"><?= h($dash($net)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

    <?php elseif ($section === 'penerimaan' || $section === 'pengeluaran'): ?>
        <?php
        $components = $section === 'penerimaan' ? $incomeSources : $expenseComponents;
        $monthTotals = $section === 'penerimaan' ? $monthIncome : $monthExpense;
        $consolidatedTotal = array_sum(array_column($consolidation[$section], 'jumlah'));
        $inputTotal = array_sum($inputTotals[$section]);
        ?>
        <section class="panel mb-6">
            <div class="panel-heading"><h2><?= $section === 'penerimaan' ? 'Penerimaan' : 'Pengeluaran' ?> per Bulan <?= (int) $report['tahun'] ?></h2></div>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Bulan</th>
                            <?php foreach ($components as $label): ?><th class="text-right"><?= h($label) ?></th><?php endforeach; ?>
                            <th class="text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($monthNames as $number => $name): ?>
                            <tr>
                                <td><?= h($name) ?></td>
                                <?php foreach ($components as $key => $_label): ?>
                                    <td class="text-right whitespace-nowrap"><?= h($dash($monthly[$number][$section][$key])) ?></td>
                                <?php endforeach; ?>
                                <td class="text-right whitespace-nowrap"><strong><?= h($dash($monthTotals[$number])) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr>
                            <th>Total Tahunan</th>
                            <?php foreach ($components as $key => $_label): ?>
                                <th class="text-right whitespace-nowrap"><?= h($rp($inputTotals[$section][$key])) ?></th>
                            <?php endforeach; ?>
                            <th class="text-right whitespace-nowrap"><?= h($rp($inputTotal)) ?></th>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel mb-6">
            <div class="panel-heading"><h2>Realisasi Kumulatif dari Laporan Sumber</h2></div>
            <div class="table-container">
                <table class="data-table">
                    <thead><tr><th><?= $section === 'penerimaan' ? 'Sumber' : 'Komponen' ?></th><th class="text-right">Laporan sumber</th><th class="text-right">Input bulanan</th><th class="text-right">Selisih</th><th>Keterangan</th></tr></thead>
                    <tbody>
                        <?php foreach ($components as $key => $label): ?>
                            <?php $difference = $consolidation[$section][$key]['jumlah'] - $inputTotals[$section][$key]; ?>
                            <tr>
                                <td><?= h($label) ?></td>
                                <td class="text-right whitespace-nowrap"><?= h($rp($consolidation[$section][$key]['jumlah'])) ?></td>
                                <td class="text-right whitespace-nowrap"><?= h($rp($inputTotals[$section][$key])) ?></td>
                                <td class="text-right whitespace-nowrap <?= abs($difference) >= 0.005 ? 'text-amber-800' : '' ?>"><?= h(abs($difference) >= 0.005 ? $rp($difference) : '-') ?></td>
                                <td class="text-slate-500"><?= h($consolidation[$section][$key]['keterangan'] !== '' ? $consolidation[$section][$key]['keterangan'] : '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr>
                            <th>Total</th>
                            <th class="text-right whitespace-nowrap"><?= h($rp($consolidatedTotal)) ?></th>
                            <th class="text-right whitespace-nowrap"><?= h($rp($inputTotal)) ?></th>
                            <th class="text-right whitespace-nowrap"><?= h(abs($consolidatedTotal - $inputTotal) >= 0.005 ? $rp($consolidatedTotal - $inputTotal) : '-') ?></th>
                            <th></th>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="field-note mt-3">Selisih muncul bila angka laporan sumber berbeda dari jumlah input bulanan pada berkas Excel.</p>
        </section>

    <?php else: ?>
        <section class="panel mb-6">
            <div class="panel-heading"><h2>Target dan Capaian Penerimaan <?= (int) $report['tahun'] ?></h2></div>
            <div class="table-container">
                <table class="data-table">
                    <thead><tr><th>Sumber</th><th class="text-right">Target tahunan</th><th class="text-right">Realisasi</th><th>Capaian</th><th class="text-right">Sisa target</th><th>Catatan</th></tr></thead>
                    <tbody>
                        <?php foreach ($incomeSources as $key => $label): ?>
                            <?php
                            $target = $targets[$key]['jumlah'];
                            $actual = $consolidation['penerimaan'][$key]['jumlah'];
                            $percent = $target > 0 ? $actual / $target * 100 : 0.0;
                            ?>
                            <tr>
                                <td><?= h($label) ?></td>
                                <td class="text-right whitespace-nowrap"><?= h($rp($target)) ?></td>
                                <td class="text-right whitespace-nowrap"><?= h($rp($actual)) ?></td>
                                <td>
                                    <?php if ($target > 0): ?>
                                        <div class="kl-bar" role="img" aria-label="Capaian <?= h($pct($percent)) ?>"><span style="width:<?= h(number_format(min(100, $percent), 1, '.', '')) ?>%"></span></div>
                                        <span class="text-sm text-slate-500"><?= h($pct($percent)) ?></span>
                                    <?php else: ?>
                                        <span class="text-sm text-slate-500">Tanpa target</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-right whitespace-nowrap"><?= h($rp(max(0.0, $target - $actual))) ?></td>
                                <td class="text-slate-500"><?= h($targets[$key]['catatan'] !== '' ? $targets[$key]['catatan'] : '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr>
                            <th>Total</th>
                            <th class="text-right whitespace-nowrap"><?= h($rp($targetTotal)) ?></th>
                            <th class="text-right whitespace-nowrap"><?= h($rp($incomeTotal)) ?></th>
                            <th><?= $targetTotal > 0 ? h($pct($targetPercent)) : '-' ?></th>
                            <th class="text-right whitespace-nowrap"><?= h($rp(max(0.0, $targetTotal - $incomeTotal))) ?></th>
                            <th></th>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($section === 'ringkasan'): ?>
        <script type="application/json" id="data-grafik-keuangan"><?= json_encode($chartData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
        <script>
        (function () {
            var data = JSON.parse(document.getElementById('data-grafik-keuangan').textContent);
            var charts = [];
            var rupiah = function (v) { return 'Rp ' + Number(v).toLocaleString('id-ID'); };
            var singkat = function (v) {
                return v >= 1e9 ? (v / 1e9).toFixed(1).replace('.', ',') + ' M' : v >= 1e6 ? (v / 1e6).toFixed(0) + ' jt' : v;
            };

            function tampilkanGalat(idWadah) {
                var wadah = document.getElementById(idWadah);
                if (wadah) { wadah.innerHTML = '<p class="empty-state">Grafik tidak dapat dimuat. Periksa koneksi internet lalu muat ulang halaman.</p>'; }
            }

            function bangun() {
                charts.forEach(function (c) { c.destroy(); });
                charts = [];
                var gelap = document.documentElement.dataset.theme === 'dark';
                Chart.defaults.color = gelap ? '#a3b1c6' : '#475569';
                Chart.defaults.borderColor = gelap ? '#243247' : '#e2e8f0';

                var kanvasBulanan = document.getElementById('grafik-bulanan');
                if (kanvasBulanan) {
                    charts.push(new Chart(kanvasBulanan, {
                        type: 'bar',
                        data: {
                            labels: data.bulan,
                            datasets: [
                                { label: 'Penerimaan', data: data.penerimaan, backgroundColor: '#22c55e' },
                                { label: 'Pengeluaran', data: data.pengeluaran, backgroundColor: '#ef4444' }
                            ]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            scales: { y: { beginAtZero: true, ticks: { callback: singkat } } },
                            plugins: {
                                legend: { position: 'bottom' },
                                tooltip: { callbacks: { label: function (c) { return c.dataset.label + ': ' + rupiah(c.parsed.y); } } }
                            }
                        }
                    }));
                }

                var kanvasTarget = document.getElementById('grafik-target');
                if (kanvasTarget) {
                    charts.push(new Chart(kanvasTarget, {
                        type: 'bar',
                        data: {
                            labels: data.sumber,
                            datasets: [
                                { label: 'Target tahunan', data: data.target, backgroundColor: '#3b82f6' },
                                { label: 'Realisasi', data: data.realisasi, backgroundColor: '#22c55e' }
                            ]
                        },
                        options: {
                            indexAxis: 'y',
                            responsive: true, maintainAspectRatio: false,
                            scales: { x: { beginAtZero: true, ticks: { callback: singkat } } },
                            plugins: {
                                legend: { position: 'bottom' },
                                tooltip: { callbacks: { label: function (c) { return c.dataset.label + ': ' + rupiah(c.parsed.x); } } }
                            }
                        }
                    }));
                }
            }

            if (typeof Chart === 'undefined') {
                tampilkanGalat('wadah-grafik-bulanan');
                tampilkanGalat('wadah-grafik-target');
                return;
            }

            bangun();
            // Gambar ulang grafik saat tema diganti.
            new MutationObserver(bangun).observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
        })();
        </script>
    <?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/footer.php'; ?>