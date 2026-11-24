<?php
defined('KEUANGAN_PARTIAL') || exit; // tidak boleh dibuka langsung lewat URL

/**
 * Variabel berikut disediakan oleh modules/keuangan/index.php.
 *
 * @var array<int, array<string, mixed>> $recent
 * @var int                              $totalTransactions
 * @var int                              $totalPages
 * @var int                              $page
 * @var int                              $offset
 * @var string                           $q
 * @var Closure                          $pageUrl
 * @var array<int, int|string>           $pageItems
 */
?>
<?php if ($totalTransactions > 0): ?>
    <p class="mb-2 text-sm text-slate-500">
        Menampilkan <?= h(number_format($offset + 1, 0, ',', '.')) ?>&ndash;<?= h(number_format($offset + count($recent), 0, ',', '.')) ?>
        dari <?= h(number_format($totalTransactions, 0, ',', '.')) ?> transaksi<?php if ($q !== ''): ?>
        untuk &ldquo;<?= h($q) ?>&rdquo;<?php endif; ?>
    </p>
<?php endif; ?>

<div class="table-container">
    <table class="data-table">
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>Keterangan</th>
                <th>Kategori</th>
                <th>Pos</th>
                <th class="text-right">Jumlah</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($recent as $row): ?>
                <tr>
                    <td class="whitespace-nowrap"><?= h(format_date($row['tanggal'])) ?></td>
                    <td><?= h($row['keterangan'] ?? '-') ?></td>
                    <td><span class="badge"><?= h($row['kategori']) ?></span></td>
                    <td><?= h(pos_label($row['pos'])) ?></td>
                    <td class="whitespace-nowrap text-right <?= $row['jenis'] === 'pemasukan' ? 'text-green-800' : 'text-red-800' ?>">
                        <?= $row['jenis'] === 'pemasukan' ? '+' : '-' ?> <?= h(format_rupiah($row['jumlah'])) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$recent): ?>
                <tr>
                    <td colspan="5" class="py-4 text-center text-slate-500">
                        <?php if ($q !== ''): ?>Tidak ada transaksi yang cocok dengan &ldquo;<?= h($q) ?>&rdquo;.<?php else: ?>Tidak ada transaksi pada filter ini.<?php endif; ?>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($totalPages > 1): ?>
    <nav class="mt-4 flex flex-wrap items-center justify-center gap-2" aria-label="Halaman transaksi">
        <?php if ($page > 1): ?>
            <a class="button button-secondary button-sm" href="<?= h($pageUrl($page - 1)) ?>">&lsaquo; Sebelumnya</a>
        <?php else: ?>
            <span class="button button-secondary button-sm" style="opacity:.5;pointer-events:none" aria-disabled="true">&lsaquo; Sebelumnya</span>
        <?php endif; ?>

        <?php foreach ($pageItems as $item): ?>
            <?php if ($item === '...'): ?>
                <span class="text-sm text-slate-500" aria-hidden="true">&hellip;</span>
            <?php elseif ($item === $page): ?>
                <span class="button button-primary button-sm" aria-current="page"><?= (int) $item ?></span>
            <?php else: ?>
                <a class="button button-secondary button-sm" href="<?= h($pageUrl($item)) ?>"><?= (int) $item ?></a>
            <?php endif; ?>
        <?php endforeach; ?>

        <?php if ($page < $totalPages): ?>
            <a class="button button-secondary button-sm" href="<?= h($pageUrl($page + 1)) ?>">Berikutnya &rsaquo;</a>
        <?php else: ?>
            <span class="button button-secondary button-sm" style="opacity:.5;pointer-events:none" aria-disabled="true">Berikutnya &rsaquo;</span>
        <?php endif; ?>
    </nav>
<?php endif; ?>