<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_layout.php';

$q = trim($_GET['q'] ?? '');
$tab = $_GET['tab'] ?? 'watch';
$page = max(1,(int)($_GET['page']??1));
$per=20; $off=($page-1)*$per;

// CSV export?
if (isset($_GET['export']) && $_GET['export']==='watch') {
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="izleme-log-'.date('Y-m-d').'.csv"');
  echo "\xEF\xBB\xBF";
  $out=fopen('php://output','w');
  fputcsv($out,['ID','Kullanıcı','E-posta','Reklam','Kazanılan D-Coin','Tarih','IP']);
  $rows=$pdo->query("SELECT l.*, u.username, u.email, a.title FROM user_watch_logs l JOIN users u ON u.id=l.user_id JOIN ads a ON a.id=l.ad_id ORDER BY l.watched_at DESC")->fetchAll();
  foreach($rows as $r) fputcsv($out,[$r['id'],$r['username'],$r['email'],$r['title'],$r['earned_dcoin'],$r['watched_at'],$r['ip_address']]);
  exit;
}
if (isset($_GET['export']) && $_GET['export']==='purchase') {
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="satin-alim-'.date('Y-m-d').'.csv"');
  echo "\xEF\xBB\xBF";
  $out=fopen('php://output','w');
  fputcsv($out,['ID','Kullanıcı','E-posta','Ürün','Ödeme','Tutar','Key','Tarih']);
  $rows=$pdo->query("SELECT p.*, u.username, u.email, si.name FROM purchases p JOIN users u ON u.id=p.user_id JOIN store_items si ON si.id=p.store_item_id ORDER BY p.created_at DESC")->fetchAll();
  foreach($rows as $r) fputcsv($out,[$r['id'],$r['username'],$r['email'],$r['name'],$r['payment_method'],$r['payment_method']=='dcoin'?$r['price_paid_dcoin']:$r['price_paid_tl'],$r['delivered_key'],$r['created_at']]);
  exit;
}

admin_head('Log & İzleme Geçmişi');
?>

<div style="display:flex;align-items:end;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:18px">
  <div>
    <p class="dg-kicker">LOGS // AUDIT TRAIL</p>
    <h1 style="font-family:Orbitron,monospace;font-weight:800;font-size:22px">Log & İzleme Geçmişi</h1>
    <p class="dg-muted" style="font-size:13px">Kim, ne zaman, ne izledi ve ne satın aldı — tam denetim.</p>
  </div>
  <div style="display:flex;gap:8px">
    <a href="?export=watch" class="dg-btn dg-btn-ghost dg-btn-sm">⬇ İzleme CSV</a>
    <a href="?export=purchase" class="dg-btn dg-btn-ghost dg-btn-sm">⬇ Satış CSV</a>
  </div>
</div>

<div style="display:flex;gap:8px;margin-bottom:14px">
  <a href="?tab=watch" class="dg-btn <?= $tab==='watch'?'dg-btn-primary':'dg-btn-ghost' ?> dg-btn-sm">▶ İzleme Logları</a>
  <a href="?tab=purchase" class="dg-btn <?= $tab==='purchase'?'dg-btn-primary':'dg-btn-ghost' ?> dg-btn-sm">🛒 Satın Alma Logları</a>
  <form method="get" style="margin-left:auto;display:flex;gap:6px">
    <input type="hidden" name="tab" value="<?=e($tab)?>">
    <div class="dg-search"><input type="search" name="q" value="<?=e($q)?>" placeholder="Kullanıcı / ürün / reklam ara..."></div>
    <button class="dg-btn dg-btn-ghost dg-btn-sm">Ara</button>
  </form>
</div>

<?php if($tab==='watch'):
  $where="1"; $params=[];
  if($q!==''){ $where="(u.username LIKE ? OR u.email LIKE ? OR a.title LIKE ?)"; $params=["%$q%","%$q%","%$q%"]; }
  $cnt=$pdo->prepare("SELECT COUNT(*) FROM user_watch_logs l JOIN users u ON u.id=l.user_id JOIN ads a ON a.id=l.ad_id WHERE $where"); $cnt->execute($params); $total=(int)$cnt->fetchColumn();
  $pages=max(1,ceil($total/$per));
  $st=$pdo->prepare("SELECT l.*, u.username, u.email, a.title FROM user_watch_logs l JOIN users u ON u.id=l.user_id JOIN ads a ON a.id=l.ad_id WHERE $where ORDER BY l.watched_at DESC LIMIT $per OFFSET $off"); $st->execute($params); $rows=$st->fetchAll();
?>
<div class="dg-card">
  <div class="dg-card-head"><h2>Reklam İzleme Geçmişi</h2><span class="dg-badge"><?=$total?> KAYIT</span></div>
  <div class="dg-table-wrap" style="border:none;border-radius:0">
  <table class="dg-table">
    <thead><tr><th>#</th><th>Kullanıcı</th><th>E-posta</th><th>Reklam</th><th>+Coin</th><th>Tarih</th><th>IP</th></tr></thead>
    <tbody>
    <?php if(!$rows): ?><tr><td colspan="7" class="dg-empty">Kayıt yok</td></tr><?php endif; ?>
    <?php foreach($rows as $r): ?>
      <tr><td><span class="dg-chip">#<?= (int)$r['id'] ?></span></td><td><b><?=e($r['username'])?></b></td><td style="font-size:12px;color:var(--muted)"><?=e($r['email'])?></td><td style="max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?=e($r['title'])?></td><td><span style="background:linear-gradient(135deg,#FFD60A,#FF8C00);color:#1a1200;font:700 11px JetBrains Mono,monospace;padding:4px 8px;border-radius:999px">+<?= (int)$r['earned_dcoin'] ?></span></td><td><small class="dg-mini"><?=e($r['watched_at'])?></small></td><td><small class="dg-mini"><?=e($r['ip_address'])?></small></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php if($pages>1): ?><div style="display:flex;gap:6px;justify-content:center;padding:12px;border-top:1px solid var(--line)"><?php for($i=1;$i<=$pages;$i++): ?><a href="?<?=http_build_query(['tab'=>'watch','q'=>$q,'page'=>$i])?>" class="dg-btn <?= $i===$page?'dg-btn-primary':'dg-btn-ghost' ?> dg-btn-sm"><?=$i?></a><?php endfor; ?></div><?php endif; ?>
</div>

<?php else:
  $where="1"; $params=[];
  if($q!==''){ $where="(u.username LIKE ? OR u.email LIKE ? OR si.name LIKE ?)"; $params=["%$q%","%$q%","%$q%"]; }
  $cnt=$pdo->prepare("SELECT COUNT(*) FROM purchases p JOIN users u ON u.id=p.user_id JOIN store_items si ON si.id=p.store_item_id WHERE $where"); $cnt->execute($params); $total=(int)$cnt->fetchColumn();
  $pages=max(1,ceil($total/$per));
  $st=$pdo->prepare("SELECT p.*, u.username, u.email, si.name FROM purchases p JOIN users u ON u.id=p.user_id JOIN store_items si ON si.id=p.store_item_id WHERE $where ORDER BY p.created_at DESC LIMIT $per OFFSET $off"); $st->execute($params); $rows=$st->fetchAll();
?>
<div class="dg-card" id="sat">
  <div class="dg-card-head"><h2>Mağaza Satın Alma Geçmişi</h2><span class="dg-badge"><?=$total?> KAYIT</span></div>
  <div class="dg-table-wrap" style="border:none;border-radius:0">
  <table class="dg-table">
    <thead><tr><th>#</th><th>Kullanıcı</th><th>Ürün</th><th>Ödeme</th><th>Tutar</th><th>Key</th><th>Tarih</th></tr></thead>
    <tbody>
    <?php if(!$rows): ?><tr><td colspan="7" class="dg-empty">Kayıt yok</td></tr><?php endif; ?>
    <?php foreach($rows as $r): ?>
      <tr><td><span class="dg-chip">#<?= (int)$r['id'] ?></span></td><td><b><?=e($r['username'])?></b><br><small style="color:var(--muted)"><?=e($r['email'])?></small></td><td><?=e($r['name'])?></td><td><?php if($r['payment_method']=='dcoin'): ?><span style="background:rgba(255,214,10,0.15);color:var(--amber);font:700 10px JetBrains Mono,monospace;padding:4px 8px;border-radius:999px;border:1px solid rgba(255,214,10,0.25)">D-Coin</span><?php else: ?><span style="background:rgba(0,230,118,0.12);color:#7CFFB2;font:700 10px JetBrains Mono,monospace;padding:4px 8px;border-radius:999px;border:1px solid rgba(0,230,118,0.2)">Nakit</span><?php endif;?></td><td><?php if($r['payment_method']=='dcoin'): ?><b>◈ <?= (int)$r['price_paid_dcoin'] ?></b><?php else: ?><b><?=tl_format($r['price_paid_tl'])?></b><?php endif;?></td><td><code style="background:rgba(0,229,255,0.08);border:1px solid rgba(0,229,255,0.15);padding:4px 8px;border-radius:8px;font:600 11px JetBrains Mono,monospace;color:var(--cyan)"><?=e($r['delivered_key'])?></code></td><td><small class="dg-mini"><?=e($r['created_at'])?></small></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php if($pages>1): ?><div style="display:flex;gap:6px;justify-content:center;padding:12px;border-top:1px solid var(--line)"><?php for($i=1;$i<=$pages;$i++): ?><a href="?<?=http_build_query(['tab'=>'purchase','q'=>$q,'page'=>$i])?>" class="dg-btn <?= $i===$page?'dg-btn-primary':'dg-btn-ghost' ?> dg-btn-sm"><?=$i?></a><?php endfor; ?></div><?php endif; ?>
</div>
<?php endif; ?>

<?php admin_foot(); ?>
