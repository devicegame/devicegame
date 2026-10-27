<?php
require_once __DIR__ . '/_common.php';
social_require_login();

$notifications = social_notifications((int)$socialUser['id']);
$actorIds = array_map(static fn(array $item): int => (int)($item['actor_id'] ?? 0), $notifications);
$profiles = social_profiles_by_ids($actorIds);
$unread = social_unread_notification_count((int)$socialUser['id']);

$page_title = 'Bildirimler — DG Social';
$page_meta_desc = 'DG Social bildirimlerin: yorumlar, cevaplar, takipler ve bahsetmeler.';
$page_canonical = false;
$page_robots = 'noindex,nofollow';
$page_css = ['shop.css', 'social.css'];
$body_class = 'shop-page social-page';
include __DIR__ . '/../header.php';
?>

<section class="social-shell is-narrow">
    <header class="social-page-head">
        <p class="eyebrow">DG Social</p>
        <h1>Bildirimler.</h1>
        <p class="muted"><?= $unread > 0 ? $unread . ' okunmamış bildirimin var.' : 'Okunmamış bildirimin yok.' ?></p>
    </header>

    <?= social_subnav('notifications') ?>
    <?= social_flash_html() ?>

    <?php if ($notifications && $unread > 0): ?>
        <form method="post" action="aksiyon.php" class="social-notifications-tools">
            <?= csrf_field() ?>
            <input type="hidden" name="islem" value="bildirim_okundu">
            <input type="hidden" name="return" value="bildirimler.php#bildirim-listesi">
            <button class="btn btn-ghost social-small-btn" type="submit">Tümünü okundu işaretle</button>
        </form>
    <?php endif; ?>

    <?php if (!$notifications): ?>
        <div class="social-empty">
            <h2>Henüz bildirimin yok.</h2>
            <p>Bir gönderine yorum yapıldığında, cevabın cevaplandığında veya biri seni takip ettiğinde burada görünecek.</p>
            <a class="btn btn-primary" href="index.php#akis">Akışa git</a>
        </div>
    <?php else: ?>
        <div class="social-notification-list" id="bildirim-listesi">
            <?php foreach ($notifications as $notification): ?>
                <?php
                $actorId = (int)($notification['actor_id'] ?? 0);
                $actor = $profiles[$actorId] ?? ['user_id' => $actorId, 'username' => 'oyuncu', 'display_name' => 'Bir oyuncu'];
                $icon = match ((string)($notification['type'] ?? '')) {
                    'comment' => '💬',
                    'reply' => '↩',
                    'follow' => '👥',
                    'mention' => '@',
                    'event_rsvp' => '🗓',
                    'award' => '🏆',
                    default => '🔔',
                };
                ?>
                <article class="social-notification<?= empty($notification['read']) ? ' is-unread' : '' ?>">
                    <span class="social-notification-icon" aria-hidden="true"><?= e($icon) ?></span>
                    <?= social_avatar_html($actor) ?>
                    <div>
                        <p><?= e($notification['message'] ?? '') ?></p>
                        <small>@<?= e($actor['username']) ?> · <?= e(social_time_ago((int)($notification['created_at'] ?? time()))) ?></small>
                    </div>
                    <form method="post" action="aksiyon.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="islem" value="bildirim_ac">
                        <input type="hidden" name="notification_id" value="<?= (int)($notification['id'] ?? 0) ?>">
                        <button class="btn btn-ghost social-small-btn" type="submit">Aç</button>
                    </form>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<script src="../assets/js/social.js" defer></script>
<?php include __DIR__ . '/../footer.php'; ?>
