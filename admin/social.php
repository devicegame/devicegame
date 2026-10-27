<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../lib/social.php';

social_ensure_storage();
$view = (string)($_GET['gorunum'] ?? 'onay');
if (!in_array($view, ['onay', 'raporlar', 'gonderiler', 'konular', 'bakim'], true)) $view = 'onay';
$topicErrors = [];
$topicValues = null;
$editingTopic = null;

function dg_social_admin_url(array $params = []): string
{
    return 'social.php' . ($params ? '?' . http_build_query($params) : '');
}

function dg_social_return(array $params = []): string
{
    return '../admin/social.php' . ($params ? '?' . http_build_query($params) : '');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $returnParams = ['gorunum' => (string)($_POST['return_view'] ?? $view)];
    if (!csrf_verify($_POST['csrf'] ?? '')) {
        flash_error_set('CSRF hatası. Sayfayı yenileyip tekrar deneyin.');
        header('Location: ' . dg_social_admin_url($returnParams));
        exit;
    }

    try {
        if (isset($_POST['save_topic'])) {
            $topicValues = $_POST;
            $result = social_save_topic($user, $_POST);
            if (!empty($result['errors'])) {
                $topicErrors = $result['errors'];
                $view = 'konular';
                if (!empty($_POST['edit_slug'])) $editingTopic = social_topic((string)$_POST['edit_slug'], false);
            } else {
                flash_set(!empty($_POST['edit_slug']) ? 'Sosyal konusu güncellendi.' : 'Sosyal konusu eklendi.');
                header('Location: ' . dg_social_admin_url(['gorunum' => 'konular']));
                exit;
            }
        }

        if (isset($_POST['delete_topic'])) {
            $result = social_delete_topic($user, (string)($_POST['topic_slug'] ?? ''));
            if (!empty($result['errors'])) flash_error_set(implode(' ', $result['errors']));
            else flash_set('Sosyal konusu silindi.');
            header('Location: ' . dg_social_admin_url(['gorunum' => 'konular']));
            exit;
        }

        if (isset($_POST['report_status'])) {
            $result = social_set_report_status($user, (int)($_POST['report_id'] ?? 0), (string)($_POST['status'] ?? 'open'));
            if (!empty($result['errors'])) flash_error_set(implode(' ', $result['errors']));
            else flash_set('Şikâyet durumu güncellendi.');
            header('Location: ' . dg_social_admin_url(['gorunum' => 'raporlar']));
            exit;
        }

        if (isset($_POST['rebuild_indexes'])) {
            $result = social_rebuild_indexes();
            if (!empty($result['errors'])) flash_error_set(implode(' ', $result['errors']));
            else flash_set('Sosyal indeksleri yeniden oluşturuldu: ' . (int)$result['posts'] . ' gönderi, ' . (int)$result['comments'] . ' yorum.');
            header('Location: ' . dg_social_admin_url(['gorunum' => 'bakim']));
            exit;
        }
    } catch (Throwable $exception) {
        error_log('[admin social] ' . $exception->getMessage());
        flash_error_set('Sosyal işlemi tamamlanamadı. Depolama izinlerini kontrol edin.');
        header('Location: ' . dg_social_admin_url($returnParams));
        exit;
    }
}

if ($topicValues === null && isset($_GET['konu_duzenle'])) {
    $editingTopic = social_topic((string)$_GET['konu_duzenle'], false);
    if (!$editingTopic) {
        flash_error_set('Düzenlenecek sosyal konusu bulunamadı.');
        header('Location: ' . dg_social_admin_url(['gorunum' => 'konular']));
        exit;
    }
    $view = 'konular';
}
$topicForm = [
    'slug' => (string)($topicValues['slug'] ?? $editingTopic['slug'] ?? ''),
    'name' => (string)($topicValues['name'] ?? $editingTopic['name'] ?? ''),
    'description' => (string)($topicValues['description'] ?? $editingTopic['description'] ?? ''),
    'icon' => (string)($topicValues['icon'] ?? $editingTopic['icon'] ?? '💬'),
    'order' => (int)($topicValues['order'] ?? $editingTopic['order'] ?? 99),
    'official_only' => $topicValues !== null ? !empty($topicValues['official_only']) : !empty($editingTopic['official_only']),
    'active' => $topicValues !== null ? !empty($topicValues['active']) : (!isset($editingTopic['active']) || !empty($editingTopic['active'])),
];

$stats = social_stats(true);
$storageStats = social_storage_stats();
$pendingPosts = social_list_posts(['status' => 'pending', 'include_moderation' => true, 'sort' => 'new', 'per_page' => 50], $user)['items'];
$pendingComments = array_values(array_filter(social_moderation_comments(100), static fn(array $item): bool => ($item['status'] ?? '') === 'pending'));
$reports = social_reports();
$postQuery = trim((string)($_GET['q'] ?? ''));
$postStatus = (string)($_GET['durum'] ?? 'all');
if (!in_array($postStatus, ['all', 'published', 'pending', 'hidden'], true)) $postStatus = 'all';
$postPage = max(1, (int)($_GET['sayfa'] ?? 1));
$postResult = social_list_posts([
    'q' => $postQuery,
    'status' => $postStatus === 'all' ? '' : $postStatus,
    'include_moderation' => true,
    'sort' => 'new',
    'page' => $postPage,
    'per_page' => 25,
], $user);
$topics = social_topics(false);
$currentReturn = dg_social_return(array_filter([
    'gorunum' => $view,
    'q' => $postQuery,
    'durum' => $postStatus !== 'all' ? $postStatus : '',
    'sayfa' => $postPage > 1 ? $postPage : '',
], static fn($value): bool => $value !== ''));

admin_head('Sosyal Moderasyon');
?>

<div style="display:flex;align-items:end;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:18px">
  <div>
    <p class="dg-kicker">DG SOCIAL // MODERATION</p>
    <h1 style="font-family:Bricolage Grotesque,sans-serif;font-weight:800;font-size:24px">Sosyal Moderasyon</h1>
    <p class="dg-muted" style="font-size:13px">Forum + akış içeriklerini, şikâyetleri ve konuları yönet. Veriler parçalı JSON/JSONL dosyalarında tutulur.</p>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
    <span class="dg-badge"><?= (int)$stats['posts'] ?> YAYINDA</span>
    <span class="dg-badge"><?= (int)$stats['open_reports'] ?> AÇIK ŞİKÂYET</span>
    <a class="dg-btn dg-btn-ghost dg-btn-sm" href="../sosyal/index.php" target="_blank" rel="noopener">DG Social’ı Aç ↗</a>
  </div>
</div>

<div class="dg-stats" style="margin-bottom:16px">
  <div class="dg-stat"><label>📰 Yayındaki Gönderi</label><strong><?= (int)$stats['posts'] ?></strong><small>Akışta görünen içerikler</small></div>
  <div class="dg-stat"><label>⏳ Onay Bekleyen</label><strong><?= count($pendingPosts) + count($pendingComments) ?></strong><small><?= count($pendingPosts) ?> gönderi · <?= count($pendingComments) ?> yorum</small></div>
  <div class="dg-stat"><label>🚩 Açık Şikâyet</label><strong><?= (int)$stats['open_reports'] ?></strong><small>İnceleme bekleyen bildirimler</small></div>
  <div class="dg-stat"><label>💾 Sosyal Veri</label><strong><?= number_format(((int)$storageStats['bytes']) / 1024, 1, ',', '.') ?> KB</strong><small><?= (int)$storageStats['files'] ?> dosya</small></div>
</div>

<nav class="dg-social-admin-tabs" aria-label="Sosyal yönetim görünümleri">
  <a href="<?= e(dg_social_admin_url(['gorunum' => 'onay'])) ?>" class="<?= $view === 'onay' ? 'is-active' : '' ?>">Onay kuyruğu <span><?= count($pendingPosts) + count($pendingComments) ?></span></a>
  <a href="<?= e(dg_social_admin_url(['gorunum' => 'raporlar'])) ?>" class="<?= $view === 'raporlar' ? 'is-active' : '' ?>">Şikâyetler <span><?= (int)$stats['open_reports'] ?></span></a>
  <a href="<?= e(dg_social_admin_url(['gorunum' => 'gonderiler'])) ?>" class="<?= $view === 'gonderiler' ? 'is-active' : '' ?>">Gönderiler</a>
  <a href="<?= e(dg_social_admin_url(['gorunum' => 'konular'])) ?>" class="<?= $view === 'konular' ? 'is-active' : '' ?>">Konular</a>
  <a href="<?= e(dg_social_admin_url(['gorunum' => 'bakim'])) ?>" class="<?= $view === 'bakim' ? 'is-active' : '' ?>">Bakım</a>
</nav>

<?php if ($view === 'onay'): ?>
  <div class="dg-social-admin-grid">
    <section class="dg-card">
      <div class="dg-card-head"><h2>Bekleyen Gönderiler</h2><span class="dg-badge"><?= count($pendingPosts) ?></span></div>
      <?php if (!$pendingPosts): ?>
        <div class="dg-empty">Onay bekleyen gönderi yok.</div>
      <?php else: ?>
        <div class="dg-social-admin-list">
          <?php foreach ($pendingPosts as $post): ?>
            <?php $fullPost = social_get_post((int)$post['id'], true) ?: $post; ?>
            <article class="dg-social-admin-item">
              <div>
                <a class="dg-social-admin-title" href="../sosyal/gonderi.php?id=<?= (int)$post['id'] ?>" target="_blank" rel="noopener"><?= e($post['title']) ?></a>
                <p><?= e(social_excerpt((string)$fullPost['body'], 280)) ?></p>
                <div class="dg-social-admin-meta">
                  <span class="dg-chip">@<?= e($post['author_username']) ?></span>
                  <span class="dg-chip"><?= e($post['topic_slug']) ?></span>
                  <span class="dg-chip"><?= e(date('d.m.Y H:i', (int)$post['created_at'])) ?></span>
                </div>
              </div>
              <div class="dg-social-admin-actions">
                <form method="post" action="../sosyal/aksiyon.php"><?= csrf_field() ?><input type="hidden" name="islem" value="gonderi_onayla"><input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>"><input type="hidden" name="return" value="<?= e(dg_social_return(['gorunum' => 'onay'])) ?>"><button class="dg-btn dg-btn-primary dg-btn-sm">Yayınla</button></form>
                <form method="post" action="../sosyal/aksiyon.php"><?= csrf_field() ?><input type="hidden" name="islem" value="gonderi_gizle"><input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>"><input type="hidden" name="return" value="<?= e(dg_social_return(['gorunum' => 'onay'])) ?>"><button class="dg-btn dg-btn-ghost dg-btn-sm">Gizle</button></form>
                <form method="post" action="../sosyal/aksiyon.php" onsubmit="return confirm('Bu gönderi silinsin mi?')"><?= csrf_field() ?><input type="hidden" name="islem" value="gonderi_sil"><input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>"><input type="hidden" name="return" value="<?= e(dg_social_return(['gorunum' => 'onay'])) ?>"><button class="dg-btn dg-btn-ghost dg-btn-sm dg-social-danger">Sil</button></form>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <section class="dg-card">
      <div class="dg-card-head"><h2>Bekleyen Yorumlar</h2><span class="dg-badge"><?= count($pendingComments) ?></span></div>
      <?php if (!$pendingComments): ?>
        <div class="dg-empty">Onay bekleyen yorum yok.</div>
      <?php else: ?>
        <div class="dg-social-admin-list">
          <?php foreach ($pendingComments as $summary): ?>
            <?php $comment = social_get_comment((int)$summary['post_id'], (int)$summary['id']); ?>
            <article class="dg-social-admin-item">
              <div>
                <a class="dg-social-admin-title" href="../sosyal/gonderi.php?id=<?= (int)$summary['post_id'] ?>#yorum-<?= (int)$summary['id'] ?>" target="_blank" rel="noopener"><?= e($summary['post_title']) ?></a>
                <p><?= e($comment['body'] ?? $summary['excerpt']) ?></p>
                <div class="dg-social-admin-meta">
                  <span class="dg-chip">@<?= e($summary['author_username']) ?></span>
                  <span class="dg-chip"><?= e(date('d.m.Y H:i', (int)$summary['created_at'])) ?></span>
                </div>
              </div>
              <div class="dg-social-admin-actions">
                <form method="post" action="../sosyal/aksiyon.php"><?= csrf_field() ?><input type="hidden" name="islem" value="yorum_onayla"><input type="hidden" name="post_id" value="<?= (int)$summary['post_id'] ?>"><input type="hidden" name="comment_id" value="<?= (int)$summary['id'] ?>"><input type="hidden" name="return" value="<?= e(dg_social_return(['gorunum' => 'onay'])) ?>"><button class="dg-btn dg-btn-primary dg-btn-sm">Yayınla</button></form>
                <form method="post" action="../sosyal/aksiyon.php"><?= csrf_field() ?><input type="hidden" name="islem" value="yorum_gizle"><input type="hidden" name="post_id" value="<?= (int)$summary['post_id'] ?>"><input type="hidden" name="comment_id" value="<?= (int)$summary['id'] ?>"><input type="hidden" name="return" value="<?= e(dg_social_return(['gorunum' => 'onay'])) ?>"><button class="dg-btn dg-btn-ghost dg-btn-sm">Gizle</button></form>
                <form method="post" action="../sosyal/aksiyon.php" onsubmit="return confirm('Bu yorum silinsin mi?')"><?= csrf_field() ?><input type="hidden" name="islem" value="yorum_sil"><input type="hidden" name="post_id" value="<?= (int)$summary['post_id'] ?>"><input type="hidden" name="comment_id" value="<?= (int)$summary['id'] ?>"><input type="hidden" name="return" value="<?= e(dg_social_return(['gorunum' => 'onay'])) ?>"><button class="dg-btn dg-btn-ghost dg-btn-sm dg-social-danger">Sil</button></form>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  </div>
<?php endif; ?>

<?php if ($view === 'raporlar'): ?>
  <section class="dg-card">
    <div class="dg-card-head"><h2>Şikâyetler</h2><span class="dg-badge"><?= count($reports) ?> kayıt</span></div>
    <?php if (!$reports): ?>
      <div class="dg-empty">Henüz şikâyet yok.</div>
    <?php else: ?>
      <div class="dg-social-admin-list">
        <?php foreach ($reports as $report): ?>
          <article class="dg-social-admin-item">
            <div>
              <a class="dg-social-admin-title" href="../sosyal/gonderi.php?id=<?= (int)$report['post_id'] ?><?= $report['target_type'] === 'comment' ? '#yorum-' . (int)$report['target_id'] : '' ?>" target="_blank" rel="noopener"><?= e($report['target_title'] ?: 'Şikâyet edilen içerik') ?></a>
              <p><b><?= e($report['reason']) ?>:</b> <?= e($report['target_excerpt']) ?></p>
              <?php if (trim((string)$report['details']) !== ''): ?><p><b>Not:</b> <?= e($report['details']) ?></p><?php endif; ?>
              <div class="dg-social-admin-meta">
                <span class="dg-chip <?= $report['status'] === 'open' ? 'on' : '' ?>"><?= e($report['status']) ?></span>
                <span class="dg-chip"><?= $report['target_type'] === 'comment' ? 'Yorum' : 'Gönderi' ?></span>
                <span class="dg-chip">Şikâyet eden: @<?= e($report['reporter_username']) ?></span>
                <span class="dg-chip"><?= e(date('d.m.Y H:i', (int)$report['created_at'])) ?></span>
              </div>
            </div>
            <div class="dg-social-admin-actions">
              <?php if ($report['target_type'] === 'post'): ?>
                <form method="post" action="../sosyal/aksiyon.php"><?= csrf_field() ?><input type="hidden" name="islem" value="gonderi_gizle"><input type="hidden" name="post_id" value="<?= (int)$report['post_id'] ?>"><input type="hidden" name="return" value="<?= e(dg_social_return(['gorunum' => 'raporlar'])) ?>"><button class="dg-btn dg-btn-ghost dg-btn-sm">Gönderiyi gizle</button></form>
              <?php else: ?>
                <form method="post" action="../sosyal/aksiyon.php"><?= csrf_field() ?><input type="hidden" name="islem" value="yorum_gizle"><input type="hidden" name="post_id" value="<?= (int)$report['post_id'] ?>"><input type="hidden" name="comment_id" value="<?= (int)$report['target_id'] ?>"><input type="hidden" name="return" value="<?= e(dg_social_return(['gorunum' => 'raporlar'])) ?>"><button class="dg-btn dg-btn-ghost dg-btn-sm">Yorumu gizle</button></form>
              <?php endif; ?>
              <form method="post"><?= csrf_field() ?><input type="hidden" name="report_id" value="<?= (int)$report['id'] ?>"><input type="hidden" name="status" value="resolved"><button name="report_status" value="1" class="dg-btn dg-btn-primary dg-btn-sm">Çözüldü</button></form>
              <form method="post"><?= csrf_field() ?><input type="hidden" name="report_id" value="<?= (int)$report['id'] ?>"><input type="hidden" name="status" value="dismissed"><button name="report_status" value="1" class="dg-btn dg-btn-ghost dg-btn-sm">Reddet</button></form>
              <?php if ($report['status'] !== 'open'): ?>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="report_id" value="<?= (int)$report['id'] ?>"><input type="hidden" name="status" value="open"><button name="report_status" value="1" class="dg-btn dg-btn-ghost dg-btn-sm">Yeniden aç</button></form>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
<?php endif; ?>

<?php if ($view === 'gonderiler'): ?>
  <section class="dg-card">
    <div class="dg-card-head"><h2>Tüm Gönderiler</h2><span class="dg-badge"><?= (int)$postResult['total'] ?></span></div>
    <form method="get" class="dg-social-admin-filter">
      <input type="hidden" name="gorunum" value="gonderiler">
      <div class="dg-search"><input type="search" name="q" value="<?= e($postQuery) ?>" placeholder="Başlık, içerik, etiket veya kullanıcı ara"></div>
      <select name="durum" aria-label="Durum">
        <option value="all"<?= $postStatus === 'all' ? ' selected' : '' ?>>Tüm durumlar</option>
        <option value="published"<?= $postStatus === 'published' ? ' selected' : '' ?>>Yayında</option>
        <option value="pending"<?= $postStatus === 'pending' ? ' selected' : '' ?>>Onay bekliyor</option>
        <option value="hidden"<?= $postStatus === 'hidden' ? ' selected' : '' ?>>Gizli</option>
      </select>
      <button class="dg-btn dg-btn-ghost dg-btn-sm">Filtrele</button>
      <?php if ($postQuery !== '' || $postStatus !== 'all'): ?><a class="dg-btn dg-btn-ghost dg-btn-sm" href="<?= e(dg_social_admin_url(['gorunum' => 'gonderiler'])) ?>">Temizle</a><?php endif; ?>
    </form>
    <?php if (!$postResult['items']): ?>
      <div class="dg-empty">Filtreye uyan gönderi bulunamadı.</div>
    <?php else: ?>
      <div class="dg-social-admin-list">
        <?php foreach ($postResult['items'] as $post): ?>
          <article class="dg-social-admin-item">
            <div>
              <a class="dg-social-admin-title" href="../sosyal/gonderi.php?id=<?= (int)$post['id'] ?>" target="_blank" rel="noopener"><?= e($post['title']) ?></a>
              <p><?= e($post['excerpt']) ?></p>
              <div class="dg-social-admin-meta">
                <span class="dg-chip <?= $post['status'] === 'published' ? 'on' : '' ?>"><?= e($post['status']) ?></span>
                <span class="dg-chip">@<?= e($post['author_username']) ?></span>
                <span class="dg-chip"><?= e($post['topic_slug']) ?></span>
                <span class="dg-chip">GG <?= (int)$post['gg_count'] ?></span>
                <span class="dg-chip"><?= (int)$post['comment_count'] ?> yorum</span>
                <?php if (!empty($post['pinned'])): ?><span class="dg-chip on">Sabit</span><?php endif; ?>
                <?php if (!empty($post['locked'])): ?><span class="dg-chip">Kilitli</span><?php endif; ?>
              </div>
            </div>
            <div class="dg-social-admin-actions">
              <a class="dg-btn dg-btn-ghost dg-btn-sm" href="../sosyal/yeni.php?duzenle=<?= (int)$post['id'] ?>" target="_blank" rel="noopener">Düzenle</a>
              <?php if ($post['status'] !== 'published'): ?>
                <form method="post" action="../sosyal/aksiyon.php"><?= csrf_field() ?><input type="hidden" name="islem" value="gonderi_onayla"><input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>"><input type="hidden" name="return" value="<?= e($currentReturn) ?>"><button class="dg-btn dg-btn-primary dg-btn-sm">Yayınla</button></form>
              <?php else: ?>
                <form method="post" action="../sosyal/aksiyon.php"><?= csrf_field() ?><input type="hidden" name="islem" value="gonderi_gizle"><input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>"><input type="hidden" name="return" value="<?= e($currentReturn) ?>"><button class="dg-btn dg-btn-ghost dg-btn-sm">Gizle</button></form>
              <?php endif; ?>
              <form method="post" action="../sosyal/aksiyon.php"><?= csrf_field() ?><input type="hidden" name="islem" value="gonderi_sabitle"><input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>"><input type="hidden" name="return" value="<?= e($currentReturn) ?>"><button class="dg-btn dg-btn-ghost dg-btn-sm"><?= !empty($post['pinned']) ? 'Sabitten kaldır' : 'Sabitle' ?></button></form>
              <form method="post" action="../sosyal/aksiyon.php"><?= csrf_field() ?><input type="hidden" name="islem" value="gonderi_kilitle"><input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>"><input type="hidden" name="return" value="<?= e($currentReturn) ?>"><button class="dg-btn dg-btn-ghost dg-btn-sm"><?= !empty($post['locked']) ? 'Kilidi aç' : 'Kilitle' ?></button></form>
              <form method="post" action="../sosyal/aksiyon.php" onsubmit="return confirm('Bu gönderi silinsin mi?')"><?= csrf_field() ?><input type="hidden" name="islem" value="gonderi_sil"><input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>"><input type="hidden" name="return" value="<?= e($currentReturn) ?>"><button class="dg-btn dg-btn-ghost dg-btn-sm dg-social-danger">Sil</button></form>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
      <?php if ((int)$postResult['pages'] > 1): ?>
        <div class="dg-social-admin-pagination">
          <?php if ($postPage > 1): ?><a href="<?= e(dg_social_admin_url(['gorunum' => 'gonderiler', 'q' => $postQuery, 'durum' => $postStatus, 'sayfa' => $postPage - 1])) ?>">← Önceki</a><?php endif; ?>
          <span><?= $postPage ?> / <?= (int)$postResult['pages'] ?></span>
          <?php if ($postPage < (int)$postResult['pages']): ?><a href="<?= e(dg_social_admin_url(['gorunum' => 'gonderiler', 'q' => $postQuery, 'durum' => $postStatus, 'sayfa' => $postPage + 1])) ?>">Sonraki →</a><?php endif; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </section>
<?php endif; ?>

<?php if ($view === 'konular'): ?>
  <div class="dg-social-admin-grid">
    <section class="dg-card">
      <div class="dg-card-head"><h2><?= $editingTopic ? 'Konuyu Düzenle' : 'Yeni Konu' ?></h2><?php if ($editingTopic): ?><a class="dg-btn dg-btn-ghost dg-btn-sm" href="<?= e(dg_social_admin_url(['gorunum' => 'konular'])) ?>">İptal</a><?php endif; ?></div>
      <div class="dg-card-body">
        <?php if ($topicErrors): ?>
          <div class="dg-alert dg-alert-error"><b>Konu kaydedilemedi:</b><ul style="margin:6px 0 0 18px;padding:0"><?php foreach ($topicErrors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
        <form method="post" class="dg-form">
          <?= csrf_field() ?>
          <input type="hidden" name="edit_slug" value="<?= $editingTopic ? e($editingTopic['slug']) : '' ?>">
          <label class="dg-field"><span>Konu adı *</span><input type="text" name="name" required maxlength="60" value="<?= e($topicForm['name']) ?>" placeholder="Örn. Turnuvalar"></label>
          <label class="dg-field"><span>Adres (slug)<?= $editingTopic ? ' — düzenlerken korunur' : '' ?></span><input type="text" name="slug" maxlength="80" value="<?= e($topicForm['slug']) ?>" placeholder="turnuvalar"<?= $editingTopic ? ' readonly' : '' ?>></label>
          <label class="dg-field"><span>Açıklama</span><textarea name="description" maxlength="180"><?= e($topicForm['description']) ?></textarea></label>
          <div class="dg-blog-form-grid">
            <label class="dg-field"><span>İkon</span><input type="text" name="icon" maxlength="12" value="<?= e($topicForm['icon']) ?>"></label>
            <label class="dg-field"><span>Sıra</span><input type="number" name="order" min="1" max="999" value="<?= (int)$topicForm['order'] ?>"></label>
          </div>
          <label class="dg-check"><input type="checkbox" name="official_only" value="1"<?= !empty($topicForm['official_only']) ? ' checked' : '' ?>><span>Yalnızca ekip gönderi açabilir</span></label>
          <label class="dg-check"><input type="checkbox" name="active" value="1"<?= !empty($topicForm['active']) ? ' checked' : '' ?>><span>Aktif</span></label>
          <button name="save_topic" value="1" class="dg-btn dg-btn-primary dg-btn-block"><?= $editingTopic ? 'Konuyu Güncelle' : 'Konu Ekle' ?></button>
        </form>
      </div>
    </section>

    <section class="dg-card">
      <div class="dg-card-head"><h2>Forum Konuları</h2><span class="dg-badge"><?= count($topics) ?></span></div>
      <div class="dg-social-admin-list">
        <?php foreach ($topics as $topic): ?>
          <article class="dg-social-admin-item">
            <div>
              <strong><?= e($topic['icon']) ?> <?= e($topic['name']) ?></strong>
              <p><?= e($topic['description'] ?: 'Açıklama yok.') ?></p>
              <div class="dg-social-admin-meta">
                <span class="dg-chip <?= !empty($topic['active']) ? 'on' : '' ?>"><?= !empty($topic['active']) ? 'Aktif' : 'Pasif' ?></span>
                <?php if (!empty($topic['official_only'])): ?><span class="dg-chip">Yalnızca ekip</span><?php endif; ?>
                <code><?= e($topic['slug']) ?></code>
              </div>
            </div>
            <div class="dg-social-admin-actions">
              <a class="dg-btn dg-btn-ghost dg-btn-sm" href="../sosyal/index.php?konu=<?= rawurlencode((string)$topic['slug']) ?>" target="_blank" rel="noopener">Görüntüle</a>
              <a class="dg-btn dg-btn-ghost dg-btn-sm" href="<?= e(dg_social_admin_url(['gorunum' => 'konular', 'konu_duzenle' => (string)$topic['slug']])) ?>">Düzenle</a>
              <form method="post" onsubmit="return confirm('Bu konu silinsin mi? İçinde gönderi varsa silinmez.')"><?= csrf_field() ?><input type="hidden" name="topic_slug" value="<?= e($topic['slug']) ?>"><button name="delete_topic" value="1" class="dg-btn dg-btn-ghost dg-btn-sm dg-social-danger">Sil</button></form>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
  </div>
<?php endif; ?>

<?php if ($view === 'bakim'): ?>
  <div class="dg-social-admin-grid">
    <section class="dg-card">
      <div class="dg-card-head"><h2>Depolama</h2><span class="dg-badge">JSON / JSONL</span></div>
      <div class="dg-card-body">
        <p class="dg-muted" style="font-size:13px;line-height:1.6">Sosyal veriler <code>storage/social/</code> altında profiller, gönderi belgeleri, yorum JSONL dosyaları ve akış indeksleri olarak saklanır. Yüklenen görseller <code>uploads/social/</code> altındadır.</p>
        <div class="dg-divider"></div>
        <p><b><?= (int)$storageStats['files'] ?></b> dosya · <b><?= number_format(((int)$storageStats['bytes']) / 1024, 1, ',', '.') ?> KB</b></p>
        <p class="dg-muted" style="font-size:12px">Yedek alırken iki klasörü birlikte yedekleyin.</p>
      </div>
    </section>
    <section class="dg-card">
      <div class="dg-card-head"><h2>İndeks Bakımı</h2></div>
      <div class="dg-card-body">
        <p class="dg-muted" style="font-size:13px;line-height:1.6">Akış, konu, profil ve moderasyon indekslerini kanonik gönderi/yorum dosyalarından yeniden oluşturur; yorum ve GG sayaçlarını doğrular.</p>
        <form method="post" style="margin-top:16px" onsubmit="return confirm('Sosyal indeksleri yeniden oluşturulsun mu?')">
          <?= csrf_field() ?>
          <button name="rebuild_indexes" value="1" class="dg-btn dg-btn-primary">İndeksleri yeniden oluştur</button>
        </form>
      </div>
    </section>
  </div>
<?php endif; ?>

<?php admin_foot(); ?>
