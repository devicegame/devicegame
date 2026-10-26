<?php
// Admin layout helpers
function admin_head(string $title): void {
  $u = $GLOBALS['user'] ?? null;
?>
<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=$title?> — DeviceGame Admin</title>
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600;700&family=Orbitron:wght@600;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/devicegame.css">
<style>
.admin-wrap{max-width:1280px;margin:0 auto;padding:20px 24px 60px}
.admin-topbar{position:sticky;top:0;z-index:30;background:rgba(7,10,20,0.9);backdrop-filter:blur(12px);border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 24px;flex-wrap:wrap}
.admin-tabs{display:flex;gap:8px;flex-wrap:wrap}
.admin-tabs a{font:600 12px JetBrains Mono,monospace;letter-spacing:0.06em;text-transform:uppercase;padding:9px 14px;border-radius:12px;border:1px solid var(--line);color:var(--muted);background:rgba(255,255,255,0.03);transition:.2s}
.admin-tabs a:hover{color:var(--text);border-color:rgba(0,229,255,0.25)}
.admin-tabs a.active{color:#00141a;background:linear-gradient(135deg,var(--cyan),var(--purple));border-color:transparent;box-shadow:0 4px 16px rgba(0,229,255,0.25)}
</style>
</head>
<body class="dg-body">
<div class="dg-grid-bg"></div>
<div class="admin-topbar">
  <a href="../index.php" class="dg-logo" style="font-size:16px">DEVICE<span>GAME</span> <small style="font:600 10px JetBrains Mono,monospace;letter-spacing:0.12em;background:linear-gradient(135deg,var(--cyan),var(--purple));color:#fff;padding:4px 8px;border-radius:999px;margin-left:8px">ADMIN</small></a>
  <nav class="admin-tabs">
    <a href="index.php" class="<?=basename($_SERVER['SCRIPT_NAME'])==='index.php'?'active':''?>">📊 Dashboard</a>
    <a href="ads.php" class="<?=basename($_SERVER['SCRIPT_NAME'])==='ads.php'?'active':''?>">🎬 Reklamlar</a>
    <a href="users.php" class="<?=basename($_SERVER['SCRIPT_NAME'])==='users.php'?'active':''?>">👥 Kullanıcılar</a>
    <a href="store.php" class="<?=basename($_SERVER['SCRIPT_NAME'])==='store.php'?'active':''?>">🛒 Mağaza</a>
    <a href="logs.php" class="<?=basename($_SERVER['SCRIPT_NAME'])==='logs.php'?'active':''?>">📜 Loglar</a>
  </nav>
  <div style="display:flex;gap:8px;align-items:center">
    <span class="dg-user-pill"><b><?=e($u['username']??'admin')?></b> <span class="dg-coin"><i>◈</i> <?=number_format((int)($u['dcoin_balance']??0))?></span></span>
    <a href="../index.php" class="dg-btn dg-btn-ghost dg-btn-sm">Siteye Dön</a>
    <a href="../logout.php" class="dg-btn dg-btn-ghost dg-btn-sm">Çıkış</a>
  </div>
</div>
<?php
  $f = flash_get(); $fe = flash_error_get();
  if($f) echo '<div style="max-width:1280px;margin:14px auto 0;padding:0 24px"><div class="dg-alert dg-alert-success">'.e($f['msg']).'</div></div>';
  if($fe) echo '<div style="max-width:1280px;margin:14px auto 0;padding:0 24px"><div class="dg-alert dg-alert-error">'.e($fe).'</div></div>';
 echo '<main class="admin-wrap">';
}
function admin_foot(): void {
  echo '</main><footer style="text-align:center;color:var(--dim);font:500 11px JetBrains Mono,monospace;padding:18px">DeviceGame Admin • '.date('Y').' • Cyberpunk Edition</footer></body></html>';
}
