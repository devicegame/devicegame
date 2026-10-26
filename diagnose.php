<?php
/**
 * diagnose.php — DeviceGame Otomatik Teşhis v2
 * ---------------------------------------------------------------
 * 500 hatası / "Veritabanı bağlantısı kurulamadı" durumunda aç:
 *   siteadın.com/diagnose.php
 * Kurulumun her adımını kontrol edip sorunun tam yerini söyler.
 *
 * ⚠️ GÜVENLİK: Kurulum bitince bu dosyayı SİL!
 *    Kolay yol: siteadın.com/diagnose.php?sil=1  (kendini siler)
 * ---------------------------------------------------------------
 */
declare(strict_types=1);

// Kendini silme (sadece bu dosyayı siler, başka hiçbir şeye dokunmaz)
if (isset($_GET['sil']) && $_GET['sil'] === '1') {
    @unlink(__FILE__);
    header('Content-Type: text/html; charset=utf-8');
    die('<!doctype html><meta charset="utf-8"><body style="background:#070A14;color:#EAF0FF;font-family:system-ui;padding:2rem"><h1>✅ diagnose.php silindi</h1><p>Kurulum tamam. <a style="color:#00E5FF" href="index.php">Siteye git →</a></p></body>');
}

error_reporting(E_ALL);
ini_set('display_errors', '1');

// db.php'ye "ölme, exception fırlat" de — hatayı raporda gösterebilelim.
define('DG_DIAGNOSE', true);

$pdo_ok  = false;
$db_err  = null;
$db_host = $db_name = $db_user = '(okunamadı)';
try {
    require __DIR__ . '/db.php';   // $pdo, $db_host, $db_name, $db_user tanimlar
    $pdo_ok = ($pdo instanceof PDO);
} catch (Throwable $e) {
    $db_err = $e->getMessage();
}

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Teşhis — DeviceGame</title>
<style>
body{background:#070A14;color:#EAF0FF;font-family:system-ui,-apple-system,sans-serif;padding:24px;line-height:1.65;max-width:920px;margin:0 auto}
h1{color:#00E5FF;font-size:22px} h2{color:#8B95B5;font-size:13px;letter-spacing:.08em;text-transform:uppercase;margin-top:26px}
pre{background:#0F1428;border:1px solid rgba(0,229,255,.15);padding:14px;border-radius:12px;overflow:auto;white-space:pre-wrap;word-break:break-all;font-size:12.5px}
.ok{color:#00E676}.bad{color:#FF2A6D}.warn{color:#FFD60A}
code{background:rgba(255,255,255,.08);padding:2px 6px;border-radius:6px}
a{color:#00E5FF}.card{background:#0F1428;border:1px solid rgba(255,255,255,.08);border-radius:16px;padding:16px;margin:10px 0}
.danger{background:rgba(255,42,109,.1);border:1px solid rgba(255,42,109,.35);border-radius:16px;padding:16px;margin:14px 0}
.btn{display:inline-block;background:linear-gradient(135deg,#FF2A6D,#7B2DFF);color:#fff;padding:10px 16px;border-radius:12px;text-decoration:none;font-weight:700}
</style></head><body>
<h1>◉ DeviceGame — Otomatik Teşhis</h1>
<p style="color:#8B95B5">Bu sayfa kurulumu adım adım kontrol eder. Çıktının ekran görüntüsünü destek ile paylaşabilirsin (şifre gösterilmez).</p>

<div class="danger"><b>⚠️ Kurulum bitince bu dosyayı sil:</b> <a class="btn" href="diagnose.php?sil=1">diagnose.php kendini silsin →</a></div>

<?php
$ok   = fn($m) => print '<div class="card"><span class="ok">✅ ' . $m . '</span></div>';
$bad  = fn($m) => print '<div class="card"><span class="bad">❌ ' . $m . '</span></div>';
$warn = fn($m) => print '<div class="card"><span class="warn">⚠️ ' . $m . '</span></div>';

// 1) PHP
echo '<h2>1. PHP & Eklentiler</h2><pre>'
   . 'PHP surumu    : ' . PHP_VERSION . ' (' . PHP_OS . ")\n"
   . 'pdo           : ' . (extension_loaded('pdo') ? 'var' : 'YOK') . "\n"
   . 'pdo_mysql     : ' . (extension_loaded('pdo_mysql') ? 'var' : 'YOK — HOSTING DESTEĞİ ŞART') . "\n"
   . 'mbstring      : ' . (extension_loaded('mbstring') ? 'var' : 'YOK (site yine calisir, fallback var)') . "\n"
   . 'session       : ' . (function_exists('session_start') ? 'var' : 'YOK') . "\n"
   . 'display_errors: ' . ini_get('display_errors') . '  | memory_limit: ' . ini_get('memory_limit') . "\n"
   . 'SAPI          : ' . PHP_SAPI . "\n</pre>";
if (!extension_loaded('pdo_mysql')) $bad('pdo_mysql kapalı! cPanel → PHP Eklentileri/Select PHP Version → pdo_mysql işaretle, yoksa hosting desteğine yaz.');
if (version_compare(PHP_VERSION, '8.0.0', '<')) $bad('PHP çok eski (' . PHP_VERSION . '). Bu sistem PHP 8.0+ ister (tercihen 8.1/8.2). cPanel → MultiPHP Yöneticisi → alan adini 8.1 veya 8.2 yap. Tipik "boş 500" sebebi budur.');
else $ok('PHP surumu uygun (' . PHP_VERSION . ')');

// 2) Dosyalar & izinler
echo '<h2>2. Dosyalar & İzinler</h2><pre>';
foreach (['db.php','schema.sql','schema_hosting.sql','index.php','login.php','assets/css/devicegame.css','admin/index.php','api/purchase.php'] as $f) {
    echo h(str_pad($f, 32)) . (is_readable(__DIR__ . '/' . $f) ? 'var' : 'YOK / okunamıyor') . "\n";
}
foreach (['uploads','storage'] as $d) {
    $p = __DIR__ . '/' . $d;
    echo h(str_pad($d . '/', 32)) . (is_dir($p) ? (is_writable($p) ? 'var, yazilabilir' : 'var ama YAZILAMIYOR (755/775 yap)') : 'KLASOR YOK') . "\n";
}
echo '</pre>';

// 3) Veritabani
echo '<h2>3. Veritabanı Bağlantısı</h2>';
echo '<pre>db.php ayarlari → Host: ' . h($db_host) . ' | DB: ' . h($db_name) . ' | User: ' . h($db_user) . ' | Sifre: (gizli)</pre>';
if (!$pdo_ok) {
    $bad('Bağlantı BAŞARISIZ');
    echo '<pre>' . h($db_err) . '</pre>';
    echo '<div class="card"><b>Hata mesajına göre çözüm:</b><ul>'
       . '<li><code>Can\'t connect to server on \'127.0.0.1\' (115)</code> → db.php\'de host\'u <code>localhost</code> yap. Paylaşımlı hostingler 127.0.0.1 TCP bağlantısını kapatır.</li>'
       . '<li><code>Access denied for user \'...\'@\'localhost\' (using password: YES)</code> → Şifre yanlış VEYA kullanıcı veritabanına eklenmemiş. cPanel → MySQL Veritabanları → (1) kullanıcının şifresini sıfırla, (2) "Kullanıcıyı Veritabanına Ekle" → TÜM AYRICALIKLAR, (3) db.php\'deki şifreyi yenisiyle değiştir.</li>'
       . '<li><code>Unknown database \'...\'</code> → DB adını yanlış yazdın. cPanel\'deki tam adı (prefix ile, örn: <code>devicegamer_devicegame</code>) kopyala.</li>'
       . '<li><code>Access denied ... to database</code> → Kullanıcı var ama bu DB üzerinde yetkisi yok → "Kullanıcıyı Veritabanına Ekle" adımını yap.</li>'
       . '</ul></div>';
} else {
    $ok('Bağlantı BAŞARILI — PDO çalışıyor');
    try {
        $cur = $pdo->query('SELECT DATABASE()')->fetchColumn();
        echo '<pre>Baglanilan veritabani (SELECT DATABASE()): ' . h($cur) . "\n"
           . 'MySQL surumu: ' . h($pdo->query('SELECT VERSION()')->fetchColumn()) . '</pre>';
        if ((string)$cur !== (string)$db_name) $warn('db.php\'deki DB adi ile baglanilan DB farkli gorunuyor — settings\'i kontrol et.');

        echo '<h2>4. Tablolar</h2>';
        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        echo '<pre>Bu veritabanindaki tablolar (' . count($tables) . '): ' . h(implode(', ', $tables) ?: '(hic tablo yok)') . '</pre>';
        $need = ['users','ads','user_watch_logs','watch_sessions','store_items','store_keys','purchases','settings'];
        $missing = array_diff($need, $tables);
        if ($missing) {
            $bad('Eksik tablolar: ' . h(implode(', ', $missing)) . ' → Şema bu veritabanına kurulmamış. <b>Boş 500 hatasının en sık sebebi budur.</b>');
            echo '<div class="card"><b>Çözüm (cPanel):</b> phpMyAdmin → solda <code>' . h($db_name) . '</code> veritabanını seç → İçe aktar → <code>schema_hosting.sql</code> dosyasını yükle → Git.<br>'
               . 'Not: <code>schema.sql</code> içindeki <code>CREATE DATABASE</code> satırı cPanel\'de yetki hatası verip içe aktarımı yarıda kesebilir; o yüzden hostingde <code>schema_hosting.sql</code> kullan.<br>'
               . 'Tabloları başka bir veritabanında görüyorsan (örn: <code>devicegame</code>), db.php yanlış DB\'ye bakıyor demektir — db.php\'deki DB adını tabloların olduğu DB yap.</div>';
        } else {
            $ok('Tüm tablolar mevcut');
            $u = $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
            $a = $pdo->query('SELECT COUNT(*) FROM ads')->fetchColumn();
            $s = $pdo->query('SELECT COUNT(*) FROM store_items')->fetchColumn();
            echo '<pre>Kullanici: ' . h($u) . ' | Reklam: ' . h($a) . ' | Urun: ' . h($s) . '</pre>';

            echo '<h2>5. Admin Hesabı</h2>';
            $adm = $pdo->query("SELECT username,email,password_hash,is_admin FROM users WHERE email='admin@devicegame.com' LIMIT 1")->fetch();
            if (!$adm) {
                $bad('admin@devicegame.com kullanıcısı yok — örnek veriler (seed) içe aktarılmamış. schema_hosting.sql\'i tekrar içe aktar.');
            } else {
                echo '<pre>Admin: ' . h($adm['username']) . ' &lt;' . h($adm['email']) . '&gt; is_admin=' . h($adm['is_admin']) . '</pre>';
                if (password_verify('Admin123!', $adm['password_hash']))       $ok('Admin şifresi <code>Admin123!</code> — giriş çalışmalı. (Canlıda mutlaka değiştir!)');
                elseif (password_verify('password', $adm['password_hash']))    $warn('Bu veritabanında ESKİ hatalı şema var: admin şifresi <code>password</code>. Yeni schema_hosting.sql ile tekrar kur veya phpMyAdmin\'de users tablosunda şifreyi güncelle.');
                else $warn('Admin şifresi Admin123! değil (değiştirilmiş olabilir — sorun değil, kendi şifrenle gir).');
            }
        }
    } catch (Throwable $e) {
        $bad('Sorgu hatası: ' . h($e->getMessage()));
    }
}

// 6) .htaccess
echo '<h2>6. .htaccess Dosyaları</h2><pre>';
foreach (['.htaccess','uploads/.htaccess','storage/.htaccess'] as $f) {
    $p = __DIR__ . '/' . $f;
    if (is_file($p)) {
        $c = (string)file_get_contents($p);
        echo h($f) . ":\n" . h(substr($c, 0, 600)) . "\n---\n";
        if (strpos($c, 'php_flag') !== false || strpos($c, 'php_value') !== false) {
            echo '</pre>';
            $warn(h($f) . ' içinde php_flag/php_value var — PHP-FPM/CGI hostinglerde <b>500 hatası</b> yapar! Repodaki yeni sürümle değiştir veya o satırları sil.');
            echo '<pre>';
        }
    } else echo h($f) . ": yok\n";
}
echo '</pre>';

// 7) error_log son satirlar
echo '<h2>7. error_log (son satırlar)</h2>';
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
if (!$foundLog) echo '<pre>error_log dosyasi bulunamadi/okunamiyor. cPanel → Errors bolumune de bakabilirsin.</pre>';

echo '<h2>Sonuç</h2><div class="card">'
   . 'Her şey ✅ ise <a href="index.php">index.php</a> açılmalı. Gerçek hatayı anlık görmek için: <a href="index.php?debug=1">index.php?debug=1</a><br>'
   . 'Bağlantı ❌ → yukarıdaki 3. bölümdeki çözümü uygula. &nbsp;|&nbsp; Tablo ❌ → phpMyAdmin → DB seç → İçe aktar → <code>schema_hosting.sql</code><br>'
   . 'Hâlâ boş 500 alıyorsan 7. bölümdeki son "PHP Fatal error" satırı gerçek sebeptir — o satırı kopyalayıp destekle paylaş.'
   . '</div>';
echo '<p style="color:#5A6585;font-size:12px;margin-top:20px">İşin bitince: <a href="diagnose.php?sil=1">bu dosyayı sil</a> • DeviceGame diagnose v2</p>';
?>
</body></html>
