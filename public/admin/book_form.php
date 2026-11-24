<?php
require_once __DIR__ . '/../../src/bootstrap.php';
require_once __DIR__ . '/../../src/layout.php';

require_admin();

$id   = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$book = [
    'title' => '', 'author' => '', 'category' => '',
    'year' => (int) date('Y'), 'stock' => 1, 'cover_file' => '', 'description' => '',
];

if ($id > 0) {
    $st = db()->prepare('SELECT * FROM books WHERE id = ?');
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) {
        http_response_code(404);
        exit('Buku tidak ditemukan.');
    }
    $book = $row;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $book['title']       = trim((string) ($_POST['title'] ?? ''));
    $book['author']      = trim((string) ($_POST['author'] ?? ''));
    $book['category']    = trim((string) ($_POST['category'] ?? ''));
    $book['year']        = (int) ($_POST['year'] ?? 0);
    $book['stock']       = (int) ($_POST['stock'] ?? 0);
    $book['cover_file']  = trim((string) ($_POST['cover_file'] ?? ''));
    $book['description'] = trim((string) ($_POST['description'] ?? ''));

    if ($book['title'] === '')    { $errors[] = 'Judul wajib diisi.'; }
    if ($book['author'] === '')   { $errors[] = 'Penulis wajib diisi.'; }
    if ($book['category'] === '') { $errors[] = 'Kategori wajib diisi.'; }
    if ($book['year'] < 1900 || $book['year'] > 2100) { $errors[] = 'Tahun tidak valid.'; }
    if ($book['stock'] < 0)       { $errors[] = 'Stok tidak boleh negatif.'; }
    if ($book['cover_file'] !== '' && !preg_match('/^[A-Za-z0-9._-]+$/', $book['cover_file'])) {
        $errors[] = 'Nama file cover hanya boleh huruf, angka, titik, minus, dan underscore.';
    }

    if (!$errors) {
        $cover = $book['cover_file'] === '' ? null : $book['cover_file'];
        $vals  = [
            $book['title'], $book['author'], $book['category'],
            $book['year'], $book['stock'], $cover, $book['description'],
        ];
        if ($id > 0) {
            $st = db()->prepare(
                'UPDATE books SET title=?, author=?, category=?, year=?, stock=?, cover_file=?, description=? WHERE id=?'
            );
            $st->execute(array_merge($vals, [$id]));
            flash('Buku berhasil diperbarui.', 'success');
        } else {
            $st = db()->prepare(
                'INSERT INTO books (title, author, category, year, stock, cover_file, description) VALUES (?,?,?,?,?,?,?)'
            );
            $st->execute($vals);
            flash('Buku berhasil ditambahkan.', 'success');
        }
        redirect('/admin/index.php');
    }
}

page_header($id > 0 ? 'Ubah Buku' : 'Tambah Buku', 'admin');
?>
<h1><?= $id > 0 ? 'Ubah Buku' : 'Tambah Buku' ?></h1>
<?php foreach ($errors as $err): ?>
  <div class="flash error"><?= e($err) ?></div>
<?php endforeach; ?>
<form class="form" method="post" action="/admin/book_form.php<?= $id > 0 ? '?id=' . $id : '' ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= $id ?>">
  <label>Judul <input type="text" name="title" value="<?= e($book['title']) ?>" required></label>
  <label>Penulis <input type="text" name="author" value="<?= e($book['author']) ?>" required></label>
  <label>Kategori <input type="text" name="category" value="<?= e($book['category']) ?>" required></label>
  <label>Tahun terbit <input type="number" name="year" value="<?= (int) $book['year'] ?>" required></label>
  <label>Stok <input type="number" name="stock" min="0" value="<?= (int) $book['stock'] ?>" required></label>
  <label>Nama file cover di S3 (folder covers/)
    <input type="text" name="cover_file" value="<?= e((string) $book['cover_file']) ?>" placeholder="cover-01.png">
  </label>
  <label>Deskripsi <textarea name="description" rows="4"><?= e((string) $book['description']) ?></textarea></label>
  <button type="submit">Simpan</button>
  <a class="btn secondary" href="/admin/index.php">Batal</a>
</form>
<?php page_footer();
