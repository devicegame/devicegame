<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_layout.php';

// İstatistikler
$total_users = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_admins = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE is_admin=1")->fetchColumn();
$total_banned = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE is_banned=1")->fetchColumn();
$total_dcoin_circulating = (int)$pdo->query("SELECT COALESCE(SUM(dcoin_balance),0) FROM users")->fetchColumn();
$total_dcoin_distributed = (int)$pdo->query("SELECT COALESCE(SUM(earned_dcoin),0) FROM user_watch_logs")->fetchColumn();
$total_watches = (int)$pdo->query("SELECT COUNT(*) FROM user_watch_logs")->fetchColumn();
$total_purchases = (int)$pdo->query("SELECT COUNT(*) FROM purchases")->fetchColumn();
$total_revenue_tl = $pdo->query("SELECT COALESCE(SUM(price_paid_tl),0) FROM purchases WHERE payment_method='cash'")->fetchColumn();
$today_watches = (int)$pdo->query("SELECT COUNT(*) FROM user_watch_logs WHERE DATE(watched_at)=CURDATE()")->fetchColumn();
$today_users = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE DATE(created_at)=CURDATE()")->fetchColumn();
$active_ads = (int)$pdo->query("SELECT COUNT(*) FROM ads WHERE is_active=1")->fetchColumn();
$active_products = (int)$pdo->query("SELECT COUNT(*) FROM store_items WHERE is_active=1")->fetchColumn();

// Son hareketler
$recent_watches = $pdo->query("SELECT l.*, u.username, a.title FROM user_watch_logs l JOIN users u ON u.id=l.user_id JOIN ads a ON a.id=l.ad_id ORDER BY l.watched_at DESC LIMIT 6")->fetchAll();
$recent_purchases = $pdo->query("SELECT p.*, u.username, si.name FROM purchases p JOIN users u ON u.id=p.user_id JOIN store_items si ON si.id=p.store_item_id ORDER BY p.created_at DESC LIMIT 6")->fetchAll();

// Ayar: günlük limit güncelleme
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['update_limit'])) {
  if (!csrf_verify($_POST['csrf'] ?? '')) { flash_error_set('CSRF hatası'); header('Location: index.php'); exit; }
  $new = max(1, min(50, (int)($_POST['daily_limit'] ?? 5)));
  $pdo->prepare("INSERT INTO settings (skey,svalue) VALUES ('daily_watch_limit',?) ON DUPLICATE KEY UPDATE svalue=?")->execute([$new,$new]);
  flash_set("Günlük limit $new olarak güncellendi.");
  header('Location: index.php'); exit;
}
$cur_limit = daily_watch_limit($pdo);

admin_head('Dashboard');
?>
<div style="display:flex;align-items:end;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:18px">
  <div>
    <p class="dg-kicker">ADMIN // CONTROL CENTER</p>
    <h1 style="font-family:Orbitron,monospace;font-weight:800;font-size:26px;letter-spacing:-0.02em">Genel Bakış</h1>
    <p class="dg-muted" style="font-size:13px">Sistemin kalbi burada atıyor. Tüm metrikler canlı.</p>
  </div>
  <div style="display:flex;gap:8px">
    <a href="ads.php" class="dg-btn dg-btn-primary dg-btn-sm">+ Reklam Ekle</a>
    <a href="store.php" class="dg-btn dg-btn-ghost dg-btn-sm">+ Ürün Ekle</a>
  </div>
</div>

<div class="dg-stats">
  <div class="dg-stat" style="border-color:rgba(0,229,255,0.25)"><label>👥 Toplam Kullanıcı</label><strong><?=$total_users?></strong><small><?=$total_admins?> admin • <?=$total_banned?> banlı</small></div>
  <div class="dg-stat" style="border-color:rgba(255,214,10,0.35)"><label>◈ Dolaşımdaki D-Coin</label><strong style="color:var(--amber)"><?=number_format($total_dcoin_circulating)?></strong><small>Dağıtılan toplam: <?=number_format($total_dcoin_distributed)?></small></div>
  <div class="dg-stat" style="border-color:rgba(123,45,255,0.3)"><label>▶ İzlenen Reklam</label><strong style="color:var(--purple2)"><?=number_format($total_watches)?></strong><small>Bugün: <?=$today_watches?> izleme</small></div>
  <div class="dg-stat" style="border-color:rgba(0,230,118,0.25)"><label>🛒 Satılan Ürün</label><strong style="color:var(--green)"><?=number_format($total_purchases)?></strong><small>Nakit ciro: <?=tl_format($total_revenue_tl)?></small></div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:16px">
  <div class="dg-card">
    <div class="dg-card-head"><h2>⚙ Hızlı Ayar</h2></div>
    <div class="dg-card-body">
      <form method="post" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap">
        <?=csrf_field()?>
        <label class="dg-field" style="flex:1;min-width:160px"><span>Günlük İzleme Limiti</span><input type="number" name="daily_limit" min="1" max="50" value="<?=$cur_limit?>"></label>
        <button name="update_limit" value="1" class="dg-btn dg-btn-primary">Güncelle</button>
      </form>
      <p class="dg-mini" style="margin-top:8px">Kullanıcı başına günde maksimum video. Varsayılan 5.</p>
      <div style="display:flex;gap:8px;margin-top:14px;flex-wrap:wrap">
        <span class="dg-chip on">Aktif reklam: <?=$active_ads?></span>
        <span class="dg-chip on">Aktif ürün: <?=$active_products?></span>
        <span class="dg-chip">Bugün kayıt: <?=$today_users?></span>
      </div>
    </div>
  </div>
  <div class="dg-card">
    <div class="dg-card-head"><h2>📈 Sistem Durumu</h2><span class="dg-badge dg-badge-live">CANLI</span></div>
    <div class="dg-card-body" style="display:grid;gap:10px">
      <div style="display:flex;justify-content:space-between;align-items:center;background:rgba(0,229,255,0.06);border:1px solid var(--line);border-radius:12px;padding:10px 14px"><span style="font-size:13px">Veritabanı</span><span style="font:700 11px JetBrains Mono,monospace;background:rgba(0,230,118,0.15);color:#7CFFB2;padding:5px 10px;border-radius:999px">● Bağlı</span></div>
      <div style="display:flex;justify-content:space-between;align-items:center;background:rgba(255,255,255,0.03);border:1px solid var(--line);border-radius:12px;padding:10px 14px"><span style="font-size:13px">Anti-Cheat</span><span style="font:700 11px JetBrains Mono,monospace;background:rgba(0,229,255,0.12);color:var(--cyan);padding:5px 10px;border-radius:999px">🛡️ Aktif</span></div>
      <div style="display:flex;justify-content:space-between;align-items:center;background:rgba(255,255,255,0.03);border:1px solid var(--line);border-radius:12px;padding:10px 14px"><span style="font-size:13px">PHP Sürümü</span><span class="dg-chip"><?=PHP_VERSION?></span></div>
    </div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:16px">
  <div class="dg-card">
    <div class="dg-card-head"><h2>Son İzlemeler</h2><a href="logs.php" class="dg-btn dg-btn-ghost dg-btn-sm">Tümü →</a></div>
    <div style="overflow:auto">
      <table class="dg-table" style="min-width:unset">
        <thead><tr><th>Kullanıcı</th><th>Reklam</th><th>+Coin</th><th>Zaman</th></tr></thead>
        <tbody>
        <?php if(!$recent_watches): ?><tr><td colspan="4" class="dg-empty">Henüz izleme yok</td></tr><?php endif; ?>
        <?php foreach($recent_watches as $r): ?>
          <tr><td><b><?=e($r['username'])?></b></td><td style="max-width:160px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?=e($r['title'])?></td><td><span style="background:linear-gradient(135deg,#FFD60A,#FF8C00);color:#1a1200;font:700 11px JetBrains Mono,monospace;padding:4px 8px;border-radius:999px">+<?= (int)$r['earned_dcoin'] ?></span></td><td><small class="dg-mini"><?=e($r['watched_at'])?></small></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="dg-card">
    <div class="dg-card-head"><h2>Son Satışlar</h2><a href="logs.php#sat" class="dg-btn dg-btn-ghost dg-btn-sm">Tümü →</a></div>
    <div style="overflow:auto">
      <table class="dg-table" style="min-width:unset">
        <thead><tr><th>Kullanıcı</th><th>Ürün</th><th>Ödeme</th><th>Zaman</th></tr></thead>
        <tbody>
        <?php if(!$recent_purchases): ?><tr><td colspan="4" class="dg-empty">Henüz satış yok</td></tr><?php endif; ?>
        <?php foreach($recent_purchases as $r): ?>
          <tr><td><b><?=e($r['username'])?></b></td><td style="max-width:160px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?=e($r['name'])?></td><td><?php if($r['payment_method']=='dcoin'): ?><span style="background:rgba(255,214,10,0.15);color:var(--amber);font:700 11px JetBrains Mono,monospace;padding:4px 8px;border-radius:999px;border:1px solid rgba(255,214,10,0.25)">◈ <?= (int)$r['price_paid_dcoin'] ?></span><?php else: ?><span style="background:rgba(0,230,118,0.12);color:#7CFFB2;font:700 11px JetBrains Mono,monospace;padding:4px 8px;border-radius:999px;border:1px solid rgba(0,230,118,0.2)">₺ <?=tl_format($r['price_paid_tl'])?></span><?php endif;?></td><td><small class="dg-mini"><?=e($r['created_at'])?></small></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php admin_foot(); ?>
