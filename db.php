<?php
/**
 * DEVICEGAME — Veritabanı & Oturum Çekirdeği
 * db.php
 * Her sayfanın en üstünde: require_once __DIR__.'/db.php';
 */
declare(strict_types=1);

// ——— Hata raporlama (canlıda display_errors kapalı olmalı) ———
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// ——— Oturum — güvenli çerez ———
if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
           || (($_SERVER['SERVER_PORT'] ?? '') === '443')
           || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ——— Ayarlar ———
define('DG_DAILY_LIMIT_DEFAULT', 5);
define('DG_UPLOAD_DIR', __DIR__ . '/uploads');
define('DG_UPLOAD_URL', 'uploads/');

// ——— DB Bağlantısı (PDO) ———
// Ortam değişkenlerinden oku, yoksa varsayılanlara düş
$db_host = getenv('DB_HOST') ?: '127.0.0.1';
$db_name = getenv('DB_NAME') ?: 'devicegame';
$db_user = getenv('DB_USER') ?: 'root';
$db_pass = getenv('DB_PASS') ?: '';
$db_port = getenv('DB_PORT') ?: '3306';
$db_charset = 'utf8mb4';

$dsn = "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset={$db_charset}";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
    // Türkçe için collation garantisi
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
} catch (PDOException $e) {
    // Kurulum aşamasında DB yoksa nazik hata
    http_response_code(500);
    // Logla, kullanıcıya genel mesaj göster
    error_log('[DB] ' . $e->getMessage());
    // Eğer kurulum sihirbazı istenirse yönlendirilebilir
    die('<!doctype html><meta charset="utf-8"><body style="background:#0a0e1a;color:#e8ebe4;font-family:system-ui;padding:2rem"><h1>Veritabanı bağlantısı kurulamadı</h1><p>Lütfen <code>db.php</code> içindeki DB bilgilerini ve <code>schema.sql</code> kurulumunu kontrol edin.</p><p style="color:#93a89e">Hata kodu: DB_CONN_FAILED</p><pre style="background:#112a21;padding:1rem;border-radius:8px;overflow:auto">'.htmlspecialchars($e->getMessage()).'</pre></body>');
}

// ——— Yardımcılar ———
if (!function_exists('e')) {
    function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="'.e(csrf_token()).'">';
}
function csrf_verify(?string $token): bool {
    return !empty($_SESSION['csrf']) && !empty($token) && hash_equals($_SESSION['csrf'], (string)$token);
}

function is_logged_in(): bool { return !empty($_SESSION['user_id']); }
function is_admin(): bool     { return !empty($_SESSION['is_admin']) && (int)$_SESSION['is_admin'] === 1; }
function current_user_id(): ?int { return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null; }

function require_login(): void {
    if (!is_logged_in()) {
        $_SESSION['flash_error'] = 'Devam etmek için giriş yapmalısın.';
        header('Location: login.php'); exit;
    }
}
function require_admin(): void {
    require_login();
    if (!is_admin()) { http_response_code(403); die('<h1>403 — Yetkisiz erişim</h1><p>Bu alana sadece yöneticiler girebilir.</p><a href="index.php">Ana sayfaya dön</a>'); }
}

/** Güncel kullanıcıyı DB'den çek (bakiye güncel olsun) */
function current_user(PDO $pdo): ?array {
    if (!is_logged_in()) return null;
    $st = $pdo->prepare("SELECT id,username,email,dcoin_balance,is_admin,is_banned,created_at FROM users WHERE id=?");
    $st->execute([$_SESSION['user_id']]);
    $u = $st->fetch();
    if ($u) {
        // Session bakiyesini senkron tut
        $_SESSION['dcoin_balance'] = (int)$u['dcoin_balance'];
        $_SESSION['is_admin'] = (int)$u['is_admin'];
        if ((int)$u['is_banned'] === 1) {
            // Banlı kullanıcı oturumunu düşür
            session_destroy();
            header('Location: login.php?banned=1'); exit;
        }
    }
    return $u ?: null;
}

/** Günlük izleme limitini settings tablosundan al */
function daily_watch_limit(PDO $pdo): int {
    try {
        $st = $pdo->query("SELECT svalue FROM settings WHERE skey='daily_watch_limit' LIMIT 1");
        $v = $st->fetchColumn();
        if ($v !== false && is_numeric($v)) return max(1, (int)$v);
    } catch (Throwable $e) {}
    return DG_DAILY_LIMIT_DEFAULT;
}

/** Kullanıcının bugün kaç video izlediği */
function today_watch_count(PDO $pdo, int $user_id): int {
    $st = $pdo->prepare("SELECT COUNT(*) FROM user_watch_logs WHERE user_id=? AND DATE(watched_at)=CURDATE()");
    $st->execute([$user_id]);
    return (int)$st->fetchColumn();
}

/** Para formatı */
function tl_format($n): string {
    return number_format((float)$n, 2, ',', '.') . ' ₺';
}

/** Flash mesajlar */
function flash_set(string $msg, string $type='success'): void { $_SESSION['flash'] = ['msg'=>$msg,'type'=>$type]; }
function flash_get(): ?array { $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f; }
function flash_error_set(string $msg): void { $_SESSION['flash_error'] = $msg; }
function flash_error_get(): ?string { $m = $_SESSION['flash_error'] ?? null; unset($_SESSION['flash_error']); return $m; }

// ——— Yükleme klasörü ———
if (!is_dir(DG_UPLOAD_DIR)) @mkdir(DG_UPLOAD_DIR, 0775, true);
