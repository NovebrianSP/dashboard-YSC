<?php
require_once __DIR__ . '/../../../includes/auth_check.php';
require_admin();
require_once __DIR__ . '/../../../config/database.php';
$meetingId = filter_input(INPUT_GET, 'id_rapat', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'id_rapat', FILTER_VALIDATE_INT);
$statement = $pdo->prepare("SELECT j.* FROM jadwal_rapat j LEFT JOIN notulensi n ON n.id_rapat=j.id_rapat WHERE j.id_rapat=:id AND j.status_rapat='selesai' AND n.id_notulensi IS NULL");
$statement->execute(['id' => $meetingId ?: 0]);
$meeting = $statement->fetch();
if (!$meeting) { http_response_code(404); exit('Rapat tidak ditemukan, belum berstatus selesai, atau sudah memiliki notulensi.'); }
$errors = [];
$values = ['daftar_hadir' => '', 'isi_pembahasan' => '', 'keputusan_final' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    foreach ($values as $key => $_) $values[$key] = trim((string) ($_POST[$key] ?? ''));
    $values['isi_pembahasan'] = sanitize_rich_text($values['isi_pembahasan']);
    $plainDiscussion = trim(html_entity_decode(strip_tags($values['isi_pembahasan']), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($values['daftar_hadir'] === '' || $plainDiscussion === '' || $values['keputusan_final'] === '') $errors[] = 'Daftar hadir, pembahasan, dan keputusan wajib diisi.';
    $filename = null;
    try {
        if (!$errors) {
            $filename = upload_attachment($_FILES['lampiran'] ?? []);
            $pdo->beginTransaction();
            $statement = $pdo->prepare('INSERT INTO notulensi (id_rapat,daftar_hadir,isi_pembahasan,keputusan_final,file_lampiran,dibuat_oleh) VALUES (:id,:hadir,:pembahasan,:keputusan,:file,:user)');
            $statement->execute(['id'=>$meeting['id_rapat'],'hadir'=>$values['daftar_hadir'],'pembahasan'=>$values['isi_pembahasan'],'keputusan'=>$values['keputusan_final'],'file'=>$filename,'user'=>current_user()['id_user']]);
            $minuteId = (int) $pdo->lastInsertId();
            $pdo->commit();
            flash('success', 'Notulensi berhasil dibuat.');
            redirect('/modules/rapat/notulensi/detail.php?id=' . $minuteId);
        }
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($filename) delete_attachment($filename);
        if ($exception instanceof RuntimeException) $errors[] = $exception->getMessage();
        else throw $exception;
    }
}
$pageTitle = 'Buat Notulensi';
require __DIR__ . '/../../../includes/header.php';
?>
<div class="page-heading"><div><p class="eyebrow">DOKUMENTASI</p><h1>Buat Notulensi</h1><p class="text-slate-500"><?= h($meeting['judul_rapat']) ?> · <?= h(format_date($meeting['tanggal_rapat'])) ?></p></div></div>
<?php if ($errors): ?><div class="alert alert-danger" role="alert"><?= h(implode(' ', $errors)) ?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data" class="panel form-panel"><?= csrf_field() ?><input type="hidden" name="id_rapat" value="<?= (int) $meeting['id_rapat'] ?>">
    <div class="mb-4"><label class="field-label">Daftar peserta hadir</label><textarea class="field-control" name="daftar_hadir" rows="4" required><?= h($values['daftar_hadir']) ?></textarea></div>
    <div class="mb-4"><label class="field-label">Poin pembahasan rapat</label><div id="discussion-editor" class="rich-editor"></div><textarea class="hidden" id="discussion-content" name="isi_pembahasan"><?= h($values['isi_pembahasan']) ?></textarea><div class="field-note">Format teks dengan tebal, miring, dan daftar.</div></div>
    <div class="mb-4"><label class="field-label">Keputusan final</label><textarea class="field-control" name="keputusan_final" rows="5" required><?= h($values['keputusan_final']) ?></textarea></div>
    <div class="mb-5"><label class="field-label">Lampiran (opsional, PDF/JPG/PNG maks. 5 MB)</label><input class="field-control file:mr-3 file:rounded-md file:border-0 file:bg-blue-50 file:px-3 file:py-1 file:text-sm file:font-semibold file:text-blue-700 hover:file:bg-blue-100" type="file" name="lampiran" accept=".pdf,.jpg,.jpeg,.png"></div>
    <div class="flex flex-wrap gap-2"><button class="button button-primary">Simpan Notulensi</button><a class="button button-secondary" href="<?= h(app_url('/modules/rapat/index.php#arsip-notulensi')) ?>">Batal</a></div>
</form>
<script src="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.min.js"></script>
<script>
const discussionEditor = new Quill('#discussion-editor', {
    theme: 'snow',
    modules: { toolbar: [['bold', 'italic', 'underline'], [{ list: 'ordered' }, { list: 'bullet' }], ['clean']] }
});
discussionEditor.root.innerHTML = <?= json_encode($values['isi_pembahasan'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
document.querySelector('form.form-panel').addEventListener('submit', () => {
    document.querySelector('#discussion-content').value = discussionEditor.root.innerHTML;
});
</script>
<?php require __DIR__ . '/../../../includes/footer.php'; ?>
