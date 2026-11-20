<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/database.php';

$visibility = meeting_visibility_sql('j');
$statement = $pdo->prepare("SELECT COUNT(*) FROM jadwal_rapat j WHERE {$visibility} AND YEAR(j.tanggal_rapat) = YEAR(CURRENT_DATE()) AND MONTH(j.tanggal_rapat) = MONTH(CURRENT_DATE()) AND j.status_rapat = 'terjadwal'");
$statement->execute();
$meetingsThisMonth = (int) $statement->fetchColumn();
$statement = $pdo->prepare("SELECT COUNT(*) FROM notulensi n JOIN jadwal_rapat j ON j.id_rapat = n.id_rapat WHERE {$visibility}");
$statement->execute();
$minutesCount = (int) $statement->fetchColumn();
$statement = $pdo->prepare("SELECT COUNT(*) FROM tindak_lanjut t JOIN notulensi n ON n.id_notulensi = t.id_notulensi JOIN jadwal_rapat j ON j.id_rapat = n.id_rapat WHERE {$visibility} AND t.status_tugas IN ('pending', 'proses')");
$statement->execute();
$openTasks = (int) $statement->fetchColumn();
$statement = $pdo->prepare("SELECT j.* FROM jadwal_rapat j WHERE {$visibility} AND j.status_rapat = 'terjadwal' AND j.tanggal_rapat >= CURRENT_DATE() ORDER BY j.tanggal_rapat, j.waktu_mulai LIMIT 5");
$statement->execute();
$upcoming = $statement->fetchAll();
$statement = $pdo->prepare("SELECT t.*, j.judul_rapat, j.kategori_unit FROM tindak_lanjut t JOIN notulensi n ON n.id_notulensi = t.id_notulensi JOIN jadwal_rapat j ON j.id_rapat = n.id_rapat WHERE {$visibility} AND t.status_tugas IN ('pending', 'proses') ORDER BY t.tenggat_waktu LIMIT 5");
$statement->execute();
$tasks = $statement->fetchAll();

$pageTitle = 'Ringkasan';
require __DIR__ . '/includes/header.php';
?>
<div class="page-heading">
    <div><p class="eyebrow">OVERVIEW</p><h1>Ringkasan Dashboard</h1><p class="text-slate-500">Pantau jadwal rapat dan tindak lanjut terbaru.</p></div>
    <?php if (can_create_meeting()): ?><a href="<?= h(app_url('/modules/jadwal/tambah.php')) ?>" class="button button-primary">+ Buat Jadwal</a><?php endif; ?>
</div>
<div class="mb-4 grid grid-cols-1 gap-3 md:grid-cols-3">
    <div class="stat-card"><span>Rapat terjadwal bulan ini</span><strong><?= $meetingsThisMonth ?></strong><a href="<?= h(app_url('/modules/jadwal/index.php')) ?>">Lihat jadwal →</a></div>
    <div class="stat-card"><span>Arsip notulensi</span><strong><?= $minutesCount ?></strong><a href="<?= h(app_url('/modules/notulensi/index.php')) ?>">Lihat arsip →</a></div>
    <div class="stat-card"><span>Tugas belum selesai</span><strong><?= $openTasks ?></strong><a href="#tugas">Pantau tugas →</a></div>
</div>
<div class="grid grid-cols-1 gap-4 lg:grid-cols-12">
    <section class="lg:col-span-7">
        <div class="panel">
            <div class="panel-heading"><h2>Jadwal Terdekat</h2><a href="<?= h(app_url('/modules/jadwal/index.php')) ?>">Semua jadwal</a></div>
            <?php if (!$upcoming): ?><p class="empty-state">Belum ada jadwal rapat mendatang.</p><?php endif; ?>
            <?php foreach ($upcoming as $meeting): ?>
                <article class="meeting-row">
                    <div class="date-tile"><strong><?= h(date('d', strtotime($meeting['tanggal_rapat']))) ?></strong><span><?= h(date('M', strtotime($meeting['tanggal_rapat']))) ?></span></div>
                    <div class="min-w-0 flex-1"><a class="item-title" href="<?= h(app_url('/modules/jadwal/index.php')) ?>"><?= h($meeting['judul_rapat']) ?></a><div class="text-sm text-slate-500"><?= h(format_date($meeting['tanggal_rapat'])) ?> · <?= h(substr($meeting['waktu_mulai'], 0, 5)) ?> · <?= h($meeting['lokasi']) ?></div></div>
                    <span class="badge"><?= h(category_label($meeting['kategori_unit'])) ?></span>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
    <section class="lg:col-span-5" id="tugas">
        <div class="panel">
            <div class="panel-heading"><h2>Tindak Lanjut</h2><a href="<?= h(app_url('/modules/notulensi/index.php')) ?>">Lihat notulensi</a></div>
            <?php if (!$tasks): ?><p class="empty-state">Tidak ada tugas terbuka. Bagus!</p><?php endif; ?>
            <?php foreach ($tasks as $task): ?>
                <article class="task-row"><span class="task-dot"></span><div><strong><?= h($task['deskripsi_tugas']) ?></strong><div class="text-sm text-slate-500"><?= h($task['judul_rapat']) ?> · <?= h(category_label($task['kategori_unit'])) ?></div><div class="text-sm">Tenggat <?= h(format_date($task['tenggat_waktu'])) ?> · <?= h(status_label($task['status_tugas'])) ?></div></div></article>
            <?php endforeach; ?>
        </div>
    </section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
