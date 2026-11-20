<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_admin();
require_once __DIR__ . '/../../config/database.php';
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$statement = $pdo->prepare('SELECT id_user,nama_lengkap,jabatan,email,role FROM users WHERE id_user=:id');
$statement->execute(['id'=>$id ?: 0]);
$user = $statement->fetch();
if (!$user) { http_response_code(404); exit('Pengguna tidak ditemukan.'); }
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $wasAdmin = $user['role'] === 'admin';
    foreach (['nama_lengkap','jabatan','email','role'] as $key) $user[$key] = trim((string) ($_POST[$key] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    if ($user['nama_lengkap'] === '' || $user['jabatan'] === '' || !filter_var($user['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Nama, jabatan, dan email valid wajib diisi.';
    if (!in_array($user['role'], ['admin','pimpinan','unit_sdit','unit_smpit'], true)) $errors[] = 'Role tidak valid.';
    if ($password !== '' && strlen($password) < 10) $errors[] = 'Kata sandi baru minimal 10 karakter.';
    $check = $pdo->prepare('SELECT 1 FROM users WHERE email=:email AND id_user<>:id');
    $check->execute(['email'=>$user['email'],'id'=>$id ?: 0]);
    if ($check->fetchColumn()) $errors[] = 'Email tersebut sudah digunakan.';
    if ($wasAdmin && $user['role'] !== 'admin') {
        if ((int) current_user()['id_user'] === (int) $id) {
            $errors[] = 'Admin tidak dapat mengubah role akunnya sendiri.';
        }
        $adminCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
        if ($adminCount <= 1) {
            $errors[] = 'Sistem harus memiliki setidaknya satu akun admin.';
        }
    }
    if (!$errors) {
        if ($password !== '') {
            $statement = $pdo->prepare('UPDATE users SET nama_lengkap=:nama,jabatan=:jabatan,email=:email,role=:role,password=:password WHERE id_user=:id');
            $statement->execute(['nama'=>$user['nama_lengkap'],'jabatan'=>$user['jabatan'],'email'=>$user['email'],'role'=>$user['role'],'password'=>password_hash($password,PASSWORD_DEFAULT),'id'=>$id]);
        } else {
            $statement = $pdo->prepare('UPDATE users SET nama_lengkap=:nama,jabatan=:jabatan,email=:email,role=:role WHERE id_user=:id');
            $statement->execute(['nama'=>$user['nama_lengkap'],'jabatan'=>$user['jabatan'],'email'=>$user['email'],'role'=>$user['role'],'id'=>$id]);
        }
        if ((int) current_user()['id_user'] === (int) $id) {
            $_SESSION['user']['nama_lengkap'] = $user['nama_lengkap'];
            $_SESSION['user']['jabatan'] = $user['jabatan'];
            $_SESSION['user']['email'] = $user['email'];
            $_SESSION['user']['role'] = $user['role'];
        }
        flash('success', 'Akun pengguna berhasil diperbarui.');
        redirect('/modules/users/index.php');
    }
}
$pageTitle = 'Edit Pengguna';
require __DIR__ . '/../../includes/header.php';
?>
<div class="page-heading"><div><p class="eyebrow">ADMINISTRASI</p><h1>Edit Pengguna</h1></div></div>
<?php if ($errors): ?><div class="alert alert-danger" role="alert"><?= h(implode(' ', $errors)) ?></div><?php endif; ?>
<form method="post" class="panel form-panel"><?= csrf_field() ?>
    <div class="mb-4"><label class="field-label">Nama lengkap</label><input class="field-control" name="nama_lengkap" maxlength="100" value="<?= h($user['nama_lengkap']) ?>" required></div>
    <div class="mb-4"><label class="field-label">Jabatan</label><input class="field-control" name="jabatan" maxlength="100" value="<?= h($user['jabatan']) ?>" required></div>
    <div class="mb-4"><label class="field-label">Email</label><input class="field-control" type="email" name="email" maxlength="100" value="<?= h($user['email']) ?>" required></div>
    <div class="mb-4"><label class="field-label">Role</label><select class="field-control" name="role"><?php foreach (['admin','pimpinan','unit_sdit','unit_smpit'] as $role): ?><option value="<?= h($role) ?>" <?= $user['role']===$role?'selected':'' ?>><?= h($role) ?></option><?php endforeach; ?></select></div>
    <div class="mb-5"><label class="field-label">Kata sandi baru (kosongkan jika tidak diubah)</label><input class="field-control" type="password" name="password" minlength="10" autocomplete="new-password"></div>
    <div class="flex flex-wrap gap-2"><button class="button button-primary">Simpan Perubahan</button><a class="button button-secondary" href="<?= h(app_url('/modules/users/index.php')) ?>">Batal</a></div>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
