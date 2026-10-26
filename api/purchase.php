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

$item_id = (int)($input['store_item_id'] ?? 0);
$method  = $input['payment_method'] ?? '';
if (!in_array($method, ['dcoin','cash'], true)) { echo json_encode(['ok'=>false,'error'=>'Geçersiz ödeme yöntemi']); exit; }
if ($item_id<=0) { echo json_encode(['ok'=>false,'error'=>'Geçersiz ürün']); exit; }

$st = $pdo->prepare("SELECT * FROM store_items WHERE id=? AND is_active=1 LIMIT 1");
$st->execute([$item_id]); $item = $st->fetch();
if (!$item) { echo json_encode(['ok'=>false,'error'=>'Ürün bulunamadı']); exit; }

// Stok kontrol — kullanılmamış key var mı?
$st = $pdo->prepare("SELECT * FROM store_keys WHERE store_item_id=? AND is_used=0 LIMIT 1 FOR UPDATE");
try{
  $pdo->beginTransaction();
  // FOR UPDATE için tekrar seç (transaction içinde)
  $st->execute([$item_id]);
  $key = $st->fetch();
  if (!$key) { $pdo->rollBack(); echo json_encode(['ok'=>false,'error'=>'Üzgünüz, bu ürün stokta kalmadı']); exit; }

  $price_tl = $item['price_tl'];
  $price_dcoin = (int)$item['price_dcoin'];

  if ($method==='dcoin') {
    if ($price_dcoin<=0) { $pdo->rollBack(); echo json_encode(['ok'=>false,'error'=>'Bu ürün D-Coin ile alınamıyor']); exit; }
    // Bakiye kontrolü
    $bal = (int)$pdo->query("SELECT dcoin_balance FROM users WHERE id=$uid FOR UPDATE")->fetchColumn();
    if ($bal < $price_dcoin) { $pdo->rollBack(); echo json_encode(['ok'=>false,'error'=>"Yetersiz D-Coin. Gerekli: $price_dcoin, bakiyen: $bal"]); exit; }
    // Düş
    $pdo->prepare("UPDATE users SET dcoin_balance = dcoin_balance - ? WHERE id=?")->execute([$price_dcoin, $uid]);
    // Key'i işaretle
    $pdo->prepare("UPDATE store_keys SET is_used=1, used_by=?, used_at=NOW() WHERE id=?")->execute([$uid, $key['id']]);
    // Purchases
    $pdo->prepare("INSERT INTO purchases (user_id, store_item_id, store_key_id, payment_method, price_paid_dcoin, delivered_key) VALUES (?,?,?,?,?,?)")
        ->execute([$uid, $item_id, $key['id'], 'dcoin', $price_dcoin, $key['key_code']]);
    // Stok güncelle
    $pdo->prepare("UPDATE store_items SET stock = (SELECT COUNT(*) FROM store_keys WHERE store_item_id=? AND is_used=0) WHERE id=?")->execute([$item_id, $item_id]);
    $pdo->commit();
    $newBal = (int)$pdo->query("SELECT dcoin_balance FROM users WHERE id=$uid")->fetchColumn();
    $_SESSION['dcoin_balance']=$newBal;
    echo json_encode(['ok'=>true,'payment_method'=>'dcoin','item_name'=>$item['name'],'delivered_key'=>$key['key_code'],'new_balance'=>$newBal,'price_paid_dcoin'=>$price_dcoin]);
    exit;
  } else {
    // cash — demo: direkt teslim, bakiye düşmez
    $pdo->prepare("UPDATE store_keys SET is_used=1, used_by=?, used_at=NOW() WHERE id=?")->execute([$uid, $key['id']]);
    $pdo->prepare("INSERT INTO purchases (user_id, store_item_id, store_key_id, payment_method, price_paid_tl, delivered_key) VALUES (?,?,?,?,?,?)")
        ->execute([$uid, $item_id, $key['id'], 'cash', $price_tl, $key['key_code']]);
    $pdo->prepare("UPDATE store_items SET stock = (SELECT COUNT(*) FROM store_keys WHERE store_item_id=? AND is_used=0) WHERE id=?")->execute([$item_id, $item_id]);
    $pdo->commit();
    $newBal = (int)$pdo->query("SELECT dcoin_balance FROM users WHERE id=$uid")->fetchColumn();
    echo json_encode(['ok'=>true,'payment_method'=>'cash','item_name'=>$item['name'],'delivered_key'=>$key['key_code'],'new_balance'=>$newBal,'price_paid_tl'=>$price_tl]);
    exit;
  }

}catch(Throwable $e){
  if($pdo->inTransaction()) $pdo->rollBack();
  error_log('[purchase] '.$e->getMessage());
  echo json_encode(['ok'=>false,'error'=>'Satın alma sırasında hata: '.$e->getMessage()]);
}
