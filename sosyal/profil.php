<?php
require_once __DIR__ . '/_common.php';

$username = trim((string)($_GET['kullanici'] ?? ''));
$profile = $username !== '' ? social_profile_by_username($username, false) : null;
$profileErrors = [];
$profileForm = null;

if ($profile && $socialUser && (int)$socialUser['id'] === (int)$profile['user_id'] && $_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['social_action'] ?? '') === 'profile_update') {
    $profileForm = $_POST;
    if (!csrf_verify((string)($_POST['csrf'] ?? ''))) {
        $profileErrors[] = 'Güvenlik doğrulaması başarısız. Sayfayı yenileyip tekrar dene.';
    } else {
        try {
            $result = social_update_profile($socialUser, $_POST);
        } catch (Throwable $exception) {
            error_log('[social profile] ' . $exception->getMessage());
            $result = ['errors' => ['Profil kaydedilemedi. Depolama izinlerini kontrol edin.'], 'profile' => $profile];
        }
        if (!empty($result['errors'])) {
            $profileErrors = $result['errors'];
        } else {
            flash_set('Sosyal profilin güncellendi.');
            header('Location: ' . social_profile_url($result['profile']));
            exit;
        }
    }
}

if (!$profile) {
    http_response_code(404);
    $page_title = 'Kullanıcı bulunamadı — DG Social';
    $page_meta_desc = 'Aradığın DG Social kullanıcısı bulunamadı.';
    $page_css = ['shop.css', 'social.css'];
    $body_class = 'shop-page social-page';
    include __DIR__ . '/../header.php';
    ?>
    <section class="social-shell is-narrow">
        <div class="social-empty">
            <p class="eyebrow">404</p>
            <h1>Bu kullanıcıyı bulamadık.</h1>
            <p>Kullanıcı adı değişmiş veya hesap kaldırılmış olabilir.</p>
            <a class="btn btn-primary" href="index.php">Akışa dön</a>
        </div>
    </section>
    <?php
    include __DIR__ . '/../footer.php';
    exit;
}

$profileId = (int)$profile['user_id'];
$isOwn = $socialUser && (int)$socialUser['id'] === $profileId;
$isAdmin = !empty($socialUser['is_admin']);
$profileUser = $profileId > 0 && function_exists('dg_find_user_by_id') ? dg_find_user_by_id($profileId) : null;
$targetFollows = social_follows($profileId);
$viewerFollows = $socialUser ? social_follows((int)$socialUser['id']) : ['following' => [], 'followers' => [], 'blocked' => []];
$blockedByTarget = $socialUser && in_array((int)$socialUser['id'], $targetFollows['blocked'], true);
$viewerBlockedTarget = $socialUser && in_array($profileId, $viewerFollows['blocked'], true);
$isFollowing = $socialUser && in_array($profileId, $viewerFollows['following'], true);
$canViewContent = $isAdmin || (!$blockedByTarget && !$viewerBlockedTarget);
$badges = social_profile_badges($profile, $profileUser);
$page = max(1, (int)($_GET['sayfa'] ?? 1));
$result = $canViewContent
    ? social_list_posts(['author_id' => $profileId, 'page' => $page, 'per_page' => 10, 'sort' => 'activity'], $socialUser)
    : ['items' => [], 'total' => 0, 'page' => $page, 'pages' => 1];
$posts = $result['items'];
$socialStates = social_post_states(array_map(static fn(array $post): int => (int)$post['id'], $posts), $socialUser);
$recentComments = $canViewContent ? social_user_comments($profileId, $socialUser, 8) : [];
$followers = $canViewContent ? social_profiles_by_ids(array_slice($targetFollows['followers'], 0, 12)) : [];
$following = $canViewContent ? social_profiles_by_ids(array_slice($targetFollows['following'], 0, 12)) : [];
$returnUrl = 'profil.php?kullanici=' . rawurlencode((string)$profile['username']);
if ($profileForm === null) {
    $profileForm = [
        'display_name' => (string)$profile['display_name'],
        'bio' => (string)$profile['bio'],
        'platforms' => implode(', ', (array)$profile['platforms']),
        'favorite_games' => implode(', ', (array)$profile['favorite_games']),
        'show_balance' => !empty($profile['show_balance']),
    ];
}

$page_title = $profile['display_name'] . ' (@' . $profile['username'] . ') — DG Social';
$page_meta_desc = trim((string)$profile['bio']) !== '' ? social_excerpt((string)$profile['bio'], 155) : $profile['display_name'] . ' kullanıcısının DeviceGame sosyal profili.';
$page_css = ['shop.css', 'social.css'];
$body_class = 'shop-page social-page';
include __DIR__ . '/../header.php';
?>

<section class="social-shell">
    <?= social_subnav($isOwn ? 'profile' : 'feed') ?>
    <?= social_flash_html() ?>

    <header class="social-profile-hero" style="--avatar-h:<?= social_avatar_hue($profileId) ?>">
        <div class="social-profile-main">
            <?= social_avatar_html($profile, 'is-xlarge') ?>
            <div>
                <p class="eyebrow">DG Social Profili</p>
                <h1><?= e($profile['display_name']) ?></h1>
                <p class="social-username">@<?= e($profile['username']) ?> · Katılım: <?= e(date('d.m.Y', (int)$profile['joined_at'])) ?></p>
                <?php if ($badges): ?>
                    <div class="social-badges">
                        <?php foreach ($badges as $badge): ?><span><?= e($badge) ?></span><?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="social-profile-actions">
            <?php if ($isOwn): ?>
                <a class="btn btn-primary social-small-btn" href="#profil-duzenle">Profili düzenle</a>
                <a class="btn btn-ghost social-small-btn" href="yeni.php">Yeni gönderi</a>
            <?php elseif ($socialUser): ?>
                <?php if ($viewerBlockedTarget): ?>
                    <form method="post" action="aksiyon.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="islem" value="engelle">
                        <input type="hidden" name="target_id" value="<?= $profileId ?>">
                        <input type="hidden" name="return" value="<?= e($returnUrl) ?>">
                        <button class="btn btn-primary social-small-btn" type="submit">Engeli kaldır</button>
                    </form>
                <?php elseif (!$blockedByTarget): ?>
                    <form method="post" action="aksiyon.php" data-social-ajax="follow">
                        <?= csrf_field() ?>
                        <input type="hidden" name="islem" value="takip">
                        <input type="hidden" name="target_id" value="<?= $profileId ?>">
                        <input type="hidden" name="return" value="<?= e($returnUrl) ?>">
                        <button class="btn <?= $isFollowing ? 'btn-ghost' : 'btn-primary' ?> social-small-btn" type="submit" aria-pressed="<?= $isFollowing ? 'true' : 'false' ?>"><?= $isFollowing ? 'Takip ediliyor' : 'Takip et' ?></button>
                    </form>
                    <form method="post" action="aksiyon.php" data-confirm="Bu kullanıcıyı engellemek istiyor musun?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="islem" value="engelle">
                        <input type="hidden" name="target_id" value="<?= $profileId ?>">
                        <input type="hidden" name="return" value="<?= e($returnUrl) ?>">
                        <button class="btn btn-ghost social-small-btn" type="submit">Engelle</button>
                    </form>
                <?php endif; ?>
            <?php else: ?>
                <a class="btn btn-primary social-small-btn" href="<?= e(social_login_url('sosyal/' . $returnUrl)) ?>">Takip etmek için giriş yap</a>
            <?php endif; ?>
        </div>
    </header>

    <?php if ($blockedByTarget && !$isAdmin): ?>
        <div class="social-empty">
            <h2>Bu profil sana uygun değil.</h2>
            <p>Kullanıcının gönderileri ve yorumları sana gösterilmiyor.</p>
            <a class="btn btn-primary" href="index.php">Akışa dön</a>
        </div>
    <?php elseif ($viewerBlockedTarget && !$isAdmin): ?>
        <div class="social-empty">
            <h2>Bu kullanıcıyı engelledin.</h2>
            <p>İçeriklerini görmek için engeli kaldırabilirsin.</p>
        </div>
    <?php else: ?>
        <div class="social-profile-grid">
            <aside class="social-sidebar">
                <section class="social-panel">
                    <h2>Hakkında</h2>
                    <p><?= trim((string)$profile['bio']) !== '' ? e($profile['bio']) : 'Henüz bir biyografi yazılmamış.' ?></p>
                    <dl class="social-profile-facts">
                        <div><dt>Gönderi</dt><dd><?= (int)$profile['post_count'] ?></dd></div>
                        <div><dt>Yorum</dt><dd><?= (int)$profile['comment_count'] ?></dd></div>
                        <div><dt>Takipçi</dt><dd><?= (int)$profile['follower_count'] ?></dd></div>
                        <div><dt>Takip</dt><dd><?= (int)$profile['following_count'] ?></dd></div>
                        <?php if ($profileUser && (!empty($profile['show_balance']) || $isOwn || $isAdmin)): ?>
                            <div><dt>D-Coin</dt><dd><?= number_format((int)$profileUser['dcoin_balance'], 0, ',', '.') ?></dd></div>
                        <?php endif; ?>
                    </dl>
                    <?php if (!empty($profile['platforms'])): ?>
                        <h3>Platformlar</h3>
                        <div class="social-tags"><?php foreach ((array)$profile['platforms'] as $platform): ?><span><?= e($platform) ?></span><?php endforeach; ?></div>
                    <?php endif; ?>
                    <?php if (!empty($profile['favorite_games'])): ?>
                        <h3>Favori oyunlar</h3>
                        <div class="social-tags"><?php foreach ((array)$profile['favorite_games'] as $game): ?><span><?= e($game) ?></span><?php endforeach; ?></div>
                    <?php endif; ?>
                </section>

                <?php if ($followers): ?>
                    <section class="social-panel">
                        <h2>Takipçiler</h2>
                        <div class="social-user-list">
                            <?php foreach ($followers as $follower): ?>
                                <a href="<?= e(social_profile_url($follower)) ?>"><?= social_avatar_html($follower) ?><span><strong><?= e($follower['display_name']) ?></strong><small>@<?= e($follower['username']) ?></small></span></a>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if ($following): ?>
                    <section class="social-panel">
                        <h2>Takip ettikleri</h2>
                        <div class="social-user-list">
                            <?php foreach ($following as $followed): ?>
                                <a href="<?= e(social_profile_url($followed)) ?>"><?= social_avatar_html($followed) ?><span><strong><?= e($followed['display_name']) ?></strong><small>@<?= e($followed['username']) ?></small></span></a>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if ($isAdmin && !$isOwn && $profileId > 0): ?>
                    <section class="social-panel">
                        <h2>Yönetici araçları</h2>
                        <form method="post" action="aksiyon.php" class="social-inline-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="islem" value="guven">
                            <input type="hidden" name="target_id" value="<?= $profileId ?>">
                            <input type="hidden" name="return" value="<?= e($returnUrl) ?>">
                            <label for="trust-level">Güven seviyesi</label>
                            <select id="trust-level" name="trust_level">
                                <option value="0"<?= (int)$profile['trust_level'] === 0 ? ' selected' : '' ?>>0 — Yeni / onaylı</option>
                                <option value="1"<?= (int)$profile['trust_level'] === 1 ? ' selected' : '' ?>>1 — Güvenilir</option>
                                <option value="2"<?= (int)$profile['trust_level'] === 2 ? ' selected' : '' ?>>2 — Ayrıcalıklı</option>
                            </select>
                            <button class="btn btn-ghost social-small-btn" type="submit">Güncelle</button>
                        </form>
                        <p class="muted">Kullanıcıyı tamamen engellemek için yönetim panelindeki Kullanıcılar bölümünü kullan.</p>
                    </section>
                <?php endif; ?>
            </aside>

            <div class="social-main">
                <?php if ($isOwn): ?>
                    <section id="profil-duzenle" class="social-form-card">
                        <h2>Profili düzenle</h2>
                        <?php if ($profileErrors): ?>
                            <div class="social-alert is-error"><ul><?php foreach ($profileErrors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
                        <?php endif; ?>
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="social_action" value="profile_update">
                            <div class="field">
                                <label for="profile-display">Görünen ad *</label>
                                <input id="profile-display" type="text" name="display_name" required maxlength="50" value="<?= e($profileForm['display_name'] ?? '') ?>">
                            </div>
                            <div class="field">
                                <label for="profile-bio">Biyografi</label>
                                <textarea id="profile-bio" name="bio" maxlength="500" rows="4" data-character-count><?= e($profileForm['bio'] ?? '') ?></textarea>
                                <div class="social-form-meta"><span>Oyuncu kimliğini kısaca anlat.</span><span data-count-for="profile-bio">0 / 500</span></div>
                            </div>
                            <div class="social-form-grid">
                                <div class="field">
                                    <label for="profile-platforms">Platformlar</label>
                                    <input id="profile-platforms" type="text" name="platforms" value="<?= e($profileForm['platforms'] ?? '') ?>" placeholder="PC, PS5, Switch">
                                </div>
                                <div class="field">
                                    <label for="profile-games">Favori oyunlar</label>
                                    <input id="profile-games" type="text" name="favorite_games" value="<?= e($profileForm['favorite_games'] ?? '') ?>" placeholder="Oyunları virgülle ayır">
                                </div>
                            </div>
                            <label class="social-check"><input type="checkbox" name="show_balance" value="1"<?= !empty($profileForm['show_balance']) ? ' checked' : '' ?>> D-Coin bakiyemi profilimde göster</label>
                            <button class="btn btn-primary" type="submit">Profili kaydet</button>
                        </form>
                    </section>
                <?php endif; ?>

                <section>
                    <div class="social-section-title">
                        <h2>Gönderiler</h2>
                        <span><?= (int)$result['total'] ?> içerik</span>
                    </div>
                    <?php if ($posts): ?>
                        <div class="social-feed">
                            <?php foreach ($posts as $post): ?>
                                <?php include __DIR__ . '/_post-card.php'; ?>
                            <?php endforeach; ?>
                        </div>
                        <?= social_pagination_html('profil.php', ['kullanici' => (string)$profile['username']], (int)$result['page'], (int)$result['pages']) ?>
                    <?php else: ?>
                        <div class="social-empty is-small"><h3>Henüz gönderi yok.</h3><p>Bu kullanıcı henüz bir şey paylaşmamış.</p></div>
                    <?php endif; ?>
                </section>

                <?php if ($recentComments): ?>
                    <section class="social-panel social-recent-comments">
                        <h2>Son yorumlar</h2>
                        <?php foreach ($recentComments as $comment): ?>
                            <a href="<?= e(social_post_url((int)$comment['post_id'], (int)$comment['id'])) ?>">
                                <small><?= e($comment['post_title']) ?> · <?= e(social_time_ago((int)$comment['created_at'])) ?><?php if (($comment['status'] ?? '') !== 'published'): ?> · <?= e(social_status_label((string)$comment['status'])) ?><?php endif; ?></small>
                                <p><?= e($comment['excerpt']) ?></p>
                            </a>
                        <?php endforeach; ?>
                    </section>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</section>

<script src="../assets/js/social.js" defer></script>
<?php include __DIR__ . '/../footer.php'; ?>
