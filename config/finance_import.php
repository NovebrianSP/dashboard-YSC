<?php

/**
 * Impor laporan keuangan dari template Excel "Dashboard Capaian Keuangan".
 *
 * Template yang dibaca (nama sheet dan letak sel harus sama dengan template):
 * - Dashboard         : A7 saldo awal, C7 saldo akhir menurut laporan
 * - Rekap Penerimaan  : B5:G16 realisasi bulanan, A22:C27 laporan sumber (nilai dan keterangan)
 * - Rekap Pengeluaran : B5:F16 realisasi bulanan, A22:C26 laporan sumber (nilai dan keterangan)
 * - Target Penerimaan : B5:B10 target tahunan, F5:F10 catatan
 * Tahun dibaca dari judul sheet (misalnya "REKAP PENERIMAAN 2026").
 */
require_once __DIR__ . '/finance.php';
require_once __DIR__ . '/xlsx_reader.php';

final class FinanceImportException extends RuntimeException
{
    /** @var list<string> */
    public array $errors;

    /** @param list<string> $errors */
    public function __construct(array $errors)
    {
        $this->errors = $errors;
        parent::__construct(implode(' ', $errors));
    }
}

function finance_import_find_sheet(array $names, string $wanted): ?string
{
    $needle = mb_strtolower(trim($wanted));
    foreach ($names as $name) {
        if (mb_strtolower(trim((string) $name)) === $needle) {
            return (string) $name;
        }
    }

    return null;
}

function finance_import_label_matches(string $label, string $key): bool
{
    $keywords = [
        'sdit' => ['sdit'],
        'smpit' => ['smpit'],
        'yayasan' => ['yayasan'],
        'wakaf' => ['wakaf'],
        'infak' => ['infak', 'infaq'],
        'lain_lain' => ['lain'],
    ];
    $text = mb_strtolower($label);
    foreach ($keywords[$key] ?? [] as $word) {
        if (str_contains($text, $word)) {
            return true;
        }
    }

    return false;
}

function finance_import_text(array $cells, string $ref): string
{
    $cell = $cells[$ref] ?? null;
    if ($cell === null) {
        return '';
    }
    if ($cell['kind'] === 'string') {
        return trim((string) $cell['value']);
    }
    if ($cell['kind'] === 'number') {
        return rtrim(rtrim(number_format((float) $cell['value'], 2, '.', ''), '0'), '.');
    }

    return '';
}

/**
 * Membaca satu sel angka menjadi string "123.45". Sel kosong dianggap 0.
 * Mengembalikan null (dan mencatat kesalahan) jika isinya tidak valid.
 */
function finance_import_amount(array $cells, string $ref, string $sheet, string $label, bool $signed, callable $addError): ?string
{
    $cell = $cells[$ref] ?? null;
    if ($cell === null) {
        return '0.00';
    }
    if ($cell['kind'] === 'string' && trim((string) $cell['value']) === '') {
        return '0.00';
    }
    if ($cell['kind'] !== 'number') {
        $found = $cell['kind'] === 'string'
            ? 'teks "' . mb_substr((string) $cell['value'], 0, 30) . '"'
            : 'nilai yang bukan angka';
        $addError("{$sheet}!{$ref} ({$label}) harus berisi angka, tetapi berisi {$found}.");

        return null;
    }

    $value = (float) $cell['value'];
    if (!is_finite($value) || abs($value) >= 1.0E13) {
        $addError("{$sheet}!{$ref} ({$label}) di luar batas yang diizinkan.");

        return null;
    }
    if (!$signed && $value < 0) {
        $addError("{$sheet}!{$ref} ({$label}) tidak boleh negatif.");

        return null;
    }

    $text = number_format(round($value, 2), 2, '.', '');

    return $text === '-0.00' ? '0.00' : $text;
}

/** @return list<string> */
function finance_import_check_labels(array $cells, array $refs, string $context): array
{
    $problems = [];
    foreach ($refs as $key => $ref) {
        $label = finance_import_text($cells, $ref);
        if (!finance_import_label_matches($label, (string) $key)) {
            $problems[] = $ref . ' (' . ($label === '' ? 'kosong' : '"' . mb_substr($label, 0, 30) . '"') . ')';
        }
    }

    return $problems === []
        ? []
        : [$context . ': judul tidak sesuai template pada sel ' . implode(', ', $problems) . '.'];
}

function finance_import_cents(string $amount): int
{
    return (int) str_replace('.', '', $amount);
}

function finance_import_from_cents(int $cents): string
{
    return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
}

function finance_import_default_title(int $year, int $month): string
{
    $names = finance_month_names();

    return 'Laporan ' . $year . ' s.d. ' . ($names[$month] ?? $month);
}

/**
 * Membaca dan memvalidasi berkas Excel. Mengembalikan data terstruktur, atau melempar
 * FinanceImportException berisi daftar kesalahan yang dapat ditampilkan ke pengguna.
 *
 * @return array{
 *     tahun: int, sampai_bulan: int, saldo_awal: string, saldo_akhir: ?string,
 *     monthly: array<int, array<string, array<string, string>>>,
 *     consolidation: array<string, array<string, array{jumlah: string, keterangan: string}>>,
 *     targets: array<string, array{jumlah: string, catatan: string}>
 * }
 */
function finance_import_parse(string $path): array
{
    $errors = [];
    $addError = static function (string $message) use (&$errors): void {
        if (count($errors) < 20) {
            $errors[] = $message;
        }
    };

    try {
        $reader = XlsxReader::open($path);
    } catch (RuntimeException $exception) {
        throw new FinanceImportException([$exception->getMessage()]);
    }

    $wantedSheets = [
        'dashboard' => 'Dashboard',
        'penerimaan' => 'Rekap Penerimaan',
        'pengeluaran' => 'Rekap Pengeluaran',
        'target' => 'Target Penerimaan',
    ];
    $resolved = [];
    $missing = [];
    foreach ($wantedSheets as $key => $wanted) {
        $found = finance_import_find_sheet($reader->sheetNames(), $wanted);
        if ($found === null) {
            $missing[] = $wanted;
        } else {
            $resolved[$key] = $found;
        }
    }
    if ($missing) {
        $reader->close();
        throw new FinanceImportException([
            'Sheet berikut tidak ditemukan: ' . implode(', ', $missing) . '. Gunakan template Dashboard Capaian Keuangan.',
        ]);
    }

    $cells = [];
    try {
        foreach ($resolved as $key => $name) {
            $cells[$key] = $reader->cells($name);
        }
    } catch (RuntimeException $exception) {
        throw new FinanceImportException([$exception->getMessage()]);
    } finally {
        $reader->close();
    }

    // Tahun laporan dari judul sheet.
    $years = [];
    foreach (['penerimaan', 'pengeluaran', 'target', 'dashboard'] as $key) {
        if (preg_match('/\b(20\d{2})\b/', finance_import_text($cells[$key], 'A1'), $match)) {
            $years[$key] = (int) $match[1];
        }
    }
    if (!isset($years['penerimaan'])) {
        throw new FinanceImportException([
            'Tahun laporan tidak ditemukan. Judul sheet Rekap Penerimaan (sel A1) harus memuat tahun, misalnya "REKAP PENERIMAAN 2026".',
        ]);
    }
    if (count(array_unique($years)) > 1) {
        throw new FinanceImportException([
            'Tahun pada judul sheet tidak sama: ' . implode(', ', array_map(
                static fn(string $key, int $year): string => $wantedSheets[$key] . ' = ' . $year,
                array_keys($years),
                $years
            )) . '.',
        ]);
    }
    $year = $years['penerimaan'];

    $incomeSources = finance_income_sources();
    $expenseComponents = finance_expense_components();
    $monthNames = finance_month_names();
    $incomeColumns = array_combine(array_keys($incomeSources), array_slice(range('B', 'Z'), 0, count($incomeSources)));
    $expenseColumns = array_combine(array_keys($expenseComponents), array_slice(range('B', 'Z'), 0, count($expenseComponents)));

    // Pastikan susunan template tidak bergeser.
    $headerRefs = static function (array $columns, int $row): array {
        $refs = [];
        foreach ($columns as $key => $column) {
            $refs[$key] = $column . $row;
        }

        return $refs;
    };
    $rowRefs = static function (array $components, string $column, int $firstRow): array {
        $refs = [];
        $row = $firstRow;
        foreach (array_keys($components) as $key) {
            $refs[$key] = $column . $row++;
        }

        return $refs;
    };
    $problems = array_merge(
        finance_import_check_labels($cells['penerimaan'], $headerRefs($incomeColumns, 4), 'Rekap Penerimaan'),
        finance_import_check_labels($cells['pengeluaran'], $headerRefs($expenseColumns, 4), 'Rekap Pengeluaran'),
        finance_import_check_labels($cells['penerimaan'], $rowRefs($incomeSources, 'A', 22), 'Rekap Penerimaan (laporan sumber)'),
        finance_import_check_labels($cells['pengeluaran'], $rowRefs($expenseComponents, 'A', 22), 'Rekap Pengeluaran (laporan sumber)'),
        finance_import_check_labels($cells['target'], $rowRefs($incomeSources, 'A', 5), 'Target Penerimaan')
    );
    foreach (['penerimaan', 'pengeluaran'] as $sheetKey) {
        for ($month = 1; $month <= 12; $month++) {
            $label = mb_strtolower(finance_import_text($cells[$sheetKey], 'A' . (4 + $month)));
            if (!str_starts_with($label, mb_strtolower(mb_substr($monthNames[$month], 0, 3)))) {
                $problems[] = $wantedSheets[$sheetKey] . ': sel A' . (4 + $month) . ' seharusnya bulan ' . $monthNames[$month] . '.';
                break;
            }
        }
    }
    if ($problems) {
        throw new FinanceImportException($problems);
    }

    // Realisasi bulanan.
    $monthly = [];
    for ($month = 1; $month <= 12; $month++) {
        $row = 4 + $month;
        foreach ($incomeColumns as $key => $column) {
            $monthly[$month]['penerimaan'][$key] = finance_import_amount(
                $cells['penerimaan'], $column . $row, 'Rekap Penerimaan',
                $incomeSources[$key] . ' ' . $monthNames[$month], false, $addError
            ) ?? '0.00';
        }
        foreach ($expenseColumns as $key => $column) {
            $monthly[$month]['pengeluaran'][$key] = finance_import_amount(
                $cells['pengeluaran'], $column . $row, 'Rekap Pengeluaran',
                $expenseComponents[$key] . ' ' . $monthNames[$month], false, $addError
            ) ?? '0.00';
        }
    }

    // Laporan sumber (konsolidasi). Jika nilai tidak tersimpan di berkas, dipakai total bulanan.
    $consolidation = ['penerimaan' => [], 'pengeluaran' => []];
    $groups = [
        'penerimaan' => [$incomeSources, 'Rekap Penerimaan'],
        'pengeluaran' => [$expenseComponents, 'Rekap Pengeluaran'],
    ];
    foreach ($groups as $type => [$components, $sheetTitle]) {
        $row = 22;
        foreach ($components as $key => $label) {
            $valueRef = 'B' . $row;
            if (isset($cells[$type][$valueRef])) {
                $amount = finance_import_amount($cells[$type], $valueRef, $sheetTitle, 'Laporan sumber ' . $label, false, $addError) ?? '0.00';
            } else {
                $cents = 0;
                for ($month = 1; $month <= 12; $month++) {
                    $cents += finance_import_cents($monthly[$month][$type][$key]);
                }
                $amount = finance_import_from_cents($cents);
            }
            $consolidation[$type][$key] = [
                'jumlah' => $amount,
                'keterangan' => mb_substr(finance_import_text($cells[$type], 'C' . $row), 0, 500),
            ];
            $row++;
        }
    }

    // Target tahunan.
    $targets = [];
    $row = 5;
    foreach ($incomeSources as $key => $label) {
        $targets[$key] = [
            'jumlah' => finance_import_amount($cells['target'], 'B' . $row, 'Target Penerimaan', 'Target ' . $label, false, $addError) ?? '0.00',
            'catatan' => mb_substr(finance_import_text($cells['target'], 'F' . $row), 0, 500),
        ];
        $row++;
    }

    // Saldo. Saldo akhir 0 dianggap belum diisi.
    $opening = finance_import_amount($cells['dashboard'], 'A7', 'Dashboard', 'Saldo awal', true, $addError) ?? '0.00';
    $closing = finance_import_amount($cells['dashboard'], 'C7', 'Dashboard', 'Saldo akhir menurut laporan', true, $addError);
    if ($closing === '0.00') {
        $closing = null;
    }

    // Bulan terakhir yang terisi.
    $lastMonth = 0;
    foreach ($monthly as $month => $types) {
        foreach ($types as $values) {
            foreach ($values as $amount) {
                if ($amount !== '0.00') {
                    $lastMonth = max($lastMonth, $month);
                }
            }
        }
    }
    if ($lastMonth === 0) {
        $addError('Belum ada realisasi bulanan yang terisi: seluruh nilai penerimaan dan pengeluaran bernilai 0.');
    }

    if ($errors) {
        throw new FinanceImportException($errors);
    }

    return [
        'tahun' => $year,
        'sampai_bulan' => $lastMonth,
        'saldo_awal' => $opening,
        'saldo_akhir' => $closing,
        'monthly' => $monthly,
        'consolidation' => $consolidation,
        'targets' => $targets,
    ];
}

/**
 * Menyimpan hasil parse sebagai satu laporan baru. Mengembalikan id laporan.
 */
function finance_import_save(PDO $pdo, array $data, string $title, string $originalName, string $hash, ?int $userId): int
{
    $pdo->beginTransaction();
    try {
        $statement = $pdo->prepare(
            'INSERT INTO keuangan_laporan
                (judul, tahun, sampai_bulan, saldo_awal, saldo_akhir_laporan, nama_file_asli, hash_file, diimpor_oleh)
             VALUES
                (:judul, :tahun, :sampai_bulan, :saldo_awal, :saldo_akhir, :nama_file, :hash_file, :user_id)'
        );
        $statement->execute([
            'judul' => $title,
            'tahun' => $data['tahun'],
            'sampai_bulan' => $data['sampai_bulan'],
            'saldo_awal' => $data['saldo_awal'],
            'saldo_akhir' => $data['saldo_akhir'],
            'nama_file' => $originalName,
            'hash_file' => $hash,
            'user_id' => $userId,
        ]);
        $reportId = (int) $pdo->lastInsertId();

        $statement = $pdo->prepare(
            'INSERT INTO keuangan_laporan_realisasi (id_laporan, bulan, jenis, komponen, jumlah)
             VALUES (:id, :bulan, :jenis, :komponen, :jumlah)'
        );
        foreach ($data['monthly'] as $month => $types) {
            foreach ($types as $type => $values) {
                foreach ($values as $key => $amount) {
                    $statement->execute([
                        'id' => $reportId, 'bulan' => $month, 'jenis' => $type,
                        'komponen' => $key, 'jumlah' => $amount,
                    ]);
                }
            }
        }

        $statement = $pdo->prepare(
            'INSERT INTO keuangan_laporan_konsolidasi (id_laporan, jenis, komponen, jumlah, keterangan)
             VALUES (:id, :jenis, :komponen, :jumlah, :keterangan)'
        );
        foreach ($data['consolidation'] as $type => $components) {
            foreach ($components as $key => $item) {
                $statement->execute([
                    'id' => $reportId, 'jenis' => $type, 'komponen' => $key,
                    'jumlah' => $item['jumlah'], 'keterangan' => $item['keterangan'] !== '' ? $item['keterangan'] : null,
                ]);
            }
        }

        $statement = $pdo->prepare(
            'INSERT INTO keuangan_laporan_target (id_laporan, sumber, jumlah, catatan)
             VALUES (:id, :sumber, :jumlah, :catatan)'
        );
        foreach ($data['targets'] as $key => $item) {
            $statement->execute([
                'id' => $reportId, 'sumber' => $key,
                'jumlah' => $item['jumlah'], 'catatan' => $item['catatan'] !== '' ? $item['catatan'] : null,
            ]);
        }

        $pdo->commit();

        return $reportId;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}
