<?php
require_once __DIR__ . '/../../src/bootstrap.php';
require_once __DIR__ . '/../../src/layout.php';

require_admin();
$pdo = db();

$stats = [
    'Buku'            => (int) $pdo->query('SELECT COUNT(*) FROM books')->fetchColumn(),
    'Anggota'         => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'member'")->fetchColumn(),
    'Sedang dipinjam' => (int) $pdo->query('SELECT COUNT(*) FROM loans WHERE returned_at IS NULL')->fetchColumn(),
];

$books = $pdo->query('SELECT * FROM books ORDER BY id DESC')->fetchAll();
$loans = $pdo->query(
    'SELECT l.borrowed_at, l.due_date, l.returned_at, u.name AS member, b.title
     FROM loans l
     JOIN users u ON u.id = l.user_id
     JOIN books b ON b.id = l.book_id
     ORDER BY l.id DESC LIMIT 10'
)->fetchAll();

page_header('Admin', 'admin');
?>
<h1>Dashboard Admin</h1>
<div class="stats">
<?php foreach ($stats as $label => $val): ?>
  <div class="stat"><strong><?= $val ?></strong><span><?= e($label) ?></span></div>
<?php endforeach; ?>
</div>

<div class="row-head">
  <h2>Daftar Buku</h2>
  <a class="btn" href="/admin/book_form.php">+ Tambah Buku</a>
</div>
<div class="scroll">
<table>
  <thead>
    <tr><th>ID</th><th>Judul</th><th>Penulis</th><th>Kategori</th><th>Stok</th><th>Cover</th><th></th></tr>
  </thead>
  <tbody>
  <?php foreach ($books as $b): ?>
    <tr>
      <td><?= (int) $b['id'] ?></td>
      <td><?= e($b['title']) ?></td>
      <td><?= e($b['author']) ?></td>
      <td><?= e($b['category']) ?></td>
      <td><?= (int) $b['stock'] ?></td>
      <td><?= e((string) $b['cover_file']) ?></td>
      <td class="actions">
        <a class="btn secondary" href="/admin/book_form.php?id=<?= (int) $b['id'] ?>">Ubah</a>
        <form method="post" action="/admin/book_delete.php" onsubmit="return confirm('Hapus buku ini?');">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
          <button class="danger" type="submit">Hapus</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>

<h2>10 Peminjaman Terakhir</h2>
<div class="scroll">
<table>
  <thead><tr><th>Anggota</th><th>Judul</th><th>Dipinjam</th><th>Batas kembali</th><th>Status</th></tr></thead>
  <tbody>
  <?php foreach ($loans as $l): ?>
    <tr>
      <td><?= e($l['member']) ?></td>
      <td><?= e($l['title']) ?></td>
      <td><?= e(substr($l['borrowed_at'], 0, 10)) ?></td>
      <td><?= e($l['due_date']) ?></td>
      <td><?= $l['returned_at'] ? 'Dikembalikan' : 'Dipinjam' ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$loans): ?><tr><td colspan="5">Belum ada peminjaman.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<?php page_footer();
