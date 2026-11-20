<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_meeting_create_access();
require_once __DIR__ . '/../../config/database.php';

$errors = [];
$values = ['judul_rapat' => '', 'tanggal_rapat' => '', 'waktu_mulai' => '', 'lokasi' => '', 'kategori_unit' => 'yayasan', 'deskripsi_agenda' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    foreach ($values as $key => $_) {
        $values[$key] = trim((string) ($_POST[$key] ?? ''));
    }
    if ($values['judul_rapat'] === '') $errors[] = 'Judul rapat wajib diisi.';
    if (!valid_date($values['tanggal_rapat'])) $errors[] = 'Tanggal rapat tidak valid.';
    if (!preg_match('/^\d{2}:\d{2}$/', $values['waktu_mulai'])) $errors[] = 'Waktu mulai tidak valid.';
    if ($values['lokasi'] === '') $errors[] = 'Lokasi atau tautan rapat wajib diisi.';
    if (!in_array($values['kategori_unit'], ['yayasan','sdit','smpit','semua'], true)) $errors[] = 'Kategori unit tidak valid.';
    if (!$errors) {
        $statement = $pdo->prepare('INSERT INTO jadwal_rapat (judul_rapat,tanggal_rapat,waktu_mulai,lokasi,kategori_unit,deskripsi_agenda,dibuat_oleh) VALUES (:judul,:tanggal,:waktu,:lokasi,:kategori,:deskripsi,:user)');
        $statement->execute(['judul'=>$values['judul_rapat'],'tanggal'=>$values['tanggal_rapat'],'waktu'=>$values['waktu_mulai'],'lokasi'=>$values['lokasi'],'kategori'=>$values['kategori_unit'],'deskripsi'=>$values['deskripsi_agenda'],'user'=>current_user()['id_user']]);
        flash('success', 'Jadwal rapat berhasil ditambahkan.');
        redirect('/modules/jadwal/index.php');
    }
}
$pageTitle = 'Tambah Jadwal';
require __DIR__ . '/../../includes/header.php';
?>
<div class="page-heading"><div><p class="eyebrow">AGENDA</p><h1>Tambah Jadwal Rapat</h1></div></div>
<?php if ($errors): ?><div class="alert alert-danger" role="alert"><?= h(implode(' ', $errors)) ?></div><?php endif; ?>
<form method="post" class="panel form-panel"><?= csrf_field() ?>
    <div class="mb-4"><label class="field-label">Judul rapat</label><input class="field-control" name="judul_rapat" maxlength="200" value="<?= h($values['judul_rapat']) ?>" required></div>
    <div class="mb-4 grid grid-cols-1 gap-4 md:grid-cols-2"><div><label class="field-label">Tanggal</label><input class="field-control" type="date" name="tanggal_rapat" value="<?= h($values['tanggal_rapat']) ?>" required></div><div><label class="field-label">Waktu mulai</label><input class="field-control" type="time" name="waktu_mulai" value="<?= h($values['waktu_mulai']) ?>" required></div></div>
    <div class="mb-4"><label class="field-label">Lokasi atau tautan rapat</label><input class="field-control" name="lokasi" maxlength="150" value="<?= h($values['lokasi']) ?>" required></div>
    <div class="mb-4"><label class="field-label">Kategori unit</label><select class="field-control" name="kategori_unit"><?php foreach (['yayasan','sdit','smpit','semua'] as $option): ?><option value="<?= h($option) ?>" <?= $values['kategori_unit'] === $option ? 'selected' : '' ?>><?= h(category_label($option)) ?></option><?php endforeach; ?></select></div>
    <div class="mb-5"><label class="field-label">Deskripsi agenda</label><textarea class="field-control" name="deskripsi_agenda" rows="4"><?= h($values['deskripsi_agenda']) ?></textarea></div>
    <div class="flex flex-wrap gap-2"><button class="button button-primary">Simpan Jadwal</button><a class="button button-secondary" href="<?= h(app_url('/modules/jadwal/index.php')) ?>">Batal</a></div>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
