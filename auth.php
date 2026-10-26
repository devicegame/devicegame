<?php
/**
 * auth.php — Kayıt & Giriş POST işleyici (veritabansız)
 * Formlar action="auth.php" olarak gönderilir.
 */
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/inc/helpers.php';

function dg_safe_back(string $back, string $fallback = 'login.php'): string {
    return in_array($back, ['login.php', 'register.php'], true) ? $back : $fallback;
}

function dg_safe_next(string $next): string {
    $next = trim($next);
    if ($next === '' || preg_match('#^(?:https?:)?//#i', $next)) return 'index.php';
    // Yalnızca bu projedeki göreli sayfalara izin ver.
    return preg_match('#^[a-zA-Z0-9_./?=&%-]+$#', $next) ? $next : 'index.php';
}

$action = (string)($_POST['action'] ?? $_GET['action'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify((string)($_POST['csrf'] ?? ''))) {
        flash_error_set('Güvenlik doğrulaması başarısız. Sayfayı yenileyip tekrar deneyin.');
        header('Location: ' . dg_safe_back((string)($_POST['_back'] ?? 'login.php'))); exit;
    }

    try {
        // ---------- KAYIT ----------
        if ($action === 'register') {
            $username  = trim((string)($_POST['username'] ?? ''));
            $email     = trim((string)($_POST['email'] ?? ''));
            $password  = (string)($_POST['password'] ?? '');
            $password2 = (string)($_POST['password2'] ?? '');

            $err = validate_username($username) ?? validate_email($email) ?? validate_password($password);
            if ($err) { flash_error_set($err); header('Location: register.php'); exit; }
            if ($password !== $password2) { flash_error_set('Şifreler uyuşmuyor.'); header('Location: register.php'); exit; }

            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
            $result = dg_create_user($username, $email, $hash);
            if (empty($result['ok'])) {
                flash_error_set((string)($result['error'] ?? 'Kayıt oluşturulamadı.'));
                header('Location: register.php'); exit;
            }

            $user = $result['user'];
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['dg_auth_v'] = DG_AUTH_VERSION;
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['is_admin'] = 0;
            $_SESSION['dcoin_balance'] = 0;
            flash_set('Hoş geldin, ' . $username . '! Hesabın oluşturuldu. D-Coin kazanmak için videoları izlemeye başlayabilirsin.');
            header('Location: index.php'); exit;
        }

        // ---------- GİRİŞ ----------
        if ($action === 'login') {
            $login    = trim((string)($_POST['login'] ?? ''));
            $password = (string)($_POST['password'] ?? '');

            if ($login === '' || $password === '') {
                flash_error_set('Tüm alanları doldurun.');
                header('Location: login.php'); exit;
            }

            $user = dg_find_user_for_login($login);
            if (!$user || !password_verify($password, (string)($user['password_hash'] ?? ''))) {
                flash_error_set('Kullanıcı adı/e-posta veya şifre hatalı.');
                header('Location: login.php'); exit;
            }
            if ((int)($user['is_banned'] ?? 0) === 1) {
                flash_error_set('Hesabınız engellenmiş. Destek ile iletişime geçin.');
                header('Location: login.php'); exit;
            }

            if (password_needs_rehash((string)$user['password_hash'], PASSWORD_BCRYPT)) {
                dg_update_user((int)$user['id'], [
                    'password_hash' => password_hash($password, PASSWORD_BCRYPT),
                ]);
            }

            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['dg_auth_v'] = DG_AUTH_VERSION;
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['is_admin'] = (int)($user['is_admin'] ?? 0);
            $_SESSION['dcoin_balance'] = (int)($user['dcoin_balance'] ?? 0);

            flash_set('Tekrar hoş geldin, ' . $user['username'] . '!');
            header('Location: ' . dg_safe_next((string)($_POST['next'] ?? $_GET['next'] ?? 'index.php'))); exit;
        }
    } catch (Throwable $e) {
        error_log('[auth] ' . $e->getMessage());
        flash_error_set('Depolama hatası nedeniyle işlem tamamlanamadı. diagnose.php ile kontrol edin.');
        header('Location: ' . ($action === 'register' ? 'register.php' : 'login.php')); exit;
    }
}

header('Location: login.php'); exit;
