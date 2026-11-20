<section class="panel mb-6">
    <h2 class="mb-3 text-lg font-bold text-slate-900">Pekerjaan Prioritas</h2>
    <div class="table-container">
        <table class="data-table dap-priority-table">
            <thead><tr><th class="w-20 text-center">NO</th><th>PEKERJAAN PRIORITAS</th></tr></thead>
            <tbody>
                <?php foreach ($dapPriorities as $index => $priority): ?>
                    <tr>
                        <td class="text-center font-medium"><?= $index + 1 ?></td>
                        <td><?= $priority !== '' ? h($priority) : '<span class="text-slate-400">—</span>' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
