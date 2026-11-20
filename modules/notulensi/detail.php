<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$statement = $pdo->prepare('SELECT n.*, j.judul_rapat,j.tanggal_rapat,j.waktu_mulai,j.lokasi,j.kategori_unit FROM notulensi n JOIN jadwal_rapat j ON j.id_rapat=n.id_rapat WHERE n.id_notulensi=:id AND ' . meeting_visibility_sql('j'));
$statement->execute(['id' => $id ?: 0]);
$minute = $statement->fetch();
if (!$minute) { http_response_code(404); exit('Notulensi tidak ditemukan.'); }
$statement = $pdo->prepare('SELECT * FROM tindak_lanjut WHERE id_notulensi=:id ORDER BY tenggat_waktu');
$statement->execute(['id' => $minute['id_notulensi']]);
$tasks = $statement->fetchAll();
$pageTitle = 'Detail Notulensi';
require __DIR__ . '/../../includes/header.php';
?>
<div class="page-heading"><div><p class="eyebrow">ARSIP RAPAT · <?= h(category_label($minute['kategori_unit'])) ?></p><h1><?= h($minute['judul_rapat']) ?></h1><p class="text-slate-500"><?= h(format_date($minute['tanggal_rapat'])) ?> · <?= h(substr($minute['waktu_mulai'],0,5)) ?> WIB · <?= h($minute['lokasi']) ?></p></div><div class="flex flex-wrap gap-2"><a class="button button-secondary" href="<?= h(app_url('/modules/notulensi/cetak.php')) ?>?id=<?= (int) $minute['id_notulensi'] ?>">Cetak / PDF</a><?php if (is_admin()): ?><a class="button button-primary" href="<?= h(app_url('/modules/notulensi/edit.php')) ?>?id=<?= (int) $minute['id_notulensi'] ?>">Edit</a><?php endif; ?></div></div>
<article class="panel minutes-paper">
    <h2>Daftar Hadir</h2><div class="preserve-lines"><?= h($minute['daftar_hadir']) ?></div>
    <h2 class="mt-4">Poin Pembahasan</h2><div class="rich-content"><?= sanitize_rich_text($minute['isi_pembahasan']) ?></div>
    <h2 class="mt-4">Keputusan Final</h2><div class="preserve-lines"><?= h($minute['keputusan_final']) ?></div>
    <?php if ($minute['file_lampiran']): ?><p class="mt-4"><a href="<?= h(app_url('/modules/notulensi/lampiran.php')) ?>?id=<?= (int) $minute['id_notulensi'] ?>">Unduh lampiran</a></p><?php endif; ?>
</article>
<section class="panel mt-4"><div class="panel-heading"><h2>Tindak Lanjut</h2></div>
<?php if ($tasks): ?><div class="table-container"><table class="data-table"><thead><tr><th>Tugas</th><th>Penanggung jawab</th><th>Tenggat</th><th>Status</th></tr></thead><tbody><?php foreach ($tasks as $task): ?><tr><td><?= h($task['deskripsi_tugas']) ?></td><td><?= h($task['penanggung_jawab']) ?></td><td><?= h(format_date($task['tenggat_waktu'])) ?></td><td><form method="post" action="<?= h(app_url('/modules/tugas/update_status.php')) ?>" class="flex gap-2"><input type="hidden" name="id_tugas" value="<?= (int) $task['id_tugas'] ?>"><input type="hidden" name="return_to" value="/modules/notulensi/detail.php?id=<?= (int) $minute['id_notulensi'] ?>"><?= csrf_field() ?><select class="field-control w-auto py-1.5" name="status_tugas" <?= (!is_admin() && strcasecmp((string) current_user()['nama_lengkap'], $task['penanggung_jawab']) !== 0) ? 'disabled' : '' ?>><?php foreach (['pending','proses','selesai'] as $status): ?><option value="<?= h($status) ?>" <?= $task['status_tugas'] === $status ? 'selected' : '' ?>><?= h(status_label($status)) ?></option><?php endforeach; ?></select><?php if (is_admin() || strcasecmp((string) current_user()['nama_lengkap'], $task['penanggung_jawab']) === 0): ?><button class="button button-secondary button-sm">Ubah</button><?php endif; ?></form></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><p class="text-slate-500">Belum ada tindak lanjut.</p><?php endif; ?>
<?php if (is_admin()): ?><hr class="my-5 border-slate-200"><h3 class="mb-3 text-lg font-semibold">Tambah tindak lanjut</h3><form method="post" action="<?= h(app_url('/modules/tugas/tambah.php')) ?>" class="grid grid-cols-1 gap-2 md:grid-cols-12"><?= csrf_field() ?><input type="hidden" name="id_notulensi" value="<?= (int) $minute['id_notulensi'] ?>"><div class="md:col-span-5"><input class="field-control" name="deskripsi_tugas" placeholder="Deskripsi tugas" required></div><div class="md:col-span-3"><input class="field-control" name="penanggung_jawab" placeholder="Penanggung jawab" required></div><div class="md:col-span-2"><input class="field-control" type="date" name="tenggat_waktu" required></div><div class="md:col-span-2"><button class="button button-primary w-full">Tambah</button></div></form><?php endif; ?>
</section>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
