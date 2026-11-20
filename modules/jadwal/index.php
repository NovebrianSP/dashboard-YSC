<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

$conditions = [meeting_visibility_sql('j')];
$params = [];
$status = $_GET['status'] ?? '';
$category = $_GET['unit'] ?? '';
if (in_array($status, ['terjadwal', 'selesai', 'dibatalkan'], true)) {
    $conditions[] = 'j.status_rapat = :status';
    $params['status'] = $status;
}
if (in_array($category, ['yayasan', 'sdit', 'smpit', 'semua'], true) && can_access_category($category)) {
    $conditions[] = 'j.kategori_unit = :unit';
    $params['unit'] = $category;
}
$statement = $pdo->prepare('SELECT j.* FROM jadwal_rapat j WHERE ' . implode(' AND ', $conditions) . ' ORDER BY j.tanggal_rapat DESC, j.waktu_mulai DESC');
$statement->execute($params);
$meetings = $statement->fetchAll();
$pageTitle = 'Jadwal Rapat';
require __DIR__ . '/../../includes/header.php';
?>
<div class="page-heading"><div><p class="eyebrow">AGENDA</p><h1>Jadwal Rapat</h1><p class="text-slate-500">Daftar agenda rapat yayasan dan unit pendidikan.</p></div><?php if (can_create_meeting()): ?><a class="button button-primary" href="<?= h(app_url('/modules/jadwal/tambah.php')) ?>">+ Tambah Jadwal</a><?php endif; ?></div>
<form class="filter-bar" method="get">
    <select class="field-control sm:w-auto" name="status"><option value="">Semua status</option><?php foreach (['terjadwal','selesai','dibatalkan'] as $option): ?><option value="<?= h($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= h(status_label($option)) ?></option><?php endforeach; ?></select>
    <select class="field-control sm:w-auto" name="unit"><option value="">Semua unit</option><?php foreach (['yayasan','sdit','smpit','semua'] as $option): ?><?php if (can_access_category($option)): ?><option value="<?= h($option) ?>" <?= $category === $option ? 'selected' : '' ?>><?= h(category_label($option)) ?></option><?php endif; ?><?php endforeach; ?></select>
    <button class="button button-secondary">Filter</button>
</form>
<div class="table-container">
    <table class="data-table"><thead><tr><th>Agenda</th><th>Tanggal & Waktu</th><th>Lokasi / Tautan</th><th>Unit</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
    <?php foreach ($meetings as $meeting): ?>
        <?php $invite = "Undangan rapat: {$meeting['judul_rapat']} | " . format_date($meeting['tanggal_rapat']) . " pukul " . substr($meeting['waktu_mulai'], 0, 5) . " | {$meeting['lokasi']}"; ?>
        <tr><td><strong><?= h($meeting['judul_rapat']) ?></strong><div class="text-sm text-slate-500"><?= h($meeting['deskripsi_agenda'] ?? '') ?></div></td><td><?= h(format_date($meeting['tanggal_rapat'])) ?><div class="text-sm text-slate-500"><?= h(substr($meeting['waktu_mulai'], 0, 5)) ?> WIB</div></td><td><?= h($meeting['lokasi']) ?><?php if (preg_match('~^https?://~i', $meeting['lokasi'])): ?><div><a target="_blank" rel="noopener noreferrer" href="<?= h($meeting['lokasi']) ?>">Buka tautan</a></div><?php endif; ?></td><td><span class="badge"><?= h(category_label($meeting['kategori_unit'])) ?></span></td><td><?= h(status_label($meeting['status_rapat'])) ?></td><td class="whitespace-nowrap"><a class="button button-success button-sm" target="_blank" rel="noopener noreferrer" href="https://wa.me/?text=<?= rawurlencode($invite) ?>">WhatsApp</a> <?php if (is_admin()): ?><a class="button button-secondary button-sm" href="<?= h(app_url('/modules/jadwal/edit.php')) ?>?id=<?= (int) $meeting['id_rapat'] ?>">Edit</a><form class="inline" method="post" action="<?= h(app_url('/modules/jadwal/hapus.php')) ?>" data-confirm="Hapus jadwal rapat ini? Notulensi terkait juga akan terhapus."><input type="hidden" name="id" value="<?= (int) $meeting['id_rapat'] ?>"><?= csrf_field() ?><button class="button button-danger button-sm">Hapus</button></form><?php endif; ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$meetings): ?><tr><td colspan="6" class="py-4 text-center text-slate-500">Belum ada jadwal yang cocok.</td></tr><?php endif; ?>
    </tbody></table>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
