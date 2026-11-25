<?php
// Health check Target Group ALB: cek PHP, koneksi RDS, dan tabel yang
// dibutuhkan aplikasi agar instance yang belum siap tidak menerima traffic.
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../src/env.php';

try {
    $pdo = db();
    foreach (['books', 'users', 'loans', 'sessions'] as $table) {
        $pdo->query("SELECT 1 FROM `$table` LIMIT 0");
    }
    echo "OK\n";
} catch (Throwable $e) {
    http_response_code(503);
    echo "ERROR: aplikasi belum siap\n";
}
