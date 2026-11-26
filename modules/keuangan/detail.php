<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/finance.php';
require_once __DIR__ . '/../../config/database.php';

require_finance_access();

$year = filter_input(INPUT_GET, 'tahun', FILTER_VALIDATE_INT);
$year = $year !== false && $year !== null && $year >= 2000 && $year <= 2100 ? $year : (int) date('Y');
$years = array_map('intval', $pdo->query(
    'SELECT tahun FROM keuangan_periode
     UNION SELECT tahun FROM keuangan_target_tahunan
     UNION SELECT tahun FROM keuangan_konsolidasi_sumber
    UNION SELECT tahun FROM keuangan_saldo_tahunan
     ORDER BY tahun DESC'
)->fetchAll(PDO::FETCH_COLUMN));
if (!in_array($year, $years, true)) {
    $years[] = $year;
    rsort($years);
}

$incomeSources = finance_income_sources();
$expenseComponents = finance_expense_components();
$monthNames = finance_month_names();
$monthlyIncome = [];
$monthlyExpenses = [];
$periods = [];
for ($month = 1; $month <= 12; $month++) {
    $monthlyIncome[$month] = array_fill_keys(array_keys($incomeSources), 0.0);
    $monthlyExpenses[$month] = array_fill_keys(array_keys($expenseComponents), 0.0);
}

$statement = $pdo->prepare(
    'SELECT bulan, jenis, komponen, jumlah FROM keuangan_realisasi_bulanan WHERE tahun = :tahun'
);
$statement->execute(['tahun' => $year]);
foreach ($statement->fetchAll() as $row) {
    $month = (int) $row['bulan'];
    if ($row['jenis'] === 'penerimaan' && isset($incomeSources[$row['komponen']])) {
        $monthlyIncome[$month][$row['komponen']] = (float) $row['jumlah'];
    } elseif ($row['jenis'] === 'pengeluaran' && isset($expenseComponents[$row['komponen']])) {
        $monthlyExpenses[$month][$row['komponen']] = (float) $row['jumlah'];
    }
}
$statement = $pdo->prepare('SELECT bulan FROM keuangan_periode WHERE tahun = :tahun');
$statement->execute(['tahun' => $year]);
foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $month) {
    $periods[(int) $month] = true;
}

$statement = $pdo->prepare('SELECT MAX(sampai_bulan) FROM keuangan_konsolidasi_sumber WHERE tahun = :tahun');
$statement->execute(['tahun' => $year]);
$cutoff = $statement->fetchColumn();
$cutoffMonth = $cutoff === false || $cutoff === null ? null : (int) $cutoff;
$consolidatedIncome = array_fill_keys(array_keys($incomeSources), 0.0);
$consolidatedExpenses = array_fill_keys(array_keys($expenseComponents), 0.0);
$consolidatedNotes = ['penerimaan' => [], 'pengeluaran' => []];
if ($cutoffMonth !== null) {
    $statement = $pdo->prepare(
        'SELECT jenis, komponen, jumlah, keterangan
         FROM keuangan_konsolidasi_sumber
         WHERE tahun = :tahun AND sampai_bulan = :sampai_bulan'
    );
    $statement->execute(['tahun' => $year, 'sampai_bulan' => $cutoffMonth]);
    foreach ($statement->fetchAll() as $row) {
        if ($row['jenis'] === 'penerimaan' && isset($incomeSources[$row['komponen']])) {
            $consolidatedIncome[$row['komponen']] = (float) $row['jumlah'];
        } elseif ($row['jenis'] === 'pengeluaran' && isset($expenseComponents[$row['komponen']])) {
            $consolidatedExpenses[$row['komponen']] = (float) $row['jumlah'];
        }
        $consolidatedNotes[$row['jenis']][$row['komponen']] = (string) ($row['keterangan'] ?? '');
    }
}

$monthlyIncomeYtd = array_fill_keys(array_keys($incomeSources), 0.0);
$monthlyExpensesYtd = array_fill_keys(array_keys($expenseComponents), 0.0);
if ($cutoffMonth !== null) {
    for ($month = 1; $month <= $cutoffMonth; $month++) {
        foreach ($incomeSources as $key => $_label) {
            $monthlyIncomeYtd[$key] += $monthlyIncome[$month][$key];
        }
        foreach ($expenseComponents as $key => $_label) {
            $monthlyExpensesYtd[$key] += $monthlyExpenses[$month][$key];
        }
    }
}

$statement = $pdo->prepare('SELECT sumber, jumlah FROM keuangan_target_tahunan WHERE tahun = :tahun');
$statement->execute(['tahun' => $year]);
$targets = array_fill_keys(array_keys($incomeSources), 0.0);
foreach ($statement->fetchAll() as $row) {
    if (isset($targets[$row['sumber']])) {
        $targets[$row['sumber']] = (float) $row['jumlah'];
    }
}
$targetTotal = array_sum($targets);
$reportedIncomeTotal = $cutoffMonth !== null ? array_sum($consolidatedIncome) : 0.0;
$monthlyIncomeAnnualTotal = array_sum(array_map('array_sum', $monthlyIncome));
$monthlyExpenseAnnualTotal = array_sum(array_map('array_sum', $monthlyExpenses));
$enteredMonths = count($periods);
$statement = $pdo->prepare('SELECT saldo_awal, saldo_akhir_laporan, catatan FROM keuangan_saldo_tahunan WHERE tahun = :tahun');
$statement->execute(['tahun' => $year]);
$balances = $statement->fetch() ?: null;

$pageTitle = 'Rekap Terperinci Keuangan';
require __DIR__ . '/../../includes/header.php';
?>
<div class="page-heading">
    <div>
        <p class="eyebrow">KEUANGAN</p>
        <h1>Rekap Terperinci Keuangan</h1>
        <p>Rincian input bulanan dan angka kumulatif dari laporan sumber disajikan terpisah.</p>
    </div>
</div>

<form class="filter-bar" method="get">
    <label class="sr-only" for="detail-tahun">Tahun</label>
    <select class="field-control sm:w-auto" id="detail-tahun" name="tahun">
        <?php foreach ($years as $option): ?>
            <option value="<?= (int) $option ?>" <?= $option === $year ? 'selected' : '' ?>><?= (int) $option ?></option>
        <?php endforeach; ?>
    </select>
    <button class="button button-secondary" type="submit">Terapkan</button>
    <a class="button button-primary" href="<?= h(app_url('/modules/keuangan/konsolidasi.php')) ?>?tahun=<?= (int) $year ?>">Input Laporan Sumber</a>
    <a class="button button-secondary" href="<?= h(app_url('/modules/keuangan/index.php')) ?>?tahun=<?= (int) $year ?>">Ringkasan</a>
</form>

<section class="panel mb-6">
    <div class="panel-heading">
        <h2>Realisasi Penerimaan Bulanan <?= (int) $year ?></h2>
    </div>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr><th>Bulan</th>
                    <?php foreach ($incomeSources as $label): ?><th class="text-right"><?= h($label) ?></th><?php endforeach; ?>
                    <th class="text-right">Total</th><th>Status input</th>
                </tr>
            </thead>
            <tbody>
                <?php for ($month = 1; $month <= 12; $month++): ?>
                    <?php $monthTotal = array_sum($monthlyIncome[$month]); ?>
                    <tr>
                        <th><a href="<?= h(app_url('/modules/keuangan/input_bulanan.php')) ?>?tahun=<?= (int) $year ?>&amp;bulan=<?= (int) $month ?>"><?= h($monthNames[$month]) ?></a></th>
                        <?php foreach ($incomeSources as $key => $_label): ?>
                            <td class="text-right whitespace-nowrap"><?= h(format_rupiah($monthlyIncome[$month][$key])) ?></td>
                        <?php endforeach; ?>
                        <td class="text-right whitespace-nowrap font-semibold"><?= h(format_rupiah($monthTotal)) ?></td>
                        <td><?= isset($periods[$month]) ? 'Diinput' : 'Belum diinput' ?></td>
                    </tr>
                <?php endfor; ?>
            </tbody>
            <tfoot>
                <tr><th>Total tahunan</th>
                    <?php foreach ($incomeSources as $key => $_label): ?>
                        <th class="text-right whitespace-nowrap"><?= h(format_rupiah(array_sum(array_column($monthlyIncome, $key))) ) ?></th>
                    <?php endforeach; ?>
                    <th class="text-right whitespace-nowrap"><?= h(format_rupiah(array_sum(array_map('array_sum', $monthlyIncome)))) ?></th>
                    <th><?= count($periods) ?>/12 bulan</th>
                </tr>
            </tfoot>
        </table>
    </div>
</section>

<section class="panel mb-6">
    <div class="panel-heading">
        <h2>Realisasi Pengeluaran Bulanan <?= (int) $year ?></h2>
    </div>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr><th>Bulan</th>
                    <?php foreach ($expenseComponents as $label): ?><th class="text-right"><?= h($label) ?></th><?php endforeach; ?>
                    <th class="text-right">Total</th><th>Status input</th>
                </tr>
            </thead>
            <tbody>
                <?php for ($month = 1; $month <= 12; $month++): ?>
                    <?php $monthTotal = array_sum($monthlyExpenses[$month]); ?>
                    <tr>
                        <th><a href="<?= h(app_url('/modules/keuangan/input_bulanan.php')) ?>?tahun=<?= (int) $year ?>&amp;bulan=<?= (int) $month ?>"><?= h($monthNames[$month]) ?></a></th>
                        <?php foreach ($expenseComponents as $key => $_label): ?>
                            <td class="text-right whitespace-nowrap"><?= h(format_rupiah($monthlyExpenses[$month][$key])) ?></td>
                        <?php endforeach; ?>
                        <td class="text-right whitespace-nowrap font-semibold"><?= h(format_rupiah($monthTotal)) ?></td>
                        <td><?= isset($periods[$month]) ? 'Diinput' : 'Belum diinput' ?></td>
                    </tr>
                <?php endfor; ?>
            </tbody>
            <tfoot>
                <tr><th>Total tahunan</th>
                    <?php foreach ($expenseComponents as $key => $_label): ?>
                        <th class="text-right whitespace-nowrap"><?= h(format_rupiah(array_sum(array_column($monthlyExpenses, $key))) ) ?></th>
                    <?php endforeach; ?>
                    <th class="text-right whitespace-nowrap"><?= h(format_rupiah(array_sum(array_map('array_sum', $monthlyExpenses)))) ?></th>
                    <th><?= count($periods) ?>/12 bulan</th>
                </tr>
            </tfoot>
        </table>
    </div>
</section>

<section class="panel mb-6">
    <div class="panel-heading">
        <h2>Perbandingan Laporan Sumber vs Input Bulanan</h2>
        <?php if ($cutoffMonth !== null): ?><span class="text-sm text-slate-500">Kumulatif Januari–<?= h($monthNames[$cutoffMonth]) ?> <?= (int) $year ?></span><?php endif; ?>
    </div>
    <?php if ($cutoffMonth === null): ?>
        <p class="empty-state">Belum ada realisasi kumulatif dari laporan sumber untuk tahun ini.</p>
    <?php else: ?>
        <p class="field-note mb-4">Selisih dihitung sebagai nilai laporan sumber dikurangi jumlah input bulanan sampai bulan yang sama. Angka laporan sumber tidak ditambahkan ke total bulanan.</p>
        <?php foreach ([
            'penerimaan' => ['title' => 'Penerimaan', 'labels' => $incomeSources, 'reported' => $consolidatedIncome, 'monthly' => $monthlyIncomeYtd],
            'pengeluaran' => ['title' => 'Pengeluaran', 'labels' => $expenseComponents, 'reported' => $consolidatedExpenses, 'monthly' => $monthlyExpensesYtd],
        ] as $type => $section): ?>
            <?php
            $reportedTotal = array_sum($section['reported']);
            $monthlyTotal = array_sum($section['monthly']);
            ?>
            <h3 class="mb-2 mt-5 text-base font-semibold"><?= h($section['title']) ?></h3>
            <div class="table-container">
                <table class="data-table">
                    <thead><tr><th>Komponen</th><th class="text-right">Laporan sumber</th><th class="text-right">Input bulanan s.d. <?= h($monthNames[$cutoffMonth]) ?></th><th class="text-right">Selisih</th><th>Keterangan</th></tr></thead>
                    <tbody>
                        <?php foreach ($section['labels'] as $key => $label): ?>
                            <tr>
                                <td><?= h($label) ?></td>
                                <td class="text-right whitespace-nowrap"><?= h(format_rupiah($section['reported'][$key])) ?></td>
                                <td class="text-right whitespace-nowrap"><?= h(format_rupiah($section['monthly'][$key])) ?></td>
                                <td class="text-right whitespace-nowrap"><?= h(format_rupiah($section['reported'][$key] - $section['monthly'][$key])) ?></td>
                                <td><?= h($consolidatedNotes[$type][$key] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr>
                            <th>Total</th>
                            <th class="text-right whitespace-nowrap"><?= h(format_rupiah($reportedTotal)) ?></th>
                            <th class="text-right whitespace-nowrap"><?= h(format_rupiah($monthlyTotal)) ?></th>
                            <th class="text-right whitespace-nowrap"><?= h(format_rupiah($reportedTotal - $monthlyTotal)) ?></th>
                            <th></th>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

<section class="panel mb-6">
    <div class="panel-heading"><h2>Target &amp; Capaian Penerimaan <?= (int) $year ?></h2><a href="<?= h(app_url('/modules/keuangan/target.php')) ?>?tahun=<?= (int) $year ?>">Atur target</a></div>
    <?php if ($cutoffMonth === null): ?>
        <p class="field-note mb-4">Realisasi pada tabel ini berasal dari laporan sumber kumulatif. Input bulanan ditampilkan terpisah dan tidak digunakan sebagai pengganti laporan sumber.</p>
    <?php endif; ?>
    <div class="table-container">
        <table class="data-table">
            <thead><tr><th>Sumber penerimaan</th><th class="text-right">Target tahunan</th><th class="text-right">Realisasi laporan sumber</th><th class="text-right">Capaian</th><th class="text-right">Sisa target</th></tr></thead>
            <tbody>
                <?php foreach ($incomeSources as $key => $label): ?>
                    <?php
                    $actual = $cutoffMonth !== null ? $consolidatedIncome[$key] : null;
                    $target = $targets[$key];
                    $achievement = $actual !== null && $target > 0 ? ($actual / $target) * 100 : null;
                    ?>
                    <tr>
                        <td><?= h($label) ?></td>
                        <td class="text-right whitespace-nowrap"><?= h(format_rupiah($target)) ?></td>
                        <td class="text-right whitespace-nowrap"><?= $actual !== null ? h(format_rupiah($actual)) : 'Belum ada laporan' ?></td>
                        <td class="text-right"><?= $achievement !== null ? h(number_format($achievement, 1, ',', '.') . '%') : '-' ?></td>
                        <td class="text-right whitespace-nowrap"><?= $actual !== null ? h(format_rupiah(max(0, $target - $actual))) : '-' ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr>
                    <th>Total</th>
                    <th class="text-right whitespace-nowrap"><?= h(format_rupiah($targetTotal)) ?></th>
                    <th class="text-right whitespace-nowrap"><?= $cutoffMonth !== null ? h(format_rupiah($reportedIncomeTotal)) : 'Belum ada laporan' ?></th>
                    <th class="text-right"><?= $cutoffMonth !== null && $targetTotal > 0 ? h(number_format(($reportedIncomeTotal / $targetTotal) * 100, 1, ',', '.') . '%') : '-' ?></th>
                    <th class="text-right whitespace-nowrap"><?= $cutoffMonth !== null ? h(format_rupiah(max(0, $targetTotal - $reportedIncomeTotal))) : '-' ?></th>
                </tr>
            </tbody>
        </table>
    </div>
</section>

<section class="panel">
    <div class="panel-heading"><h2>Saldo Tahunan <?= (int) $year ?></h2><a href="<?= h(app_url('/modules/keuangan/saldo.php')) ?>?tahun=<?= (int) $year ?>">Atur saldo</a></div>
    <?php if ($balances): ?>
        <?php $estimatedClosing = (float) $balances['saldo_awal'] + $monthlyIncomeAnnualTotal - $monthlyExpenseAnnualTotal; ?>
        <?php $balanceDifference = $balances['saldo_akhir_laporan'] !== null ? (float) $balances['saldo_akhir_laporan'] - $estimatedClosing : null; ?>
        <div class="table-container">
            <table class="data-table">
                <thead><tr><th>Saldo awal</th><th>Penerimaan input bulanan</th><th>Pengeluaran input bulanan</th><th>Estimasi saldo akhir</th><th>Saldo akhir menurut laporan</th><th>Selisih</th></tr></thead>
                <tbody><tr>
                    <td class="whitespace-nowrap"><?= h(format_rupiah($balances['saldo_awal'])) ?></td>
                    <td class="whitespace-nowrap"><?= h(format_rupiah($monthlyIncomeAnnualTotal)) ?></td>
                    <td class="whitespace-nowrap"><?= h(format_rupiah($monthlyExpenseAnnualTotal)) ?></td>
                    <td class="whitespace-nowrap"><?= h(format_rupiah($estimatedClosing)) ?></td>
                    <td class="whitespace-nowrap"><?= $balances['saldo_akhir_laporan'] !== null ? h(format_rupiah($balances['saldo_akhir_laporan'])) : 'Belum diinput' ?></td>
                    <td class="whitespace-nowrap"><?= $balanceDifference !== null ? h(format_rupiah($balanceDifference)) : '-' ?></td>
                </tr></tbody>
            </table>
        </div>
        <p class="field-note mt-3">Estimasi memakai input bulanan. Periode telah dicatat: <?= (int) $enteredMonths ?>/12.</p>
        <?php if (!empty($balances['catatan'])): ?><p class="field-note mt-3"><?= h($balances['catatan']) ?></p><?php endif; ?>
    <?php else: ?>
        <p class="empty-state">Saldo tahunan belum diinput.</p>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
