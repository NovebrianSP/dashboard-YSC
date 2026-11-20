<?php
declare(strict_types=1);

function dap_excel_column(int $number): string
{
    $column = '';
    while ($number > 0) {
        $number--;
        $column = chr(65 + ($number % 26)) . $column;
        $number = intdiv($number, 26);
    }
    return $column;
}

function dap_excel_xml_text(string $value): string
{
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value) ?? '';
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function dap_excel_cell(int $row, int $column, string $value, int $style = 0): string
{
    $reference = dap_excel_column($column) . $row;
    return '<c r="' . $reference . '" s="' . $style . '" t="inlineStr"><is><t xml:space="preserve">'
        . dap_excel_xml_text($value)
        . '</t></is></c>';
}

function dap_excel_row(int $rowNumber, array $cells): string
{
    $xml = '<row r="' . $rowNumber . '">';
    foreach ($cells as $column => $cell) {
        if ($cell !== null) {
            $xml .= dap_excel_cell($rowNumber, $column, $cell[0], $cell[1] ?? 0);
        }
    }
    return $xml . '</row>';
}

function dap_build_xlsx(array $dap, array $days, array $priorities, array $activities): string
{
    if (!class_exists(ZipArchive::class)) {
        throw new RuntimeException('Ekstensi PHP ZipArchive diperlukan untuk mengunduh file Excel.');
    }

    $weekStart = new DateTimeImmutable($dap['tanggal_mulai']);
    $daySlots = [];
    foreach ($days as $day) {
        $daySlots[$day['date']] = dap_time_slots($day['end_time']);
    }

    $rows = [];
    $rows[] = dap_excel_row(1, [1 => ['YAYASAN SMART CENDEKIA · DAILY ACTIVITIES PLAN', 1]]);
    $rows[] = dap_excel_row(2, [1 => [
        'Periode: ' . $weekStart->format('d/m/Y') . ' – ' . $weekStart->modify('+5 days')->format('d/m/Y'),
        3,
    ]]);
    $rows[] = dap_excel_row(3, [1 => ['Nama: ' . $dap['nama_lengkap'] . ' · Jabatan: ' . $dap['jabatan'], 3]]);
    $rows[] = dap_excel_row(4, []);
    $rows[] = dap_excel_row(5, [1 => ['PEKERJAAN PRIORITAS', 2]]);
    $rows[] = dap_excel_row(6, [1 => ['NO', 3], 2 => ['PEKERJAAN PRIORITAS', 3]]);
    for ($index = 0; $index < 10; $index++) {
        $rows[] = dap_excel_row(7 + $index, [
            1 => [(string) ($index + 1), 5],
            2 => [$priorities[$index] ?? '', 6],
        ]);
    }
    $rows[] = dap_excel_row(17, []);
    $rows[] = dap_excel_row(18, [1 => ['RENCANA AKTIVITAS PEKERJAAN', 2]]);

    $dayHeaderCells = [];
    $columnHeaders = [];
    foreach ($days as $index => $day) {
        $firstColumn = ($index * 2) + 1;
        $dayHeaderCells[$firstColumn] = [$day['name'] . ', ' . $day['label'], 4];
        $columnHeaders[$firstColumn] = ['JAM', 3];
        $columnHeaders[$firstColumn + 1] = ['AKTIVITAS', 3];
    }
    $rows[] = dap_excel_row(19, $dayHeaderCells);
    $rows[] = dap_excel_row(20, $columnHeaders);

    for ($rowIndex = 0; $rowIndex < 16; $rowIndex++) {
        $cells = [];
        foreach ($days as $dayIndex => $day) {
            $slot = $daySlots[$day['date']][$rowIndex] ?? null;
            $timeColumn = ($dayIndex * 2) + 1;
            $activityColumn = $timeColumn + 1;
            $cells[$timeColumn] = [$slot ? $slot['start'] : '', 5];
            $cells[$activityColumn] = [
                $slot ? ($activities[$day['date']][$slot['start']] ?? '') : '',
                6,
            ];
        }
        $rows[] = dap_excel_row(21 + $rowIndex, $cells);
    }

    $sheetRows = implode('', $rows);
    $mergeCells = ['A1:L1', 'A2:L2', 'A3:L3', 'A5:L5', 'A18:L18'];
    for ($row = 7; $row <= 16; $row++) {
        $mergeCells[] = 'B' . $row . ':L' . $row;
    }
    for ($index = 0; $index < count($days); $index++) {
        $firstColumn = dap_excel_column(($index * 2) + 1);
        $lastColumn = dap_excel_column(($index * 2) + 2);
        $mergeCells[] = $firstColumn . '19:' . $lastColumn . '19';
    }
    $mergeXml = '<mergeCells count="' . count($mergeCells) . '">';
    foreach ($mergeCells as $range) {
        $mergeXml .= '<mergeCell ref="' . $range . '"/>';
    }
    $mergeXml .= '</mergeCells>';

    $columnWidths = '';
    foreach ($days as $index => $_day) {
        $timeColumn = ($index * 2) + 1;
        $activityColumn = $timeColumn + 1;
        $columnWidths .= '<col min="' . $timeColumn . '" max="' . $timeColumn . '" width="9" customWidth="1"/>'
            . '<col min="' . $activityColumn . '" max="' . $activityColumn . '" width="32" customWidth="1"/>';
    }

    $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
        . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<dimension ref="A1:L36"/><sheetViews><sheetView workbookViewId="0">'
        . '<pane ySplit="6" topLeftCell="A7" activePane="bottomLeft" state="frozen"/>'
        . '</sheetView></sheetViews><sheetFormatPr defaultRowHeight="20"/>'
        . '<cols>' . $columnWidths . '</cols><sheetData>' . $sheetRows . '</sheetData>'
        . $mergeXml
        . '<pageMargins left="0.25" right="0.25" top="0.5" bottom="0.5" header="0.2" footer="0.2"/>'
        . '<pageSetup orientation="landscape" fitToWidth="1" fitToHeight="0" paperSize="9"/>'
        . '</worksheet>';

    $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="4"><font><sz val="10"/><name val="Calibri"/></font>'
        . '<font><b/><sz val="16"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
        . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
        . '<font><b/><sz val="10"/><color rgb="FF1F2937"/><name val="Calibri"/></font></fonts>'
        . '<fills count="5"><fill><patternFill patternType="none"/></fill>'
        . '<fill><patternFill patternType="gray125"/></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FF142B4A"/><bgColor indexed="64"/></patternFill></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FFDBEAFE"/><bgColor indexed="64"/></patternFill></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FFF1F5F9"/><bgColor indexed="64"/></patternFill></fill></fills>'
        . '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border>'
        . '<border><left style="thin"><color rgb="FF94A3B8"/></left><right style="thin"><color rgb="FF94A3B8"/></right>'
        . '<top style="thin"><color rgb="FF94A3B8"/></top><bottom style="thin"><color rgb="FF94A3B8"/></bottom>'
        . '<diagonal/></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="7">'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
        . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
        . '<xf numFmtId="0" fontId="2" fillId="2" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="center"/></xf>'
        . '<xf numFmtId="0" fontId="3" fillId="3" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
        . '<xf numFmtId="0" fontId="3" fillId="3" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
        . '<xf numFmtId="0" fontId="3" fillId="4" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
        . '</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        . '</styleSheet>';

    $files = [
        '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>',
        '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>',
        'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Daily Activities Plan" sheetId="1" r:id="rId1"/></sheets></workbook>',
        'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>',
        'xl/styles.xml' => $styles,
        'xl/worksheets/sheet1.xml' => $sheet,
    ];

    $tempPath = tempnam(sys_get_temp_dir(), 'dap_');
    if ($tempPath === false) {
        throw new RuntimeException('File sementara Excel tidak dapat dibuat.');
    }

    try {
        $zip = new ZipArchive();
        if ($zip->open($tempPath, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('File Excel tidak dapat dibuat.');
        }
        foreach ($files as $name => $contents) {
            if (!$zip->addFromString($name, $contents)) {
                $zip->close();
                throw new RuntimeException('Komponen file Excel tidak dapat ditulis.');
            }
        }
        if (!$zip->close()) {
            throw new RuntimeException('File Excel tidak dapat diselesaikan.');
        }

        $content = file_get_contents($tempPath);
        if ($content === false) {
            throw new RuntimeException('File Excel tidak dapat dibaca.');
        }
        return $content;
    } finally {
        if (is_file($tempPath)) {
            unlink($tempPath);
        }
    }
}
