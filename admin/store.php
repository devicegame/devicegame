<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_layout.php';

if ($_SERVER['REQUEST_METHOD']==='POST') {
  if (!csrf_verify($_POST['csrf'] ?? '')) { flash_error_set('CSRF hatası'); header('Location: store.php'); exit; }

  if (isset($_POST['save_item'])) {
    $id = (int)($_POST['id']??0);
    $name = trim($_POST['name']??'');
    $desc = trim($_POST['description']??'');
    $image_url = trim($_POST['image_url']??'');
    $price_tl = (float)($_POST['price_tl']??0);
    $price_dcoin = (int)($_POST['price_dcoin']??0);
    $is_active = isset($_POST['is_active'])?1:0;
    $keys_raw = trim($_POST['keys']??'');

    if ($name==='') { flash_error_set('Ürün adı zorunlu'); header('Location: store.php'); exit; }
    if ($price_tl<0) $price_tl=0;
    if ($price_dcoin<0) $price_dcoin=0;

    if ($id>0) {
      $pdo->prepare("UPDATE store_items SET name=?, description=?, image_url=?, price_tl=?, price_dcoin=?, is_active=? WHERE id=?")
          ->execute([$name,$desc,$image_url,$price_tl,$price_dcoin,$is_active,$id]);
      // Yeni keyler varsa ekle
      if ($keys_raw!=='') {
        $keys = preg_split('/\r\n|\n|\r|,/', $keys_raw);
        $ins=$pdo->prepare("INSERT INTO store_keys (store_item_id, key_code) VALUES (?,?)");
        foreach($keys as $k){ $k=trim($k); if($k!=='') $ins->execute([$id,$k]); }
        // stok güncelle
        $pdo->prepare("UPDATE store_items SET stock=(SELECT COUNT(*) FROM store_keys WHERE store_item_id=? AND is_used=0) WHERE id=?")->execute([$id,$id]);
      }
      flash_set('Ürün güncellendi.');
    } else {
      $pdo->prepare("INSERT INTO store_items (name, description, image_url, price_tl, price_dcoin, is_active) VALUES (?,?,?,?,?,?)")
          ->execute([$name,$desc,$image_url,$price_tl,$price_dcoin,$is_active]);
      $newId=(int)$pdo->lastInsertId();
      if ($keys_raw!=='') {
        $keys = preg_split('/\r\n|\n|\r|,/', $keys_raw);
        $ins=$pdo->prepare("INSERT INTO store_keys (store_item_id, key_code) VALUES (?,?)");
        foreach($keys as $k){ $k=trim($k); if($k!=='') $ins->execute([$newId,$k]); }
        $pdo->prepare("UPDATE store_items SET stock=(SELECT COUNT(*) FROM store_keys WHERE store_item_id=? AND is_used=0) WHERE id=?")->execute([$newId,$newId]);
      }
      flash_set('Ürün eklendi.');
    }
    header('Location: store.php'); exit;
  }
  if (isset($_POST['delete_item'])) {
    $id=(int)($_POST['id']??0);
    $pdo->prepare("DELETE FROM store_items WHERE id=?")->execute([$id]);
    flash_set('Ürün silindi.');
    header('Location: store.php'); exit;
  }
  if (isset($_POST['toggle_item'])) {
    $id=(int)($_POST['id']??0);
    $pdo->prepare("UPDATE store_items SET is_active=1-is_active WHERE id=?")->execute([$id]);
    header('Location: store.php'); exit;
  }
  if (isset($_POST['delete_key'])) {
    $kid=(int)($_POST['key_id']??0);
    $iid=(int)($_POST['item_id']??0);
    $pdo->prepare("DELETE FROM store_keys WHERE id=? AND is_used=0")->execute([$kid]);
    $pdo->prepare("UPDATE store_items SET stock=(SELECT COUNT(*) FROM store_keys WHERE store_item_id=? AND is_used=0) WHERE id=?")->execute([$iid,$iid]);
    header('Location: store.php?edit='.$iid); exit;
  }
}

$items = $pdo->query("SELECT si.*, (SELECT COUNT(*) FROM store_keys WHERE store_item_id=si.id AND is_used=0) as avail, (SELECT COUNT(*) FROM store_keys WHERE store_item_id=si.id AND is_used=1) as used FROM store_items ORDER BY si.created_at DESC")->fetchAll();
$edit=null;
if (isset($_GET['edit'])) {
  $eid=(int)$_GET['edit'];
  $st=$pdo->prepare("SELECT * FROM store_items WHERE id=?"); $st->execute([$eid]); $edit=$st->fetch();
  $keys=[]; if($edit){ $keys=$pdo->prepare("SELECT * FROM store_keys WHERE store_item_id=? ORDER BY id DESC"); $keys->execute([$eid]); $keys=$keys->fetchAll(); }
}

admin_head('Mağaza Yönetimi');
?>

<div style="display:flex;align-items:end;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:18px">
  <div>
    <p class="dg-kicker">STORE // INVENTORY</p>
    <h1 style="font-family:Orbitron,monospace;font-weight:800;font-size:22px">Mağaza & Stok Yönetimi</h1>
    <p class="dg-muted" style="font-size:13px">Tek mağaza mimarisi — her ürün hem TL hem D-Coin fiyatına sahip. Key kodlarını satır satır ekle.</p>
  </div>
  <span class="dg-badge"><?=count($items)?> ÜRÜN</span>
</div>

<div style="display:grid;grid-template-columns:1fr 1.15fr;gap:16px;align-items:start">
  <!-- Form -->
  <div class="dg-card">
    <div class="dg-card-head"><h2><?= $edit ? '✏ Ürünü Düzenle' : '＋ Yeni Ürün Ekle' ?></h2><?php if($edit): ?><a href="store.php" class="dg-btn dg-btn-ghost dg-btn-sm">İptal</a><?php endif; ?></div>
    <div class="dg-card-body">
      <form method="post" class="dg-form">
        <?=csrf_field()?>
        <input type="hidden" name="id" value="<?= (int)($edit['id']??0) ?>">
        <label class="dg-field"><span>Ürün Adı *</span><input type="text" name="name" required maxlength="180" placeholder="Cyberpunk 2077 — Steam Key" value="<?=e($edit['name']??'')?>"></label>
        <label class="dg-field"><span>Açıklama</span><textarea name="description" rows="3" placeholder="Kısa açıklama — anında teslim, bölge TR vb."><?=e($edit['description']??'')?></textarea></label>
        <label class="dg-field"><span>Görsel URL</span><input type="url" name="image_url" placeholder="https://images.unsplash.com/..." value="<?=e($edit['image_url']??'')?>"></label>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <label class="dg-field"><span>TL Fiyatı (₺)</span><input type="number" step="0.01" min="0" name="price_tl" required value="<?=e($edit['price_tl']??'99.00')?>"></label>
          <label class="dg-field"><span>D-Coin Fiyatı</span><input type="number" min="0" name="price_dcoin" required value="<?= (int)($edit['price_dcoin']??50) ?>"></label>
        </div>
        <label class="dg-field"><span>Stok / Key Kodları <small style="font-weight:400;text-transform:none;letter-spacing:0;color:var(--dim)">— her satır 1 key. Virgül de olur. Düzenlerken sadece yeni keyler ekle.</small></span><textarea name="keys" rows="5" placeholder="CP77-XXXX-0001&#10;CP77-XXXX-0002&#10;CP77-XXXX-0003"></textarea></label>
        <?php if($edit && !empty($keys)): ?>
          <div style="background:rgba(255,255,255,0.04);border:1px solid var(--line);border-radius:12px;padding:10px;max-height:180px;overflow:auto">
            <p style="font:700 10px JetBrains Mono,monospace;letter-spacing:0.08em;text-transform:uppercase;color:var(--muted);margin-bottom:8px">Mevcut Keyler (<?=count($keys)?>)</p>
            <?php foreach($keys as $k): ?>
              <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;background:<?= $k['is_used']?'rgba(255,42,109,0.08)':'rgba(0,230,118,0.08)' ?>;border:1px solid <?= $k['is_used']?'rgba(255,42,109,0.2)':'rgba(0,230,118,0.2)' ?>;border-radius:8px;padding:6px 10px;margin-bottom:6px;font:600 11px JetBrains Mono,monospace">
                <span style="word-break:break-all"><?=e($k['key_code'])?> <?= $k['is_used']?'<small style="color:var(--magenta)">• KULLANILDI</small>':'<small style="color:#7CFFB2">• BOŞTA</small>' ?></span>
                <?php if(!(int)$k['is_used']): ?><form method="post" style="margin:0"><?=csrf_field()?><input type="hidden" name="item_id" value="<?= (int)$edit['id'] ?>"><input type="hidden" name="key_id" value="<?= (int)$k['id'] ?>"><button name="delete_key" style="background:none;border:none;color:var(--magenta);cursor:pointer;font-size:11px">✕</button></form><?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <label class="dg-check"><input type="checkbox" name="is_active" value="1" <?= !isset($edit['is_active'])|| (int)$edit['is_active']===1 ? 'checked':'' ?>> <span>Aktif (mağazada görünsün)</span></label>
        <button name="save_item" class="dg-btn dg-btn-primary dg-btn-block"><?= $edit ? 'Güncelle' : 'Ürünü Ekle' ?> →</button>
      </form>
    </div>
  </div>

  <!-- Liste -->
  <div class="dg-card">
    <div class="dg-card-head"><h2>Ürünler</h2></div>
    <?php if(!$items): ?><div class="dg-empty">Henüz ürün yok</div>
    <?php else: ?>
    <div style="display:flex;flex-direction:column;gap:10px;padding:14px">
      <?php foreach($items as $it): ?>
        <div style="display:flex;gap:12px;align-items:center;background:var(--card2);border:1px solid <?= $it['is_active']?'rgba(0,229,255,0.18)':'var(--line)' ?>;border-radius:14px;padding:12px">
          <img src="<?=e($it['image_url'] ?: 'https://images.unsplash.com/photo-1511512578047-dfb367046420?w=200&q=80')?>" alt="" style="width:64px;height:64px;border-radius:10px;object-fit:cover;flex-shrink:0">
          <div style="flex:1;min-width:0">
            <b style="font-size:13px"><?=e($it['name'])?></b>
            <div style="font:600 11px JetBrains Mono,monospace;color:var(--muted);margin-top:2px"><?=tl_format($it['price_tl'])?> • ◈ <?= (int)$it['price_dcoin'] ?> • <span style="color:<?= $it['avail']>0?'#7CFFB2':'var(--magenta)' ?>"><?= (int)$it['avail'] ?> stok</span> / <?= (int)$it['used'] ?> satıldı</div>
            <div style="margin-top:6px;display:flex;gap:6px"><span class="dg-chip <?= $it['is_active']?'on':'' ?>"><?= $it['is_active']?'Aktif':'Pasif' ?></span><span class="dg-chip">ID #<?= (int)$it['id'] ?></span></div>
          </div>
          <div style="display:flex;flex-direction:column;gap:6px">
            <a href="store.php?edit=<?= (int)$it['id'] ?>" class="dg-btn dg-btn-ghost dg-btn-sm" style="font-size:11px">Düzenle</a>
            <form method="post" style="margin:0"><?=csrf_field()?><input type="hidden" name="id" value="<?= (int)$it['id'] ?>"><button name="toggle_item" class="dg-btn <?= $it['is_active']?'dg-btn-ghost':'dg-btn-primary' ?> dg-btn-sm" style="font-size:11px;width:100%"><?= $it['is_active']?'Pasife Al':'Aktifleştir' ?></button></form>
            <form method="post" style="margin:0" onsubmit="return confirm('Ürün ve tüm keyleri silinsin mi?')"><?=csrf_field()?><input type="hidden" name="id" value="<?= (int)$it['id'] ?>"><button name="delete_item" class="dg-btn dg-btn-ghost dg-btn-sm" style="font-size:11px;width:100%;color:var(--magenta);border-color:rgba(255,42,109,0.25)">Sil</button></form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php admin_foot(); ?>
