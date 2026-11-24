<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/layout.php';

if (current_user()) {
    redirect('/index.php');
}

$errors = [];
$name = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name    = trim((string) ($_POST['name'] ?? ''));
    $email   = trim((string) ($_POST['email'] ?? ''));
    $pass    = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm'] ?? '');

    if ($name === '' || mb_strlen($name) > 100) {
        $errors[] = 'Nama wajib diisi (maksimal 100 karakter).';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format email tidak valid.';
    }
    if (strlen($pass) < 6) {
        $errors[] = 'Password minimal 6 karakter.';
    }
    if ($pass !== $confirm) {
        $errors[] = 'Konfirmasi password tidak sama.';
    }

    if (!$errors) {
        $st = db()->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
        $st->execute([$email]);
        if ((int) $st->fetchColumn() > 0) {
            $errors[] = 'Email sudah terdaftar.';
        }
    }

    if (!$errors) {
        $st = db()->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)');
        $st->execute([$name, $email, password_hash($pass, PASSWORD_DEFAULT), 'member']);
        flash('Pendaftaran berhasil. Silakan masuk.', 'success');
        redirect('/login.php');
    }
}

page_header('Daftar', 'register');
?>
<h1>Daftar Anggota</h1>
<?php foreach ($errors as $err): ?>
  <div class="flash error"><?= e($err) ?></div>
<?php endforeach; ?>
<form class="form" method="post" action="/register.php">
  <?= csrf_field() ?>
  <label>Nama lengkap
    <input type="text" name="name" value="<?= e($name) ?>" required>
  </label>
  <label>Email
    <input type="email" name="email" value="<?= e($email) ?>" required>
  </label>
  <label>Password
    <input type="password" name="password" minlength="6" required>
  </label>
  <label>Ulangi password
    <input type="password" name="confirm" minlength="6" required>
  </label>
  <button type="submit">Daftar</button>
</form>
<?php page_footer();
