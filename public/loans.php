<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/layout.php';

$user = require_login();
$pdo  = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'borrow') {
            $bookId = (int) ($_POST['book_id'] ?? 0);
            $pdo->beginTransaction();

            $st = $pdo->prepare('SELECT COUNT(*) FROM loans WHERE user_id = ? AND book_id = ? AND returned_at IS NULL');
            $st->execute([$user['id'], $bookId]);

            if ((int) $st->fetchColumn() > 0) {
                $pdo->rollBack();
                flash('Buku ini masih Anda pinjam.', 'error');
            } else {
                $st = $pdo->prepare('UPDATE books SET stock = stock - 1 WHERE id = ? AND stock > 0');
                $st->execute([$bookId]);
                if ($st->rowCount() === 0) {
                    $pdo->rollBack();
                    flash('Stok buku habis atau buku tidak ditemukan.', 'error');
                } else {
                    $st = $pdo->prepare(
                        'INSERT INTO loans (user_id, book_id, borrowed_at, due_date)
                         VALUES (?, ?, NOW(), DATE_ADD(CURDATE(), INTERVAL 7 DAY))'
                    );
                    $st->execute([$user['id'], $bookId]);
                    $pdo->commit();
                    flash('Buku berhasil dipinjam. Batas pengembalian 7 hari.', 'success');
                }
            }
        } elseif ($action === 'return') {
            $loanId = (int) ($_POST['loan_id'] ?? 0);
            $pdo->beginTransaction();

            $st = $pdo->prepare('SELECT book_id FROM loans WHERE id = ? AND user_id = ? AND returned_at IS NULL FOR UPDATE');
            $st->execute([$loanId, $user['id']]);
            $loan = $st->fetch();

            if (!$loan) {
                $pdo->rollBack();
                flash('Data pinjaman tidak ditemukan.', 'error');
            } else {
                $pdo->prepare('UPDATE loans SET returned_at = NOW() WHERE id = ?')->execute([$loanId]);
                $pdo->prepare('UPDATE books SET stock = stock + 1 WHERE id = ?')->execute([$loan['book_id']]);
                $pdo->commit();
                flash('Buku berhasil dikembalikan.', 'success');
            }
        }
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('Terjadi kesalahan saat memproses permintaan.', 'error');
    }
    redirect('/loans.php');
}

$st = $pdo->prepare(
    'SELECT l.id, l.borrowed_at, l.due_date, l.returned_at, b.title, b.author
     FROM loans l JOIN books b ON b.id = l.book_id
     WHERE l.user_id = ?
     ORDER BY (l.returned_at IS NULL) DESC, l.borrowed_at DESC'
);
$st->execute([$user['id']]);
$loans = $st->fetchAll();

page_header('Pinjaman Saya', 'loans');
?>
<h1>Pinjaman Saya</h1>
<?php if (!$loans): ?>
  <p>Anda belum pernah meminjam buku. <a href="/index.php">Lihat katalog</a>.</p>
<?php else: ?>
<div class="scroll">
<table>
  <thead>
    <tr><th>Judul</th><th>Penulis</th><th>Dipinjam</th><th>Batas kembali</th><th>Status</th><th></th></tr>
  </thead>
  <tbody>
  <?php foreach ($loans as $l): ?>
    <tr>
      <td><?= e($l['title']) ?></td>
      <td><?= e($l['author']) ?></td>
      <td><?= e(substr($l['borrowed_at'], 0, 10)) ?></td>
      <td><?= e($l['due_date']) ?></td>
      <td>
        <?php if ($l['returned_at']): ?>
          <span class="tag ok">Dikembalikan</span>
        <?php elseif ($l['due_date'] < date('Y-m-d')): ?>
          <span class="tag late">Terlambat</span>
        <?php else: ?>
          <span class="tag">Dipinjam</span>
        <?php endif; ?>
      </td>
      <td>
        <?php if (!$l['returned_at']): ?>
          <form method="post" action="/loans.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="return">
            <input type="hidden" name="loan_id" value="<?= (int) $l['id'] ?>">
            <button type="submit">Kembalikan</button>
          </form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
<?php page_footer();
