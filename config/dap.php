<?php
declare(strict_types=1);

function require_dap_access(): void
{
    require_login();
    if (!in_array(current_user()['role'] ?? '', ['admin', 'pimpinan'], true)) {
        http_response_code(403);
        exit('Fitur DAP hanya tersedia untuk admin dan pimpinan.');
    }
}

function dap_week_days(DateTimeImmutable $weekStart): array
{
    $names = [
        0 => 'Senin',
        1 => 'Selasa',
        2 => 'Rabu',
        3 => 'Kamis',
        4 => 'Jumat',
        5 => 'Sabtu',
    ];
    $days = [];
    foreach ($names as $offset => $name) {
        $date = $weekStart->modify('+' . $offset . ' days');
        $days[] = [
            'name' => $name,
            'date' => $date->format('Y-m-d'),
            'label' => $date->format('d/m/Y'),
            'end_time' => $offset === 5 ? '13:30' : '16:00',
        ];
    }
    return $days;
}

function dap_time_slots(string $endTime): array
{
    [$endHour, $endMinute] = array_map('intval', explode(':', $endTime));
    $end = ($endHour * 60) + $endMinute;
    $slots = [];
    for ($start = (7 * 60) + 30; $start + 30 <= $end; $start += 30) {
        $slots[] = [
            'start' => sprintf('%02d:%02d', intdiv($start, 60), $start % 60),
            'end' => sprintf('%02d:%02d', intdiv($start + 30, 60), ($start + 30) % 60),
        ];
    }
    return $slots;
}

function dap_priorities_from_storage(string $stored): array
{
    $priorities = json_decode($stored, true);
    if (!is_array($priorities) || json_last_error() !== JSON_ERROR_NONE) {
        $priorities = trim($stored) === '' ? [] : preg_split('/\R/u', $stored);
    }

    $result = array_fill(0, 10, '');
    foreach (array_slice($priorities, 0, 10) as $index => $priority) {
        if (is_string($priority)) {
            $result[$index] = $priority;
        }
    }
    return $result;
}

function dap_collect_priorities(mixed $submitted): array
{
    $priorities = array_fill(0, 10, '');
    $errors = [];
    if (!is_array($submitted)) {
        return [$priorities, ['Data pekerjaan prioritas tidak valid.']];
    }

    foreach ($submitted as $index => $priority) {
        if (!is_string($index) && !is_int($index)) {
            $errors[] = 'Data pekerjaan prioritas tidak valid.';
            continue;
        }
        $index = filter_var($index, FILTER_VALIDATE_INT);
        if ($index === false || $index < 0 || $index > 9 || !is_string($priority)) {
            $errors[] = 'Data pekerjaan prioritas tidak valid.';
            continue;
        }

        $priority = trim($priority);
        $length = preg_match_all('/./us', $priority, $matches);
        if ($length === false || $length > 500) {
            $errors[] = 'Setiap pekerjaan prioritas maksimal 500 karakter.';
            continue;
        }
        $priorities[$index] = $priority;
    }

    if (!array_filter($priorities, static fn (string $priority): bool => $priority !== '')) {
        $errors[] = 'Isi setidaknya satu pekerjaan prioritas.';
    }
    return [$priorities, array_values(array_unique($errors))];
}

function dap_priorities_to_storage(array $priorities): string
{
    return json_encode($priorities, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
}

function dap_collect_activities(mixed $submitted, array $days): array
{
    $activities = [];
    $errors = [];
    if (!is_array($submitted)) {
        return [[], ['Data jadwal tidak valid.']];
    }

    $allowedDates = array_column($days, 'date');
    foreach ($submitted as $date => $dayActivities) {
        if (!is_string($date) || !in_array($date, $allowedDates, true) || !is_array($dayActivities)) {
            $errors[] = 'Data jadwal tidak valid.';
            continue;
        }
        $day = $days[array_search($date, $allowedDates, true)];
        $allowedTimes = array_column(dap_time_slots($day['end_time']), 'start');
        foreach ($dayActivities as $time => $activity) {
            if (!is_string($time) || !in_array($time, $allowedTimes, true) || !is_string($activity)) {
                $errors[] = 'Data jadwal tidak valid.';
                continue;
            }
            $activity = trim($activity);
            $activityLength = preg_match_all('/./us', $activity, $matches);
            if ($activityLength === false || $activityLength > 2000) {
                $errors[] = 'Setiap aktivitas maksimal 2.000 karakter.';
                continue;
            }
            if ($activity !== '') {
                $activities[$date][$time] = $activity;
            }
        }
    }
    return [$activities, array_values(array_unique($errors))];
}

function dap_save_activities(PDO $pdo, int $dapId, array $activities): void
{
    $statement = $pdo->prepare(
        'INSERT INTO dap_activities (id_dap, tanggal, waktu_mulai, aktivitas) '
        . 'VALUES (:dap_id, :date, :start_time, :activity)'
    );
    foreach ($activities as $date => $dayActivities) {
        foreach ($dayActivities as $startTime => $activity) {
            $statement->execute([
                'dap_id' => $dapId,
                'date' => $date,
                'start_time' => $startTime,
                'activity' => $activity,
            ]);
        }
    }
}

function dap_activity_map(array $activities): array
{
    $map = [];
    foreach ($activities as $activity) {
        $map[$activity['tanggal']][substr($activity['waktu_mulai'], 0, 5)] = $activity['aktivitas'];
    }
    return $map;
}
