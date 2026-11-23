<?php
require_once __DIR__ . '/../../../includes/auth_check.php';
require_once __DIR__ . '/../../../config/database.php';
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$statement = $pdo->prepare('SELECT n.*,j.judul_rapat,j.tanggal_rapat,j.waktu_mulai,j.kategori_unit FROM notulensi n JOIN jadwal_rapat j ON j.id_rapat=n.id_rapat WHERE n.id_notulensi=:id AND ' . meeting_visibility_sql('j'));
$statement->execute(['id' => $id ?: 0]);
$minute = $statement->fetch();
if (!$minute) { http_response_code(404); exit('Notulensi tidak ditemukan.'); }
$pageTitle = 'Cetak Notulensi';
require __DIR__ . '/../../../includes/header.php';
?>
<div class="print-actions"><button class="button button-primary" onclick="window.print()">Cetak / Simpan PDF</button> <a class="button button-secondary" href="<?= h(app_url('/modules/rapat/notulensi/detail.php')) ?>?id=<?= (int) $minute['id_notulensi'] ?>">Kembali</a></div>
<article class="panel minutes-paper print-document mx-auto w-full max-w-4xl">
    <header class="print-letterhead"><strong>YAYASAN SMART CENDEKIA</strong><span>NOTULENSI RAPAT</span><hr></header>
    <h1><?= h($minute['judul_rapat']) ?></h1><p><?= h(format_date($minute['tanggal_rapat'])) ?> · <?= h(substr($minute['waktu_mulai'],0,5)) ?> WIB · <?= h(category_label($minute['kategori_unit'])) ?></p>
    <h2>Daftar Hadir</h2><div class="preserve-lines"><?= h($minute['daftar_hadir']) ?></div>
    <h2 class="mt-4">Poin Pembahasan</h2><div class="rich-content"><?= sanitize_rich_text($minute['isi_pembahasan']) ?></div>
    <h2 class="mt-4">Keputusan Final</h2><div class="preserve-lines"><?= h($minute['keputusan_final']) ?></div>
</article>
<?php require __DIR__ . '/../../../includes/footer.php'; ?>
