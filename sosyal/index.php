<?php
require_once __DIR__ . '/_common.php';

$topicSlug = social_slugify((string)($_GET['konu'] ?? ''));
$activeTopic = $topicSlug !== '' ? ($socialTopicMap[$topicSlug] ?? null) : null;
$feed = (string)($_GET['akis'] ?? 'kesfet');
if (!in_array($feed, ['kesfet', 'takip', 'kaydedilenler'], true)) $feed = 'kesfet';
$sort = (string)($_GET['sirala'] ?? 'aktivite');
$sortMap = ['aktivite' => 'activity', 'yeni' => 'new', 'populer' => 'popular'];
if (!isset($sortMap[$sort])) $sort = 'aktivite';
$query = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['sayfa'] ?? 1));
$needsLogin = !$socialUser && in_array($feed, ['takip', 'kaydedilenler'], true);

if ($needsLogin || ($topicSlug !== '' && !$activeTopic)) {
    $result = ['items' => [], 'total' => 0, 'page' => $page, 'pages' => 1, 'per_page' => 15];
} else {
    $result = social_list_posts([
        'topic' => $topicSlug,
        'feed' => $feed === 'takip' ? 'following' : ($feed === 'kaydedilenler' ? 'saved' : 'latest'),
        'q' => $query,
        'sort' => $sortMap[$sort],
        'page' => $page,
        'per_page' => 15,
    ], $socialUser);
}
$posts = $result['items'];
$socialStates = social_post_states(array_map(static fn(array $post): int => (int)$post['id'], $posts), $socialUser);
$matchedProfiles = $query !== '' ? social_search_profiles($query, $socialUser, 6) : [];
$stats = social_stats(!empty($socialUser['is_admin']));
$topicCounts = [];
foreach ($socialTopics as $topic) {
    $topicCounts[(string)$topic['slug']] = social_list_posts([
        'topic' => (string)$topic['slug'],
        'per_page' => 1,
    ], null)['total'];
}
$baseParams = array_filter([
    'konu' => $topicSlug,
    'akis' => $feed !== 'kesfet' ? $feed : '',
    'sirala' => $sort !== 'aktivite' ? $sort : '',
    'q' => $query,
], static fn($value): bool => $value !== '');

if ($topicSlug !== '' && !$activeTopic && !headers_sent()) http_response_code(404);
$page_title = $activeTopic ? $activeTopic['name'] . ' — DG Social' : 'DG Social';
$page_meta_desc = $activeTopic && trim((string)$activeTopic['description']) !== ''
    ? trim((string)$activeTopic['description'])
    : 'DeviceGame oyuncularının forum ve sosyal akış alanı. Oyun önerileri, oyun arkadaşları, rehberler ve stüdyo duyuruları.';
$page_css = ['shop.css', 'social.css'];
$body_class = 'shop-page social-page';
include __DIR__ . '/../header.php';
?>

<section class="social-shell">
    <header class="social-hero">
        <div>
            <p class="eyebrow">DG Social · Topluluk</p>
            <h1><?= $activeTopic ? e($activeTopic['name']) . '.' : 'Oyunu birlikte konuşalım.' ?></h1>
            <p><?= e($activeTopic ? ($activeTopic['description'] ?: 'Bu konudaki gönderiler ve tartışmalar.') : 'Oyun önerileri paylaş, ekip arkadaşı bul, rehberler yaz ve DeviceGame projelerini toplulukla birlikte takip et.') ?></p>
        </div>
        <div class="social-hero-stats" aria-label="Topluluk istatistikleri">
            <div><strong><?= number_format((int)$stats['posts'], 0, ',', '.') ?></strong><span>gönderi</span></div>
            <div><strong><?= number_format((int)$stats['members'], 0, ',', '.') ?></strong><span>üye</span></div>
            <div><strong><?= (int)$stats['topics'] ?></strong><span>konu</span></div>
        </div>
    </header>

    <?= social_subnav('feed') ?>
    <?= social_flash_html() ?>

    <div class="social-layout">
        <aside class="social-sidebar social-sidebar-left">
            <section class="social-panel">
                <div class="social-panel-head">
                    <h2>Konular</h2>
                    <?php if ($topicSlug !== ''): ?><a href="index.php">Temizle</a><?php endif; ?>
                </div>
                <nav class="social-topic-list" aria-label="Forum konuları">
                    <a href="index.php"<?= $topicSlug === '' ? ' aria-current="true"' : '' ?>><span>🌐 Tümü</span><small><?= (int)$stats['posts'] ?></small></a>
                    <?php foreach ($socialTopics as $topic): ?>
                        <a href="index.php?konu=<?= rawurlencode((string)$topic['slug']) ?>"<?= $topicSlug === (string)$topic['slug'] ? ' aria-current="true"' : '' ?>>
                            <span><?= e($topic['icon']) ?> <?= e($topic['name']) ?></span>
                            <small><?= (int)($topicCounts[(string)$topic['slug']] ?? 0) ?></small>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </section>

            <section class="social-panel social-rules">
                <h2>Topluluk kuralları</h2>
                <ul>
                    <li>Saygılı ol, kişisel bilgi paylaşma.</li>
                    <li>Spam ve tekrar eden tanıtım yapma.</li>
                    <li>Hesap/key alışverişini yalnızca mağazadan yap.</li>
                    <li>Sorunlu içerikleri tartışmak yerine şikâyet et.</li>
                </ul>
            </section>
        </aside>

        <div class="social-main">
            <?php if ($socialUser): ?>
                <section class="social-composer-cta">
                    <?= social_avatar_html($socialProfile) ?>
                    <a href="yeni.php<?= $topicSlug !== '' ? '?konu=' . rawurlencode($topicSlug) : '' ?>">Ne düşünüyorsun, <?= e($socialProfile['display_name'] ?? $socialUser['username']) ?>?</a>
                    <a class="btn btn-primary social-small-btn" href="yeni.php<?= $topicSlug !== '' ? '?konu=' . rawurlencode($topicSlug) : '' ?>">Gönderi aç</a>
                </section>
            <?php else: ?>
                <section class="social-composer-cta is-guest">
                    <div>
                        <strong>Topluluğa katıl</strong>
                        <p>Gönderileri herkes okuyabilir. Paylaşmak ve yorum yapmak için DeviceGame hesabın yeterli.</p>
                    </div>
                    <a class="btn btn-primary social-small-btn" href="<?= e(social_login_url('sosyal/index.php')) ?>">Giriş yap</a>
                </section>
            <?php endif; ?>

            <div class="social-toolbar">
                <nav class="social-tabs" aria-label="Akış türü">
                    <a href="<?= e(social_query_url('index.php', array_merge($baseParams, ['akis' => '']))) ?>"<?= $feed === 'kesfet' ? ' aria-current="true"' : '' ?>>Keşfet</a>
                    <a href="<?= e(social_query_url('index.php', array_merge($baseParams, ['akis' => 'takip']))) ?>"<?= $feed === 'takip' ? ' aria-current="true"' : '' ?>>Takip ettiklerim</a>
                    <a href="<?= e(social_query_url('index.php', array_merge($baseParams, ['akis' => 'kaydedilenler']))) ?>"<?= $feed === 'kaydedilenler' ? ' aria-current="true"' : '' ?>>Kaydedilenler</a>
                </nav>
                <form method="get" class="social-search" role="search">
                    <?php if ($topicSlug !== ''): ?><input type="hidden" name="konu" value="<?= e($topicSlug) ?>"><?php endif; ?>
                    <?php if ($feed !== 'kesfet'): ?><input type="hidden" name="akis" value="<?= e($feed) ?>"><?php endif; ?>
                    <label class="social-sr-only" for="social-q">Gönderi veya kullanıcı ara</label>
                    <input id="social-q" type="search" name="q" value="<?= e($query) ?>" placeholder="Gönderi, etiket veya kullanıcı ara">
                    <label class="social-sr-only" for="social-sort">Sıralama</label>
                    <select id="social-sort" name="sirala">
                        <option value="aktivite"<?= $sort === 'aktivite' ? ' selected' : '' ?>>Son aktivite</option>
                        <option value="yeni"<?= $sort === 'yeni' ? ' selected' : '' ?>>En yeni</option>
                        <option value="populer"<?= $sort === 'populer' ? ' selected' : '' ?>>Popüler</option>
                    </select>
                    <button type="submit" class="btn btn-ghost social-small-btn">Ara</button>
                </form>
            </div>

            <?php if ($matchedProfiles): ?>
                <section class="social-panel social-profile-results">
                    <h2>Kullanıcılar</h2>
                    <div class="social-user-list">
                        <?php foreach ($matchedProfiles as $profile): ?>
                            <a href="<?= e(social_profile_url($profile)) ?>">
                                <?= social_avatar_html($profile) ?>
                                <span><strong><?= e($profile['display_name']) ?></strong><small>@<?= e($profile['username']) ?></small></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($needsLogin): ?>
                <div class="social-empty">
                    <h2>Bu akış için giriş yapmalısın.</h2>
                    <p>Takip ettiklerin ve kaydedilenler yalnızca hesabına özeldir.</p>
                    <a class="btn btn-primary" href="<?= e(social_login_url('sosyal/' . basename((string)$_SERVER['SCRIPT_NAME']) . '?' . (string)($_SERVER['QUERY_STRING'] ?? ''))) ?>">Giriş yap</a>
                </div>
            <?php elseif (!$posts): ?>
                <div class="social-empty">
                    <h2><?= $topicSlug !== '' && !$activeTopic ? 'Bu konu bulunamadı.' : 'Henüz gösterilecek gönderi yok.' ?></h2>
                    <p><?= $query !== '' ? 'Aramana uygun gönderi bulamadık. Farklı bir kelime veya etiket dene.' : 'Bu alanda ilk tartışmayı sen başlatabilirsin.' ?></p>
                    <?php if ($socialUser): ?>
                        <a class="btn btn-primary" href="yeni.php<?= $topicSlug !== '' ? '?konu=' . rawurlencode($topicSlug) : '' ?>">İlk gönderiyi aç</a>
                    <?php else: ?>
                        <a class="btn btn-primary" href="<?= e(social_login_url('sosyal/yeni.php')) ?>">Katıl ve paylaş</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="social-feed" aria-live="polite">
                    <?php foreach ($posts as $post): ?>
                        <?php include __DIR__ . '/_post-card.php'; ?>
                    <?php endforeach; ?>
                </div>
                <?= social_pagination_html('index.php', $baseParams, (int)$result['page'], (int)$result['pages']) ?>
            <?php endif; ?>
        </div>

        <aside class="social-sidebar social-sidebar-right">
            <?php if ($socialUser && $socialProfile): ?>
                <section class="social-panel social-mini-profile">
                    <a class="social-author" href="<?= e(social_profile_url($socialProfile)) ?>">
                        <?= social_avatar_html($socialProfile, 'is-large') ?>
                        <span><strong><?= e($socialProfile['display_name']) ?></strong><small>@<?= e($socialProfile['username']) ?></small></span>
                    </a>
                    <div class="social-mini-stats">
                        <span><b><?= (int)$socialProfile['post_count'] ?></b> gönderi</span>
                        <span><b><?= (int)$socialProfile['follower_count'] ?></b> takipçi</span>
                        <span><b><?= number_format((int)$socialUser['dcoin_balance'], 0, ',', '.') ?></b> D-Coin</span>
                    </div>
                    <a class="text-link" href="<?= e(social_profile_url($socialProfile)) ?>">Profilimi düzenle</a>
                </section>
            <?php else: ?>
                <section class="social-panel social-join-panel">
                    <h2>DeviceGame hesabınla katıl</h2>
                    <p>Ayrı bir sosyal medya hesabı açmana gerek yok. Aynı hesabın mağaza ve D-Coin tarafında da çalışır.</p>
                    <a class="btn btn-primary btn-full" href="../register.php">Hesap oluştur</a>
                    <a class="btn btn-ghost btn-full" href="<?= e(social_login_url('sosyal/index.php')) ?>">Giriş yap</a>
                </section>
            <?php endif; ?>

            <section class="social-panel">
                <h2>Öne çıkan etiketler</h2>
                <?php $trendingTags = social_trending_tags(10); ?>
                <?php if ($trendingTags): ?>
                    <div class="social-tags is-large">
                        <?php foreach ($trendingTags as $tag => $count): ?>
                            <a href="index.php?q=<?= rawurlencode('#' . $tag) ?>">#<?= e($tag) ?> <small><?= (int)$count ?></small></a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="muted">Henüz etiket yok.</p>
                <?php endif; ?>
            </section>

            <section class="social-panel">
                <div class="social-panel-head">
                    <h2>Blogdan</h2>
                    <a href="../blog/index.php">Tümü</a>
                </div>
                <?php $blogLatest = function_exists('blog_posts') ? array_slice(blog_posts(), 0, 3) : []; ?>
                <?php if ($blogLatest): ?>
                    <div class="social-blog-links">
                        <?php foreach ($blogLatest as $blogPost): ?>
                            <a href="../blog/yazi.php?slug=<?= rawurlencode((string)$blogPost['slug']) ?>">
                                <small><?= e($blogPost['category_name'] ?? 'Blog') ?> · <?= e(date('d.m.Y', strtotime((string)$blogPost['date']) ?: time())) ?></small>
                                <strong><?= e($blogPost['title']) ?></strong>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="muted">Henüz blog yazısı yok.</p>
                <?php endif; ?>
            </section>
        </aside>
    </div>
</section>

<script src="../assets/js/social.js" defer></script>
<?php include __DIR__ . '/../footer.php'; ?>
