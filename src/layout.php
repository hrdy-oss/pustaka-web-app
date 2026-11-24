<?php
// Kerangka halaman (header/footer) + info server yang melayani request.

function server_info(): array
{
    static $info = null;
    if ($info !== null) {
        return $info;
    }

    $cache = sys_get_temp_dir() . '/pustaka_server_info.json';
    if (is_readable($cache)) {
        $data = json_decode((string) file_get_contents($cache), true);
        if (is_array($data)) {
            return $info = $data;
        }
    }

    $info = ['hostname' => gethostname() ?: 'unknown', 'instance_id' => '-', 'az' => '-'];

    // Metadata EC2 (IMDSv2); jika gagal (mis. dijalankan lokal) pakai hostname saja.
    $ctx = stream_context_create(['http' => [
        'method'  => 'PUT',
        'header'  => "X-aws-ec2-metadata-token-ttl-seconds: 120\r\n",
        'timeout' => 1,
    ]]);
    $token = @file_get_contents('http://169.254.169.254/latest/api/token', false, $ctx);
    if ($token) {
        $ctx2 = stream_context_create(['http' => [
            'header'  => "X-aws-ec2-metadata-token: $token\r\n",
            'timeout' => 1,
        ]]);
        $id = @file_get_contents('http://169.254.169.254/latest/meta-data/instance-id', false, $ctx2);
        $az = @file_get_contents('http://169.254.169.254/latest/meta-data/placement/availability-zone', false, $ctx2);
        if ($id) {
            $info['instance_id'] = $id;
        }
        if ($az) {
            $info['az'] = $az;
        }
        @file_put_contents($cache, json_encode($info));
    }
    return $info;
}

function page_header(string $title, string $active = ''): void
{
    $user = current_user();
    $flash = flash();
    $nav = function (string $key, string $href, string $label) use ($active): string {
        $cls = $active === $key ? ' class="active"' : '';
        return '<a' . $cls . ' href="' . $href . '">' . e($label) . '</a>';
    };
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> | Pustaka Digital SMK</title>
<link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<header class="top">
  <div class="wrap bar">
    <a class="brand" href="/index.php">Pustaka Digital SMK</a>
    <nav>
      <?= $nav('katalog', '/index.php', 'Katalog') ?>
      <?php if ($user): ?>
        <?= $nav('loans', '/loans.php', 'Pinjaman Saya') ?>
        <?php if ($user['role'] === 'admin'): ?>
          <?= $nav('admin', '/admin/index.php', 'Admin') ?>
        <?php endif; ?>
        <form class="inline" method="post" action="/logout.php">
          <?= csrf_field() ?>
          <button class="link" type="submit">Keluar (<?= e($user['name']) ?>)</button>
        </form>
      <?php else: ?>
        <?= $nav('login', '/login.php', 'Masuk') ?>
        <?= $nav('register', '/register.php', 'Daftar') ?>
      <?php endif; ?>
    </nav>
  </div>
</header>
<main class="wrap">
<?php if ($flash): ?>
  <div class="flash <?= e($flash['type']) ?>"><?= e($flash['msg']) ?></div>
<?php endif; ?>
<?php
}

function page_footer(): void
{
    $s = server_info();
    ?>
</main>
<footer class="foot">
  <div class="wrap">
    Dilayani oleh server <strong><?= e($s['instance_id']) ?></strong>
    (<?= e($s['az']) ?>) &middot; hostname <code><?= e($s['hostname']) ?></code>
  </div>
</footer>
</body>
</html>
<?php
}
