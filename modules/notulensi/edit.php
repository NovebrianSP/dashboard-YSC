<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_admin();
require_once __DIR__ . '/../../config/database.php';
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$statement = $pdo->prepare('SELECT n.*,j.judul_rapat,j.tanggal_rapat FROM notulensi n JOIN jadwal_rapat j ON j.id_rapat=n.id_rapat WHERE n.id_notulensi=:id');
$statement->execute(['id' => $id ?: 0]);
$minute = $statement->fetch();
if (!$minute) { http_response_code(404); exit('Notulensi tidak ditemukan.'); }
$errors = [];
$newFile = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    foreach (['daftar_hadir','isi_pembahasan','keputusan_final'] as $key) $minute[$key] = trim((string) ($_POST[$key] ?? ''));
    $minute['isi_pembahasan'] = sanitize_rich_text($minute['isi_pembahasan']);
    $plainDiscussion = trim(html_entity_decode(strip_tags($minute['isi_pembahasan']), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($minute['daftar_hadir'] === '' || $plainDiscussion === '' || $minute['keputusan_final'] === '') $errors[] = 'Semua bagian notulensi wajib diisi.';
    try {
        if (!$errors) {
            $newFile = upload_attachment($_FILES['lampiran'] ?? []);
            $pdo->beginTransaction();
            $filename = $newFile ?: $minute['file_lampiran'];
            if (!empty($_POST['hapus_lampiran']) && !$newFile) $filename = null;
            $statement = $pdo->prepare('UPDATE notulensi SET daftar_hadir=:hadir,isi_pembahasan=:pembahasan,keputusan_final=:keputusan,file_lampiran=:file WHERE id_notulensi=:id');
            $statement->execute(['hadir'=>$minute['daftar_hadir'],'pembahasan'=>$minute['isi_pembahasan'],'keputusan'=>$minute['keputusan_final'],'file'=>$filename,'id'=>$id]);
            $pdo->commit();
            if ($minute['file_lampiran'] && $filename !== $minute['file_lampiran']) delete_attachment($minute['file_lampiran']);
            flash('success', 'Notulensi berhasil diperbarui.');
            redirect('/modules/notulensi/detail.php?id=' . $id);
        }
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($newFile) delete_attachment($newFile);
        if ($exception instanceof RuntimeException) $errors[] = $exception->getMessage();
        else throw $exception;
    }
}
$pageTitle = 'Edit Notulensi';
require __DIR__ . '/../../includes/header.php';
?>
<div class="page-heading"><div><p class="eyebrow">DOKUMENTASI</p><h1>Edit Notulensi</h1><p class="text-slate-500"><?= h($minute['judul_rapat']) ?> · <?= h(format_date($minute['tanggal_rapat'])) ?></p></div></div>
<?php if ($errors): ?><div class="alert alert-danger" role="alert"><?= h(implode(' ', $errors)) ?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data" class="panel form-panel"><?= csrf_field() ?>
    <div class="mb-4"><label class="field-label">Daftar peserta hadir</label><textarea class="field-control" name="daftar_hadir" rows="4" required><?= h($minute['daftar_hadir']) ?></textarea></div>
    <div class="mb-4"><label class="field-label">Poin pembahasan rapat</label><div id="discussion-editor" class="rich-editor"></div><textarea class="hidden" id="discussion-content" name="isi_pembahasan"></textarea></div>
    <div class="mb-4"><label class="field-label">Keputusan final</label><textarea class="field-control" name="keputusan_final" rows="5" required><?= h($minute['keputusan_final']) ?></textarea></div>
    <?php if ($minute['file_lampiran']): ?><div class="mb-4 flex items-center gap-2"><input class="h-4 w-4 rounded border-slate-300 text-blue-700 focus:ring-blue-500" type="checkbox" name="hapus_lampiran" id="hapus_lampiran" value="1"><label class="text-sm text-slate-700" for="hapus_lampiran">Hapus lampiran saat ini</label></div><?php endif; ?>
    <div class="mb-5"><label class="field-label">Ganti / tambah lampiran (PDF/JPG/PNG maks. 5 MB)</label><input class="field-control file:mr-3 file:rounded-md file:border-0 file:bg-blue-50 file:px-3 file:py-1 file:text-sm file:font-semibold file:text-blue-700 hover:file:bg-blue-100" type="file" name="lampiran" accept=".pdf,.jpg,.jpeg,.png"></div>
    <div class="flex flex-wrap gap-2"><button class="button button-primary">Simpan Perubahan</button><a class="button button-secondary" href="<?= h(app_url('/modules/notulensi/detail.php')) ?>?id=<?= (int) $id ?>">Batal</a></div>
</form>
<script src="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.min.js"></script>
<script>
const discussionEditor = new Quill('#discussion-editor', {
    theme: 'snow',
    modules: { toolbar: [['bold', 'italic', 'underline'], [{ list: 'ordered' }, { list: 'bullet' }], ['clean']] }
});
discussionEditor.root.innerHTML = <?= json_encode(sanitize_rich_text($minute['isi_pembahasan']), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
document.querySelector('form.form-panel').addEventListener('submit', () => {
    document.querySelector('#discussion-content').value = discussionEditor.root.innerHTML;
});
</script>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
