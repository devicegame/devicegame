<?php
require_once __DIR__ . '/db.php';
if (is_logged_in()) { header('Location: index.php'); exit; }
$err = flash_error_get();
?>
<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Kayıt Ol — DeviceGame</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600&family=Orbitron:wght@600;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/devicegame.css">
</head>
<body class="dg-body dg-auth">
<div class="dg-grid-bg"></div>
<header class="dg-nav">
  <div class="dg-nav-inner">
    <a href="index.php" class="dg-logo">DEVICE<span>GAME</span><i></i></a>
    <nav class="dg-nav-links">
      <a href="index.php">Ana Sayfa</a>
      <a href="login.php" class="dg-btn dg-btn-ghost">Giriş Yap</a>
    </nav>
  </div>
</header>

<main class="dg-auth-wrap">
  <div class="dg-auth-card">
    <div class="dg-auth-head">
      <p class="dg-kicker">// NEW IDENTITY // JOIN THE GRID</p>
      <h1>Hesap Oluştur</h1>
      <p class="dg-muted">30 saniyede kayıt ol, ilk videonu izle ve D-Coin kazan.</p>
    </div>

    <?php if ($err): ?><div class="dg-alert dg-alert-error"><?=e($err)?></div><?php endif; ?>

    <form method="post" action="auth.php" class="dg-form" autocomplete="on">
      <?=csrf_field()?>
      <input type="hidden" name="action" value="register">
      <label class="dg-field">
        <span>Kullanıcı Adı</span>
        <input type="text" name="username" required minlength="3" maxlength="32" placeholder="oyuncu_ismi" pattern="[a-zA-Z0-9_.-]+">
      </label>
      <label class="dg-field">
        <span>E-posta</span>
        <input type="email" name="email" required placeholder="sen@ornek.com">
      </label>
      <label class="dg-field">
        <span>Şifre</span>
        <input type="password" name="password" required minlength="6" placeholder="En az 6 karakter">
      </label>
      <label class="dg-field">
        <span>Şifre (Tekrar)</span>
        <input type="password" name="password2" required placeholder="Şifreyi doğrula">
      </label>
      <label class="dg-check"><input type="checkbox" required> <span>Kullanım koşullarını ve gizlilik metnini okudum, onaylıyorum.</span></label>
      <button type="submit" class="dg-btn dg-btn-primary dg-btn-block dg-btn-glow">KAYIT OL & BAĞLAN →</button>
    </form>

    <div class="dg-auth-foot">
      <p>Zaten hesabın var mı? <a href="login.php">Giriş yap</a></p>
    </div>
  </div>

  <div class="dg-auth-side">
    <div class="dg-side-card dg-side-highlight">
      <h3>🎁 Hoş geldin bonusu</h3>
      <p>Kayıt sonrası ilk 5 videonun her biri <b>+D-Coin</b> kazandırır. Günlük limit otomatik sıfırlanır.</p>
    </div>
    <div class="dg-side-card">
      <div class="dg-side-icon">🛡️</div>
      <h3>Güvenli & Hızlı</h3>
      <p>Şifreler bcrypt ile saklanır. Kart bilgisi sormuyoruz.</p>
    </div>
  </div>
</main>
</body>
</html>
