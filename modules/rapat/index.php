<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

$conditions = [meeting_visibility_sql('j')];
$params = [];
$status = (string) ($_GET['status'] ?? '');
$requestedCategory = (string) ($_GET['unit'] ?? '');
$category = in_array($requestedCategory, ['yayasan', 'sdit', 'smpit', 'semua'], true)
    && can_access_category($requestedCategory)
    ? $requestedCategory
    : '';
$search = trim((string) ($_GET['q'] ?? ''));
$month = (string) ($_GET['bulan'] ?? '');
if (in_array($status, ['terjadwal', 'selesai', 'dibatalkan'], true)) {
    $conditions[] = 'j.status_rapat = :status';
    $params['status'] = $status;
}
if ($category !== '') {
    $conditions[] = 'j.kategori_unit = :unit';
    $params['unit'] = $category;
}
if ($search !== '') {
    $conditions[] = 'j.judul_rapat LIKE :search';
    $params['search'] = '%' . $search . '%';
}
if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
    $conditions[] = "DATE_FORMAT(j.tanggal_rapat, '%Y-%m') = :month";
    $params['month'] = $month;
}
$visibleMeetingsSql = implode(' AND ', $conditions);
$statement = $pdo->prepare(
    'SELECT j.*, n.id_notulensi FROM jadwal_rapat j '
    . 'LEFT JOIN notulensi n ON n.id_rapat = j.id_rapat '
    . 'WHERE ' . $visibleMeetingsSql
    . ' ORDER BY j.tanggal_rapat DESC, j.waktu_mulai DESC'
);
$statement->execute($params);
$meetings = $statement->fetchAll();
$statement = $pdo->prepare(
    'SELECT n.id_notulensi, n.created_at, j.judul_rapat, j.tanggal_rapat, j.kategori_unit '
    . 'FROM notulensi n JOIN jadwal_rapat j ON j.id_rapat = n.id_rapat '
    . 'WHERE ' . $visibleMeetingsSql
    . ' ORDER BY j.tanggal_rapat DESC'
);
$statement->execute($params);
$minutes = $statement->fetchAll();

$availableMeetings = [];
if (is_admin()) {
    $availableConditions = [meeting_visibility_sql('j'), "j.status_rapat = 'selesai'", 'n.id_notulensi IS NULL'];
    $availableParams = [];
    if ($category !== '') {
        $availableConditions[] = 'j.kategori_unit = :unit';
        $availableParams['unit'] = $category;
    }
    if ($search !== '') {
        $availableConditions[] = 'j.judul_rapat LIKE :search';
        $availableParams['search'] = '%' . $search . '%';
    }
    if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
        $availableConditions[] = "DATE_FORMAT(j.tanggal_rapat, '%Y-%m') = :month";
        $availableParams['month'] = $month;
    }
    $statement = $pdo->prepare(
        'SELECT j.id_rapat, j.judul_rapat, j.tanggal_rapat FROM jadwal_rapat j '
        . 'LEFT JOIN notulensi n ON n.id_rapat = j.id_rapat '
        . 'WHERE ' . implode(' AND ', $availableConditions)
        . ' ORDER BY j.tanggal_rapat DESC'
    );
    $statement->execute($availableParams);
    $availableMeetings = $statement->fetchAll();
}
$pageTitle = 'Jadwal Rapat dan Notulensi';
require __DIR__ . '/../../includes/header.php';
?>
<div class="page-heading">
    <div>
        <p class="eyebrow">AGENDA</p>
        <h1>Rapat dan Notulensi</h1>
        <p class="text-slate-500">Kelola jadwal rapat, arsip notulensi, dan tindak lanjut.</p>
    </div><?php if (can_create_meeting()): ?><a class="button button-primary" href="<?= h(app_url('/modules/rapat/tambah.php')) ?>">+ Tambah Jadwal</a><?php endif; ?>
</div>
<form class="filter-bar" method="get">
    <input class="field-control search-field" type="search" name="q" placeholder="Cari judul rapat..." value="<?= h($search) ?>">
    <input class="field-control sm:w-auto" type="month" name="bulan" value="<?= h($month) ?>">
    <select class="field-control sm:w-auto" name="status">
        <option value="">Semua status</option><?php foreach (['terjadwal', 'selesai', 'dibatalkan'] as $option): ?><option value="<?= h($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= h(status_label($option)) ?></option><?php endforeach; ?>
    </select>
    <select class="field-control sm:w-auto" name="unit">
        <option value="">Semua unit</option><?php foreach (['yayasan', 'sdit', 'smpit', 'semua'] as $option): ?><?php if (can_access_category($option)): ?><option value="<?= h($option) ?>" <?= $category === $option ? 'selected' : '' ?>><?= h(category_label($option)) ?></option><?php endif; ?><?php endforeach; ?>
    </select>
    <button class="button button-secondary">Filter</button>
</form>
<div class="table-container">
    <table class="data-table">
        <thead>
            <tr>
                <th>Agenda</th>
                <th>Tanggal & Waktu</th>
                <th>Lokasi / Tautan</th>
                <th>Unit</th>
                <th>Status</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($meetings as $meeting): ?>
                <?php $invite = meeting_invite_text($meeting); ?>
                <tr>
                    <td><strong><?= h($meeting['judul_rapat']) ?></strong>
                        <div class="text-sm text-slate-500"><?= h($meeting['deskripsi_agenda'] ?? '') ?></div>
                    </td>
                    <td><?= h(format_date($meeting['tanggal_rapat'])) ?><div class="text-sm text-slate-500"><?= h(substr($meeting['waktu_mulai'], 0, 5)) ?> WIB</div>
                    </td>
                    <td><?= h($meeting['lokasi']) ?><?php if (preg_match('~^https?://~i', $meeting['lokasi'])): ?><div><a target="_blank" rel="noopener noreferrer" href="<?= h($meeting['lokasi']) ?>">Buka tautan</a></div><?php endif; ?></td>
                    <td><span class="badge"><?= h(category_label($meeting['kategori_unit'])) ?></span></td>
                    <td>
                        <?php if (can_change_meeting_status()): ?>
                            <select class="field-control status-select sm:w-auto"
                                data-id="<?= (int) $meeting['id_rapat'] ?>"
                                data-title="<?= h($meeting['judul_rapat']) ?>"
                                data-original="<?= h($meeting['status_rapat']) ?>"
                                aria-label="Ubah status rapat">
                                <?php foreach (['terjadwal', 'selesai', 'dibatalkan'] as $option): ?>
                                    <option value="<?= h($option) ?>" <?= $meeting['status_rapat'] === $option ? 'selected' : '' ?>><?= h(status_label($option)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <?= h(status_label($meeting['status_rapat'])) ?>
                        <?php endif; ?>
                    </td>
                    <td class="whitespace-nowrap"><a class="button button-success button-sm" target="_blank" rel="noopener noreferrer" href="https://wa.me/?text=<?= rawurlencode($invite) ?>">WhatsApp</a>
                        <?php if ($meeting['id_notulensi']): ?>
                            <a class="button button-primary button-sm" href="<?= h(app_url('/modules/rapat/notulensi/detail.php')) ?>?id=<?= (int) $meeting['id_notulensi'] ?>">Notulensi</a>
                        <?php elseif (is_admin() && $meeting['status_rapat'] === 'selesai'): ?>
                            <a class="button button-secondary button-sm" href="<?= h(app_url('/modules/rapat/notulensi/tambah.php')) ?>?id_rapat=<?= (int) $meeting['id_rapat'] ?>">Tambah Notulensi</a>
                        <?php endif; ?>
                        <?php if (is_admin()): ?><a class="button button-secondary button-sm" href="<?= h(app_url('/modules/rapat/edit.php')) ?>?id=<?= (int) $meeting['id_rapat'] ?>">Edit</a>
                            <form class="inline" method="post" action="<?= h(app_url('/modules/rapat/hapus.php')) ?>" data-confirm="Hapus jadwal rapat ini? Notulensi terkait juga akan terhapus."><input type="hidden" name="id" value="<?= (int) $meeting['id_rapat'] ?>"><?= csrf_field() ?><button class="button button-danger button-sm">Hapus</button></form><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$meetings): ?><tr>
                    <td colspan="6" class="py-4 text-center text-slate-500">Belum ada jadwal yang cocok.</td>
                </tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php if (is_admin()): ?>
    <section class="panel mt-5" aria-labelledby="create-minute-title">
        <div class="panel-heading">
            <div>
                <h2 id="create-minute-title">Tambah Notulensi Rapat Selesai</h2>
                <p class="mt-1 text-sm text-slate-500">Pilih rapat yang sudah selesai dan belum memiliki notulensi.</p>
            </div>
        </div>
        <?php if ($availableMeetings): ?>
            <form action="<?= h(app_url('/modules/rapat/notulensi/tambah.php')) ?>" method="get" class="flex flex-col gap-2 sm:flex-row">
                <select class="field-control min-w-0 flex-1" name="id_rapat" required>
                    <option value="">Pilih rapat yang telah dilaksanakan</option>
                    <?php foreach ($availableMeetings as $meeting): ?>
                        <option value="<?= (int) $meeting['id_rapat'] ?>"><?= h(format_date($meeting['tanggal_rapat']) . ' — ' . $meeting['judul_rapat']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="button button-primary whitespace-nowrap">Buat Notulensi</button>
            </form>
        <?php else: ?>
            <p class="empty-state">Belum ada rapat selesai yang belum memiliki notulensi.</p>
        <?php endif; ?>
    </section>
<?php endif; ?>
<section class="panel mt-5" id="arsip-notulensi" aria-labelledby="minutes-archive-title">
    <div class="panel-heading">
        <div>
            <h2 id="minutes-archive-title">Arsip Notulensi</h2>
            <p class="mt-1 text-sm text-slate-500">Notulensi untuk rapat yang dapat Anda akses.</p>
        </div>
    </div>
    <div class="table-container">
        <table class="data-table">
            <thead><tr><th>Judul rapat</th><th>Tanggal</th><th>Unit</th><th>Dibuat</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($minutes as $minute): ?>
                    <tr>
                        <td><strong><?= h($minute['judul_rapat']) ?></strong></td>
                        <td><?= h(format_date($minute['tanggal_rapat'])) ?></td>
                        <td><?= h(category_label($minute['kategori_unit'])) ?></td>
                        <td><?= h(format_date($minute['created_at'])) ?></td>
                        <td class="text-right"><a class="button button-secondary button-sm" href="<?= h(app_url('/modules/rapat/notulensi/detail.php')) ?>?id=<?= (int) $minute['id_notulensi'] ?>">Buka</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$minutes): ?><tr><td colspan="5" class="py-4 text-center text-slate-500">Belum ada notulensi yang cocok.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php if (can_change_meeting_status()): ?>
    <form id="status-form" method="post" action="<?= h(app_url('/modules/rapat/ubah_status.php')) ?>" hidden>
        <?= csrf_field() ?>
        <input type="hidden" name="id">
        <input type="hidden" name="status">
        <input type="hidden" name="return_query" value="<?= h($_SERVER['QUERY_STRING'] ?? '') ?>">
    </form>

    <script>
        (function() {
            const form = document.getElementById('status-form');
            const labels = {
                terjadwal: 'Terjadwal',
                selesai: 'Selesai',
                dibatalkan: 'Dibatalkan'
            };

            document.querySelectorAll('.status-select').forEach((select) => {
                select.addEventListener('change', () => {
                    if (select.value === select.dataset.original) return;
                    const confirmed = window.confirm(
                        'Ubah status rapat "' + select.dataset.title + '" dari ' +
                        labels[select.dataset.original] + ' menjadi ' + labels[select.value] + '?'
                    );
                    if (!confirmed) {
                        select.value = select.dataset.original;
                        return;
                    }

                    form.querySelector('[name="id"]').value = select.dataset.id;
                    form.querySelector('[name="status"]').value = select.value;
                    form.submit();
                });
            });
        })();
    </script>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/footer.php'; ?>