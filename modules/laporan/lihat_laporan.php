<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/monthly_reports.php';
require_monthly_report_access();
require_once __DIR__ . '/../../config/database.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$statement = $pdo->prepare('SELECT nama_file, nama_asli, bulan FROM laporan_bulanan WHERE id_laporan = :id');
$statement->execute(['id' => $id ?: 0]);
$report = $statement->fetch();
if (!$report) {
    http_response_code(404);
    exit('Laporan bulanan tidak ditemukan.');
}

$filename = (string) $report['nama_file'];
if (basename($filename) !== $filename || !preg_match('/^[a-f0-9]{32}\.(doc|docx|pdf)$/', $filename)) {
    http_response_code(404);
    exit('File laporan tidak tersedia.');
}
$path = __DIR__ . '/../../uploads/laporan_bulanan/' . $filename;
if (!is_file($path)) {
    http_response_code(404);
    exit('File laporan tidak tersedia.');
}

$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$originalName = (string) $report['nama_asli'];
$originalName = preg_replace('/[\x00-\x1F\x7F]/', '', $originalName) ?? '';
if ($originalName === '') {
    $originalName = 'laporan.' . $extension;
}

if ($extension === 'pdf') {
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="laporan.pdf"; filename*=UTF-8\'\'' . rawurlencode($originalName));
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
}

$pageTitle = 'Pratinjau Laporan Bulanan';
require __DIR__ . '/../../includes/header.php';
?>
<div class="page-heading">
    <div>
        <p class="eyebrow">ARSIP LAPORAN BULANAN</p>
        <h1><?= h($originalName) ?></h1>
        <p class="text-slate-500">Bulan laporan: <?= h(format_date($report['bulan'])) ?></p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a class="button button-secondary" href="<?= h(app_url('/modules/laporan/index.php')) ?>">Kembali ke arsip</a>
        <a class="button button-primary" href="<?= h(app_url('/modules/laporan/unduh_laporan.php')) ?>?id=<?= (int) $id ?>">Unduh file</a>
    </div>
</div>
<?php if ($extension === 'docx'): ?>
    <section class="panel">
        <p id="docx-preview-status" class="alert alert-info" role="status">Memuat pratinjau dokumen...</p>
        <iframe
            id="docx-preview"
            class="hidden h-[80vh] min-h-[600px] max-h-[900px] w-full rounded-lg border border-slate-200 bg-white"
            title="<?= h('Pratinjau ' . $originalName) ?>"
            sandbox=""
            referrerpolicy="no-referrer"
        ></iframe>
        <noscript><p class="alert alert-warning">JavaScript harus diaktifkan untuk menampilkan pratinjau DOCX.</p></noscript>
    </section>
    <script src="<?= h(app_url('/assets/js/mammoth.browser.min.js')) ?>"></script>
    <script>
    (async function () {
        const status = document.querySelector('#docx-preview-status');
        const preview = document.querySelector('#docx-preview');
        const reportUrl = <?= json_encode(
            app_url('/modules/laporan/unduh_laporan.php') . '?id=' . (int) $id,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        ) ?>;

        try {
            const response = await fetch(reportUrl, { credentials: 'same-origin' });
            if (!response.ok) {
                throw new Error('Server gagal mengirim dokumen (' + response.status + ').');
            }
            const result = await window.mammoth.convertToHtml({
                arrayBuffer: await response.arrayBuffer()
            });
            const documentHtml = '<!doctype html><html lang="id"><head><meta charset="utf-8">'
                + '<meta name="viewport" content="width=device-width, initial-scale=1">'
                + '<meta http-equiv="Content-Security-Policy" content="default-src \'none\'; img-src data:; style-src \'unsafe-inline\';">'
                + '<style>body{max-width:900px;margin:0 auto;padding:32px;color:#1e293b;font:16px/1.65 system-ui,sans-serif}img{max-width:100%;height:auto}table{width:100%;border-collapse:collapse}td,th{border:1px solid #cbd5e1;padding:8px}blockquote{border-left:3px solid #cbd5e1;margin-left:0;padding-left:16px}h1,h2,h3{line-height:1.3}</style>'
                + '</head><body>' + result.value + '</body></html>';
            preview.srcdoc = documentHtml;
            preview.classList.remove('hidden');
            status.classList.add('hidden');
        } catch (error) {
            status.classList.remove('alert-info');
            status.classList.add('alert-danger');
            status.textContent = 'Pratinjau DOCX gagal dimuat. Gunakan tombol Unduh untuk membuka file.';
            console.error('DOCX preview failed:', error);
        }
    }());
    </script>
<?php else: ?>
    <div class="alert alert-warning" role="status">
        Pratinjau langsung belum tersedia untuk file DOC lama. Silakan unduh file untuk membukanya.
    </div>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
