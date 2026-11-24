<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/layout.php';

$q       = trim((string) ($_GET['q'] ?? ''));
$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 8;
$offset  = ($page - 1) * $perPage;
$like    = '%' . $q . '%';
$where   = 'title LIKE :q1 OR author LIKE :q2 OR category LIKE :q3';

$st = db()->prepare("SELECT COUNT(*) FROM books WHERE $where");
$st->execute([':q1' => $like, ':q2' => $like, ':q3' => $like]);
$total = (int) $st->fetchColumn();
$pages = max(1, (int) ceil($total / $perPage));

$st = db()->prepare("SELECT * FROM books WHERE $where ORDER BY title LIMIT :lim OFFSET :off");
$st->bindValue(':q1', $like);
$st->bindValue(':q2', $like);
$st->bindValue(':q3', $like);
$st->bindValue(':lim', $perPage, PDO::PARAM_INT);
$st->bindValue(':off', $offset, PDO::PARAM_INT);
$st->execute();
$books = $st->fetchAll();

$user = current_user();

page_header('Katalog Buku', 'katalog');
?>
<h1>Katalog Buku</h1>
<form class="search" method="get" action="/index.php">
  <input type="text" name="q" value="<?= e($q) ?>" placeholder="Cari judul, penulis, atau kategori...">
  <button type="submit">Cari</button>
</form>
<p class="muted"><?= $total ?> buku ditemukan.</p>

<div class="grid">
<?php foreach ($books as $b): ?>
  <?php $img = cover_url($b['cover_file']); ?>
  <article class="card">
    <?php if ($img): ?>
      <img class="cover" src="<?= e($img) ?>" alt="Cover <?= e($b['title']) ?>" loading="lazy">
    <?php else: ?>
      <div class="cover empty">Tanpa cover</div>
    <?php endif; ?>
    <h3><?= e($b['title']) ?></h3>
    <p class="muted"><?= e($b['author']) ?> &middot; <?= e((string) $b['year']) ?></p>
    <p><span class="tag"><?= e($b['category']) ?></span> Stok: <strong><?= (int) $b['stock'] ?></strong></p>
    <?php if ($user && (int) $b['stock'] > 0): ?>
      <form method="post" action="/loans.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="borrow">
        <input type="hidden" name="book_id" value="<?= (int) $b['id'] ?>">
        <button type="submit">Pinjam</button>
      </form>
    <?php elseif (!$user): ?>
      <a class="btn secondary" href="/login.php">Masuk untuk meminjam</a>
    <?php else: ?>
      <span class="muted">Stok habis</span>
    <?php endif; ?>
  </article>
<?php endforeach; ?>
<?php if (!$books): ?>
  <p>Tidak ada buku yang cocok.</p>
<?php endif; ?>
</div>

<?php if ($pages > 1): ?>
<nav class="pager">
  <?php for ($i = 1; $i <= $pages; $i++): ?>
    <a class="<?= $i === $page ? 'active' : '' ?>" href="/index.php?q=<?= urlencode($q) ?>&amp;page=<?= $i ?>"><?= $i ?></a>
  <?php endfor; ?>
</nav>
<?php endif; ?>
<?php page_footer();
