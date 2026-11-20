<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';
$conditions = [meeting_visibility_sql('j')];
$params = [];
$search = trim((string) ($_GET['q'] ?? ''));
$unit = (string) ($_GET['unit'] ?? '');
$month = (string) ($_GET['bulan'] ?? '');
if ($search !== '') {
    $conditions[] = 'j.judul_rapat LIKE :search';
    $params['search'] = '%' . $search . '%';
}
if (in_array($unit, ['yayasan','sdit','smpit','semua'], true) && can_access_category($unit)) {
    $conditions[] = 'j.kategori_unit = :unit';
    $params['unit'] = $unit;
}
if (preg_match('/^\d{4}-\d{2}$/', $month)) {
    $conditions[] = "DATE_FORMAT(j.tanggal_rapat, '%Y-%m') = :month";
    $params['month'] = $month;
}
$statement = $pdo->prepare('SELECT n.id_notulensi, n.created_at, j.judul_rapat, j.tanggal_rapat, j.kategori_unit FROM notulensi n JOIN jadwal_rapat j ON j.id_rapat=n.id_rapat WHERE ' . implode(' AND ', $conditions) . ' ORDER BY j.tanggal_rapat DESC');
$statement->execute($params);
$minutes = $statement->fetchAll();
$pageTitle = 'Arsip Notulensi';
require __DIR__ . '/../../includes/header.php';
?>
<div class="page-heading"><div><p class="eyebrow">DOKUMENTASI</p><h1>Arsip Notulensi</h1><p class="text-slate-500">Cari catatan rapat dan tindak lanjutnya.</p></div></div>
<form class="filter-bar" method="get">
    <input class="field-control search-field" name="q" placeholder="Cari judul rapat..." value="<?= h($search) ?>">
    <input class="field-control sm:w-auto" type="month" name="bulan" value="<?= h($month) ?>">
    <select class="field-control sm:w-auto" name="unit"><option value="">Semua unit yang dapat diakses</option><?php foreach (['yayasan','sdit','smpit','semua'] as $option): ?><?php if (can_access_category($option)): ?><option value="<?= h($option) ?>" <?= $unit === $option ? 'selected' : '' ?>><?= h(category_label($option)) ?></option><?php endif; ?><?php endforeach; ?></select>
    <button class="button button-secondary">Cari</button>
</form>
<div class="table-container"><table class="data-table"><thead><tr><th>Judul rapat</th><th>Tanggal</th><th>Unit</th><th>Dibuat</th><th></th></tr></thead><tbody>
<?php foreach ($minutes as $minute): ?><tr><td><strong><?= h($minute['judul_rapat']) ?></strong></td><td><?= h(format_date($minute['tanggal_rapat'])) ?></td><td><?= h(category_label($minute['kategori_unit'])) ?></td><td><?= h(format_date($minute['created_at'])) ?></td><td class="text-right"><a class="button button-secondary button-sm" href="<?= h(app_url('/modules/notulensi/detail.php')) ?>?id=<?= (int) $minute['id_notulensi'] ?>">Buka</a></td></tr><?php endforeach; ?>
<?php if (!$minutes): ?><tr><td colspan="5" class="py-4 text-center text-slate-500">Belum ada notulensi yang cocok.</td></tr><?php endif; ?>
</tbody></table></div>
<?php if (is_admin()): ?>
<div class="panel mt-4"><div class="panel-heading"><h2>Buat Notulensi</h2></div><p class="text-slate-500">Pilih rapat yang belum memiliki notulensi.</p>
<?php
$statement = $pdo->query("SELECT j.id_rapat, j.judul_rapat, j.tanggal_rapat FROM jadwal_rapat j LEFT JOIN notulensi n ON n.id_rapat=j.id_rapat WHERE n.id_notulensi IS NULL ORDER BY j.tanggal_rapat DESC");
$availableMeetings = $statement->fetchAll();
?>
<?php if ($availableMeetings): ?><form action="<?= h(app_url('/modules/notulensi/tambah.php')) ?>" method="get" class="flex flex-col gap-2 sm:flex-row"><select class="field-control min-w-0 flex-1" name="id_rapat" required><option value="">Pilih jadwal rapat</option><?php foreach ($availableMeetings as $meeting): ?><option value="<?= (int) $meeting['id_rapat'] ?>"><?= h(format_date($meeting['tanggal_rapat']) . ' — ' . $meeting['judul_rapat']) ?></option><?php endforeach; ?></select><button class="button button-primary whitespace-nowrap">Buat Notulensi</button></form><?php else: ?><p class="mb-0">Semua jadwal sudah memiliki notulensi.</p><?php endif; ?>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
