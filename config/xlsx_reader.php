<?php

/**
 * Pembaca berkas .xlsx minimal tanpa pustaka luar (hanya membutuhkan ekstensi zip dan simplexml).
 *
 * Hanya membaca nilai sel. Untuk sel berumus, yang dibaca adalah nilai hasil hitung yang
 * tersimpan di dalam berkas oleh Excel atau LibreOffice.
 *
 * Tiap sel dikembalikan sebagai ['kind' => 'number'|'string'|'bool'|'error', 'value' => ...].
 * Sel kosong tidak disertakan.
 */
final class XlsxReader
{
    private const NS_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    private const MAX_ENTRIES = 300;
    private const MAX_ENTRY_BYTES = 5242880; // 5 MB per entri setelah dekompresi
    private const MAX_CELLS = 50000;

    private ZipArchive $zip;

    /** @var array<string, string> nama sheet => jalur XML di dalam arsip */
    private array $sheetPaths = [];

    /** @var array<int, string>|null */
    private ?array $sharedStrings = null;

    private function __construct(ZipArchive $zip)
    {
        $this->zip = $zip;
    }

    public static function open(string $path): self
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('Ekstensi PHP zip belum aktif di server ini.');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('File bukan berkas Excel (.xlsx) yang valid.');
        }
        if ($zip->numFiles > self::MAX_ENTRIES) {
            $zip->close();
            throw new RuntimeException('Isi berkas Excel tidak wajar (terlalu banyak komponen).');
        }

        $reader = new self($zip);
        $reader->loadWorkbook();

        return $reader;
    }

    /** @return list<string> */
    public function sheetNames(): array
    {
        return array_keys($this->sheetPaths);
    }

    /** @return array<string, array{kind: string, value: mixed}> */
    public function cells(string $sheetName): array
    {
        $path = $this->sheetPaths[$sheetName] ?? null;
        if ($path === null) {
            throw new RuntimeException('Sheet "' . $sheetName . '" tidak ditemukan.');
        }
        $xml = $this->readEntry($path);
        if ($xml === null) {
            throw new RuntimeException('Isi sheet "' . $sheetName . '" tidak dapat dibaca.');
        }

        $doc = $this->parseXml($xml);
        $nodes = $doc->xpath("//*[local-name()='sheetData']/*[local-name()='row']/*[local-name()='c']");
        if ($nodes === false) {
            return [];
        }
        if (count($nodes) > self::MAX_CELLS) {
            throw new RuntimeException('Sheet "' . $sheetName . '" terlalu besar.');
        }

        $strings = $this->sharedStrings();
        $cells = [];
        foreach ($nodes as $cell) {
            $ref = (string) $cell['r'];
            if ($ref === '') {
                continue;
            }
            $type = (string) $cell['t'];

            if ($type === 'inlineStr') {
                $parts = $cell->xpath("*[local-name()='is']//*[local-name()='t']");
                $text = '';
                foreach ($parts ?: [] as $part) {
                    $text .= (string) $part;
                }
                $cells[$ref] = ['kind' => 'string', 'value' => $text];
                continue;
            }

            $valueNodes = $cell->xpath("*[local-name()='v']");
            if (!$valueNodes) {
                continue; // sel kosong (hanya memiliki gaya)
            }
            $raw = (string) $valueNodes[0];

            switch ($type) {
                case 's':
                    $index = ctype_digit($raw) ? (int) $raw : -1;
                    $cells[$ref] = ['kind' => 'string', 'value' => $strings[$index] ?? ''];
                    break;
                case 'str':
                    $cells[$ref] = ['kind' => 'string', 'value' => $raw];
                    break;
                case 'b':
                    $cells[$ref] = ['kind' => 'bool', 'value' => $raw === '1'];
                    break;
                case 'e':
                    $cells[$ref] = ['kind' => 'error', 'value' => $raw];
                    break;
                default:
                    if ($raw === '') {
                        break;
                    }
                    $cells[$ref] = is_numeric($raw)
                        ? ['kind' => 'number', 'value' => (float) $raw]
                        : ['kind' => 'string', 'value' => $raw];
            }
        }

        return $cells;
    }

    public function close(): void
    {
        $this->zip->close();
    }

    private function loadWorkbook(): void
    {
        $workbookXml = $this->readEntry('xl/workbook.xml');
        if ($workbookXml === null) {
            throw new RuntimeException('File bukan berkas Excel (.xlsx) yang valid.');
        }

        // Peta id relasi => jalur sheet.
        $targets = [];
        $relsXml = $this->readEntry('xl/_rels/workbook.xml.rels');
        if ($relsXml !== null) {
            $rels = $this->parseXml($relsXml);
            foreach ($rels->xpath("//*[local-name()='Relationship']") ?: [] as $rel) {
                $target = (string) $rel['Target'];
                $targets[(string) $rel['Id']] = str_starts_with($target, '/')
                    ? ltrim($target, '/')
                    : 'xl/' . $target;
            }
        }

        $workbook = $this->parseXml($workbookXml);
        foreach ($workbook->xpath("//*[local-name()='sheet']") ?: [] as $sheet) {
            $name = (string) $sheet['name'];
            $relAttributes = $sheet->attributes(self::NS_REL);
            $relId = $relAttributes !== null ? (string) $relAttributes['id'] : '';
            if ($name !== '' && isset($targets[$relId])) {
                $this->sheetPaths[$name] = $targets[$relId];
            }
        }

        if (!$this->sheetPaths) {
            throw new RuntimeException('Tidak ada sheet yang dapat dibaca pada berkas Excel ini.');
        }
    }

    /** @return array<int, string> */
    private function sharedStrings(): array
    {
        if ($this->sharedStrings !== null) {
            return $this->sharedStrings;
        }

        $this->sharedStrings = [];
        $xml = $this->readEntry('xl/sharedStrings.xml');
        if ($xml === null) {
            return $this->sharedStrings;
        }

        $doc = $this->parseXml($xml);
        foreach ($doc->xpath("//*[local-name()='si']") ?: [] as $item) {
            $text = '';
            $parts = $item->xpath(".//*[local-name()='t'][not(ancestor::*[local-name()='rPh'])]");
            foreach ($parts ?: [] as $part) {
                $text .= (string) $part;
            }
            $this->sharedStrings[] = $text;
        }

        return $this->sharedStrings;
    }

    private function readEntry(string $name): ?string
    {
        $stat = $this->zip->statName($name);
        if ($stat === false) {
            return null;
        }
        if ($stat['size'] > self::MAX_ENTRY_BYTES) {
            throw new RuntimeException('Salah satu komponen berkas Excel terlalu besar.');
        }
        $data = $this->zip->getFromName($name);

        return $data === false ? null : $data;
    }

    private function parseXml(string $xml): SimpleXMLElement
    {
        // Tolak DOCTYPE/ENTITY: berkas Excel normal tidak memakainya.
        if (stripos($xml, '<!DOCTYPE') !== false || stripos($xml, '<!ENTITY') !== false) {
            throw new RuntimeException('Berkas Excel memuat struktur yang tidak diizinkan.');
        }

        $previous = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($doc === false) {
            throw new RuntimeException('Isi berkas Excel rusak dan tidak dapat dibaca.');
        }

        return $doc;
    }
}
