<?php
require_once __DIR__ . '/_common.php';

function social_safe_return(string $return, string $fallback = 'index.php'): string
{
    $return = trim($return);
    if ($return === '' || preg_match('~^(?:https?:)?//~i', $return) || str_contains($return, '\\')) {
        return $fallback;
    }
    $pattern = '~^(?:\.\./admin/social\.php|(?:index|gonderi|profil|bildirimler|yeni|etkinlikler|kurallar)\.php)(?:\?[^#\s]*)?(?:#[A-Za-z0-9_-]+)?$~';
    return preg_match($pattern, $return) ? $return : $fallback;
}

function social_action_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function social_action_finish(array $result, string $successMessage, string $return, bool $ajax, array $extra = []): void
{
    $errors = array_values(array_filter((array)($result['errors'] ?? []), static fn($value): bool => trim((string)$value) !== ''));
    if ($ajax) {
        social_action_json([
            'ok' => !$errors,
            'message' => $errors ? implode(' ', $errors) : $successMessage,
            'errors' => $errors,
        ] + $extra, $errors ? 422 : 200);
    }
    if ($errors) {
        flash_error_set(implode(' ', $errors));
    } else {
        flash_set($successMessage);
    }
    header('Location: ' . social_safe_return($return));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && (string)($_GET['islem'] ?? '') === 'bildirim_sayisi') {
    social_action_json([
        'ok' => true,
        'count' => $socialUser ? social_unread_notification_count((int)$socialUser['id']) : 0,
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$ajax = (string)($_POST['ajax'] ?? '') === '1'
    || str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
$return = social_safe_return((string)($_POST['return'] ?? 'index.php'));

if (!$socialUser) {
    if ($ajax) social_action_json(['ok' => false, 'message' => 'Bu işlem için giriş yapmalısın.'], 401);
    flash_error_set('Bu işlem için giriş yapmalısın.');
    header('Location: ' . social_login_url('sosyal/' . $return));
    exit;
}

if (!csrf_verify((string)($_POST['csrf'] ?? ''))) {
    social_action_finish(['errors' => ['Güvenlik doğrulaması başarısız. Sayfayı yenileyip tekrar dene.']], '', $return, $ajax);
}

$action = (string)($_POST['islem'] ?? '');
$postId = (int)($_POST['post_id'] ?? 0);
$commentId = (int)($_POST['comment_id'] ?? 0);
$targetId = (int)($_POST['target_id'] ?? $_POST['user_id'] ?? 0);

try {
    switch ($action) {
        case 'gg':
            $result = social_toggle_gg($socialUser, $postId);
            social_action_finish($result, !empty($result['active']) ? 'GG verildi.' : 'GG geri alındı.', $return, $ajax, [
                'active' => !empty($result['active']),
                'count' => (int)($result['count'] ?? 0),
            ]);

        case 'kaydet':
            $result = social_toggle_save($socialUser, $postId);
            social_action_finish($result, !empty($result['active']) ? 'Gönderi kaydedildi.' : 'Kayıt kaldırıldı.', $return, $ajax, [
                'active' => !empty($result['active']),
            ]);

        case 'anket_oy':
            $result = social_vote_poll($socialUser, $postId, (string)($_POST['option_id'] ?? ''));
            social_action_finish($result, 'Oyun kaydedildi.', $return, $ajax, [
                'poll' => $result['poll'] ?? null,
                'selected' => (string)($result['selected'] ?? (($result['poll'] ?? [])['selected'] ?? '')),
            ]);

        case 'etkinlik_katil':
            $result = social_toggle_event_rsvp($socialUser, $postId);
            social_action_finish($result, !empty($result['active']) ? 'Etkinliğe katıldın.' : 'Etkinlik katılımın kaldırıldı.', $return, $ajax, [
                'active' => !empty($result['active']),
                'count' => (int)($result['count'] ?? 0),
                'capacity' => (int)($result['capacity'] ?? 0),
                'event_at' => (int)($result['event_at'] ?? 0),
            ]);

        case 'odul_ver':
            $result = social_award_post($socialUser, $postId, (int)($_POST['amount'] ?? 0), (string)($_POST['reason'] ?? ''));
            social_action_finish($result, 'Editör Ödülü verildi ve D-Coin gönderi sahibine eklendi.', $return, $ajax, [
                'award' => $result['award'] ?? null,
                'balance' => (int)($result['balance'] ?? 0),
            ]);

        case 'takip':
            $result = social_toggle_follow($socialUser, $targetId);
            social_action_finish($result, !empty($result['following']) ? 'Kullanıcı takip ediliyor.' : 'Takipten çıkıldı.', $return, $ajax, [
                'active' => !empty($result['following']),
            ]);

        case 'engelle':
            $result = social_toggle_block($socialUser, $targetId);
            $nextReturn = !empty($result['blocked']) ? 'index.php' : $return;
            social_action_finish($result, !empty($result['blocked']) ? 'Kullanıcı engellendi.' : 'Kullanıcının engeli kaldırıldı.', $nextReturn, $ajax, [
                'active' => !empty($result['blocked']),
            ]);

        case 'gonderi_sil':
            $result = social_set_post_status($socialUser, $postId, 'deleted');
            // Silinen detay sayfasına geri dönme; moderasyon ekranından gelindiyse orada kal.
            $deleteReturn = str_starts_with($return, '../admin/social.php') ? $return : 'index.php';
            social_action_finish($result, 'Gönderi silindi.', $deleteReturn, $ajax);

        case 'gonderi_onayla':
        case 'gonderi_yayinla':
            $result = social_set_post_status($socialUser, $postId, 'published');
            social_action_finish($result, 'Gönderi yayınlandı.', $return, $ajax);

        case 'gonderi_gizle':
            $result = social_set_post_status($socialUser, $postId, 'hidden');
            social_action_finish($result, 'Gönderi gizlendi.', $return, $ajax);

        case 'gonderi_sabitle':
            $result = social_set_post_flag($socialUser, $postId, 'pinned');
            social_action_finish($result, !empty($result['value']) ? 'Gönderi sabitlendi.' : 'Gönderi sabitten kaldırıldı.', $return, $ajax, ['active' => !empty($result['value'])]);

        case 'gonderi_kilitle':
            $result = social_set_post_flag($socialUser, $postId, 'locked');
            social_action_finish($result, !empty($result['value']) ? 'Konu yorumlara kilitlendi.' : 'Konu kilidi açıldı.', $return, $ajax, ['active' => !empty($result['value'])]);

        case 'yorum_sil':
            $result = social_set_comment_status($socialUser, $postId, $commentId, 'deleted');
            social_action_finish($result, 'Yorum silindi.', $return, $ajax);

        case 'yorum_onayla':
        case 'yorum_yayinla':
            $result = social_set_comment_status($socialUser, $postId, $commentId, 'published');
            social_action_finish($result, 'Yorum yayınlandı.', $return, $ajax);

        case 'yorum_gizle':
            $result = social_set_comment_status($socialUser, $postId, $commentId, 'hidden');
            social_action_finish($result, 'Yorum gizlendi.', $return, $ajax);

        case 'sikayet':
            $result = social_create_report($socialUser, $_POST);
            social_action_finish($result, 'Şikâyetin moderasyon ekibine gönderildi.', $return, $ajax);

        case 'bildirim_okundu':
            social_mark_notifications_read((int)$socialUser['id'], (int)($_POST['notification_id'] ?? 0));
            social_action_finish(['errors' => []], 'Bildirimler okundu işaretlendi.', $return, $ajax, ['count' => 0]);

        case 'bildirim_ac':
            $notificationId = (int)($_POST['notification_id'] ?? 0);
            $target = 'bildirimler.php';
            foreach (social_notifications((int)$socialUser['id']) as $notification) {
                if ((int)($notification['id'] ?? 0) === $notificationId) {
                    $target = social_safe_return((string)($notification['url'] ?? $target), $target);
                    break;
                }
            }
            social_mark_notifications_read((int)$socialUser['id'], $notificationId);
            header('Location: ' . $target);
            exit;

        case 'guven':
            $result = social_set_trust($targetId, (int)($_POST['trust_level'] ?? 0), $socialUser);
            social_action_finish($result, 'Kullanıcı güven seviyesi güncellendi.', $return, $ajax);

        default:
            social_action_finish(['errors' => ['Geçersiz sosyal işlem.']], '', $return, $ajax);
    }
} catch (Throwable $exception) {
    error_log('[social action] ' . $exception->getMessage());
    social_action_finish(['errors' => ['İşlem tamamlananamadı. Depolama izinlerini kontrol edip tekrar dene.']], '', $return, $ajax);
}
