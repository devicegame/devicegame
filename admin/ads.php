<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_layout.php';

// İşlemler
if ($_SERVER['REQUEST_METHOD']==='POST') {
  if (!csrf_verify($_POST['csrf'] ?? '')) { flash_error_set('CSRF hatası'); header('Location: ads.php'); exit; }

  // Ekle / Güncelle
  if (isset($_POST['save_ad'])) {
    $id = (int)($_POST['id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $video_url = trim($_POST['video_url'] ?? '');
    $reward = (int)($_POST['reward_dcoin'] ?? 0);
    $duration = (int)($_POST['duration_sec'] ?? 30);
    $is_active = isset($_POST['is_active']) ? 1:0;

    if ($title==='' || $video_url==='') { flash_error_set('Başlık ve video URL zorunlu'); header('Location: ads.php'); exit; }
    if ($reward<1 || $reward>1000) $reward = 10;
    if ($duration<3 || $duration>600) $duration = 30;

    // Dosya yükleme varsa işle
    $uploaded_path = null;
    if (!empty($_FILES['video_file']['name']) && $_FILES['video_file']['error']===UPLOAD_ERR_OK) {
      $ext = strtolower(pathinfo($_FILES['video_file']['name'], PATHINFO_EXTENSION));
      if (!in_array($ext, ['mp4','webm','ogg'])) { flash_error_set('Sadece .mp4/.webm/.ogg yüklenebilir'); header('Location: ads.php'); exit; }
      if ($_FILES['video_file']['size'] > 80*1024*1024) { flash_error_set('Dosya en fazla 80MB olabilir'); header('Location: ads.php'); exit; }
      $fname = 'ad_'.time().'_'.bin2hex(random_bytes(4)).'.'.$ext;
      $dest = DG_UPLOAD_DIR.'/'.$fname;
      if (move_uploaded_file($_FILES['video_file']['tmp_name'], $dest)) {
        $uploaded_path = DG_UPLOAD_URL.$fname;
        $video_url = $uploaded_path; // yüklenen dosya URL'i kullan
      }
    }

    if ($id>0) {
      // Güncelle — eğer yeni dosya yüklenmediyse mevcut video_path'i koru
      if ($uploaded_path) {
        $pdo->prepare("UPDATE ads SET title=?, video_url=?, video_path=?, reward_dcoin=?, duration_sec=?, is_active=? WHERE id=?")
            ->execute([$title,$video_url,$uploaded_path,$reward,$duration,$is_active,$id]);
      } else {
        $pdo->prepare("UPDATE ads SET title=?, video_url=?, reward_dcoin=?, duration_sec=?, is_active=? WHERE id=?")
            ->execute([$title,$video_url,$reward,$duration,$is_active,$id]);
      }
      flash_set('Reklam güncellendi.');
    } else {
      $pdo->prepare("INSERT INTO ads (title, video_url, video_path, reward_dcoin, duration_sec, is_active) VALUES (?,?,?,?,?,?)")
          ->execute([$title,$video_url,$uploaded_path,$reward,$duration,$is_active]);
      flash_set('Reklam eklendi.');
    }
    header('Location: ads.php'); exit;
  }

  if (isset($_POST['delete_ad'])) {
    $id=(int)($_POST['id']??0);
    $pdo->prepare("DELETE FROM ads WHERE id=?")->execute([$id]);
    flash_set('Reklam silindi.');
    header('Location: ads.php'); exit;
  }
  if (isset($_POST['toggle_ad'])) {
    $id=(int)($_POST['id']??0);
    $pdo->prepare("UPDATE ads SET is_active = 1 - is_active WHERE id=?")->execute([$id]);
    header('Location: ads.php'); exit;
  }
}

// Liste
$ads = $pdo->query("SELECT * FROM ads ORDER BY created_at DESC")->fetchAll();
$edit = null;
if (isset($_GET['edit'])) {
  $eid=(int)$_GET['edit'];
  $st=$pdo->prepare("SELECT * FROM ads WHERE id=?"); $st->execute([$eid]); $edit=$st->fetch();
}

admin_head('Reklam Yönetimi');
?>

<div style="display:flex;align-items:end;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:18px">
  <div>
    <p class="dg-kicker">ADS // VIDEO MANAGEMENT</p>
    <h1 style="font-family:Orbitron,monospace;font-weight:800;font-size:22px">Reklam Yönetimi</h1>
    <p class="dg-muted" style="font-size:13px">Video ekle, düzenle, duraklat. Scrubbing zaten yasak — anti-cheat korunuyor.</p>
  </div>
  <span class="dg-badge"><?=count($ads)?> REKLAM</span>
</div>

<div style="display:grid;grid-template-columns:1fr 1.2fr;gap:16px;align-items:start">
  <!-- Form -->
  <div class="dg-card">
    <div class="dg-card-head"><h2><?= $edit ? '✏ Düzenle' : '＋ Yeni Reklam Ekle' ?></h2><?php if($edit): ?><a href="ads.php" class="dg-btn dg-btn-ghost dg-btn-sm">İptal</a><?php endif; ?></div>
    <div class="dg-card-body">
      <form method="post" enctype="multipart/form-data" class="dg-form">
        <?=csrf_field()?>
        <input type="hidden" name="id" value="<?= (int)($edit['id']??0) ?>">
        <label class="dg-field"><span>Reklam Başlığı *</span><input type="text" name="title" required maxlength="180" placeholder="Cyber Hunt — Fragman" value="<?=e($edit['title']??'')?>"></label>
        <label class="dg-field"><span>Video URL * <small style="font-weight:400;text-transform:none;letter-spacing:0;color:var(--dim)">— dış link veya yükle</small></span><input type="url" name="video_url" required placeholder="https://.../video.mp4 veya uploads/..." value="<?=e($edit['video_url']??'')?>"></label>
        <label class="dg-field"><span>Video Dosyası Yükle <small style="font-weight:400;text-transform:none;letter-spacing:0;color:var(--dim)">.mp4/.webm — max 80MB — URL’i otomatik doldurur</small></span><input type="file" name="video_file" accept=".mp4,.webm,.ogg,video/mp4,video/webm"></label>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <label class="dg-field"><span>Kazanç (D-Coin) *</span><input type="number" name="reward_dcoin" min="1" max="1000" required value="<?= (int)($edit['reward_dcoin']??10) ?>"></label>
          <label class="dg-field"><span>Süre (saniye) *</span><input type="number" name="duration_sec" min="3" max="600" required value="<?= (int)($edit['duration_sec']??30) ?>"></label>
        </div>
        <label class="dg-check"><input type="checkbox" name="is_active" value="1" <?= !isset($edit['is_active'])|| (int)$edit['is_active']===1 ? 'checked':'' ?>> <span>Aktif (kullanıcılar görebilir)</span></label>
        <button name="save_ad" value="1" class="dg-btn dg-btn-primary dg-btn-block"><?= $edit ? 'Güncelle' : 'Reklamı Ekle' ?> →</button>
        <p class="dg-mini">İpucu: Video süresini doğru gir — ödül sadece süre dolunca verilir.</p>
      </form>
    </div>
  </div>

  <!-- Liste -->
  <div class="dg-card">
    <div class="dg-card-head"><h2>Mevcut Reklamlar</h2></div>
    <?php if(!$ads): ?><div class="dg-empty">Henüz reklam yok</div>
    <?php else: ?>
    <div style="display:flex;flex-direction:column;gap:10px;padding:14px">
      <?php foreach($ads as $a): ?>
        <div style="display:flex;gap:12px;align-items:center;background:var(--card2);border:1px solid <?= $a['is_active'] ? 'rgba(0,229,255,0.18)' : 'var(--line)' ?>;border-radius:14px;padding:12px">
          <div style="width:84px;height:48px;border-radius:10px;overflow:hidden;background:#000;flex-shrink:0">
            <video src="<?=e($a['video_url'])?>" muted preload="metadata" style="width:100%;height:100%;object-fit:cover"></video>
          </div>
          <div style="flex:1;min-width:0">
            <b style="font-size:13px"><?=e($a['title'])?></b>
            <div style="font:500 11px JetBrains Mono,monospace;color:var(--muted);margin-top:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">ID #<?= (int)$a['id'] ?> • <?= (int)$a['reward_dcoin'] ?> D-Coin • <?= (int)$a['duration_sec'] ?>s • <?=e($a['video_url'])?></div>
            <div style="margin-top:6px;display:flex;gap:6px">
              <span class="dg-chip <?= $a['is_active']?'on':'' ?>"><?= $a['is_active']?'● Aktif':'○ Pasif' ?></span>
              <span class="dg-chip"><?=e($a['created_at'])?></span>
            </div>
          </div>
          <div style="display:flex;flex-direction:column;gap:6px">
            <a href="ads.php?edit=<?= (int)$a['id'] ?>" class="dg-btn dg-btn-ghost dg-btn-sm" style="font-size:11px">Düzenle</a>
            <form method="post" style="margin:0"><?=csrf_field()?><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><button name="toggle_ad" class="dg-btn <?= $a['is_active']?'dg-btn-ghost':'dg-btn-primary' ?> dg-btn-sm" style="font-size:11px;width:100%"><?= $a['is_active']?'Duraklat':'Aktifleştir' ?></button></form>
            <form method="post" style="margin:0" onsubmit="return confirm('Silinsin mi?')"><?=csrf_field()?><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><button name="delete_ad" class="dg-btn dg-btn-ghost dg-btn-sm" style="font-size:11px;width:100%;color:var(--magenta);border-color:rgba(255,42,109,0.25)">Sil</button></form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php admin_foot(); ?>
