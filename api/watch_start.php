<?php
declare(strict_types=1);
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok'=>false,'error'=>'Method not allowed']); exit; }
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
if (!csrf_verify($input['csrf'] ?? '')) { echo json_encode(['ok'=>false,'error'=>'CSRF doğrulaması başarısız']); exit; }
require_login();
$user = current_user($pdo);
$uid = (int)$user['id'];
if ((int)$user['is_banned']===1) { echo json_encode(['ok'=>false,'error'=>'Hesabın engellenmiş']); exit; }

$ad_id = (int)($input['ad_id'] ?? 0);
if ($ad_id<=0) { echo json_encode(['ok'=>false,'error'=>'Geçersiz reklam']); exit; }

$st = $pdo->prepare("SELECT * FROM ads WHERE id=? AND is_active=1 LIMIT 1");
$st->execute([$ad_id]);
$ad = $st->fetch();
if (!$ad) { echo json_encode(['ok'=>false,'error'=>'Reklam bulunamadı veya pasif']); exit; }

// Günlük limit
$limit = daily_watch_limit($pdo);
$today = today_watch_count($pdo, $uid);
if ($today >= $limit) { echo json_encode(['ok'=>false,'error'=>"Günlük limit doldu ($limit/$limit). Yarın tekrar gel!"]); exit; }

// Token oluştur
$token = bin2hex(random_bytes(32));
$ip = $_SERVER['REMOTE_ADDR'] ?? null;
try{
  $pdo->prepare("INSERT INTO watch_sessions (user_id, ad_id, token, ip_address) VALUES (?,?,?,?)")
      ->execute([$uid, $ad_id, $token, $ip]);
  // Session'a da yaz
  $_SESSION['watch_'.$ad_id] = $token;
  echo json_encode(['ok'=>true,'token'=>$token,'reward'=>$ad['reward_dcoin'],'duration'=>$ad['duration_sec']]);
}catch(Throwable $e){
  echo json_encode(['ok'=>false,'error'=>'Oturum oluşturulamadı']);
}
