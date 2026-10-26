<?php
declare(strict_types=1);
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok'=>false,'error'=>'Method not allowed']); exit; }
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
if (!csrf_verify($input['csrf'] ?? '')) { echo json_encode(['ok'=>false,'error'=>'CSRF doğrulaması başarısız']); exit; }
// API'de require_login() kullanma: HTML redirect doner, fetch().json() patlar. JSON 401 don.
if (!is_logged_in()) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'Giriş yapmalısın']); exit; }
$user = current_user($pdo);
if (!$user) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'Giriş yapmalısın']); exit; }
$uid = (int)$user['id'];
$ad_id = (int)($input['ad_id'] ?? 0);
$token = trim((string)($input['token'] ?? ''));
if ($ad_id<=0 || $token==='') { echo json_encode(['ok'=>false,'error'=>'Eksik parametre']); exit; }

$st = $pdo->prepare("SELECT * FROM ads WHERE id=? AND is_active=1 LIMIT 1");
$st->execute([$ad_id]); $ad = $st->fetch();
if (!$ad) { echo json_encode(['ok'=>false,'error'=>'Reklam bulunamadı']); exit; }

// Günlük limit tekrar kontrol
$limit = daily_watch_limit($pdo);
$today = today_watch_count($pdo, $uid);
if ($today >= $limit) { echo json_encode(['ok'=>false,'error'=>'Günlük limit doldu']); exit; }

// Token doğrulaması
$st = $pdo->prepare("SELECT * FROM watch_sessions WHERE token=? AND user_id=? AND ad_id=? AND is_completed=0 LIMIT 1");
$st->execute([$token, $uid, $ad_id]); $ws = $st->fetch();
if (!$ws) { echo json_encode(['ok'=>false,'error'=>'Geçersiz veya zaten kullanılmış oturum']); exit; }

// Süre doğrulaması: started_at ile şimdi arası en az duration - tolerans olmalı
$started = strtotime($ws['started_at']);
$now = time();
$elapsed = $now - $started;
$required = max(5, (int)$ad['duration_sec'] - 2); // 2 sn tolerans, min 5 sn
if ($elapsed < $required) {
  echo json_encode(['ok'=>false,'error'=>"Video henüz tam izlenmedi. Beklenen: {$required}s, geçen: {$elapsed}s. Lütfen sonuna kadar izleyin."]);
  exit;
}

// Aynı reklamı aynı gün tekrar izleme engeli? İsteğe bağlı — şimdilik izin veriyoruz ama limit dahilinde.
// İstersen aynı reklamı günde 1 kez ile sınırlandır:
// $st = $pdo->prepare("SELECT COUNT(*) FROM user_watch_logs WHERE user_id=? AND ad_id=? AND DATE(watched_at)=CURDATE()");
// $st->execute([$uid,$ad_id]); if((int)$st->fetchColumn()>0){ echo json_encode(['ok'=>false,'error'=>'Bu reklamı bugün zaten izledin']); exit; }

// Ödüllendirme — atomik işlem
try{
  $pdo->beginTransaction();

  // Bakiye artır
  $pdo->prepare("UPDATE users SET dcoin_balance = dcoin_balance + ? WHERE id=?")
      ->execute([(int)$ad['reward_dcoin'], $uid]);

  // Log ekle
  $ip = $_SERVER['REMOTE_ADDR'] ?? null;
  $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '',0,250);
  $pdo->prepare("INSERT INTO user_watch_logs (user_id, ad_id, earned_dcoin, ip_address, user_agent) VALUES (?,?,?,?,?)")
      ->execute([$uid, $ad_id, (int)$ad['reward_dcoin'], $ip, $ua]);

  // Session'ı kapat
  $pdo->prepare("UPDATE watch_sessions SET is_completed=1, completed_at=NOW() WHERE id=?")
      ->execute([$ws['id']]);

  $pdo->commit();

  // Yeni bakiye ve bugün sayısı
  $newBal = $pdo->query("SELECT dcoin_balance FROM users WHERE id=$uid")->fetchColumn();
  $today2 = today_watch_count($pdo, $uid);

  // Session güncelle
  $_SESSION['dcoin_balance'] = (int)$newBal;

  echo json_encode([
    'ok'=>true,
    'earned'=>(int)$ad['reward_dcoin'],
    'new_balance'=>(int)$newBal,
    'today_count'=>(int)$today2,
    'daily_limit'=>(int)$limit
  ]);
}catch(Throwable $e){
  $pdo->rollBack();
  error_log('[watch_complete] '.$e->getMessage());
  echo json_encode(['ok'=>false,'error'=>'Ödül verilirken hata oluştu']);
}
