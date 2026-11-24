<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/layout.php';

if (current_user()) {
    redirect('/index.php');
}

$error = null;
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = trim((string) ($_POST['email'] ?? ''));
    $pass  = (string) ($_POST['password'] ?? '');

    $st = db()->prepare('SELECT * FROM users WHERE email = ?');
    $st->execute([$email]);
    $u = $st->fetch();

    if ($u && password_verify($pass, $u['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user'] = ['id' => (int) $u['id'], 'name' => $u['name'], 'role' => $u['role']];
        flash('Selamat datang, ' . $u['name'] . '.', 'success');
        redirect($u['role'] === 'admin' ? '/admin/index.php' : '/index.php');
    }
    $error = 'Email atau password salah.';
}

page_header('Masuk', 'login');
?>
<h1>Masuk</h1>
<?php if ($error): ?><div class="flash error"><?= e($error) ?></div><?php endif; ?>
<form class="form" method="post" action="/login.php">
  <?= csrf_field() ?>
  <label>Email
    <input type="email" name="email" value="<?= e($email) ?>" required>
  </label>
  <label>Password
    <input type="password" name="password" required>
  </label>
  <button type="submit">Masuk</button>
  <p class="muted">Belum punya akun? <a href="/register.php">Daftar di sini</a>.</p>
</form>
<?php page_footer();
