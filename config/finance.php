<?php
declare(strict_types=1);

function finance_income_sources(): array
{
    return [
        'sdit' => 'SDIT',
        'smpit' => 'SMPIT',
        'yayasan' => 'Yayasan (termasuk DANUS)',
        'wakaf' => 'Wakaf',
        'infak' => 'Infak',
        'lain_lain' => 'Lain-lain',
    ];
}

function finance_expense_components(): array
{
    return [
        'sdit' => 'Biaya SDIT',
        'smpit' => 'Biaya SMPIT',
        'yayasan' => 'Biaya Yayasan',
        'wakaf' => 'Biaya Wakaf',
        'infak' => 'Biaya Infak',
    ];
}

function finance_consolidated_notes(): array
{
    return [
        'penerimaan' => [
            'sdit' => 'Total penerimaan SDIT pada konsolidasi',
            'smpit' => 'Total penerimaan SMPIT pada konsolidasi',
            'yayasan' => 'Termasuk DANUS pada laporan aktivitas YSC; bukan tambahan di luar total Yayasan',
            'wakaf' => 'Penerimaan wakaf pada konsolidasi',
            'infak' => 'Penerimaan infak pada konsolidasi',
            'lain_lain' => 'Penerimaan lain-lain pada konsolidasi',
        ],
        'pengeluaran' => [
            'sdit' => 'Angka konsolidasi',
            'smpit' => 'Angka konsolidasi',
            'yayasan' => 'Angka konsolidasi',
            'wakaf' => 'Angka konsolidasi',
            'infak' => 'Angka konsolidasi',
        ],
    ];
}

function finance_month_names(): array
{
    return [
        1 => 'Januari',
        'Februari',
        'Maret',
        'April',
        'Mei',
        'Juni',
        'Juli',
        'Agustus',
        'September',
        'Oktober',
        'November',
        'Desember',
    ];
}

function finance_normalize_amount(mixed $value): ?string
{
    if (!is_string($value) && !is_int($value)) {
        return null;
    }

    $value = trim((string) $value);
    if (!preg_match('/\A\d{1,13}(?:\.\d{1,2})?\z/', $value)) {
        return null;
    }

    [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
    $whole = ltrim($whole, '0');
    $whole = $whole === '' ? '0' : $whole;
    if (strlen($whole) > 13) {
        return null;
    }

    return $whole . '.' . str_pad($fraction, 2, '0');
}

function finance_normalize_signed_amount(mixed $value): ?string
{
    if (!is_string($value) && !is_int($value)) {
        return null;
    }

    $value = trim((string) $value);
    $negative = str_starts_with($value, '-');
    $amount = finance_normalize_amount($negative ? substr($value, 1) : $value);
    if ($amount === null) {
        return null;
    }

    return $negative && $amount !== '0.00' ? '-' . $amount : $amount;
}
