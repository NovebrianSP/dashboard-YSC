<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_admin();
require_once __DIR__ . '/../../config/database.php';
$statement = $pdo->query('SELECT id_user,nama_lengkap,jabatan,email,role,created_at FROM users ORDER BY nama_lengkap');
$users = $statement->fetchAll();
$pageTitle = 'Manajemen Pengguna';
require __DIR__ . '/../../includes/header.php';
?>
<div class="page-heading"><div><p class="eyebrow">ADMINISTRASI</p><h1>Manajemen Pengguna</h1><p class="text-slate-500">Kelola akun dan hak akses pengurus.</p></div><a class="button button-primary" href="<?= h(app_url('/modules/users/tambah.php')) ?>">+ Tambah Pengguna</a></div>
<div class="table-container"><table class="data-table"><thead><tr><th>Nama</th><th>Jabatan</th><th>Email</th><th>Role</th><th>Aksi</th></tr></thead><tbody>
<?php foreach ($users as $user): ?><tr><td><strong><?= h($user['nama_lengkap']) ?></strong></td><td><?= h($user['jabatan']) ?></td><td><?= h($user['email']) ?></td><td><span class="badge"><?= h($user['role']) ?></span></td><td><a class="button button-secondary button-sm" href="<?= h(app_url('/modules/users/edit.php')) ?>?id=<?= (int) $user['id_user'] ?>">Edit</a></td></tr><?php endforeach; ?>
<?php if (!$users): ?><tr><td colspan="5" class="py-4 text-center text-slate-500">Belum ada pengguna.</td></tr><?php endif; ?>
</tbody></table></div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
