<?php
require_once __DIR__ . '/db.php';

$user = current_user($pdo);
$is_logged = $user !== null;

// Ayarlar
$daily_limit = daily_watch_limit($pdo);
$today_count = $is_logged ? today_watch_count($pdo, (int)$user['id']) : 0;
$remaining   = max(0, $daily_limit - $today_count);

// Aktif reklamlar
$ads = $pdo->query("SELECT * FROM ads WHERE is_active=1 ORDER BY created_at DESC")->fetchAll();
if (!$ads) {
  // Hiç reklam yoksa boş dizi
  $ads = [];
}
$active_ad = $ads[0] ?? null;

// Mağaza ürünleri
$store_items = $pdo->query("SELECT si.*, (SELECT COUNT(*) FROM store_keys WHERE store_item_id=si.id AND is_used=0) as keys_left FROM store_items WHERE is_active=1 ORDER BY created_at DESC")->fetchAll();

// Kullanıcının satın alımları (son 10)
$my_purchases = [];
if ($is_logged) {
  $st = $pdo->prepare("SELECT p.*, si.name as item_name FROM purchases p JOIN store_items si ON si.id=p.store_item_id WHERE p.user_id=? ORDER BY p.created_at DESC LIMIT 10");
  $st->execute([(int)$user['id']]);
  $my_purchases = $st->fetchAll();
}

$flash = flash_get();
$flash_err = flash_error_get();

?>
<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>DeviceGame — Oyna, İzle, Kazan. D-Coin Evreni</title>
<meta name="description" content="Video izle D-Coin kazan, mağazada coin ile alışveriş yap. Cyberpunk gamer evreni DeviceGame.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600;700&family=Orbitron:wght@600;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/devicegame.css">
</head>
<body class="dg-body">
<div class="dg-grid-bg"></div>

<header class="dg-nav">
  <div class="dg-nav-inner">
    <a href="index.php" class="dg-logo">DEVICE<span>GAME</span><i></i></a>
    <nav class="dg-nav-links">
      <a href="index.php" class="is-active">Ana Sayfa</a>
      <a href="#watch">Reklamlar</a>
      <a href="#store">Mağaza</a>
      <?php if ($is_logged && is_admin()): ?><a href="admin/index.php" style="color:var(--amber);border:1px solid rgba(255,214,10,0.3);background:rgba(255,214,10,0.08)">⚙ Admin</a><?php endif; ?>
      <?php if ($is_logged): ?>
        <span class="dg-user-pill">
          <b><?=e($user['username'])?></b>
          <span class="dg-coin"><i>◈</i> <span class="dg-balance-live" id="liveBalance"><?=number_format((int)$user['dcoin_balance'])?></span> D-Coin</span>
        </span>
        <a href="logout.php" class="dg-btn dg-btn-ghost dg-btn-sm">Çıkış</a>
      <?php else: ?>
        <a href="login.php" class="dg-btn dg-btn-ghost dg-btn-sm">Giriş</a>
        <a href="register.php" class="dg-btn dg-btn-primary dg-btn-sm">Kayıt Ol</a>
      <?php endif; ?>
    </nav>
  </div>
</header>

<?php if ($flash): ?>
<div class="dg-flash"><div class="dg-flash-inner"><?=e($flash['msg'])?></div></div>
<?php endif; ?>
<?php if ($flash_err): ?>
<div class="dg-flash"><div class="dg-flash-inner error"><?=e($flash_err)?></div></div>
<?php endif; ?>

<main class="dg-wrap">

  <!-- HERO -->
  <section class="dg-hero">
    <div>
      <p class="dg-kicker">DEVICE GAME // SEASON 01 // CYBER GRID</p>
      <h1>Oyna.<br>İzle.<br><em>Kazan.</em></h1>
      <p class="dg-muted" style="margin-top:12px;max-width:52ch;font-size:15px">Reklam izle, <b style="color:var(--amber)">D-Coin</b> kazan, mağazada nakit yerine coin ile öde. Anti-cheat korumalı, sekme değiştirirsen video durur — sadece sonuna kadar izleyen ödüllendirilir.</p>
      <div style="display:flex;gap:10px;margin-top:18px;flex-wrap:wrap">
        <a href="#watch" class="dg-btn dg-btn-primary dg-btn-glow">▶ İZLEMEYE BAŞLA</a>
        <a href="#store" class="dg-btn dg-btn-ghost">Mağazayı Keşfet →</a>
      </div>
      <div class="dg-stats" style="margin-top:22px">
        <div class="dg-stat"><label>Toplam Üye</label><strong><?php $c=$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(); echo (int)$c; ?></strong><small>Kayıtlı oyuncu</small></div>
        <div class="dg-stat"><label>Dolaşımdaki D-Coin</label><strong style="color:var(--amber)"><?=number_format((int)$pdo->query("SELECT COALESCE(SUM(dcoin_balance),0) FROM users")->fetchColumn())?></strong><small>Tüm cüzdanlarda</small></div>
        <div class="dg-stat"><label>İzlenen Reklam</label><strong style="color:var(--cyan)"><?=number_format((int)$pdo->query("SELECT COUNT(*) FROM user_watch_logs")->fetchColumn())?></strong><small>Toplam oynatma</small></div>
        <div class="dg-stat"><label>Mağaza Satış</label><strong style="color:var(--purple2)"><?=number_format((int)$pdo->query("SELECT COUNT(*) FROM purchases")->fetchColumn())?></strong><small>Teslim edildi</small></div>
      </div>
    </div>
    <div>
      <div class="dg-card">
        <div class="dg-card-head">
          <h2>◉ Günlük Görev</h2>
          <span class="dg-badge <?=$remaining>0?'dg-badge-live':''?>"><?= $remaining>0 ? 'AKTİF' : 'TAMAMLANDI' ?></span>
        </div>
        <div class="dg-card-body">
          <?php if (!$is_logged): ?>
            <p class="dg-muted" style="font-size:13px">Günlük <b><?=$daily_limit?> video</b> izleyerek D-Coin kazanabilirsin. Başlamak için giriş yap.</p>
            <a href="login.php" class="dg-btn dg-btn-primary dg-btn-block" style="margin-top:14px">Giriş Yap →</a>
          <?php else: ?>
            <div class="dg-daily">
              <div class="dg-daily-bar"><div class="dg-daily-fill" style="width:<?= $daily_limit? round($today_count/$daily_limit*100):0 ?>%"></div></div>
              <b><?=$today_count?> / <?=$daily_limit?></b>
            </div>
            <p class="dg-muted" style="font-size:12px;margin-top:10px">
              <?php if ($remaining>0): ?>Bugün <b><?=$remaining?> video</b> daha izleyebilirsin. Her video sonuna kadar izlenmeli, sekme değiştirirsen durur.<?php else: ?>Günlük limit doldu. Yarın tekrar gel!<?php endif; ?>
            </p>
            <?php if ($my_purchases): ?>
            <div class="dg-divider"></div>
            <p style="font:700 11px JetBrains Mono,monospace;letter-spacing:0.08em;text-transform:uppercase;color:var(--muted)">Son Teslimatların</p>
            <div style="display:flex;flex-direction:column;gap:8px;margin-top:8px">
              <?php foreach (array_slice($my_purchases,0,3) as $p): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;background:rgba(255,255,255,0.04);border:1px solid var(--line);border-radius:12px;padding:8px 12px">
                  <span style="font-size:12px;font-weight:600"><?=e($p['item_name'])?> <small style="color:var(--muted)">• <?=e($p['payment_method']=='dcoin' ? $p['price_paid_dcoin'].' D-Coin' : tl_format($p['price_paid_tl']))?></small></span>
                  <span style="font:700 10px JetBrains Mono,monospace;background:rgba(0,230,118,0.12);color:#7CFFB2;padding:4px 8px;border-radius:999px;border:1px solid rgba(0,230,118,0.2)">TESLİM ✓</span>
                </div>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>

      <div class="dg-card" style="margin-top:14px">
        <div class="dg-card-body" style="display:flex;gap:12px;align-items:center">
          <div style="width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,var(--cyan),var(--purple));display:grid;place-items:center;font-size:18px;flex-shrink:0">🛡️</div>
          <div>
            <h4 style="font:700 13px Inter">Anti-Cheat Aktif</h4>
            <p class="dg-muted" style="font-size:12px;line-height:1.4">İleri sarma kapalı • Sekme değişirse pause • Süre doğrulaması sunucuda • Hileye sıfır tolerans</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- WATCH -->
  <section id="watch" class="dg-section">
    <div class="dg-section-head">
      <div>
        <h2>📺 Reklam Oynatıcı</h2>
        <p>Listeden bir reklam seç, sonuna kadar izle ve D-Coin’i kap.</p>
      </div>
      <span class="dg-badge dg-badge-live">LIVE • ANTI-CHEAT</span>
    </div>

    <?php if (empty($ads)): ?>
      <div class="dg-card"><div class="dg-empty">Henüz aktif reklam yok. Admin panelden ekleyin.</div></div>
    <?php else: ?>
    <div class="dg-video-grid">
      <!-- Player -->
      <div>
        <div class="dg-player" id="playerWrap">
          <div class="dg-seek-guard" id="seekGuard">⏩ İleri sarma yasak — video başa sarıldı</div>
          <video id="dgVideo" playsinline preload="metadata" controlsList="nodownload noremoteplayback" disablePictureInPicture></video>
          <div class="dg-player-overlay" id="playOverlay">
            <button class="dg-play-btn" id="bigPlay" aria-label="Oynat">▶</button>
          </div>
          <div class="dg-player-bar">
            <div class="dg-progress"><div class="dg-progress-fill" id="progressFill"></div></div>
            <div class="dg-time" id="timeLabel">00:00 / 00:00</div>
            <div class="dg-anti"><i></i> ANTI-CHEAT: AKTİF</div>
          </div>
          <div class="dg-player-controls">
            <button class="dg-btn dg-btn-ghost dg-btn-sm" id="pauseBtn">⏸ Durdur</button>
            <button class="dg-btn dg-btn-ghost dg-btn-sm" id="muteBtn">🔊 Ses</button>
            <span class="dg-muted" id="playerStatus">Bir reklam seç ve oynat</span>
            <span style="margin-left:auto" class="dg-chip" id="rewardChip">+0 D-Coin</span>
          </div>
        </div>

        <div id="watchResult" style="margin-top:12px;display:none"></div>

        <div class="dg-card" style="margin-top:12px">
          <div class="dg-card-body" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
            <span class="dg-chip on" id="limitChip"><?=$today_count?> / <?=$daily_limit?> izlendi</span>
            <span class="dg-chip" id="tabChip">Sekme: Aktif ✓</span>
            <span class="dg-chip" id="seekChip">Scrubbing: Engellendi 🛡️</span>
            <span style="margin-left:auto;font:600 12px JetBrains Mono,monospace;color:var(--muted)" id="statusText">Hazır</span>
          </div>
        </div>
      </div>

      <!-- Ads list -->
      <div class="dg-ads-list" id="adsList">
        <?php foreach ($ads as $ad): ?>
          <div class="dg-ad-item<?= $ad['id']==($active_ad['id']??'')?' is-active':'' ?>" data-ad='<?=e(json_encode($ad, JSON_UNESCAPED_UNICODE))?>'>
            <div class="dg-ad-thumb">
              <video src="<?=e($ad['video_url'])?>" muted preload="metadata"></video>
              <span><?= (int)$ad['duration_sec'] ?>s</span>
            </div>
            <div class="dg-ad-meta">
              <h4><?=e($ad['title'])?></h4>
              <p><?= (int)$ad['reward_dcoin'] ?> D-Coin • <?= (int)$ad['duration_sec'] ?> sn</p>
            </div>
            <div class="dg-ad-reward">+<?= (int)$ad['reward_dcoin'] ?> ◈</div>
          </div>
        <?php endforeach; ?>
        <?php if (!$is_logged): ?>
          <div style="background:rgba(255,214,10,0.08);border:1px solid rgba(255,214,10,0.25);border-radius:14px;padding:12px 14px;font-size:13px;color:#FFEBA0">💡 İpucu: Giriş yapmadan videoları izleyebilirsin ama D-Coin sadece giriş yapanlara verilir. <a href="login.php" style="color:var(--amber);font-weight:700;text-decoration:underline">Giriş yap →</a></div>
        <?php elseif ($remaining<=0): ?>
          <div style="background:rgba(255,42,109,0.08);border:1px solid rgba(255,42,109,0.25);border-radius:14px;padding:12px 14px;font-size:13px;color:#FF8FAF">⛔ Günlük limit doldu. Yarın tekrar gel!</div>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
  </section>

  <!-- STORE -->
  <section id="store" class="dg-section">
    <div class="dg-section-head">
      <div>
        <h2>🛒 Tek Çatı Mağaza</h2>
        <p>Her ürün hem TL hem D-Coin ile alınabilir. D-Coin’in yetiyorsa coin ile öde, yetmiyorsa nakit al.</p>
      </div>
      <div style="display:flex;gap:8px;align-items:center">
        <div class="dg-search"><input id="storeSearch" type="search" placeholder="Ürün ara..."></div>
        <span class="dg-badge"><?=count($store_items)?> ÜRÜN</span>
      </div>
    </div>

    <?php if (empty($store_items)): ?>
      <div class="dg-card"><div class="dg-empty">Mağazada henüz ürün yok. Admin panelden ekleyin.</div></div>
    <?php else: ?>
    <div class="dg-store-grid" id="storeGrid">
      <?php foreach ($store_items as $it):
        $img = $it['image_url'] ?: 'https://images.unsplash.com/photo-1511512578047-dfb367046420?w=600&q=80';
        $keys_left = (int)$it['keys_left'];
        $in_stock = $keys_left>0;
      ?>
      <article class="dg-product" data-name="<?=e(mb_strtolower($it['name'].' '.$it['description']))?>">
        <div class="dg-product-img">
          <img src="<?=e($img)?>" alt="<?=e($it['name'])?>" loading="lazy">
          <?php if ($it['price_dcoin']>0): ?><span class="dg-product-badge">◈ <?= (int)$it['price_dcoin'] ?> D-Coin</span><?php endif; ?>
          <span class="dg-product-stock <?= $keys_left<=2 && $keys_left>0 ? 'low':'' ?>"><?= $in_stock ? $keys_left.' stok' : 'Tükendi' ?></span>
        </div>
        <div class="dg-product-body">
          <h3><?=e($it['name'])?></h3>
          <p><?=e($it['description'] ?: 'DeviceGame güvencesiyle anında dijital teslimat.')?></p>
          <div class="dg-prices">
            <span class="dg-price-tl"><?=tl_format($it['price_tl'])?></span>
            <span class="dg-price-coin">◈ <?= (int)$it['price_dcoin'] ?> D-Coin</span>
          </div>
        </div>
        <div class="dg-product-actions">
          <?php if (!$in_stock): ?>
            <span class="dg-no-stock">Stokta yok</span>
          <?php else: ?>
            <?php if ($is_logged): ?>
              <?php $can_coin = (int)$user['dcoin_balance'] >= (int)$it['price_dcoin'] && (int)$it['price_dcoin']>0; ?>
              <button class="dg-btn <?= $can_coin?'dg-btn-cyan':'dg-btn-ghost' ?> dg-btn-sm"
                      onclick="purchase(<?= (int)$it['id'] ?>,'dcoin')"
                      <?= $can_coin?'':'disabled title="Bakiye yetersiz"'?>>
                <?= $can_coin ? '◈ D-Coin ile Al' : '◈ Yetersiz ('.(int)$it['price_dcoin'].')' ?>
              </button>
              <button class="dg-btn dg-btn-ghost dg-btn-sm" onclick="purchase(<?= (int)$it['id'] ?>,'cash')">💳 Nakit Al</button>
            <?php else: ?>
              <a href="login.php?next=index.php" class="dg-btn dg-btn-primary dg-btn-sm" style="flex:1">Giriş yap ve al</a>
              <span class="dg-mini" style="width:100%;text-align:center"><?=tl_format($it['price_tl'])?> • ◈ <?= (int)$it['price_dcoin'] ?> D-Coin</span>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </section>

  <section class="dg-section">
    <div class="dg-card">
      <div class="dg-card-body" style="display:flex;gap:16px;align-items:center;flex-wrap:wrap;justify-content:space-between">
        <div>
          <h3 style="font:800 14px Orbitron,monospace">DeviceGame Nedir?</h3>
          <p class="dg-muted" style="font-size:13px;margin-top:4px;max-width:60ch">Oyuncular için kurduğumuz evren: video izle, görev yap, D-Coin topla, mağazada harca. Tüm altyapı Vanilla PHP + PDO ile, hantallık yok.</p>
        </div>
        <div style="display:flex;gap:8px">
          <span class="dg-chip on">PHP 8.1+</span>
          <span class="dg-chip">MySQL PDO</span>
          <span class="dg-chip">Anti-Cheat</span>
          <span class="dg-chip">Cyberpunk</span>
        </div>
      </div>
    </div>
  </section>

</main>

<footer style="border-top:1px solid var(--line);padding:22px 24px;text-align:center;color:var(--muted);font-size:12px">
  <div style="max-width:1280px;margin:0 auto">
    <p>© <?=date('Y')?> <b style="color:var(--text)">DeviceGame</b> — Oyun dünyasının anahtarı. Tüm hakları saklıdır. <span style="color:var(--cyan)">◉</span> Made for gamers.</p>
    <p class="dg-mini" style="margin-top:6px">Torbalı, İzmir • info@devicegame.com • WhatsApp: 0551 957 83 50</p>
  </div>
</footer>

<!-- Purchase Modal -->
<div class="dg-modal" id="purchaseModal">
  <div class="dg-modal-bg" onclick="closeModal()"></div>
  <div class="dg-modal-card">
    <h3 id="modalTitle">Teslimat</h3>
    <p class="dg-muted" id="modalDesc" style="font-size:13px;margin:6px 0 14px"></p>
    <div class="dg-key-box" id="modalKey" style="display:none">
      <span id="modalKeyText"></span>
      <button class="dg-btn dg-btn-ghost dg-btn-sm" onclick="copyKey()">Kopyala</button>
    </div>
    <div style="display:flex;gap:10px;margin-top:16px">
      <button class="dg-btn dg-btn-primary dg-btn-block" onclick="closeModal()">Harika, kapat</button>
    </div>
  </div>
</div>

<div class="dg-toast" id="toast"></div>

<script>
const IS_LOGGED = <?= $is_logged ? 'true':'false' ?>;
const DAILY_LIMIT = <?= (int)$daily_limit ?>;
const TODAY_COUNT = <?= (int)$today_count ?>;
const REMAINING = <?= (int)$remaining ?>;
let currentAd = <?= $active_ad ? json_encode($active_ad, JSON_UNESCAPED_UNICODE) : 'null' ?>;
let watchToken = null;
let watchStartTime = 0;
let hasRewarded = false;
let isSeeking = false;

const video = document.getElementById('dgVideo');
const playOverlay = document.getElementById('playOverlay');
const bigPlay = document.getElementById('bigPlay');
const progressFill = document.getElementById('progressFill');
const timeLabel = document.getElementById('timeLabel');
const rewardChip = document.getElementById('rewardChip');
const playerStatus = document.getElementById('playerStatus');
const statusText = document.getElementById('statusText');
const seekGuard = document.getElementById('seekGuard');
const watchResult = document.getElementById('watchResult');
const liveBalance = document.getElementById('liveBalance');

function toast(msg, ms=3000){
  const t=document.getElementById('toast');
  t.textContent=msg; t.classList.add('show');
  setTimeout(()=>t.classList.remove('show'), ms);
}
function fmt(s){ s=Math.round(s); const m=Math.floor(s/60).toString().padStart(2,'0'); const sec=(s%60).toString().padStart(2,'0'); return `${m}:${sec}`; }

function loadAd(ad){
  currentAd = ad;
  hasRewarded = false;
  watchToken = null;
  watchResult.style.display='none';
  document.querySelectorAll('.dg-ad-item').forEach(el=>{
    try{ const d=JSON.parse(el.dataset.ad); el.classList.toggle('is-active', d.id===ad.id); }catch(e){}
  });
  video.pause();
  video.src = ad.video_url;
  video.load();
  rewardChip.textContent = `+${ad.reward_dcoin} D-Coin`;
  playerStatus.textContent = ad.title;
  // duration bilgisi geldikten sonra time güncelle
  video.onloadedmetadata = ()=>{ timeLabel.textContent = `00:00 / ${fmt(video.duration||ad.duration_sec)}`; playOverlay.style.display='grid'; };
  statusText.textContent='Hazır — oynat';
}

if (currentAd) loadAd(currentAd);
else if (!IS_LOGGED) { /* no ads */ }

// Click on ad list
document.getElementById('adsList')?.addEventListener('click', (e)=>{
  const item = e.target.closest('.dg-ad-item');
  if(!item) return;
  const ad = JSON.parse(item.dataset.ad);
  loadAd(ad);
});

// Play logic
async function startPlayback(){
  if(!currentAd){ toast('Önce bir reklam seç'); return; }
  if(!IS_LOGGED){ toast('D-Coin kazanmak için giriş yapmalısın — video yine oynayacak'); }
  if(REMAINING<=0 && IS_LOGGED){ toast('Günlük limit doldu! Yarın tekrar gel.'); return; }

  // Anti-cheat: başlangıçta token al
  if(IS_LOGGED){
    try{
      const r = await fetch('api/watch_start.php', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ad_id: currentAd.id, csrf: '<?=csrf_token()?>'})
      });
      const j = await r.json();
      if(!j.ok){ toast(j.error||'Başlatılamadı'); return; }
      watchToken = j.token;
      watchStartTime = Date.now();
    }catch(e){ toast('Bağlantı hatası'); return; }
  }

  playOverlay.style.display='none';
  try{ await video.play(); }catch(e){}
  statusText.textContent='Oynatılıyor...';
}

bigPlay?.addEventListener('click', startPlayback);
video.addEventListener('click', ()=>{ if(video.paused) startPlayback(); else video.pause(); });
document.getElementById('pauseBtn')?.addEventListener('click', ()=>{
  if(video.paused) startPlayback(); else video.pause();
});
document.getElementById('muteBtn')?.addEventListener('click', ()=>{ video.muted=!video.muted; document.getElementById('muteBtn').textContent = video.muted?'🔇 Sessiz':'🔊 Ses'; });

// Progress & time
video.addEventListener('timeupdate', ()=>{
  const cur = video.currentTime||0;
  const dur = video.duration||currentAd?.duration_sec||30;
  const pct = dur? Math.min(100, cur/dur*100):0;
  progressFill.style.width = pct+'%';
  timeLabel.textContent = `${fmt(cur)} / ${fmt(dur)}`;
  // Tam izlendi mi kontrol: %98 ve üstü + ended event
  if(!hasRewarded && dur && cur/dur >= 0.98){
    // ended'e yakın, ama ended'i bekle
  }
});

video.addEventListener('pause', ()=>{
  if(!video.ended) { statusText.textContent='Durduruldu — devam etmek için oynat'; document.getElementById('tabChip').textContent='Sekme: Aktif ✓'; }
});

video.addEventListener('play', ()=>{ statusText.textContent='Oynatılıyor... Sekme değiştirme!'; });

// ——— ANTI-CHEAT: seeking yasak ———
let lastTime = 0;
video.addEventListener('seeking', ()=>{
  // Kullanıcı ileri sarmaya çalıştıysa başa sar ve uyar
  if(!isSeeking && Math.abs(video.currentTime - lastTime) > 1.5){
    // Geriye sarmaya izin ver, ileri sarmayı engelle
    if(video.currentTime > lastTime + 1.2){
      isSeeking=true;
      video.currentTime = lastTime;
      seekGuard.classList.add('show');
      setTimeout(()=>seekGuard.classList.remove('show'), 2200);
      statusText.textContent='İleri sarma engellendi!';
      setTimeout(()=>isSeeking=false, 300);
    }
  }
});
video.addEventListener('timeupdate', ()=>{ if(!video.seeking) lastTime = video.currentTime; });

// ——— ANTI-CHEAT: visibilitychange ———
document.addEventListener('visibilitychange', ()=>{
  const chip=document.getElementById('tabChip');
  if(document.hidden){
    if(!video.paused){ video.pause(); toast('Sekme değiştirildi — video duraklatıldı'); }
    if(chip) chip.textContent='Sekme: Arka planda ⏸';
    statusText.textContent='Sekme değişti — video durdu';
  } else {
    if(chip) chip.textContent='Sekme: Aktif ✓';
    statusText.textContent= video.paused ? 'Duraklatıldı — oynatmak için tıkla' : 'Oynatılıyor...';
  }
});

// ——— Sağ tık ve kısayolları engelle (hafif) ———
video.addEventListener('contextmenu', e=>e.preventDefault());
document.addEventListener('keydown', (e)=>{
  if(document.activeElement && ['INPUT','TEXTAREA'].includes(document.activeElement.tagName)) return;
  if(['ArrowRight','ArrowLeft','ArrowUp','ArrowDown'].includes(e.key)){
    // Video odaktayken de engelle
    if(e.target===video || video.contains(e.target)) e.preventDefault();
  }
});

// ——— Video bittiğinde ödül ———
video.addEventListener('ended', async ()=>{
  progressFill.style.width='100%';
  statusText.textContent='Tamamlandı — doğrulanıyor...';
  playOverlay.style.display='grid';
  bigPlay.textContent='↺';
  if(!IS_LOGGED){
    watchResult.innerHTML = `<div class="dg-card"><div class="dg-card-body" style="background:rgba(255,214,10,0.08);border:1px solid rgba(255,214,10,0.2);border-radius:14px"><b>Harika!</b> Videoyu sonuna kadar izledin. D-Coin kazanmak için <a href="login.php" style="color:var(--amber);font-weight:700">giriş yap</a>.</div></div>`;
    watchResult.style.display='block';
    return;
  }
  if(hasRewarded) return;
  hasRewarded=true;
  // En az süre kontrolü (client tarafı da)
  const elapsed = (Date.now()-watchStartTime)/1000;
  if(elapsed < (currentAd.duration_sec - 3)){
    watchResult.innerHTML = `<div class="dg-alert dg-alert-error">Süre doğrulaması başarısız. Videoyu tam izlemelisin.</div>`;
    watchResult.style.display='block';
    return;
  }
  try{
    const r = await fetch('api/watch_complete.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ad_id: currentAd.id, token: watchToken, csrf: '<?=csrf_token()?>'})
    });
    const j = await r.json();
    if(j.ok){
      const bal = j.new_balance;
      if(liveBalance) liveBalance.textContent = Number(bal).toLocaleString('tr-TR');
      // Günlük sayacı artır
      const chip=document.getElementById('limitChip');
      if(chip) chip.textContent = `${j.today_count} / ${DAILY_LIMIT} izlendi`;
      watchResult.innerHTML = `<div style="background:linear-gradient(135deg, rgba(0,230,118,0.12), rgba(0,229,255,0.12));border:1px solid rgba(0,230,118,0.3);border-radius:16px;padding:16px;display:flex;gap:14px;align-items:center"><div style="width:40px;height:40px;border-radius:12px;background:linear-gradient(135deg, #00E676, #00B8CC);display:grid;place-items:center;font-size:20px">◈</div><div><b style="color:#7CFFB2">+${j.earned} D-Coin kazandın!</b><br><span class="dg-muted" style="font-size:12px">Bakiyen: <b style="color:var(--amber)">${bal} D-Coin</b> • Bugün ${j.today_count}/${DAILY_LIMIT}</span></div><span style="margin-left:auto;font:800 12px JetBrains Mono,monospace;background:rgba(0,230,118,0.15);color:#7CFFB2;padding:6px 10px;border-radius:999px">✓ EKLENDİ</span></div>`;
      toast(`+${j.earned} D-Coin eklendi!`);
      // Butonları güncelle: eğer limit dolduysa
      if(j.today_count >= DAILY_LIMIT){
        document.querySelectorAll('.dg-ad-item').forEach(el=>el.style.opacity='0.5');
      }
    } else {
      watchResult.innerHTML = `<div class="dg-alert dg-alert-error">${j.error||'Ödül verilemedi.'}</div>`;
      toast(j.error||'Ödül verilemedi');
    }
    watchResult.style.display='block';
  }catch(e){
    watchResult.innerHTML = `<div class="dg-alert dg-alert-error">Bağlantı hatası. Tekrar dene.</div>`;
    watchResult.style.display='block';
  }
});

// Store search
document.getElementById('storeSearch')?.addEventListener('input', (e)=>{
  const q = e.target.value.toLowerCase().trim();
  document.querySelectorAll('.dg-product').forEach(card=>{
    const n = card.dataset.name||'';
    card.style.display = !q || n.includes(q) ? '' : 'none';
  });
});

// Purchase
async function purchase(itemId, method){
  if(!IS_LOGGED){ location.href='login.php'; return; }
  const ok = confirm(method==='dcoin' ? 'D-Coin ile satın alınsın mı? Bakiyenden düşülecek.' : 'Nakit satın alma oluşturulsun mu? (Demo: anında teslim)');
  if(!ok) return;
  try{
    const r = await fetch('api/purchase.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({store_item_id:itemId, payment_method:method, csrf:'<?=csrf_token()?>'})
    });
    const j = await r.json();
    if(j.ok){
      document.getElementById('modalTitle').textContent = j.payment_method==='dcoin' ? 'Teslim edildi — D-Coin ile' : 'Teslim edildi — Nakit';
      document.getElementById('modalDesc').textContent = j.item_name + ' için anahtarın hazır. Kopyalayıp kullanabilirsin.';
      document.getElementById('modalKeyText').textContent = j.delivered_key;
      document.getElementById('modalKey').style.display='flex';
      document.getElementById('purchaseModal').classList.add('open');
      if(j.new_balance!==undefined && liveBalance) liveBalance.textContent = Number(j.new_balance).toLocaleString('tr-TR');
      toast('Satın alma başarılı!');
      // Stok sayısını azalt (görsel)
    } else {
      toast(j.error||'Satın alma başarısız', 4000);
    }
  }catch(e){ toast('Bağlantı hatası'); }
}
function closeModal(){ document.getElementById('purchaseModal').classList.remove('open'); }
function copyKey(){
  const t=document.getElementById('modalKeyText').textContent;
  navigator.clipboard.writeText(t).then(()=>toast('Key kopyalandı ✓'));
}
</script>
</body>
</html>
