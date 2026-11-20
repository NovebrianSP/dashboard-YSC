<?php
$dapDays = $dapDays ?? [];
$activityMap = $activityMap ?? [];
$dapEditable = $dapEditable ?? false;

if (!is_array($dapDays) || !is_array($activityMap)) {
    http_response_code(404);
    exit;
}

$daySlots = [];
$maxRows = 0;
foreach ($dapDays as $day) {
    $daySlots[$day['date']] = dap_time_slots($day['end_time']);
    $maxRows = max($maxRows, count($daySlots[$day['date']]));
}
?>
<section class="mb-6">
    <div class="mb-3">
        <h2 class="text-lg font-bold text-slate-900">Jadwal Aktivitas Mingguan</h2>
        <p class="text-sm text-slate-500">Geser tabel ke samping untuk melihat hari berikutnya. Slot aktivitas berdurasi 30 menit.</p>
    </div>
    <div class="table-container overflow-x-auto">
        <table class="dap-spreadsheet">
            <thead>
                <tr>
                    <?php foreach ($dapDays as $day): ?>
                        <th class="dap-day-heading" colspan="2"><?= h($day['name']) ?><span><?= h($day['label']) ?></span></th>
                    <?php endforeach; ?>
                </tr>
                <tr>
                    <?php foreach ($dapDays as $day): ?>
                        <th class="dap-column-heading dap-time-column">JAM</th>
                        <th class="dap-column-heading">AKTIVITAS</th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php for ($row = 0; $row < $maxRows; $row++): ?>
                    <tr>
                        <?php foreach ($dapDays as $day): ?>
                            <?php $slot = $daySlots[$day['date']][$row] ?? null; ?>
                            <?php if ($slot): ?>
                                <?php $activity = $activityMap[$day['date']][$slot['start']] ?? ''; ?>
                                <td class="dap-time-cell"><?= h($slot['start']) ?></td>
                                <td class="dap-activity-cell">
                                    <?php if ($dapEditable): ?>
                                        <input class="dap-activity-input" type="text" maxlength="2000" name="activities[<?= h($day['date']) ?>][<?= h($slot['start']) ?>]" value="<?= h($activity) ?>" aria-label="<?= h($day['name'] . ' ' . $slot['start'] . ' aktivitas') ?>">
                                    <?php else: ?>
                                        <?= $activity !== '' ? h($activity) : '<span class="text-slate-400">—</span>' ?>
                                    <?php endif; ?>
                                </td>
                            <?php else: ?>
                                <td class="dap-time-cell"></td>
                                <td class="dap-activity-cell"></td>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tr>
                <?php endfor; ?>
            </tbody>
        </table>
    </div>
</section>
