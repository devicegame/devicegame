<?php
/**
 * auth.php — Kayıt & Giriş POST işleyici
 * Form action="auth.php" olarak kullanılır.
 * Ayrıca doğrudan login.php / register.php içinden de include edilebilir.
 */
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/inc/helpers.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf'] ?? '';
    if (!csrf_verify($csrf)) {
        flash_error_set('Güvenlik doğrulaması başarısız. Sayfayı yenileyip tekrar deneyin.');
        // Geldiği sayfaya dön
        $back = $_POST['_back'] ?? 'login.php';
        header("Location: $back"); exit;
    }

    // ---------- KAYIT ----------
    if ($action === 'register') {
        $username = trim((string)($_POST['username'] ?? ''));
        $email    = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $password2= (string)($_POST['password2'] ?? '');

        $err = validate_username($username) ?? validate_email($email) ?? validate_password($password);
        if ($err) { flash_error_set($err); header('Location: register.php'); exit; }
        if ($password !== $password2) { flash_error_set('Şifreler uyuşmuyor.'); header('Location: register.php'); exit; }

        // Var mı kontrol
        $st = $pdo->prepare("SELECT id FROM users WHERE username=? OR email=? LIMIT 1");
        $st->execute([$username, $email]);
        if ($st->fetch()) { flash_error_set('Bu kullanıcı adı veya e-posta zaten kayıtlı.'); header('Location: register.php'); exit; }

        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost'=>10]);
        // İlk kullanıcı admin olsun mu? Hayır — sadece schema'daki admin admindir.
        $st = $pdo->prepare("INSERT INTO users (username,email,password_hash,dcoin_balance,is_admin) VALUES (?,?,?,0,0)");
        $st->execute([$username, $email, $hash]);
        $uid = (int)$pdo->lastInsertId();

        // Otomatik giriş
        session_regenerate_id(true);
        $_SESSION['user_id'] = $uid;
        $_SESSION['username']= $username;
        $_SESSION['email']   = $email;
        $_SESSION['is_admin']= 0;
        $_SESSION['dcoin_balance']=0;
        flash_set('Hoş geldin, '.$username.'! Hesabın oluşturuldu. D-Coin kazanmak için videoları izlemeye başlayabilirsin.');
        header('Location: index.php'); exit;
    }

    // ---------- GİRİŞ ----------
    if ($action === 'login') {
        $login = trim((string)($_POST['login'] ?? '')); // username veya email
        $password = (string)($_POST['password'] ?? '');

        if ($login === '' || $password === '') { flash_error_set('Tüm alanları doldurun.'); header('Location: login.php'); exit; }

        $st = $pdo->prepare("SELECT * FROM users WHERE username=? OR email=? LIMIT 1");
        $st->execute([$login, $login]);
        $user = $st->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            flash_error_set('Kullanıcı adı/e-posta veya şifre hatalı.');
            header('Location: login.php'); exit;
        }
        if ((int)$user['is_banned'] === 1) {
            flash_error_set('Hesabınız engellenmiş. Destek ile iletişime geçin.');
            header('Location: login.php'); exit;
        }
        // Rehash gerekirse güncelle
        if (password_needs_rehash($user['password_hash'], PASSWORD_BCRYPT)) {
            $new = password_hash($password, PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([$new, $user['id']]);
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['username']= $user['username'];
        $_SESSION['email']   = $user['email'];
        $_SESSION['is_admin']= (int)$user['is_admin'];
        $_SESSION['dcoin_balance'] = (int)$user['dcoin_balance'];

        $next = $_POST['next'] ?? $_GET['next'] ?? 'index.php';
        // Açık yönlendirme engeli: sadece göreli yollar
        if (preg_match('#^https?://#i', $next)) $next = 'index.php';
        flash_set('Tekrar hoş geldin, '.$user['username'].'!');
        header("Location: $next"); exit;
    }
}

// GET ile gelindiyse login'e at
header('Location: login.php'); exit;
