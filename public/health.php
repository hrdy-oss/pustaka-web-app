<?php
// Endpoint health check untuk Target Group ALB.
// /health.php        -> cek ringan (Apache + PHP hidup)
// /health.php?deep=1 -> cek koneksi database juga (untuk uji manual)
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

if (isset($_GET['deep'])) {
    require_once __DIR__ . '/../src/env.php';
    try {
        db()->query('SELECT 1');
        echo "OK (database terhubung)\n";
    } catch (Throwable $e) {
        http_response_code(503);
        echo "ERROR: database tidak terhubung\n";
    }
    exit;
}

echo "OK\n";
