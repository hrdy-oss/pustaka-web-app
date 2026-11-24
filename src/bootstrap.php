<?php
// Dimuat oleh semua halaman: env, session (disimpan di MySQL), dan fungsi bantu.
require_once __DIR__ . '/env.php';

/**
 * Session disimpan di tabel `sessions` (bukan file) supaya login tetap
 * berlaku walaupun request berikutnya dilayani EC2 yang berbeda oleh ALB.
 */
class DbSessionHandler implements SessionHandlerInterface
{
    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $st = db()->prepare('SELECT data FROM sessions WHERE id = ?');
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ? (string) $row['data'] : '';
    }

    public function write(string $id, string $data): bool
    {
        $st = db()->prepare('REPLACE INTO sessions (id, data, updated_at) VALUES (?, ?, ?)');
        return $st->execute([$id, $data, time()]);
    }

    public function destroy(string $id): bool
    {
        $st = db()->prepare('DELETE FROM sessions WHERE id = ?');
        return $st->execute([$id]);
    }

    public function gc(int $max_lifetime): int|false
    {
        $st = db()->prepare('DELETE FROM sessions WHERE updated_at < ?');
        $st->execute([time() - $max_lifetime]);
        return $st->rowCount();
    }
}

session_set_save_handler(new DbSessionHandler(), true);
session_name('PUSTAKASESS');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function redirect(string $to)
{
    header('Location: ' . $to);
    exit;
}

function flash(?string $msg = null, string $type = 'info')
{
    if ($msg !== null) {
        $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    if (!hash_equals((string) ($_SESSION['csrf'] ?? ''), (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(419);
        exit('Sesi tidak valid. Silakan muat ulang halaman dan coba lagi.');
    }
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(): array
{
    $u = current_user();
    if (!$u) {
        flash('Silakan login terlebih dahulu.', 'error');
        redirect('/login.php');
    }
    return $u;
}

function require_admin(): array
{
    $u = require_login();
    if ($u['role'] !== 'admin') {
        http_response_code(403);
        exit('Akses ditolak. Halaman ini khusus admin.');
    }
    return $u;
}

/** URL publik cover di S3 (folder covers/ dibuka lewat bucket policy). */
function cover_url(?string $file): ?string
{
    if (!$file) {
        return null;
    }
    $base = rtrim((string) env('S3_BASE_URL', ''), '/');
    return $base === '' ? null : $base . '/' . rawurlencode($file);
}
