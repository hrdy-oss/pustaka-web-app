<?php
require_once __DIR__ . '/../../src/bootstrap.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
        db()->prepare('DELETE FROM books WHERE id = ?')->execute([$id]);
        flash('Buku dihapus.', 'success');
    }
}
redirect('/admin/index.php');
