<?php
require_once __DIR__ . '/db.php';
if (is_logged_in()) { header('Location: index.php'); exit; }
$err = flash_error_get();
$flash = flash_get();
$next = $_GET['next'] ?? '';
?>
<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Giriş Yap — DeviceGame</title>
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
      <a href="register.php" class="dg-btn dg-btn-ghost">Kayıt Ol</a>
    </nav>
  </div>
</header>

<main class="dg-auth-wrap">
  <div class="dg-auth-card">
    <div class="dg-auth-head">
      <p class="dg-kicker">// AUTH // SECURE LOGIN</p>
      <h1>Sisteme Giriş</h1>
      <p class="dg-muted">DeviceGame evrenine bağlan. D-Coin bakiyen seni bekliyor.</p>
    </div>

    <?php if ($err): ?><div class="dg-alert dg-alert-error"><?=e($err)?></div><?php endif; ?>
    <?php if ($flash): ?><div class="dg-alert dg-alert-success"><?=e($flash['msg'])?></div><?php endif; ?>
    <?php if (isset($_GET['banned'])): ?><div class="dg-alert dg-alert-error">Hesabın engellenmiş. Destek ile iletişime geç.</div><?php endif; ?>

    <form method="post" action="auth.php" class="dg-form" autocomplete="on">
      <?=csrf_field()?>
      <input type="hidden" name="action" value="login">
      <input type="hidden" name="next" value="<?=e($next)?>">
      <label class="dg-field">
        <span>Kullanıcı adı veya E-posta</span>
        <input type="text" name="login" required placeholder="oyuncu1 veya oyuncu1@mail.com" autofocus>
      </label>
      <label class="dg-field">
        <span>Şifre</span>
        <input type="password" name="password" required placeholder="••••••••">
      </label>
      <button type="submit" class="dg-btn dg-btn-primary dg-btn-block dg-btn-glow">GİRİŞ YAP →</button>
    </form>

    <div class="dg-auth-foot">
      <p>Hesabın yok mu? <a href="register.php">Hemen kayıt ol</a></p>
      <p class="dg-mini">Demo admin: <code>admin@devicegame.com</code> / <code>Admin123!</code></p>
    </div>
  </div>

  <div class="dg-auth-side">
    <div class="dg-side-card">
      <div class="dg-side-icon">◉</div>
      <h3>Anti-Cheat Korumalı</h3>
      <p>Sekme değiştirirsen video durur. Sadece sonuna kadar izleyen kazanır.</p>
    </div>
    <div class="dg-side-card">
      <div class="dg-side-icon">⬡</div>
      <h3>D-Coin ile Özgürsün</h3>
      <p>Reklam izle, D-Coin kazan, mağazada TL yerine coin ile öde.</p>
    </div>
    <div class="dg-side-stats">
      <div><b>5</b><span>Günlük limit</span></div>
      <div><b>∞</b><span>Mağaza ürün</span></div>
      <div><b>⚡</b><span>Anında teslim</span></div>
    </div>
  </div>
</main>

</body>
</html>
