<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../../config/finance.php';
require_once __DIR__ . '/../../config/database.php';

$year = 2026;
$sourceNote = 'Sumber: Dashboard Capaian Keuangan.xlsx (rekap bulanan).';
$monthlyIncome = [
    1 => '92726500',
    2 => '102410000',
    3 => '86760500',
    4 => '109067500',
    5 => '49840000',
    6 => '136024000',
    7 => '155796500',
    8 => '205996000',
    9 => '174799000',
    10 => '138205000',
];
$monthlyExpense = [
    1 => '39194387.40',
    2 => '42458737.40',
    3 => '48646887.40',
    4 => '58929677.40',
    5 => '50375287.40',
    6 => '65383517.40',
    7 => '12406400',
    8 => '35194250',
    9 => '29271900',
    10 => '0',
];
$incomeSources = finance_income_sources();
$expenseComponents = finance_expense_components();
$monthlyRows = [];

for ($month = 1; $month <= 10; $month++) {
    foreach ($incomeSources as $source => $_label) {
        $monthlyRows[] = [
            'bulan' => $month,
            'jenis' => 'penerimaan',
            'komponen' => $source,
            'jumlah' => $source === 'sdit' ? $monthlyIncome[$month] : '0',
        ];
    }
    foreach ($expenseComponents as $component => $_label) {
        $monthlyRows[] = [
            'bulan' => $month,
            'jenis' => 'pengeluaran',
            'komponen' => $component,
            'jumlah' => $component === 'sdit' ? $monthlyExpense[$month] : '0',
        ];
    }
}

$targets = [
    'sdit' => '1700000000',
    'smpit' => '1000000000',
    'yayasan' => '150000000',
    'wakaf' => '350000000',
    'infak' => '100000000',
    'lain_lain' => '0',
];
$consolidatedAmounts = [
    'penerimaan' => [
        'sdit' => '1251625000',
        'smpit' => '0',
        'yayasan' => '0',
        'wakaf' => '0',
        'infak' => '0',
        'lain_lain' => '0',
    ],
    'pengeluaran' => [
        'sdit' => '381861044.40',
        'smpit' => '0',
        'yayasan' => '0',
        'wakaf' => '0',
        'infak' => '0',
    ],
];
$consolidatedNotes = finance_consolidated_notes();
$consolidatedRows = [];
foreach ($consolidatedAmounts as $type => $components) {
    foreach ($components as $component => $amount) {
        $consolidatedRows[] = [
            'sampai_bulan' => 7,
            'jenis' => $type,
            'komponen' => $component,
            'jumlah' => $amount,
            'keterangan' => $consolidatedNotes[$type][$component],
        ];
    }
}
$openingBalance = '0.00';
$reportedClosingBalance = '0.00';
$balanceNote = 'Sumber: Dashboard Capaian Keuangan.xlsx (saldo awal dan saldo akhir laporan).';

$pdo->beginTransaction();
try {
    $periodStatement = $pdo->prepare('SELECT bulan FROM keuangan_periode WHERE tahun = :tahun');
    $periodStatement->execute(['tahun' => $year]);
    $existingPeriods = array_fill_keys(array_map('intval', $periodStatement->fetchAll(PDO::FETCH_COLUMN)), true);

    $actualStatement = $pdo->prepare(
        'SELECT bulan, jenis, komponen, jumlah FROM keuangan_realisasi_bulanan WHERE tahun = :tahun'
    );
    $actualStatement->execute(['tahun' => $year]);
    $existingActuals = [];
    foreach ($actualStatement->fetchAll() as $row) {
        $key = (int) $row['bulan'] . '|' . $row['jenis'] . '|' . $row['komponen'];
        $existingActuals[$key] = finance_normalize_amount((string) $row['jumlah']);
    }

    $targetStatement = $pdo->prepare('SELECT sumber, jumlah FROM keuangan_target_tahunan WHERE tahun = :tahun');
    $targetStatement->execute(['tahun' => $year]);
    $existingTargets = [];
    foreach ($targetStatement->fetchAll() as $row) {
        $existingTargets[$row['sumber']] = finance_normalize_amount((string) $row['jumlah']);
    }

    $consolidatedStatement = $pdo->prepare(
        'SELECT sampai_bulan, jenis, komponen, jumlah FROM keuangan_konsolidasi_sumber WHERE tahun = :tahun'
    );
    $consolidatedStatement->execute(['tahun' => $year]);
    $existingConsolidated = [];
    foreach ($consolidatedStatement->fetchAll() as $row) {
        $key = (int) $row['sampai_bulan'] . '|' . $row['jenis'] . '|' . $row['komponen'];
        $existingConsolidated[$key] = finance_normalize_amount((string) $row['jumlah']);
    }

    $balanceStatement = $pdo->prepare(
        'SELECT saldo_awal, saldo_akhir_laporan FROM keuangan_saldo_tahunan WHERE tahun = :tahun'
    );
    $balanceStatement->execute(['tahun' => $year]);
    $existingBalance = $balanceStatement->fetch() ?: null;

    $conflicts = [];
    foreach ($monthlyRows as $row) {
        $key = $row['bulan'] . '|' . $row['jenis'] . '|' . $row['komponen'];
        if (array_key_exists($key, $existingActuals)
            && $existingActuals[$key] !== finance_normalize_amount($row['jumlah'])) {
            $conflicts[] = 'realisasi bulan ' . $row['bulan'] . ' (' . $row['jenis'] . '/' . $row['komponen'] . ')';
        }
    }
    foreach ($targets as $source => $amount) {
        if (array_key_exists($source, $existingTargets)
            && $existingTargets[$source] !== finance_normalize_amount($amount)) {
            $conflicts[] = 'target ' . $source;
        }
    }
    foreach ($consolidatedRows as $row) {
        $key = $row['sampai_bulan'] . '|' . $row['jenis'] . '|' . $row['komponen'];
        if (array_key_exists($key, $existingConsolidated)
            && $existingConsolidated[$key] !== finance_normalize_amount($row['jumlah'])) {
            $conflicts[] = 'laporan sumber kumulatif (' . $row['jenis'] . '/' . $row['komponen'] . ')';
        }
    }
    if ($existingBalance !== null
        && (finance_normalize_signed_amount((string) $existingBalance['saldo_awal']) !== $openingBalance
            || $existingBalance['saldo_akhir_laporan'] === null
            || finance_normalize_signed_amount((string) $existingBalance['saldo_akhir_laporan']) !== $reportedClosingBalance)) {
        $conflicts[] = 'saldo tahunan';
    }
    if ($conflicts) {
        throw new RuntimeException(
            'Data 2026 yang sudah ada berbeda dari workbook (' . implode(', ', $conflicts) . '). Tidak ada data yang ditimpa.'
        );
    }

    $insertPeriod = $pdo->prepare(
        'INSERT INTO keuangan_periode (tahun, bulan, catatan, diinput_oleh)
         VALUES (:tahun, :bulan, :catatan, NULL)'
    );
    $insertActual = $pdo->prepare(
        'INSERT INTO keuangan_realisasi_bulanan (tahun, bulan, jenis, komponen, jumlah, diinput_oleh)
         VALUES (:tahun, :bulan, :jenis, :komponen, :jumlah, NULL)'
    );
    $insertTarget = $pdo->prepare(
        'INSERT INTO keuangan_target_tahunan (tahun, sumber, jumlah, diinput_oleh)
         VALUES (:tahun, :sumber, :jumlah, NULL)'
    );
    $insertConsolidated = $pdo->prepare(
        'INSERT INTO keuangan_konsolidasi_sumber
            (tahun, sampai_bulan, jenis, komponen, jumlah, keterangan, diinput_oleh)
         VALUES (:tahun, :sampai_bulan, :jenis, :komponen, :jumlah, :keterangan, NULL)'
    );

    $periodsInserted = 0;
    for ($month = 1; $month <= 10; $month++) {
        if (!isset($existingPeriods[$month])) {
            $insertPeriod->execute([
                'tahun' => $year,
                'bulan' => $month,
                'catatan' => $sourceNote,
            ]);
            $periodsInserted++;
        }
    }

    $actualsInserted = 0;
    foreach ($monthlyRows as $row) {
        $key = $row['bulan'] . '|' . $row['jenis'] . '|' . $row['komponen'];
        if (!array_key_exists($key, $existingActuals)) {
            $insertActual->execute([
                'tahun' => $year,
                'bulan' => $row['bulan'],
                'jenis' => $row['jenis'],
                'komponen' => $row['komponen'],
                'jumlah' => finance_normalize_amount($row['jumlah']),
            ]);
            $actualsInserted++;
        }
    }

    $targetsInserted = 0;
    foreach ($targets as $source => $amount) {
        if (!array_key_exists($source, $existingTargets)) {
            $insertTarget->execute([
                'tahun' => $year,
                'sumber' => $source,
                'jumlah' => finance_normalize_amount($amount),
            ]);
            $targetsInserted++;
        }
    }

    $consolidatedInserted = 0;
    foreach ($consolidatedRows as $row) {
        $key = $row['sampai_bulan'] . '|' . $row['jenis'] . '|' . $row['komponen'];
        if (!array_key_exists($key, $existingConsolidated)) {
            $insertConsolidated->execute([
                'tahun' => $year,
                'sampai_bulan' => $row['sampai_bulan'],
                'jenis' => $row['jenis'],
                'komponen' => $row['komponen'],
                'jumlah' => finance_normalize_amount($row['jumlah']),
                'keterangan' => $row['keterangan'],
            ]);
            $consolidatedInserted++;
        }
    }

    $balanceInserted = 0;
    if ($existingBalance === null) {
        $insertBalance = $pdo->prepare(
            'INSERT INTO keuangan_saldo_tahunan (tahun, saldo_awal, saldo_akhir_laporan, catatan, diinput_oleh)
             VALUES (:tahun, :saldo_awal, :saldo_akhir, :catatan, NULL)'
        );
        $insertBalance->execute([
            'tahun' => $year,
            'saldo_awal' => $openingBalance,
            'saldo_akhir' => $reportedClosingBalance,
            'catatan' => $balanceNote,
        ]);
        $balanceInserted = 1;
    }

    $totalsStatement = $pdo->prepare(
        'SELECT jenis, SUM(jumlah) AS total FROM keuangan_realisasi_bulanan WHERE tahun = :tahun GROUP BY jenis'
    );
    $totalsStatement->execute(['tahun' => $year]);
    $totals = array_column($totalsStatement->fetchAll(), 'total', 'jenis');
    if (finance_normalize_amount((string) ($totals['penerimaan'] ?? '0')) !== '1251625000.00'
        || finance_normalize_amount((string) ($totals['pengeluaran'] ?? '0')) !== '381861044.40') {
        throw new RuntimeException('Total realisasi hasil seeder tidak cocok dengan total bulanan pada workbook.');
    }

    $targetTotalStatement = $pdo->prepare('SELECT SUM(jumlah) FROM keuangan_target_tahunan WHERE tahun = :tahun');
    $targetTotalStatement->execute(['tahun' => $year]);
    if (finance_normalize_amount((string) $targetTotalStatement->fetchColumn()) !== '3300000000.00') {
        throw new RuntimeException('Total target hasil seeder tidak cocok dengan total target pada workbook.');
    }

    $consolidatedTotalStatement = $pdo->prepare(
        'SELECT jenis, SUM(jumlah) AS total FROM keuangan_konsolidasi_sumber
         WHERE tahun = :tahun AND sampai_bulan = 7 GROUP BY jenis'
    );
    $consolidatedTotalStatement->execute(['tahun' => $year]);
    $consolidatedTotals = array_column($consolidatedTotalStatement->fetchAll(), 'total', 'jenis');
    if (finance_normalize_amount((string) ($consolidatedTotals['penerimaan'] ?? '0')) !== '1251625000.00'
        || finance_normalize_amount((string) ($consolidatedTotals['pengeluaran'] ?? '0')) !== '381861044.40') {
        throw new RuntimeException('Total konsolidasi hasil seeder tidak cocok dengan laporan sumber pada workbook.');
    }

    $pdo->commit();
    echo "Seed data keuangan workbook tahun {$year} selesai.\n";
    echo "Periode baru: {$periodsInserted}; realisasi bulanan baru: {$actualsInserted}; laporan sumber baru: {$consolidatedInserted}; target baru: {$targetsInserted}; saldo baru: {$balanceInserted}.\n";
    echo "Angka laporan sumber tetap terpisah dari realisasi bulanan dan tidak dijumlahkan kembali.\n";
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'Seeder gagal: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
