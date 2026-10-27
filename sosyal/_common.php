<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../lib/shop.php';
require_once __DIR__ . '/../lib/social.php';

$root = '../';
$socialUser = null;
$socialProfile = null;
$socialTopics = [];
$socialTopicMap = [];
$socialUnread = 0;

try {
    social_ensure_storage();
    if (is_logged_in()) {
        $socialUser = current_user();
        if ($socialUser) {
            $socialProfile = social_ensure_profile($socialUser);
            $socialUnread = social_unread_notification_count((int)$socialUser['id']);
        }
    }
    $socialTopics = social_topics(true);
    foreach ($socialTopics as $topic) {
        $socialTopicMap[(string)$topic['slug']] = $topic;
    }
} catch (Throwable $exception) {
    error_log('[social bootstrap] ' . $exception->getMessage());
    if (!headers_sent()) http_response_code(503);
    die('<!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sosyal depolama hazır değil — Device Game</title><style>body{margin:0;background:#0A1A15;color:#E8EBE4;font-family:system-ui;padding:24px;line-height:1.65}.card{max-width:760px;margin:40px auto;background:#112A21;border:1px solid rgba(190,225,208,.2);border-radius:18px;padding:24px}a{color:#4FE0A0}code{background:rgba(255,255,255,.08);padding:2px 7px;border-radius:6px}</style></head><body><div class="card"><h1>DG Social depolaması hazır değil</h1><p><code>storage/social/</code> ve <code>uploads/social/</code> klasörlerinin PHP tarafından yazılabildiğinden emin olun.</p><p><a href="../diagnose.php">diagnose.php ile kontrol edin</a></p></div></body></html>');
}

function social_current_path(): string
{
    $path = basename((string)($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
    $query = (string)($_SERVER['QUERY_STRING'] ?? '');
    return 'sosyal/' . $path . ($query !== '' ? '?' . $query : '');
}

function social_login_url(?string $next = null): string
{
    return '../login.php?next=' . rawurlencode($next ?: social_current_path());
}

function social_require_login(): void
{
    global $socialUser;
    if (!$socialUser) {
        flash_error_set('DG Social’da paylaşım yapmak için giriş yapmalısın.');
        header('Location: ' . social_login_url());
        exit;
    }
}

function social_flash_html(): string
{
    static $done = false;
    if ($done) return '';
    $done = true;
    $html = '';
    $flash = function_exists('flash_get') ? flash_get() : null;
    $error = function_exists('flash_error_get') ? flash_error_get() : null;
    if ($flash) {
        $html .= '<div class="social-alert is-success" role="status">' . e($flash['msg'] ?? '') . '</div>';
    }
    if ($error) {
        $html .= '<div class="social-alert is-error" role="alert">' . e($error) . '</div>';
    }
    return $html;
}

function social_status_label(string $status): string
{
    return match ($status) {
        'pending' => 'Onay bekliyor',
        'hidden' => 'Gizlendi',
        'deleted' => 'Silindi',
        default => 'Yayında',
    };
}

function social_avatar_html(array $profile, string $size = ''): string
{
    $name = (string)($profile['display_name'] ?? $profile['username'] ?? 'DG');
    $hue = social_avatar_hue((int)($profile['user_id'] ?? 0));
    return '<span class="social-avatar ' . e($size) . '" style="--avatar-h:' . $hue . '" aria-hidden="true">' . e(social_avatar_initials($name)) . '</span>';
}

function social_profile_url(array|string $profile): string
{
    $username = is_array($profile) ? (string)($profile['username'] ?? '') : $profile;
    return 'profil.php?kullanici=' . rawurlencode($username);
}

function social_post_url(int $postId, int $commentId = 0): string
{
    return 'gonderi.php?id=' . $postId . ($commentId > 0 ? '#yorum-' . $commentId : '');
}

function social_image_url(string $path): string
{
    $path = trim(str_replace('\\', '/', $path), '/');
    if ($path === '') return '';
    if (preg_match('~^https?://~i', $path)) return $path;
    return '../' . $path;
}

function social_query_url(string $file, array $params): string
{
    $params = array_filter($params, static fn($value): bool => $value !== '' && $value !== null);
    return $file . ($params ? '?' . http_build_query($params) : '');
}

function social_feed_url(array $params = []): string
{
    return social_query_url('index.php', $params) . '#akis';
}

function social_current_return_url(string $fragment = ''): string
{
    $path = basename((string)($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
    $query = (string)($_SERVER['QUERY_STRING'] ?? '');
    $url = $path . ($query !== '' ? '?' . $query : '');
    $fragment = trim($fragment, '#');
    return $url . ($fragment !== '' ? '#' . $fragment : '');
}

function social_subnav(string $active = 'feed'): string
{
    global $socialUser, $socialProfile, $socialUnread;
    $items = [
        'feed' => ['index.php', 'Akış'],
        'events' => ['etkinlikler.php', 'Etkinlikler'],
        'new' => ['yeni.php', 'Yeni gönderi'],
        'notifications' => ['bildirimler.php', 'Bildirimler'],
        'rules' => ['kurallar.php', 'Kurallar'],
    ];
    $html = '<nav class="social-subnav" aria-label="DG Social menüsü">';
    foreach ($items as $key => [$href, $label]) {
        $html .= '<a href="' . e($href) . '"' . ($active === $key ? ' aria-current="page"' : '') . '>' . e($label);
        if ($key === 'notifications' && $socialUnread > 0) {
            $html .= ' <span class="social-count" data-notification-count>' . (int)$socialUnread . '</span>';
        }
        $html .= '</a>';
    }
    if ($socialUser && $socialProfile) {
        $html .= '<a href="' . e(social_profile_url($socialProfile)) . '"' . ($active === 'profile' ? ' aria-current="page"' : '') . '>Profilim</a>';
    } else {
        $html .= '<a href="' . e(social_login_url('sosyal/index.php')) . '">Katıl / Giriş yap</a>';
    }
    if (!empty($socialUser['is_admin'])) {
        $html .= '<a href="../admin/social.php">Moderasyon</a>';
    }
    $html .= '</nav>';
    return $html;
}

function social_pagination_html(string $file, array $params, int $page, int $pages, string $fragment = ''): string
{
    if ($pages <= 1) return '';
    $fragment = trim($fragment, '#');
    $suffix = $fragment !== '' ? '#' . $fragment : '';
    $html = '<nav class="social-pagination" aria-label="Sayfalar">';
    if ($page > 1) {
        $html .= '<a href="' . e(social_query_url($file, array_merge($params, ['sayfa' => $page - 1])) . $suffix) . '">← Önceki</a>';
    }
    $start = max(1, $page - 2);
    $end = min($pages, $page + 2);
    for ($i = $start; $i <= $end; $i++) {
        $html .= '<a href="' . e(social_query_url($file, array_merge($params, ['sayfa' => $i])) . $suffix) . '"' . ($i === $page ? ' aria-current="page"' : '') . '>' . $i . '</a>';
    }
    if ($page < $pages) {
        $html .= '<a href="' . e(social_query_url($file, array_merge($params, ['sayfa' => $page + 1])) . $suffix) . '">Sonraki →</a>';
    }
    return $html . '</nav>';
}

function social_lfg_label(string $key): string
{
    return match ($key) {
        'game' => 'Oyun',
        'platform' => 'Platform',
        'rank' => 'Rank',
        'mic' => 'Mikrofon',
        'play_time' => 'Saat',
        'needed' => 'Aranan',
        'event_at' => 'Tarih',
        'event_place' => 'Buluşma yeri',
        'event_capacity' => 'Kapasite',
        default => $key,
    };
}

function social_meta_value_label(string $key, $value): string
{
    if ($key === 'event_at') {
        $timestamp = (int)$value;
        return $timestamp > 0 ? social_format_date($timestamp) : '';
    }
    if ($key === 'event_capacity') {
        $capacity = (int)$value;
        return $capacity > 0 ? $capacity . ' kişi' : 'Sınırsız';
    }
    return trim((string)$value);
}

function social_award_badge_html(array $post): string
{
    $award = social_normalize_award($post['award'] ?? null);
    if (!$award) return '';
    return '<span class="social-mini-badge is-award" title="' . e((string)$award['reason']) . '">🏆 Editör Ödülü · +' . number_format((int)$award['amount'], 0, ',', '.') . ' D-Coin</span>';
}

function social_poll_html(array $post, ?array $viewer, array $state = [], string $returnUrl = ''): string
{
    if (empty($post['poll']) || !is_array($post['poll'])) return '';
    $postId = (int)($post['id'] ?? 0);
    $reactions = social_reactions($postId);
    $poll = social_poll_payload($post, $viewer, $reactions);
    if (!$poll) return '';
    if (($state['poll_vote'] ?? '') !== '') $poll['selected'] = (string)$state['poll_vote'];
    $returnUrl = $returnUrl !== '' ? $returnUrl : social_current_return_url('gonderi-' . $postId);
    $published = ($post['status'] ?? '') === 'published';
    $closed = !empty($poll['closed']);
    $canVote = $viewer && $published && !$closed;
    $closesAt = (int)($poll['closes_at'] ?? 0);
    $metaText = number_format((int)$poll['total_votes'], 0, ',', '.') . ' oy';
    if ($closed) $metaText .= ' · kapandı';
    elseif ($closesAt > 0) $metaText .= ' · ' . social_format_date($closesAt) . ' tarihine kadar';
    else $metaText .= ' · süresiz';

    $html = '<section class="social-poll" data-poll data-post-id="' . $postId . '" aria-label="Anket">';
    $html .= '<div class="social-poll-head"><strong>📊 Anket</strong><small data-poll-meta>' . e($metaText) . '</small></div>';
    $html .= '<div class="social-poll-options">';
    foreach ((array)$poll['options'] as $option) {
        $optionId = (string)$option['id'];
        $votes = (int)$option['votes'];
        $percent = max(0, min(100, (int)($option['percent'] ?? 0)));
        $selected = ($poll['selected'] ?? '') === $optionId;
        $optionInner = '<span class="social-poll-fill" data-poll-fill style="width:' . $percent . '%"></span>'
            . '<span class="social-poll-label">' . e((string)$option['label']) . '</span>'
            . '<span class="social-poll-result"><b data-poll-count>' . $votes . '</b> oy · <span data-poll-percent>%' . $percent . '</span></span>'
            . '<span class="social-poll-check" data-poll-check aria-hidden="true">✓</span>';
        if ($canVote) {
            $html .= '<form method="post" action="aksiyon.php" data-social-ajax="poll" class="social-poll-form">'
                . csrf_field()
                . '<input type="hidden" name="islem" value="anket_oy">'
                . '<input type="hidden" name="post_id" value="' . $postId . '">'
                . '<input type="hidden" name="option_id" value="' . e($optionId) . '">'
                . '<input type="hidden" name="return" value="' . e($returnUrl) . '">'
                . '<button type="submit" class="social-poll-option' . ($selected ? ' is-selected' : '') . '" data-poll-option="' . e($optionId) . '" aria-pressed="' . ($selected ? 'true' : 'false') . '">' . $optionInner . '</button>'
                . '</form>';
        } else {
            $html .= '<div class="social-poll-option is-static' . ($selected ? ' is-selected' : '') . '" data-poll-option="' . e($optionId) . '">' . $optionInner . '</div>';
        }
    }
    $html .= '</div><p class="social-poll-note" data-poll-note>';
    if (!$published) {
        $html .= 'Gönderi yayına girince oylama açılır.';
    } elseif ($closed) {
        $html .= 'Anket kapandı; sonuçlar yukarıda.';
    } elseif ($viewer) {
        $html .= 'Oyunu kapanışa kadar değiştirebilirsin.';
    } else {
        $html .= 'Oy vermek için <a href="' . e(social_login_url('sosyal/' . $returnUrl)) . '">giriş yap</a>.';
    }
    return $html . '</p></section>';
}

function social_event_html(array $post, ?array $viewer, array $state = [], string $returnUrl = '', bool $detail = false): string
{
    if (!social_is_event_post($post)) return '';
    $postId = (int)($post['id'] ?? 0);
    $meta = (array)($post['meta'] ?? []);
    $startsAt = (int)($meta['event_at'] ?? 0);
    $capacity = (int)($meta['event_capacity'] ?? 0);
    $count = (int)($post['rsvp_count'] ?? 0);
    $attending = !empty($state['rsvp']);
    $published = ($post['status'] ?? '') === 'published';
    $open = social_event_is_open($post);
    $full = $capacity > 0 && $count >= $capacity && !$attending;
    $started = $startsAt > 0 && $startsAt <= time();
    $statusLabel = !$open ? 'Etkinlik bitti' : ($started ? 'Devam ediyor' : social_time_until($startsAt));
    $returnUrl = $returnUrl !== '' ? $returnUrl : social_current_return_url($detail ? 'etkinlik' : 'gonderi-' . $postId);

    $facts = [];
    if ($startsAt > 0) $facts[] = ['Tarih', social_format_date($startsAt)];
    foreach (['game' => 'Oyun', 'platform' => 'Platform', 'event_place' => 'Buluşma yeri'] as $key => $label) {
        if (trim((string)($meta[$key] ?? '')) !== '') $facts[] = [$label, (string)$meta[$key]];
    }
    $facts[] = ['Katılım', $count . ($capacity > 0 ? ' / ' . $capacity . ' kişi' : ' kişi')];

    $html = '<section class="social-event' . ($detail ? ' is-detail' : '') . '"' . ($detail ? ' id="etkinlik"' : '') . ' data-event data-post-id="' . $postId . '" aria-label="Etkinlik bilgileri">';
    $html .= '<div class="social-event-head"><strong>🗓 Etkinlik</strong><span class="social-mini-badge' . ($open ? ' is-live' : '') . '">' . e($statusLabel) . '</span></div>';
    $html .= '<div class="social-event-facts">';
    foreach ($facts as [$label, $value]) {
        $valueHtml = $label === 'Katılım'
            ? '<span data-rsvp-count>' . (int)$count . '</span>' . ($capacity > 0 ? ' / ' . (int)$capacity . ' kişi' : ' kişi')
            : e($value);
        $html .= '<div><span>' . e($label) . '</span><strong>' . $valueHtml . '</strong></div>';
    }
    $html .= '</div>';

    if ($viewer) {
        $disabled = !$published || (!$open && !$attending) || $full;
        $html .= '<form method="post" action="aksiyon.php" data-social-ajax="rsvp" class="social-event-action">'
            . csrf_field()
            . '<input type="hidden" name="islem" value="etkinlik_katil">'
            . '<input type="hidden" name="post_id" value="' . $postId . '">'
            . '<input type="hidden" name="return" value="' . e($returnUrl) . '">'
            . '<button type="submit" class="social-action' . ($attending ? ' is-active' : '') . '" aria-pressed="' . ($attending ? 'true' : 'false') . '"' . ($disabled ? ' disabled' : '') . '><span data-rsvp-label>' . ($attending ? 'Katılıyorsun ✓' : ($full ? 'Kapasite doldu' : 'Katılıyorum')) . '</span></button>'
            . '<small>' . (!$published ? 'Yayına girince katılım açılır.' : ($open ? 'Katılımını istediğin zaman geri alabilirsin.' : 'Bu etkinlik tamamlandı.')) . '</small>'
            . '</form>';
    } elseif ($published && $open && !$full) {
        $html .= '<div class="social-event-action"><a class="social-action" href="' . e(social_login_url('sosyal/' . $returnUrl)) . '">Katılmak için giriş yap</a></div>';
    } elseif ($full) {
        $html .= '<p class="social-event-note">Bu etkinliğin kapasitesi doldu.</p>';
    }

    if ($detail) {
        $attendees = social_event_attendees($postId, $viewer, 12);
        if ($attendees) {
            $html .= '<div class="social-event-attendees"><h3>Katılımcılar</h3><div class="social-user-list is-compact">';
            foreach ($attendees as $attendee) {
                $html .= '<a href="' . e(social_profile_url($attendee)) . '">' . social_avatar_html($attendee) . '<span><strong>' . e((string)$attendee['display_name']) . '</strong><small>@' . e((string)$attendee['username']) . '</small></span></a>';
            }
            $html .= '</div>';
            if ($count > count($attendees)) $html .= '<p class="muted">ve ' . ($count - count($attendees)) . ' katılımcı daha…</p>';
            $html .= '</div>';
        }
    }
    return $html . '</section>';
}

function social_onboarding_html(array $user, array $profile, bool $force = false): string
{
    $status = social_onboarding_status($user, $profile);
    if (!empty($status['complete']) || (empty($status['should_show']) && !$force)) return '';
    $returnUrl = social_current_return_url('hosgeldin');
    $trusted = social_is_trusted($profile, $user);
    $html = '<section class="social-onboarding" id="hosgeldin" data-onboarding="' . (int)$user['id'] . '" aria-labelledby="social-onboarding-title">';
    $html .= '<div class="social-onboarding-head"><div><p class="eyebrow">DG Social’a başlangıç</p><h2 id="social-onboarding-title">Hoş geldin, ' . e((string)$profile['display_name']) . '.</h2><p>Birkaç kısa adımda profilini tamamla ve topluluğa katıl.</p></div>';
    $html .= '<button type="button" class="social-text-button" data-onboarding-dismiss>Sonra hatırlat</button></div>';
    $html .= '<div class="social-progress" role="progressbar" aria-valuenow="' . (int)$status['percent'] . '" aria-valuemin="0" aria-valuemax="100"><span style="width:' . (int)$status['percent'] . '%"></span></div>';
    $html .= '<p class="social-progress-text">' . (int)$status['completed'] . '/' . (int)$status['total'] . ' adım tamamlandı</p><div class="social-onboarding-steps">';
    foreach ((array)$status['steps'] as $index => $step) {
        $done = !empty($step['complete']);
        $html .= '<article class="social-onboarding-step' . ($done ? ' is-done' : '') . '"><span class="social-step-icon" aria-hidden="true">' . ($done ? '✓' : (string)($index + 1)) . '</span><div><h3>' . e((string)$step['label']) . '</h3><p>' . e((string)$step['description']) . '</p>';
        if ($done) {
            $html .= '<span class="social-step-done">Tamamlandı</span>';
        } elseif (($step['key'] ?? '') === 'official') {
            $html .= '<form method="post" action="aksiyon.php" data-social-ajax="follow" class="social-onboarding-follow">' . csrf_field()
                . '<input type="hidden" name="islem" value="takip"><input type="hidden" name="target_id" value="0"><input type="hidden" name="return" value="' . e($returnUrl) . '">'
                . '<button class="btn btn-primary social-small-btn" type="submit">Takip et</button></form>';
        } else {
            $html .= '<a class="text-link" href="' . e((string)$step['url']) . '">' . e((string)$step['action']) . '</a>';
        }
        $html .= '</div></article>';
    }
    $html .= '</div>';
    if (!$trusted) {
        $html .= '<p class="social-onboarding-note">Bilgi: Yeni hesapların ilk gönderi ve yorumları spam’i önlemek için kısa bir moderasyon onayından geçebilir. Onaylanınca bildirim akışında görünür.</p>';
    }
    return $html . '</section>';
}
