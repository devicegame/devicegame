<?php
require_once __DIR__ . '/_common.php';
social_require_login();

$editId = (int)($_GET['duzenle'] ?? $_POST['edit_id'] ?? 0);
$editingPost = $editId > 0 ? social_get_post($editId, true) : null;
if ($editId > 0 && (!$editingPost || $editingPost['status'] === 'deleted')) {
    flash_error_set('Düzenlenecek gönderi bulunamadı.');
    header('Location: index.php');
    exit;
}
if ($editingPost && empty($socialUser['is_admin']) && (int)$socialUser['id'] !== (int)$editingPost['user_id']) {
    flash_error_set('Bu gönderiyi düzenleyemezsin.');
    header('Location: ' . social_post_url($editId));
    exit;
}

$formErrors = [];
$form = [
    'topic_slug' => social_slugify((string)($_GET['konu'] ?? 'genel-sohbet')),
    'title' => '',
    'body' => '',
    'tags' => '',
    'lfg_game' => '',
    'lfg_platform' => '',
    'lfg_rank' => '',
    'lfg_mic' => '',
    'lfg_play_time' => '',
    'lfg_needed' => '',
];
if ($editingPost) {
    $form['topic_slug'] = (string)$editingPost['topic_slug'];
    $form['title'] = (string)$editingPost['title'];
    $form['body'] = (string)$editingPost['body'];
    $form['tags'] = implode(', ', array_map(static fn($tag): string => '#' . $tag, (array)$editingPost['tags']));
    foreach (['game', 'platform', 'rank', 'mic', 'play_time', 'needed'] as $key) {
        $form['lfg_' . $key] = (string)($editingPost['meta'][$key] ?? '');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (array_keys($form) as $field) {
        $form[$field] = (string)($_POST[$field] ?? $form[$field]);
    }
    if (!csrf_verify((string)($_POST['csrf'] ?? ''))) {
        $formErrors[] = 'Güvenlik doğrulaması başarısız. Sayfayı yenileyip tekrar dene.';
    } else {
        try {
            $result = $editingPost
                ? social_edit_post($socialUser, $editId, $_POST, $_FILES)
                : social_create_post($socialUser, $_POST, $_FILES);
        } catch (Throwable $exception) {
            error_log('[social post save] ' . $exception->getMessage());
            $result = ['errors' => ['Gönderi kaydedilemedi. Depolama izinlerini kontrol edin.'], 'id' => $editId, 'status' => ''];
        }
        if (!empty($result['errors'])) {
            $formErrors = $result['errors'];
            if ($editingPost) $editingPost = social_get_post($editId, true) ?: $editingPost;
        } else {
            $savedId = (int)($result['id'] ?? $editId);
            flash_set(($result['status'] ?? '') === 'pending'
                ? 'Gönderin alındı ve moderasyon onayına gönderildi.'
                : ($editingPost ? 'Gönderin güncellendi.' : 'Gönderin yayınlandı.'));
            header('Location: ' . social_post_url($savedId));
            exit;
        }
    }
}

$topicOptions = $socialTopics;
if ($editingPost && !isset($socialTopicMap[(string)$editingPost['topic_slug']])) {
    $currentTopic = social_topic((string)$editingPost['topic_slug'], false);
    if ($currentTopic) $topicOptions[] = $currentTopic;
}
$canUpload = $socialProfile ? social_can_upload_image($socialProfile, $socialUser) : false;

$page_title = $editingPost ? 'Gönderiyi Düzenle — DG Social' : 'Yeni Gönderi — DG Social';
$page_meta_desc = 'DG Social’da yeni bir forum konusu veya topluluk gönderisi oluştur.';
$page_css = ['shop.css', 'social.css'];
$body_class = 'shop-page social-page';
include __DIR__ . '/../header.php';
?>

<section class="social-shell is-narrow">
    <header class="social-page-head">
        <p class="eyebrow">DG Social</p>
        <h1><?= $editingPost ? 'Gönderiyi düzenle.' : 'Yeni gönderi aç.' ?></h1>
        <p class="muted">Herkes gönderini okuyabilir. Yeni üyelerin ilk içerikleri spam’i önlemek için kısa bir moderasyondan geçebilir.</p>
    </header>

    <?= social_subnav('new') ?>
    <?= social_flash_html() ?>

    <?php if ($formErrors): ?>
        <div class="social-alert is-error" role="alert">
            <strong>Gönderi kaydedilemedi:</strong>
            <ul>
                <?php foreach ($formErrors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="social-form-card" data-social-post-form>
        <?= csrf_field() ?>
        <input type="hidden" name="edit_id" value="<?= $editingPost ? (int)$editingPost['id'] : 0 ?>">

        <div class="field">
            <label for="social-topic">Konu *</label>
            <select id="social-topic" name="topic_slug" required data-social-topic-select>
                <?php foreach ($topicOptions as $topic): ?>
                    <?php $disabled = (!empty($topic['official_only']) && empty($socialUser['is_admin'])) || (empty($topic['active']) && (!$editingPost || $editingPost['topic_slug'] !== $topic['slug'])); ?>
                    <option value="<?= e($topic['slug']) ?>"<?= $form['topic_slug'] === (string)$topic['slug'] ? ' selected' : '' ?><?= $disabled ? ' disabled' : '' ?>>
                        <?= e($topic['icon']) ?> <?= e($topic['name']) ?><?= !empty($topic['official_only']) ? ' — yalnızca ekip' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label for="social-title">Başlık *</label>
            <input id="social-title" type="text" name="title" required minlength="4" maxlength="140" value="<?= e($form['title']) ?>" placeholder="Tartışmanın başlığı">
        </div>

        <div class="field">
            <label for="social-body">İçerik *</label>
            <textarea id="social-body" name="body" required minlength="10" maxlength="5000" rows="10" data-character-count placeholder="Düşünceni, soru veya rehberini yaz. @kullanici ve #etiket kullanabilirsin."><?= e($form['body']) ?></textarea>
            <div class="social-form-meta"><span>Bağlantılar otomatik tanınır; HTML kabul edilmez.</span><span data-count-for="social-body">0 / 5000</span></div>
        </div>

        <fieldset class="social-lfg-fields" data-lfg-fields>
            <legend>Oyun arkadaşı bilgileri</legend>
            <p class="muted">Bu alanlar yalnızca Oyun Arkadaşı konusunda kullanılır.</p>
            <div class="social-form-grid">
                <div class="field">
                    <label for="lfg-game">Oyun</label>
                    <input id="lfg-game" type="text" name="lfg_game" maxlength="60" value="<?= e($form['lfg_game']) ?>" placeholder="Örn. Valorant">
                </div>
                <div class="field">
                    <label for="lfg-platform">Platform</label>
                    <input id="lfg-platform" type="text" name="lfg_platform" maxlength="60" value="<?= e($form['lfg_platform']) ?>" placeholder="PC, PS5, Xbox…">
                </div>
                <div class="field">
                    <label for="lfg-rank">Rank / seviye</label>
                    <input id="lfg-rank" type="text" name="lfg_rank" maxlength="60" value="<?= e($form['lfg_rank']) ?>" placeholder="Örn. Altın">
                </div>
                <div class="field">
                    <label for="lfg-mic">Mikrofon</label>
                    <select id="lfg-mic" name="lfg_mic">
                        <option value="">Seç</option>
                        <?php foreach (['Var', 'Yok', 'İsteğe bağlı'] as $option): ?>
                            <option value="<?= e($option) ?>"<?= $form['lfg_mic'] === $option ? ' selected' : '' ?>><?= e($option) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="lfg-time">Oyun saati</label>
                    <input id="lfg-time" type="text" name="lfg_play_time" maxlength="60" value="<?= e($form['lfg_play_time']) ?>" placeholder="Örn. Hafta içi 20.00 sonrası">
                </div>
                <div class="field">
                    <label for="lfg-needed">Aranan</label>
                    <input id="lfg-needed" type="text" name="lfg_needed" maxlength="60" value="<?= e($form['lfg_needed']) ?>" placeholder="Örn. 2 oyuncu">
                </div>
            </div>
        </fieldset>

        <div class="field">
            <label for="social-tags">Etiketler</label>
            <input id="social-tags" type="text" name="tags" maxlength="180" value="<?= e($form['tags']) ?>" placeholder="#rehber, #indirim, #lfg">
            <p class="form-note">En fazla 5 etiket kullanabilirsin.</p>
        </div>

        <div class="field">
            <label for="social-image">Görsel <?= $canUpload ? '' : '<small>(güvenilir üyelikten sonra açılır)</small>' ?></label>
            <?php if ($editingPost && !empty($editingPost['image'])): ?>
                <div class="social-current-image">
                    <img src="<?= e(social_image_url((string)$editingPost['image'])) ?>" alt="Mevcut gönderi görseli">
                    <label class="social-check"><input type="checkbox" name="remove_image" value="1"> Mevcut görseli kaldır</label>
                </div>
            <?php endif; ?>
            <input id="social-image" type="file" name="image" accept="image/jpeg,image/png,image/webp"<?= $canUpload ? '' : ' disabled' ?>>
            <p class="form-note">JPG, PNG veya WebP; en fazla 4 MB. Görsel otomatik küçültülür.</p>
        </div>

        <div class="social-form-actions">
            <a class="btn btn-ghost" href="<?= $editingPost ? e(social_post_url((int)$editingPost['id'])) : 'index.php' ?>">Vazgeç</a>
            <button class="btn btn-primary" type="submit"><?= $editingPost ? 'Değişiklikleri kaydet' : 'Gönderiyi paylaş' ?></button>
        </div>
    </form>
</section>

<script src="../assets/js/social.js" defer></script>
<?php include __DIR__ . '/../footer.php'; ?>
