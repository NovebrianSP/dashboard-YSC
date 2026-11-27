<?php
declare(strict_types=1);

require_once __DIR__ . '/dap.php';
require_once __DIR__ . '/xlsx_reader.php';

final class DapImportException extends RuntimeException
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

function dap_import_cell_text(array $cells, string $reference): string
{
    $cell = $cells[$reference] ?? null;
    if ($cell === null) {
        return '';
    }

    return match ($cell['kind']) {
        'string' => trim((string) $cell['value']),
        'number' => rtrim(rtrim(number_format((float) $cell['value'], 10, '.', ''), '0'), '.'),
        default => '',
    };
}

function dap_import_time(array $cells, string $reference): ?string
{
    $cell = $cells[$reference] ?? null;
    if ($cell === null) {
        return null;
    }

    if ($cell['kind'] === 'number') {
        $number = (float) $cell['value'];
        if ($number > 0 && $number < 1) {
            $minutes = (int) round($number * 24 * 60);
            $hour = intdiv($minutes, 60);
            $minute = $minutes % 60;
        } else {
            $hour = (int) floor($number);
            $minute = (int) round(($number - $hour) * 100);
        }
    } elseif ($cell['kind'] === 'string') {
        $value = trim((string) $cell['value']);
        if ($value === '') {
            return null;
        }
        if (!preg_match('/\A(\d{1,2})[.:](\d{2})\z/', $value, $matches)) {
            return null;
        }
        $hour = (int) $matches[1];
        $minute = (int) $matches[2];
    } else {
        return null;
    }

    if ($hour < 0 || $hour > 23 || !in_array($minute, [0, 30], true)) {
        return null;
    }

    return sprintf('%02d:%02d', $hour, $minute);
}

function dap_import_excel_date(array $cells, string $reference): ?string
{
    $cell = $cells[$reference] ?? null;
    if ($cell === null) {
        return null;
    }

    if ($cell['kind'] === 'number') {
        $serial = (int) floor((float) $cell['value']);
        if ($serial < 1 || $serial > 100000) {
            return null;
        }
        $epoch = new DateTimeImmutable('1899-12-30', new DateTimeZone('UTC'));
        return $epoch->modify('+' . $serial . ' days')->format('Y-m-d');
    }

    if ($cell['kind'] === 'string') {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', trim((string) $cell['value']));
        if ($date !== false && $date->format('Y-m-d') === trim((string) $cell['value'])) {
            return $date->format('Y-m-d');
        }
    }

    return null;
}

/**
 * Membaca prioritas dan jadwal dari sheet DAP dengan format 6 hari pada template YSC.
 *
 * @return array{priorities: list<string>, activities: array<string, array<string, string>>, source_dates: list<string>}
 */
function dap_import_parse(string $path, string $sheetName, DateTimeImmutable $weekStart): array
{
    if ($weekStart->format('N') !== '1') {
        throw new DapImportException(['Tanggal mulai minggu harus merupakan hari Senin.']);
    }

    $reader = XlsxReader::open($path);
    try {
        $sheet = null;
        foreach ($reader->sheetNames() as $name) {
            if (strcasecmp(trim($name), trim($sheetName)) === 0) {
                $sheet = $name;
                break;
            }
        }
        if ($sheet === null) {
            throw new DapImportException(['Sheet "' . $sheetName . '" tidak ditemukan di file Excel.']);
        }

        $cells = $reader->cells($sheet);
    } finally {
        $reader->close();
    }

    $timeColumns = ['A', 'C', 'E', 'G', 'I', 'K'];
    $activityColumns = ['B', 'D', 'F', 'H', 'J', 'L'];
    $dateColumns = ['B', 'D', 'F', 'H', 'J', 'L'];
    $sourceDates = [];
    foreach ($timeColumns as $index => $column) {
        if (strtoupper(dap_import_cell_text($cells, $column . '20')) !== 'JAM') {
            throw new DapImportException(['Susunan kolom jadwal pada sheet tidak sesuai template DAP YSC.']);
        }
        $sourceDate = dap_import_excel_date($cells, $dateColumns[$index] . '20');
        if ($sourceDate === null) {
            throw new DapImportException(['Tanggal pada header jadwal Excel tidak valid atau kosong.']);
        }
        $sourceDates[] = $sourceDate;
    }

    $submittedPriorities = [];
    for ($row = 9; $row <= 18; $row++) {
        $submittedPriorities[] = dap_import_cell_text($cells, 'B' . $row);
    }
    [$priorities, $priorityErrors] = dap_collect_priorities($submittedPriorities);
    if ($priorityErrors) {
        throw new DapImportException($priorityErrors);
    }

    $days = dap_week_days($weekStart);
    $allowedTimes = [];
    foreach ($days as $day) {
        $allowedTimes[] = array_column(dap_time_slots($day['end_time']), 'start');
    }

    $activities = [];
    $errors = [];
    $lastTimes = array_fill(0, 6, null);
    for ($row = 21; $row <= 37; $row++) {
        foreach ($timeColumns as $dayIndex => $timeColumn) {
            $timeReference = $timeColumn . $row;
            $activityReference = $activityColumns[$dayIndex] . $row;
            $activityCell = $cells[$activityReference] ?? null;
            $activity = dap_import_cell_text($cells, $activityReference);
            if ($activityCell !== null && $activityCell['kind'] === 'error') {
                $errors[] = 'Nilai aktivitas pada sel ' . $activityReference . ' tidak valid.';
                continue;
            }

            $time = dap_import_time($cells, $timeReference);
            if ($time === null) {
                if ($activity !== '') {
                    $errors[] = 'Waktu aktivitas pada sel ' . $timeReference . ' tidak valid.';
                }
                continue;
            }
            if (!in_array($time, $allowedTimes[$dayIndex], true)) {
                $errors[] = 'Waktu ' . $time . ' pada sel ' . $timeReference . ' berada di luar slot DAP.';
                continue;
            }
            if ($lastTimes[$dayIndex] !== null && $time <= $lastTimes[$dayIndex]) {
                $errors[] = 'Urutan waktu pada kolom ' . $timeColumn . ' tidak berurutan.';
                continue;
            }
            $lastTimes[$dayIndex] = $time;

            $length = preg_match_all('/./us', $activity, $matches);
            if ($length === false || $length > 2000) {
                $errors[] = 'Aktivitas pada sel ' . $activityReference . ' melebihi 2.000 karakter.';
                continue;
            }
            if ($activity !== '') {
                $activities[$days[$dayIndex]['date']][$time] = $activity;
            }
        }
    }

    if ($errors) {
        throw new DapImportException(array_values(array_unique($errors)));
    }

    [$activities, $activityErrors] = dap_collect_activities($activities, $days);
    if ($activityErrors) {
        throw new DapImportException($activityErrors);
    }

    return [
        'priorities' => $priorities,
        'activities' => $activities,
        'source_dates' => $sourceDates,
    ];
}

function dap_import_normalize_activities(array $activities): array
{
    ksort($activities);
    foreach ($activities as &$dayActivities) {
        ksort($dayActivities);
    }
    unset($dayActivities);

    return $activities;
}

/**
 * Simpan hasil impor untuk pemilik dan minggu yang dipilih tanpa menimpa DAP yang sudah ada.
 *
 * @param array{priorities: list<string>, activities: array<string, array<string, string>>, source_dates: list<string>} $import
 * @return array{id: int, created: bool}
 */
function dap_import_save(PDO $pdo, int $userId, DateTimeImmutable $weekStart, array $import): array
{
    if ($weekStart->format('N') !== '1') {
        throw new DapImportException(['Tanggal mulai minggu harus merupakan hari Senin.']);
    }

    $pdo->beginTransaction();
    try {
        $existingStatement = $pdo->prepare(
            'SELECT id_dap, pekerjaan_prioritas FROM dap '
            . 'WHERE id_user = :user_id AND tanggal_mulai = :week_start LIMIT 1 FOR UPDATE'
        );
        $existingStatement->execute([
            'user_id' => $userId,
            'week_start' => $weekStart->format('Y-m-d'),
        ]);
        $existing = $existingStatement->fetch();
        if ($existing) {
            $activityStatement = $pdo->prepare(
                'SELECT tanggal, waktu_mulai, aktivitas FROM dap_activities WHERE id_dap = :id'
            );
            $activityStatement->execute(['id' => (int) $existing['id_dap']]);
            $existingActivities = dap_activity_map($activityStatement->fetchAll());
            $samePriorities = dap_priorities_from_storage((string) $existing['pekerjaan_prioritas'])
                === $import['priorities'];
            $sameActivities = dap_import_normalize_activities($existingActivities)
                === dap_import_normalize_activities($import['activities']);
            if ($samePriorities && $sameActivities) {
                $pdo->commit();
                return ['id' => (int) $existing['id_dap'], 'created' => false];
            }

            throw new DapImportException([
                'DAP untuk akun dan minggu tersebut sudah ada dengan isi berbeda; data tidak ditimpa.',
            ]);
        }

        $insert = $pdo->prepare(
            "INSERT INTO dap (id_user, tanggal_mulai, pekerjaan_prioritas, status) "
            . "VALUES (:user_id, :week_start, :priorities, 'submitted')"
        );
        $insert->execute([
            'user_id' => $userId,
            'week_start' => $weekStart->format('Y-m-d'),
            'priorities' => dap_priorities_to_storage($import['priorities']),
        ]);
        $dapId = (int) $pdo->lastInsertId();
        dap_save_activities($pdo, $dapId, $import['activities']);
        $pdo->commit();

        return ['id' => $dapId, 'created' => true];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($exception instanceof PDOException && (string) $exception->getCode() === '23000') {
            throw new DapImportException([
                'DAP untuk akun dan minggu tersebut sudah ada; data yang sudah tersimpan tidak diubah.',
            ]);
        }
        throw $exception;
    }
}
