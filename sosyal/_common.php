<?php
require_once __DIR__ . '/../db.php';
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

function social_subnav(string $active = 'feed'): string
{
    global $socialUser, $socialProfile, $socialUnread;
    $items = [
        'feed' => ['index.php', 'Akış'],
        'new' => ['yeni.php', 'Yeni gönderi'],
        'notifications' => ['bildirimler.php', 'Bildirimler'],
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

function social_pagination_html(string $file, array $params, int $page, int $pages): string
{
    if ($pages <= 1) return '';
    $html = '<nav class="social-pagination" aria-label="Sayfalar">';
    if ($page > 1) {
        $html .= '<a href="' . e(social_query_url($file, array_merge($params, ['sayfa' => $page - 1]))) . '">← Önceki</a>';
    }
    $start = max(1, $page - 2);
    $end = min($pages, $page + 2);
    for ($i = $start; $i <= $end; $i++) {
        $html .= '<a href="' . e(social_query_url($file, array_merge($params, ['sayfa' => $i]))) . '"' . ($i === $page ? ' aria-current="page"' : '') . '>' . $i . '</a>';
    }
    if ($page < $pages) {
        $html .= '<a href="' . e(social_query_url($file, array_merge($params, ['sayfa' => $page + 1]))) . '">Sonraki →</a>';
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
        default => $key,
    };
}
