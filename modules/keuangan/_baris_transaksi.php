<?php
defined('KEUANGAN_PARTIAL') || exit; // tidak boleh dibuka langsung lewat URL

/**
 * Variabel berikut disediakan oleh modules/keuangan/index.php.
 *
 * @var array<int, array<string, mixed>> $recent
 * @var int                              $offset
 * @var string                           $q
 */
?>
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
<?php if (!$recent && $offset === 0): ?>
    <tr>
        <td colspan="5" class="py-4 text-center text-slate-500">
            <?php if ($q !== ''): ?>Tidak ada transaksi yang cocok dengan &ldquo;<?= h($q) ?>&rdquo;.<?php else: ?>Tidak ada transaksi pada filter ini.<?php endif; ?>
        </td>
    </tr>
<?php endif; ?>