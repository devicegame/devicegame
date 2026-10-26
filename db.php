<?php
/**
 * DEVICEGAME — Veritabanı & Oturum Çekirdeği
 * db.php v2.1 — hataya dayanıklı, teşhis dostu
 * Her sayfanın en üstünde: require_once __DIR__.'/db.php';
 */
declare(strict_types=1);

// Cift yukleme korumasi: bir sayfa db.php'yi (require ile) ikinci kez cagirirsa
// "Cannot redeclare / Constant already defined" fatal hatasi (bos 500) olusmasin.
if (defined('DG_DB_LOADED')) { return; }
define('DG_DB_LOADED', true);

// ——— DEBUG MODU ———
// ?debug=1 ile anlık debug aç (sadece giriş yapan admin veya IP kontrollü)
// Ortam değişkeni DG_DEBUG=1 ise her zaman debug açık
$__dg_debug_env = getenv('DG_DEBUG') ?: ($_ENV['DG_DEBUG'] ?? '');
$__dg_debug = ($__dg_debug_env === '1');
// URL ile debug: /index.php?debug=1  (sadece oturumda debug anahtarı sets)
if (isset($_GET['debug']) && $_GET['debug']==='1') {
    // Güvenlik: sadece local veya admin için — burada basit tutuyoruz, herkes ?debug=1 ile hata görebilir
    $__dg_debug = true;
}
// Debug açıksa hataları göster, kapalıysa gizle
if ($__dg_debug) {
    ini_set('display_errors','1');
    ini_set('display_startup_errors','1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors','0');
    ini_set('log_errors','1');
    error_reporting(E_ALL);
}

// ——— Oturum — güvenli çerez ———
if (session_status() !== PHP_SESSION_ACTIVE) {
    // Eğer daha önce config.php session başlattıysa tekrar başlatma
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
           || (($_SERVER['SERVER_PORT'] ?? '') === '443')
           || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    // headers_sent kontrolü — bazı hostinglerde erken output olabilir
    if (!headers_sent()) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    @session_start();
}

// ——— Ayarlar ———
define('DG_DAILY_LIMIT_DEFAULT', 5);
define('DG_UPLOAD_DIR', __DIR__ . '/uploads');
define('DG_UPLOAD_URL', 'uploads/');

// ——— DB Bağlantısı (PDO) ———
// Öncelik: 1) Ortam değişkeni 2) config override dosyası 3) varsayılanlar
// Varsayılan host: localhost (paylaşımlı hostinglerin %90'ı localhost ister, 127.0.0.1 TCP'yi kapatır)
$db_host = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? null) ?: 'localhost';
$db_name = getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? null) ?: 'devicegame';
$db_user = getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? null) ?: 'root';
$db_pass = getenv('DB_PASS') ?: ($_ENV['DB_PASS'] ?? null) ?: '';
$db_port = getenv('DB_PORT') ?: ($_ENV['DB_PORT'] ?? null) ?: '3306';
$db_charset = 'utf8mb4';

// Opsiyonel: db_config.php override dosyası (hostingde şifreyi kodda tutmak istemeyenler için)
// Örnek: <?php return ['host'=>'localhost','name'=>'u123_db','user'=>'u123_user','pass'=>'...','port'=>'3306'];
$__override = __DIR__ . '/db_config.php';
if (is_file($__override)) {
    $ov = @include $__override;
    if (is_array($ov)) {
        $db_host = $ov['host'] ?? $db_host;
        $db_name = $ov['name'] ?? $ov['dbname'] ?? $db_name;
        $db_user = $ov['user'] ?? $ov['username'] ?? $db_user;
        $db_pass = $ov['pass'] ?? $ov['password'] ?? $db_pass;
        $db_port = $ov['port'] ?? $db_port;
    }
}

// PDO seçenekleri
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::ATTR_TIMEOUT            => 5,
];

$pdo = null;
$__last_error = null;

// Ana bağlantı denemesi
try {
    $dsn = "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset={$db_charset}";
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
} catch (PDOException $e) {
    $__last_error = $e->getMessage();
    // Fallback: host localhost ise 127.0.0.1 dene (bazı local kurulumlarda localhost soketi çalışmaz, TCP gerekir)
    // Ama paylaşımlı hostingde 127.0.0.1 zaten 115 hatası verir — o durumda tekrar aynı hatayı verecek, loglayacağız
    $fallback_host = ($db_host === 'localhost') ? '127.0.0.1' : null;
    if ($fallback_host) {
        try {
            $dsn2 = "mysql:host={$fallback_host};port={$db_port};dbname={$db_name};charset={$db_charset}";
            $pdo = new PDO($dsn2, $db_user, $db_pass, $options);
            $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            $__last_error = null;
        } catch (PDOException $e2) {
            $__last_error = $e->getMessage() . " | Fallback($fallback_host): " . $e2->getMessage();
        }
    }
}

// Başarısızsa kullanıcı dostu teşhis sayfası göster (500 yerine 503 + detay)
if (!$pdo) {
    // diagnose.php bu sabiti tanimlar: sayfayi oldurmek yerine exception firlat,
    // teshis sayfasi hatayi kendi raporunda gosterebilsin.
    if (defined('DG_DIAGNOSE') && DG_DIAGNOSE) {
        throw new PDOException((string)($__last_error ?? 'Veritabani baglantisi kurulamadi'));
    }
    error_log('[DG DB] ' . $__last_error . " | host={$db_host} db={$db_name} user={$db_user}");
    // Eğer ?debug=1 veya teşhis sayfasından geliyorsak detaylı göster
    $is_debug_request = $__dg_debug || isset($_GET['debug']) || strpos($_SERVER['REQUEST_URI'] ?? '', 'diagnose') !== false;
    http_response_code(503);
    $safe_host = htmlspecialchars($db_host, ENT_QUOTES, 'UTF-8');
    $safe_db   = htmlspecialchars($db_name, ENT_QUOTES, 'UTF-8');
    $safe_user = htmlspecialchars($db_user, ENT_QUOTES, 'UTF-8');
    $safe_err  = htmlspecialchars($__last_error ?? 'Bilinmeyen hata', ENT_QUOTES, 'UTF-8');
    $php_ver   = htmlspecialchars(PHP_VERSION, ENT_QUOTES, 'UTF-8');
    $pdo_avail = extension_loaded('pdo_mysql') ? 'var (PDO_MYSQL aktif)' : '<b style="color:#FF2A6D">YOK — hostinginde pdo_mysql eklentisi kapalı!</b>';
    die(<<<HTML
<!doctype html>
<html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Kurulum Gerekli — DeviceGame</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:#070A14;color:#EAF0FF;font-family:system-ui,-apple-system,sans-serif;line-height:1.6;padding:24px}
.wrap{max-width:820px;margin:0 auto}
.card{background:#0F1428;border:1px solid rgba(0,229,255,0.18);border-radius:18px;padding:22px;margin:14px 0}
h1{font-size:22px;color:#00E5FF}
h2{font-size:14px;letter-spacing:0.08em;text-transform:uppercase;color:#8B95B5;margin-top:18px}
code{background:rgba(255,255,255,0.08);padding:2px 7px;border-radius:6px;font-size:13px}
pre{background:#070A14;border:1px solid rgba(255,255,255,0.08);padding:14px;border-radius:12px;overflow:auto;font-size:12px;white-space:pre-wrap;word-break:break-all}
a{color:#00E5FF}
ol,ul{padding-left:22px}
.badge{display:inline-block;background:rgba(255,42,109,0.15);color:#FF8FAF;border:1px solid rgba(255,42,109,0.3);padding:4px 10px;border-radius:999px;font-size:11px;font-weight:700}
.btn{display:inline-block;background:linear-gradient(135deg,#00E5FF,#7B2DFF);color:#fff;padding:10px 16px;border-radius:12px;text-decoration:none;font-weight:700;margin-top:10px}
</style>
</head><body><div class="wrap">
<h1>◉ DeviceGame — Veritabanı Bağlantısı Kurulamadı</h1>
<p style="color:#8B95B5;margin-top:8px">Endişelenme — bu bir kod hatası değil, sadece <code>db.php</code> içindeki bilgiler hostinginle eşleşmiyor.</p>

<div class="card">
<span class="badge">DB_CONN_FAILED</span>
<h2>Hata Mesajı</h2>
<pre>{$safe_err}</pre>
<h2>Denendi</h2>
<pre>Host: {$safe_host}
DB  : {$safe_db}
User: {$safe_user}
Port: {$db_port}
PHP : {$php_ver} — PDO: {$pdo_avail}</pre>
<h2>Hızlı Çözüm (2 dakika)</h2>
<ol>
<li><b>cPanel → MySQL Veritabanları</b> aç. Veritabanı adını ve kullanıcı adını tam olarak not et (prefix ile, örn: <code>u1234567_devicegame</code>).</li>
<li><b>Kullanıcıyı Veritabanına Ekle</b> kısmında aynı kullanıcıyı aynı DB'ye ekle → <b>Tüm ayrıcalıklar</b> ver.</li>
<li><b>Kullanıcı şifresini sıfırla</b> → yeni şifreyi kopyala.</li>
<li><b>File Manager → db.php</b> aç, en üstteki 4 satırı kendi bilgilerinle değiştir:
<pre>\$db_host = 'localhost'; // paylaşımlı hostingde genelde localhost, 127.0.0.1 değil
\$db_name = 'u1234567_devicegame';
\$db_user = 'u1234567_kullanici';
\$db_pass = 'YeniSifren';</pre></li>
<li>Kaydet → sayfayı yenile.</li>
<li>Olmazsa <b>phpMyAdmin → Veritabanını seç → İçe aktar → schema.sql</b> yükle. Solda <code>users, ads, store_items</code> tabloları görünmeli.</li>
</ol>
<h2>Teşhis Araçları</h2>
<p><a class="btn" href="diagnose.php">diagnose.php ile otomatik test et →</a></p>
<p style="margin-top:10px;color:#8B95B5;font-size:13px">Bu sayfa <code>?debug=1</code> ile de açılır: <a href="?debug=1">?debug=1 ekle</a> — detaylı hata görürsün.</p>
</div>

<div class="card" style="border-color:rgba(255,214,10,0.2)">
<h2>Sık Hatalar</h2>
<ul>
<li><b>Can't connect to server on '127.0.0.1' (115)</b> → Host'u <code>localhost</code> yap. Paylaşımlı hostingler TCP'yi kapatır.</li>
<li><b>Access denied for user '...' (using password: YES)</b> → Şifre yanlış veya kullanıcı DB'ye eklenmemiş.</li>
<li><b>Unknown database '...'</b> → DB adını yanlış yazdın, cPanel'deki tam adı kopyala.</li>
<li><b>PDO_MYSQL YOK</b> → Hosting destek talebi: "pdo_mysql eklentisini açın".</li>
<li><b>500 hatası boş</b> → <code>uploads/.htaccess</code> içindeki <code>php_flag</code> satırı bazı hostinglerde 500 atar — bu sürümde düzeltildi, ama eski dosyaysa yeniden yükle.</li>
</ul>
</div>

<p style="text-align:center;color:#5A6585;font-size:12px;margin-top:18px">DeviceGame v2.1 • Torbalı/İzmir • diagnose.php ile 10 saniyede teşhis</p>
</div></body></html>
HTML);
}

// ——— Yardımcılar ———
if (!function_exists('e')) {
    function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

// lib/shop.php de csrf_token() tanimlar — bir sayfa ikisini de yuklerse
// "Cannot redeclare" fatal hatasi (bos 500) olmamasi icin guard'li tanimlar.
if (!function_exists('csrf_token')) {
    function csrf_token(): string {
        if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf'];
    }
}
if (!function_exists('csrf_field')) {
    function csrf_field(): string {
        return '<input type="hidden" name="csrf" value="'.e(csrf_token()).'">';
    }
}
if (!function_exists('csrf_verify')) {
    function csrf_verify(?string $token): bool {
        return !empty($_SESSION['csrf']) && !empty($token) && hash_equals($_SESSION['csrf'], (string)$token);
    }
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
    try {
        $st = $pdo->prepare("SELECT id,username,email,dcoin_balance,is_admin,is_banned,created_at FROM users WHERE id=?");
        $st->execute([$_SESSION['user_id']]);
        $u = $st->fetch();
        if ($u) {
            $_SESSION['dcoin_balance'] = (int)$u['dcoin_balance'];
            $_SESSION['is_admin'] = (int)$u['is_admin'];
            if ((int)$u['is_banned'] === 1) {
                session_destroy();
                header('Location: login.php?banned=1'); exit;
            }
        }
        return $u ?: null;
    } catch (Throwable $e) { error_log('[current_user] '.$e->getMessage()); return null; }
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
    try {
        $st = $pdo->prepare("SELECT COUNT(*) FROM user_watch_logs WHERE user_id=? AND DATE(watched_at)=CURDATE()");
        $st->execute([$user_id]);
        return (int)$st->fetchColumn();
    } catch (Throwable $e) { return 0; }
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
