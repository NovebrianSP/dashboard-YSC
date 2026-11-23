<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_admin();
require_once __DIR__ . '/../../config/database.php';
$values = ['nama_lengkap'=>'','jabatan'=>'','email'=>'','role'=>'unit_sdit'];
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    foreach ($values as $key => $_) $values[$key] = trim((string) ($_POST[$key] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    if ($values['nama_lengkap'] === '' || $values['jabatan'] === '' || !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Nama, jabatan, dan email valid wajib diisi.';
    if (strlen($password) < 7) $errors[] = 'Kata sandi minimal 7 karakter.';
    if (!in_array($values['role'], ['admin','pimpinan','unit_sdit','unit_smpit'], true)) $errors[] = 'Role tidak valid.';
    if (!$errors) {
        $check = $pdo->prepare('SELECT 1 FROM users WHERE email=:email');
        $check->execute(['email'=>$values['email']]);
        if ($check->fetchColumn()) $errors[] = 'Email tersebut sudah digunakan.';
    }
    if (!$errors) {
        $statement = $pdo->prepare('INSERT INTO users (nama_lengkap,jabatan,email,password,role) VALUES (:nama,:jabatan,:email,:password,:role)');
        $statement->execute(['nama'=>$values['nama_lengkap'],'jabatan'=>$values['jabatan'],'email'=>$values['email'],'password'=>password_hash($password,PASSWORD_DEFAULT),'role'=>$values['role']]);
        flash('success', 'Akun pengguna berhasil dibuat.');
        redirect('/modules/users/index.php');
    }
}
$pageTitle = 'Tambah Pengguna';
require __DIR__ . '/../../includes/header.php';
?>
<div class="page-heading"><div><p class="eyebrow">ADMINISTRASI</p><h1>Tambah Pengguna</h1></div></div>
<?php if ($errors): ?><div class="alert alert-danger" role="alert"><?= h(implode(' ', $errors)) ?></div><?php endif; ?>
<form method="post" class="panel form-panel"><?= csrf_field() ?>
    <div class="mb-4"><label class="field-label">Nama lengkap</label><input class="field-control" name="nama_lengkap" maxlength="100" value="<?= h($values['nama_lengkap']) ?>" required></div>
    <div class="mb-4"><label class="field-label">Jabatan</label><input class="field-control" name="jabatan" maxlength="100" value="<?= h($values['jabatan']) ?>" required></div>
    <div class="mb-4"><label class="field-label">Email</label><input class="field-control" type="email" name="email" maxlength="100" value="<?= h($values['email']) ?>" required></div>
    <div class="mb-4"><label class="field-label">Role</label><select class="field-control" name="role"><?php foreach (['admin','pimpinan','unit_sdit','unit_smpit'] as $role): ?><option value="<?= h($role) ?>" <?= $values['role']===$role?'selected':'' ?>><?= h($role) ?></option><?php endforeach; ?></select></div>
    <div class="mb-5"><label class="field-label">Kata sandi awal</label><input class="field-control" type="password" name="password" minlength="7" autocomplete="new-password" required></div>
    <div class="flex flex-wrap gap-2"><button class="button button-primary">Simpan Akun</button><a class="button button-secondary" href="<?= h(app_url('/modules/users/index.php')) ?>">Batal</a></div>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
