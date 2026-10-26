<?php
/**
 * diagnose.php — Device Game Veritabansız Sistem Teşhisi v3
 * ---------------------------------------------------------------
 * Aç: siteadın.com/diagnose.php
 * MySQL/PDO kontrolü yoktur; JSON depolama, izinler ve dosyalar denetlenir.
 *
 * ⚠️ Kurulum bitince sil: siteadın.com/diagnose.php?sil=1
 * ---------------------------------------------------------------
 */
declare(strict_types=1);

if (isset($_GET['sil']) && $_GET['sil'] === '1') {
    @unlink(__FILE__);
    header('Content-Type: text/html; charset=utf-8');
    die('<!doctype html><meta charset="utf-8"><body style="background:#0A1A15;color:#E8EBE4;font-family:system-ui;padding:2rem"><h1>✅ diagnose.php silindi</h1><p>Kurulum tamam. <a style="color:#4FE0A0" href="index.php">Siteye git →</a></p></body>');
}

error_reporting(E_ALL);
ini_set('display_errors', '1');

define('DG_DIAGNOSE', true);

$coreOk = false;
$coreError = null;
try {
    require __DIR__ . '/db.php';
    $coreOk = true;
} catch (Throwable $e) {
    $coreError = $e->getMessage();
}

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function dg_diag_line(bool $ok, string $label, string $value): string {
    return ($ok ? '✅' : '❌') . ' ' . str_pad($label, 28) . $value . "\n";
}

/** Yorum satırlarını yok sayarak gerçek php_flag/php_value direktiflerini bulur. */
function dg_diag_bad_htaccess_directives(string $content): array {
    $bad = [];
    foreach (preg_split('/\R/', $content) ?: [] as $i => $line) {
        $trim = ltrim($line);
        if ($trim === '' || $trim[0] === '#') continue;
        if (preg_match('/^(php_flag|php_value)\b/i', $trim)) {
            $bad[] = 'Satır ' . ($i + 1) . ': ' . trim($line);
        }
    }
    return $bad;
}
?>
<!doctype html>
<html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Teşhis — Device Game (Veritabansız)</title>
<style>
body{background:#0A1A15;color:#E8EBE4;font-family:system-ui,-apple-system,sans-serif;padding:24px;line-height:1.65;max-width:920px;margin:0 auto}
h1{color:#4FE0A0;font-size:22px} h2{color:#93A89E;font-size:13px;letter-spacing:.08em;text-transform:uppercase;margin-top:26px}
pre{background:#112A21;border:1px solid rgba(79,224,160,.15);padding:14px;border-radius:12px;overflow:auto;white-space:pre-wrap;word-break:break-all;font-size:12.5px}
.ok{color:#4FE0A0}.bad{color:#E0607A}.warn{color:#F4C15D}
code{background:rgba(255,255,255,.08);padding:2px 6px;border-radius:6px}
a{color:#4FE0A0}.card{background:#112A21;border:1px solid rgba(255,255,255,.08);border-radius:16px;padding:16px;margin:10px 0}
.danger{background:rgba(224,96,122,.1);border:1px solid rgba(224,96,122,.35);border-radius:16px;padding:16px;margin:14px 0}
.btn{display:inline-block;background:linear-gradient(135deg,#E0607A,#17362B);color:#fff;padding:10px 16px;border-radius:12px;text-decoration:none;font-weight:700}
</style></head><body>
<h1>Device Game — Veritabansız Sistem Teşhisi</h1>
<p style="color:#93A89E">Bu sürüm MySQL kullanmaz. Veriler <code>storage/dg/database.json</code> içinde tutulur. Bu sayfa yalnızca PHP, dosyalar, izinler ve JSON bütünlüğünü kontrol eder.</p>

<div class="danger"><b>⚠️ Kurulum bitince bu dosyayı sil:</b> <a class="btn" href="diagnose.php?sil=1">diagnose.php kendini silsin →</a></div>

<?php
$ok   = fn($m) => print '<div class="card"><span class="ok">✅ ' . $m . '</span></div>';
$bad  = fn($m) => print '<div class="card"><span class="bad">❌ ' . $m . '</span></div>';
$warn = fn($m) => print '<div class="card"><span class="warn">⚠️ ' . $m . '</span></div>';

// 1) PHP
echo '<h2>1. PHP</h2><pre>'
   . dg_diag_line(version_compare(PHP_VERSION, '8.0.0', '>='), 'PHP surumu', PHP_VERSION . ' (' . PHP_OS . ')')
   . dg_diag_line(extension_loaded('json'), 'json', extension_loaded('json') ? 'var (zorunlu)' : 'YOK (zorunlu)')
   . dg_diag_line(function_exists('session_start'), 'session', function_exists('session_start') ? 'var (zorunlu)' : 'YOK (zorunlu)')
   . dg_diag_line(function_exists('password_hash'), 'password_hash', function_exists('password_hash') ? 'var' : 'YOK')
   . dg_diag_line(function_exists('random_bytes'), 'random_bytes', function_exists('random_bytes') ? 'var' : 'YOK')
   . 'ℹ️ ' . str_pad('mbstring', 28) . (extension_loaded('mbstring') ? 'var' : 'yok (zorunlu degil; fallback aktif)') . "\n"
   . 'ℹ️ ' . str_pad('MySQL/PDO', 28) . "gerekmez\n"
   . 'SAPI: ' . PHP_SAPI . ' | memory_limit: ' . ini_get('memory_limit') . "\n</pre>";
if (version_compare(PHP_VERSION, '8.0.0', '<')) {
    $bad('PHP çok eski. cPanel → MultiPHP Yöneticisi → alan adını PHP 8.1 veya 8.2 yap.');
} else {
    $ok('PHP sürümü uygun.');
}
if (!extension_loaded('json')) $bad('JSON eklentisi kapalı. Hosting panelinden json eklentisini açın.');

// 2) Dosyalar
echo '<h2>2. Gerekli Dosyalar</h2><pre>';
$required = [
    'db.php', 'index.php', 'login.php', 'register.php', 'auth.php', 'logout.php',
    'config.php', 'header.php', 'footer.php', 'style.css', 'shop.css', 'script.js',
    'lib/shop.php', 'partials/slider.php', 'storage/products.json',
    'assets/css/devicegame.css', 'assets/css/dcoin.css', 'assets/js/dcoin.js',
    'admin/index.php', 'admin/ads.php', 'admin/users.php',
    'admin/store.php', 'admin/logs.php', 'api/watch_start.php', 'api/watch_complete.php', 'api/purchase.php',
    'storage/.htaccess', 'storage/dg/.htaccess', 'uploads/.htaccess', 'uploads/index.html',
];
foreach ($required as $f) {
    echo dg_diag_line(is_readable(__DIR__ . '/' . $f), $f, is_readable(__DIR__ . '/' . $f) ? 'var' : 'YOK / okunamıyor');
}
echo '</pre>';

// 3) Depolama ve izinler
echo '<h2>3. JSON Depolama & İzinler</h2><pre>';
$storageDir = defined('DG_STORAGE_DIR') ? DG_STORAGE_DIR : (__DIR__ . '/storage/dg');
$dataFile = defined('DG_DATA_FILE') ? DG_DATA_FILE : ($storageDir . '/database.json');
$lockFile = defined('DG_LOCK_FILE') ? DG_LOCK_FILE : ($storageDir . '/database.lock');
foreach (['storage' => __DIR__ . '/storage', 'storage/dg' => $storageDir, 'uploads' => __DIR__ . '/uploads'] as $label => $path) {
    $exists = is_dir($path);
    echo dg_diag_line($exists && is_writable($path), $label . '/', $exists ? (is_writable($path) ? 'yazılabilir' : 'var ama YAZILAMIYOR (755/775)') : 'KLASÖR YOK');
}
echo dg_diag_line(is_file($dataFile), 'database.json', is_file($dataFile) ? 'var (' . number_format((int)@filesize($dataFile)) . ' bayt)' : 'henüz yok — ilk açılışta oluşur');
if (is_file($dataFile)) {
    echo dg_diag_line(is_readable($dataFile), 'database.json okuma', is_readable($dataFile) ? 'ok' : 'OKUNAMIYOR');
    echo dg_diag_line(is_writable($dataFile), 'database.json yazma', is_writable($dataFile) ? 'ok' : 'YAZILAMIYOR (644/664)');
}
echo dg_diag_line(!is_file($lockFile) || is_writable($lockFile), 'database.lock', is_file($lockFile) ? (is_writable($lockFile) ? 'yazılabilir' : 'YAZILAMIYOR') : 'ilk işlemde oluşur');
echo '</pre>';

if (!$coreOk) {
    $bad('Uygulama çekirdeği başlatılamadı.');
    echo '<pre>' . h($coreError) . '</pre>';
    echo '<div class="card"><b>Çözüm:</b> <code>storage/</code> ve <code>storage/dg/</code> izinlerini 755/775, varsa <code>database.json</code> izinlerini 644/664 yapın. Sonra bu sayfayı yenileyin.</div>';
} else {
    $ok('Uygulama çekirdeği ve storage/dg hazır.');

    echo '<h2>4. Veri Dosyası</h2>';
    try {
        $data = dg_read_data(true);
        $counts = [];
        foreach (['users', 'ads', 'watch_logs', 'watch_sessions', 'store_items', 'store_keys', 'purchases'] as $name) {
            $counts[] = $name . ': ' . count($data[$name] ?? []);
        }
        echo '<pre>' . h(implode("\n", $counts)) . "\n"
           . 'daily_watch_limit: ' . h($data['settings']['daily_watch_limit'] ?? '(yok)') . "\n"
           . 'updated_at: ' . h($data['meta']['updated_at'] ?? '(yok)') . '</pre>';
        $ok('database.json geçerli JSON ve okunabiliyor.');

        echo '<h2>5. Admin Hesabı</h2>';
        $adm = null;
        foreach ($data['users'] ?? [] as $u) {
            if (($u['email'] ?? '') === 'admin@devicegame.com') { $adm = $u; break; }
        }
        if (!$adm) {
            $bad('admin@devicegame.com bulunamadı. Bu genellikle database.json elle boşaltılmış demektir. Yedeği geri yükleyin veya dosyayı yedekleyip silerek ilk kurulum verilerinin yeniden oluşmasını sağlayın.');
        } else {
            echo '<pre>Admin: ' . h($adm['username'] ?? '') . ' &lt;' . h($adm['email'] ?? '') . '&gt; is_admin=' . h($adm['is_admin'] ?? '') . ' bakiye=' . h($adm['dcoin_balance'] ?? '') . '</pre>';
            if (password_verify('Admin123!', (string)($adm['password_hash'] ?? ''))) {
                $ok('Admin şifresi <code>Admin123!</code> — giriş çalışmalı. Canlıda kullanmaya başlamadan önce admin şifresini değiştirme akışını ekleyene kadar bu hesabı koruyun.');
            } elseif (password_verify('password', (string)($adm['password_hash'] ?? ''))) {
                $warn('Bu veri dosyasında eski hatalı seed var: şifre <code>password</code>. Yedeği alıp database.json dosyasını silerseniz güncel seed ile yeniden oluşur (mevcut veriler gider).');
            } else {
                $warn('Admin şifresi Admin123! değil (değiştirilmiş olabilir). Kendi şifrenizle giriş yapın.');
            }
            if ((int)($adm['is_admin'] ?? 0) !== 1) $bad('Admin hesabında is_admin=1 değil. database.json içinde bu kullanıcının is_admin değerini 1 yapın (önce yedek alın).');
        }
    } catch (Throwable $e) {
        $bad('Veri dosyası okunamadı: ' . h($e->getMessage()));
        echo '<div class="card">Dosya bozuksa sistem onu kendiliğinden silmez. <code>storage/dg/database.json</code> dosyasını önce yedekleyin, JSON sözdizimini düzeltin. Sıfırdan başlamak isterseniz yedekten sonra dosyayı silin; ilk açılışta örnek verilerle yeniden oluşur.</div>';
    }
}

// 6) Web koruması ve .htaccess
echo '<h2>6. Web Koruması & .htaccess</h2>';
$storageGuard = is_file(__DIR__ . '/storage/.htaccess') ? (string)file_get_contents(__DIR__ . '/storage/.htaccess') : '';
$dgGuard = is_file($storageDir . '/.htaccess') ? (string)file_get_contents($storageDir . '/.htaccess') : '';
if (stripos($storageGuard, 'Require all denied') !== false || stripos($dgGuard, 'Require all denied') !== false) {
    $ok('Apache için storage web erişim engeli bulundu. Nginx kullanıyorsanız ayrıca <code>location ^~ /storage/ { deny all; }</code> kuralı gerekir.');
} else {
    $bad('storage için "Require all denied" bulunamadı. storage/.htaccess ve storage/dg/.htaccess dosyalarını yeniden yükleyin; Nginx kullanıyorsanız /storage/ yolunu kapatın.');
}

echo '<pre>';
foreach (['.htaccess', 'uploads/.htaccess', 'storage/.htaccess', 'storage/dg/.htaccess'] as $f) {
    $p = __DIR__ . '/' . $f;
    if (is_file($p)) {
        $c = (string)file_get_contents($p);
        echo h($f) . ":\n" . h(substr($c, 0, 600)) . "\n---\n";
        $directives = dg_diag_bad_htaccess_directives($c);
        if ($directives) {
            echo '</pre>';
            $warn(h($f) . ' içinde gerçek PHP yapılandırma direktifi var; PHP-FPM/CGI hostinglerde 500 yapabilir: <code>' . h(implode(' | ', $directives)) . '</code>');
            echo '<pre>';
        }
    } else {
        echo h($f) . ": yok\n";
    }
}
echo '</pre>';

// 7) Eski veritabanı kalıntıları
echo '<h2>7. Eski Veritabanı/Test Kalıntıları</h2><pre>';
$leftovers = ['schema.sql', 'schema_hosting.sql', 'db_config.php', 'db_config.example.php', 'test_db.php', 'hata_gor.php', 'hata2.php'];
$foundLeftovers = [];
foreach ($leftovers as $f) {
    $exists = is_file(__DIR__ . '/' . $f);
    if ($exists) $foundLeftovers[] = $f;
    echo ($exists ? '⚠️ ' : '✅ ') . str_pad($f, 28) . ($exists ? 'var (artık kullanılmıyor, silebilirsiniz)' : 'yok') . "\n";
}
echo '</pre>';
if (!$foundLeftovers) $ok('Eski MySQL/test kalıntısı yok.');
else $warn('Bu dosyalar yeni sistem tarafından kullanılmıyor. Özellikle test dosyalarını güvenlik için silin: <code>' . h(implode(', ', $foundLeftovers)) . '</code>');

// 8) error_log
echo '<h2>8. error_log (son satırlar)</h2>';
$logCandidates = [__DIR__ . '/error_log', __DIR__ . '/../error_log', ini_get('error_log') ?: ''];
$foundLog = false;
foreach ($logCandidates as $lp) {
    if ($lp && is_file($lp) && is_readable($lp)) {
        $lines = file($lp);
        $tail = array_slice($lines ?: [], -25);
        echo '<pre>' . h($lp) . ":\n" . h(implode('', $tail)) . '</pre>';
        $foundLog = true; break;
    }
}
if (!$foundLog) echo '<pre>error_log dosyası bulunamadı/okunamıyor. cPanel → Errors bölümüne de bakabilirsiniz.</pre>';

echo '<h2>Sonuç</h2><div class="card">'
   . 'Kritik maddeler ✅ ise <a href="index.php">index.php</a> açılmalı. Gerçek hatayı anlık görmek için: <a href="index.php?debug=1">index.php?debug=1</a><br>'
   . '500 devam ediyorsa 8. bölümdeki son "PHP Fatal error" satırı gerçek sebeptir. Veritabanı/MySQL/phpMyAdmin adımı yoktur.'
   . '</div>';
echo '<p style="color:#6E8579;font-size:12px;margin-top:20px">İşin bitince: <a href="diagnose.php?sil=1">bu dosyayı sil</a> • DeviceGame diagnose v3 (database-less)</p>';
?>
</body></html>
