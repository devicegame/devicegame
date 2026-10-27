<?php
require_once __DIR__ . '/_common.php';

$postId = (int)($_GET['id'] ?? $_POST['post_id'] ?? 0);
$commentErrors = [];
$commentBody = '';
$commentParentId = 0;
$editingCommentId = 0;
$editingCommentBody = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array((string)($_POST['social_action'] ?? ''), ['comment_create', 'comment_edit'], true)) {
    social_require_login();
    $action = (string)$_POST['social_action'];
    $submittedBody = (string)($_POST['body'] ?? '');
    // Düzenleme hatası ana yorum kutusunu doldurmasın; metin ilgili düzenleme alanında kalsın.
    $commentBody = $action === 'comment_create' ? $submittedBody : '';
    $editingCommentId = $action === 'comment_edit' ? (int)($_POST['comment_id'] ?? 0) : 0;
    $editingCommentBody = $action === 'comment_edit' ? $submittedBody : '';
    $commentParentId = (int)($_POST['parent_id'] ?? 0);
    if (!csrf_verify((string)($_POST['csrf'] ?? ''))) {
        $commentErrors[] = 'Güvenlik doğrulaması başarısız. Sayfayı yenileyip tekrar dene.';
    } else {
        try {
            if ($action === 'comment_create') {
                $result = social_create_comment($socialUser, $postId, $_POST);
            } else {
                $result = social_edit_comment($socialUser, $postId, $editingCommentId, $_POST);
            }
        } catch (Throwable $exception) {
            error_log('[social comment] ' . $exception->getMessage());
            $result = ['errors' => ['Yorum kaydedilemedi. Depolama izinlerini kontrol edin.'], 'id' => $editingCommentId, 'status' => ''];
        }
        if (!empty($result['errors'])) {
            $commentErrors = $result['errors'];
        } else {
            flash_set(($result['status'] ?? '') === 'pending'
                ? 'Yorumun alındı ve moderasyon onayına gönderildi.'
                : ($action === 'comment_edit' ? 'Yorumun güncellendi.' : 'Yorumun yayınlandı.'));
            $targetComment = $action === 'comment_edit' ? $editingCommentId : (int)($result['id'] ?? 0);
            header('Location: ' . social_post_url($postId, $targetComment));
            exit;
        }
    }
}

$post = $postId > 0 ? social_get_post($postId) : null;
$blockedIds = social_blocked_ids($socialUser);
if (!$post || !social_post_visible($post, $socialUser, $blockedIds)) {
    http_response_code(404);
    $page_title = 'Gönderi bulunamadı — DG Social';
    $page_meta_desc = 'Aradığın DG Social gönderisi bulunamadı veya yayından kaldırıldı.';
    $page_css = ['shop.css', 'social.css'];
    $body_class = 'shop-page social-page';
    include __DIR__ . '/../header.php';
    ?>
    <section class="social-shell is-narrow">
        <div class="social-empty">
            <p class="eyebrow">404</p>
            <h1>Bu gönderiyi bulamadık.</h1>
            <p>Gönderi silinmiş, gizlenmiş veya adresi değişmiş olabilir.</p>
            <a class="btn btn-primary" href="index.php">Akışa dön</a>
        </div>
    </section>
    <?php
    include __DIR__ . '/../footer.php';
    exit;
}

$topic = $socialTopicMap[(string)$post['topic_slug']] ?? social_topic((string)$post['topic_slug'], false);
$comments = social_comments($postId, $socialUser);
$commentAuthorIds = [(int)$post['user_id']];
foreach ($comments as $comment) {
    $commentAuthorIds[] = (int)$comment['user_id'];
    foreach ((array)($comment['replies'] ?? []) as $reply) $commentAuthorIds[] = (int)$reply['user_id'];
}
$profiles = social_profiles_by_ids($commentAuthorIds);
$authorProfile = $profiles[(int)$post['user_id']] ?? [
    'user_id' => (int)$post['user_id'],
    'username' => (string)$post['author_username'],
    'display_name' => (string)$post['author_display'],
    'bio' => '',
    'follower_count' => 0,
    'post_count' => 0,
];
$authorUser = (int)$post['user_id'] > 0 && function_exists('dg_find_user_by_id') ? dg_find_user_by_id((int)$post['user_id']) : null;
$socialStates = social_post_states([$postId], $socialUser);
$postState = $socialStates[$postId] ?? ['gg' => false, 'saved' => false];
$isAuthor = $socialUser && (int)$socialUser['id'] === (int)$post['user_id'];
$isAdmin = !empty($socialUser['is_admin']);
$isFollowingAuthor = false;
if ($socialUser && !$isAuthor) {
    $isFollowingAuthor = in_array((int)$post['user_id'], social_follows((int)$socialUser['id'])['following'], true);
}
$relatedResult = social_list_posts(['topic' => (string)$post['topic_slug'], 'per_page' => 8], $socialUser);
$relatedPosts = array_slice(array_values(array_filter($relatedResult['items'], static fn(array $item): bool => (int)$item['id'] !== $postId)), 0, 3);
$relatedStates = social_post_states(array_map(static fn(array $item): int => (int)$item['id'], $relatedPosts), $socialUser);
$returnUrl = 'gonderi.php?id=' . $postId;

function social_detail_report_form(string $type, int $targetId, int $postId, string $returnUrl): string
{
    $html = '<details class="social-report"><summary>Şikâyet et</summary>';
    $html .= '<form method="post" action="aksiyon.php">' . csrf_field();
    $html .= '<input type="hidden" name="islem" value="sikayet">';
    $html .= '<input type="hidden" name="target_type" value="' . e($type) . '">';
    $html .= '<input type="hidden" name="target_id" value="' . $targetId . '">';
    $html .= '<input type="hidden" name="post_id" value="' . $postId . '">';
    $html .= '<input type="hidden" name="return" value="' . e($returnUrl) . '">';
    $html .= '<label>Sebep<select name="reason"><option>Spam</option><option>Hakaret / Taciz</option><option>Dolandırıcılık</option><option>Uygunsuz içerik</option><option>Telif</option><option>Diğer</option></select></label>';
    $html .= '<label>Açıklama<textarea name="details" maxlength="600" rows="3" placeholder="Moderatör için kısa not (isteğe bağlı)"></textarea></label>';
    $html .= '<button class="btn btn-ghost social-small-btn" type="submit">Şikâyeti gönder</button></form></details>';
    return $html;
}

function social_render_comment(array $comment, bool $isReply = false): void
{
    global $post, $profiles, $socialUser, $isAdmin, $returnUrl, $editingCommentId, $editingCommentBody;
    $commentId = (int)$comment['id'];
    $profile = $profiles[(int)$comment['user_id']] ?? [
        'user_id' => (int)$comment['user_id'],
        'username' => (string)$comment['author_username'],
        'display_name' => (string)$comment['author_display'],
    ];
    $isOwn = $socialUser && (int)$socialUser['id'] === (int)$comment['user_id'];
    ?>
    <article id="yorum-<?= $commentId ?>" class="social-comment<?= $isReply ? ' is-reply' : '' ?>">
        <div class="social-comment-head">
            <a class="social-author" href="<?= e(social_profile_url($profile)) ?>">
                <?= social_avatar_html($profile) ?>
                <span><strong><?= e($profile['display_name']) ?></strong><small>@<?= e($profile['username']) ?> · <?= e(social_time_ago((int)$comment['created_at'])) ?></small></span>
            </a>
            <?php if ((string)$comment['status'] !== 'published'): ?><span class="social-mini-badge is-warning"><?= e(social_status_label((string)$comment['status'])) ?></span><?php endif; ?>
        </div>
        <div class="social-rich-text"><?= social_render_text((string)$comment['body']) ?></div>
        <?php if (!empty($comment['edited_at'])): ?><p class="social-edited">Düzenlendi</p><?php endif; ?>

        <div class="social-comment-actions">
            <?php if ($socialUser && !$isReply && empty($post['locked'])): ?>
                <button type="button" class="social-text-button" data-reply-button data-parent-id="<?= $commentId ?>" data-author="@<?= e($profile['username']) ?>">Cevapla</button>
            <?php endif; ?>
            <?php if ($socialUser && ($isOwn || $isAdmin)): ?>
                <details class="social-inline-edit"<?= $editingCommentId === $commentId ? ' open' : '' ?>>
                    <summary>Düzenle</summary>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="social_action" value="comment_edit">
                        <input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>">
                        <input type="hidden" name="comment_id" value="<?= $commentId ?>">
                        <textarea name="body" maxlength="1500" rows="4" required><?= e($editingCommentId === $commentId && $editingCommentBody !== '' ? $editingCommentBody : (string)$comment['body']) ?></textarea>
                        <button class="btn btn-primary social-small-btn" type="submit">Kaydet</button>
                    </form>
                </details>
                <form method="post" action="aksiyon.php" data-confirm="Bu yorum silinsin mi?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="islem" value="yorum_sil">
                    <input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>">
                    <input type="hidden" name="comment_id" value="<?= $commentId ?>">
                    <input type="hidden" name="return" value="<?= e($returnUrl) ?>">
                    <button class="social-text-button is-danger" type="submit">Sil</button>
                </form>
            <?php endif; ?>
            <?php if ($isAdmin && (string)$comment['status'] !== 'published'): ?>
                <form method="post" action="aksiyon.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="islem" value="yorum_onayla">
                    <input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>">
                    <input type="hidden" name="comment_id" value="<?= $commentId ?>">
                    <input type="hidden" name="return" value="<?= e($returnUrl) ?>">
                    <button class="social-text-button" type="submit">Yayınla</button>
                </form>
            <?php endif; ?>
            <?php if ($isAdmin && (string)$comment['status'] === 'published'): ?>
                <form method="post" action="aksiyon.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="islem" value="yorum_gizle">
                    <input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>">
                    <input type="hidden" name="comment_id" value="<?= $commentId ?>">
                    <input type="hidden" name="return" value="<?= e($returnUrl) ?>">
                    <button class="social-text-button" type="submit">Gizle</button>
                </form>
            <?php endif; ?>
            <?php if ($socialUser && !$isOwn): ?>
                <?= social_detail_report_form('comment', $commentId, (int)$post['id'], $returnUrl) ?>
            <?php endif; ?>
        </div>

        <?php if (!empty($comment['replies'])): ?>
            <div class="social-replies">
                <?php foreach ((array)$comment['replies'] as $reply) social_render_comment($reply, true); ?>
            </div>
        <?php endif; ?>
    </article>
    <?php
}

$page_title = $post['title'] . ' — DG Social';
$page_meta_desc = social_excerpt((string)$post['body'], 155);
$page_css = ['shop.css', 'social.css'];
$body_class = 'shop-page social-page';
include __DIR__ . '/../header.php';
?>

<section class="social-shell">
    <nav class="social-breadcrumb" aria-label="Konum">
        <a href="index.php">DG Social</a><span>/</span>
        <?php if ($topic): ?><a href="index.php?konu=<?= rawurlencode((string)$topic['slug']) ?>"><?= e($topic['name']) ?></a><span>/</span><?php endif; ?>
        <span aria-current="page"><?= e($post['title']) ?></span>
    </nav>

    <?= social_subnav('feed') ?>
    <?= social_flash_html() ?>

    <?php if ((string)$post['status'] === 'pending'): ?>
        <div class="social-alert is-warning">Bu gönderi moderasyon onayını bekliyor. Şu anda yalnızca sen ve yöneticiler görebilir.</div>
    <?php elseif ((string)$post['status'] === 'hidden'): ?>
        <div class="social-alert is-error">Bu gönderi moderasyon tarafından gizlendi.</div>
    <?php endif; ?>

    <div class="social-detail-layout">
        <article class="social-detail-card">
            <div class="social-post-top">
                <a class="social-author" href="<?= e(social_profile_url($authorProfile)) ?>">
                    <?= social_avatar_html($authorProfile, 'is-large') ?>
                    <span><strong><?= e($authorProfile['display_name']) ?></strong><small>@<?= e($authorProfile['username']) ?> · <?= e(social_format_date((int)$post['created_at'])) ?></small></span>
                </a>
                <div class="social-post-badges">
                    <?php if ($topic): ?><a class="social-topic-badge" href="index.php?konu=<?= rawurlencode((string)$topic['slug']) ?>"><?= e($topic['icon']) ?> <?= e($topic['name']) ?></a><?php endif; ?>
                    <?php if (!empty($post['pinned'])): ?><span class="social-mini-badge">Sabit</span><?php endif; ?>
                    <?php if (!empty($post['locked'])): ?><span class="social-mini-badge">Kilitli</span><?php endif; ?>
                </div>
            </div>

            <h1><?= e($post['title']) ?></h1>
            <?php if (!empty($post['image'])): ?>
                <figure class="social-detail-media"><img src="<?= e(social_image_url((string)$post['image'])) ?>" alt="<?= e($post['title']) ?> görseli" onerror="this.closest('figure')?.remove()"></figure>
            <?php endif; ?>
            <div class="social-rich-text is-post"><?= social_render_text((string)$post['body']) ?></div>

            <?php if (!empty($post['meta']) && array_filter((array)$post['meta'])): ?>
                <div class="social-lfg-summary">
                    <?php foreach ((array)$post['meta'] as $key => $value): ?>
                        <?php if (trim((string)$value) !== ''): ?><div><span><?= e(social_lfg_label((string)$key)) ?></span><strong><?= e($value) ?></strong></div><?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($post['tags'])): ?>
                <div class="social-tags is-large">
                    <?php foreach ((array)$post['tags'] as $tag): ?><a href="index.php?q=<?= rawurlencode('#' . (string)$tag) ?>">#<?= e($tag) ?></a><?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($post['edited_at'])): ?><p class="social-edited">Son düzenleme: <?= e(social_format_date((int)$post['edited_at'])) ?></p><?php endif; ?>

            <div class="social-post-actions is-detail">
                <?php if ($socialUser): ?>
                    <form method="post" action="aksiyon.php" data-social-ajax="gg">
                        <?= csrf_field() ?>
                        <input type="hidden" name="islem" value="gg">
                        <input type="hidden" name="post_id" value="<?= $postId ?>">
                        <input type="hidden" name="return" value="<?= e($returnUrl) ?>">
                        <button type="submit" class="social-action<?= !empty($postState['gg']) ? ' is-active' : '' ?>" aria-pressed="<?= !empty($postState['gg']) ? 'true' : 'false' ?>">GG <span data-gg-count><?= (int)$post['gg_count'] ?></span></button>
                    </form>
                    <form method="post" action="aksiyon.php" data-social-ajax="save">
                        <?= csrf_field() ?>
                        <input type="hidden" name="islem" value="kaydet">
                        <input type="hidden" name="post_id" value="<?= $postId ?>">
                        <input type="hidden" name="return" value="<?= e($returnUrl) ?>">
                        <button type="submit" class="social-action<?= !empty($postState['saved']) ? ' is-active' : '' ?>" aria-pressed="<?= !empty($postState['saved']) ? 'true' : 'false' ?>"><?= !empty($postState['saved']) ? 'Kaydedildi' : 'Kaydet' ?></button>
                    </form>
                <?php else: ?>
                    <a class="social-action" href="<?= e(social_login_url('sosyal/' . $returnUrl)) ?>">GG <?= (int)$post['gg_count'] ?></a>
                <?php endif; ?>
                <button type="button" class="social-action" data-copy-link>Bağlantıyı kopyala</button>
                <?php if ($isAuthor || $isAdmin): ?>
                    <a class="social-action" href="yeni.php?duzenle=<?= $postId ?>">Düzenle</a>
                    <form method="post" action="aksiyon.php" data-confirm="Bu gönderi silinsin mi?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="islem" value="gonderi_sil">
                        <input type="hidden" name="post_id" value="<?= $postId ?>">
                        <button class="social-action is-danger" type="submit">Sil</button>
                    </form>
                <?php endif; ?>
                <?php if ($socialUser && !$isAuthor): ?>
                    <?= social_detail_report_form('post', $postId, $postId, $returnUrl) ?>
                <?php endif; ?>
            </div>

            <?php if ($isAdmin): ?>
                <div class="social-admin-tools">
                    <strong>Moderasyon</strong>
                    <?php if (($post['status'] ?? '') !== 'published'): ?>
                        <form method="post" action="aksiyon.php"><?= csrf_field() ?><input type="hidden" name="islem" value="gonderi_onayla"><input type="hidden" name="post_id" value="<?= $postId ?>"><input type="hidden" name="return" value="<?= e($returnUrl) ?>"><button type="submit">Yayınla</button></form>
                    <?php else: ?>
                        <form method="post" action="aksiyon.php"><?= csrf_field() ?><input type="hidden" name="islem" value="gonderi_gizle"><input type="hidden" name="post_id" value="<?= $postId ?>"><input type="hidden" name="return" value="<?= e($returnUrl) ?>"><button type="submit">Gizle</button></form>
                    <?php endif; ?>
                    <form method="post" action="aksiyon.php"><?= csrf_field() ?><input type="hidden" name="islem" value="gonderi_sabitle"><input type="hidden" name="post_id" value="<?= $postId ?>"><input type="hidden" name="return" value="<?= e($returnUrl) ?>"><button type="submit"><?= !empty($post['pinned']) ? 'Sabitten kaldır' : 'Sabitle' ?></button></form>
                    <form method="post" action="aksiyon.php"><?= csrf_field() ?><input type="hidden" name="islem" value="gonderi_kilitle"><input type="hidden" name="post_id" value="<?= $postId ?>"><input type="hidden" name="return" value="<?= e($returnUrl) ?>"><button type="submit"><?= !empty($post['locked']) ? 'Kilidi aç' : 'Kilitle' ?></button></form>
                </div>
            <?php endif; ?>

            <section id="yorumlar" class="social-comments">
                <div class="social-section-title">
                    <h2>Yorumlar</h2>
                    <span><?= (int)$post['comment_count'] ?> yayınlanmış yorum</span>
                </div>

                <?php if ($commentErrors): ?>
                    <div class="social-alert is-error" role="alert">
                        <ul><?php foreach ($commentErrors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
                    </div>
                <?php endif; ?>

                <?php if ($comments): ?>
                    <?php foreach ($comments as $comment) social_render_comment($comment); ?>
                <?php else: ?>
                    <div class="social-empty is-small"><h3>Henüz yorum yok.</h3><p>İlk cevabı sen yaz.</p></div>
                <?php endif; ?>

                <?php if ($socialUser && (empty($post['locked']) || $isAdmin)): ?>
                    <form method="post" class="social-comment-form" id="comment-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="social_action" value="comment_create">
                        <input type="hidden" name="post_id" value="<?= $postId ?>">
                        <input type="hidden" name="parent_id" value="<?= (int)$commentParentId ?>" data-comment-parent>
                        <div class="social-reply-banner" data-reply-banner hidden>
                            <span><b data-reply-author></b> yorumuna cevap veriyorsun.</span>
                            <button type="button" data-reply-cancel>Vazgeç</button>
                        </div>
                        <label for="social-comment-body">Yorumun</label>
                        <textarea id="social-comment-body" name="body" maxlength="1500" rows="5" required data-character-count placeholder="Saygılı ve konuyla ilgili bir yorum yaz…"><?= e($commentBody) ?></textarea>
                        <div class="social-form-meta"><span>Yeni üyelerin yorumları onaydan geçebilir.</span><span data-count-for="social-comment-body">0 / 1500</span></div>
                        <button class="btn btn-primary" type="submit">Yorumu gönder</button>
                    </form>
                <?php elseif (!empty($post['locked'])): ?>
                    <div class="social-locked-note">Bu konu yeni yorumlara kilitlendi.</div>
                <?php else: ?>
                    <div class="social-locked-note">Yorum yapmak için <a href="<?= e(social_login_url('sosyal/' . $returnUrl)) ?>">giriş yap</a>.</div>
                <?php endif; ?>
            </section>
        </article>

        <aside class="social-sidebar">
            <section class="social-panel social-author-panel">
                <a class="social-author" href="<?= e(social_profile_url($authorProfile)) ?>">
                    <?= social_avatar_html($authorProfile, 'is-large') ?>
                    <span><strong><?= e($authorProfile['display_name']) ?></strong><small>@<?= e($authorProfile['username']) ?></small></span>
                </a>
                <?php if (trim((string)($authorProfile['bio'] ?? '')) !== ''): ?><p><?= e($authorProfile['bio']) ?></p><?php endif; ?>
                <div class="social-mini-stats">
                    <span><b><?= (int)($authorProfile['post_count'] ?? 0) ?></b> gönderi</span>
                    <span><b><?= (int)($authorProfile['follower_count'] ?? 0) ?></b> takipçi</span>
                </div>
                <?php if ($socialUser && !$isAuthor): ?>
                    <form method="post" action="aksiyon.php" data-social-ajax="follow">
                        <?= csrf_field() ?>
                        <input type="hidden" name="islem" value="takip">
                        <input type="hidden" name="target_id" value="<?= (int)$post['user_id'] ?>">
                        <input type="hidden" name="return" value="<?= e($returnUrl) ?>">
                        <button class="btn <?= $isFollowingAuthor ? 'btn-ghost' : 'btn-primary' ?> btn-full" type="submit" aria-pressed="<?= $isFollowingAuthor ? 'true' : 'false' ?>"><?= $isFollowingAuthor ? 'Takip ediliyor' : 'Takip et' ?></button>
                    </form>
                <?php endif; ?>
                <a class="text-link" href="<?= e(social_profile_url($authorProfile)) ?>">Profili görüntüle</a>
            </section>

            <?php if ($relatedPosts): ?>
                <section class="social-related">
                    <h2>Aynı konudan</h2>
                    <?php $socialStates = $relatedStates; ?>
                    <?php foreach ($relatedPosts as $post): ?>
                        <?php include __DIR__ . '/_post-card.php'; ?>
                    <?php endforeach; ?>
                </section>
            <?php endif; ?>
        </aside>
    </div>
</section>

<script src="../assets/js/social.js" defer></script>
<?php include __DIR__ . '/../footer.php'; ?>
