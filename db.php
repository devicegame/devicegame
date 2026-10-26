<?php
/**
 * DEVICEGAME — Veritabansız Uygulama Çekirdeği
 * db.php v3.0 — JSON dosya depolama (MySQL/PDO yok)
 *
 * Her sayfanın en üstünde: require_once __DIR__ . '/db.php';
 *
 * Veriler: storage/dg/database.json
 * - İlk açılışta otomatik oluşturulur ve örnek verilerle doldurulur.
 * - flock() kilidi + geçici dosyaya yazıp rename() ile atomik güncelleme yapılır.
 */
declare(strict_types=1);

// Çift yükleme koruması
if (defined('DG_APP_LOADED')) { return; }
define('DG_APP_LOADED', true);

// ——— DEBUG MODU ———
// Ortam değişkeni DG_DEBUG=1 veya URL parametresi ?debug=1
$__dg_debug_env = getenv('DG_DEBUG') ?: ($_ENV['DG_DEBUG'] ?? '');
$__dg_debug = ($__dg_debug_env === '1') || (isset($_GET['debug']) && $_GET['debug'] === '1');
if ($__dg_debug) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL);
}

// Site Türkiye saatine göre günlük limit hesaplasın.
if (function_exists('date_default_timezone_set')) {
    @date_default_timezone_set('Europe/Istanbul');
}

// ——— Oturum — güvenli çerez ———
if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
           || (($_SERVER['SERVER_PORT'] ?? '') === '443')
           || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    if (!headers_sent()) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    @session_start();
}

// ——— Sabitler ———
define('DG_DAILY_LIMIT_DEFAULT', 5);
define('DG_AUTH_VERSION', 3); // Eski MySQL sürümünün oturum ID'leri yeni JSON kayıtlarıyla karışmasın
define('DG_UPLOAD_DIR', __DIR__ . '/uploads');
define('DG_UPLOAD_URL', 'uploads/');
define('DG_STORAGE_DIR', __DIR__ . '/storage/dg');
define('DG_DATA_FILE', DG_STORAGE_DIR . '/database.json');
define('DG_LOCK_FILE', DG_STORAGE_DIR . '/database.lock');

// ——— Genel yardımcılar ———
if (!function_exists('e')) {
    function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

// lib/shop.php de csrf_token() tanımlar — redeclare olmaması için guard'lı.
if (!function_exists('csrf_token')) {
    function csrf_token(): string {
        if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf'];
    }
}
if (!function_exists('csrf_field')) {
    function csrf_field(): string {
        return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
    }
}
if (!function_exists('csrf_verify')) {
    function csrf_verify(?string $token): bool {
        return !empty($_SESSION['csrf']) && !empty($token) && hash_equals($_SESSION['csrf'], (string)$token);
    }
}

function is_logged_in(): bool {
    return !empty($_SESSION['user_id'])
        && (int)($_SESSION['dg_auth_v'] ?? 0) === DG_AUTH_VERSION;
}
function is_admin(): bool { return !empty($_SESSION['is_admin']) && (int)$_SESSION['is_admin'] === 1; }
function current_user_id(): ?int { return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null; }

/** Alt klasörden (admin/api) de doğru login adresini üretir. */
function dg_login_url(): string {
    $dir = str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '')));
    return (substr($dir, -6) === '/admin' || substr($dir, -4) === '/api') ? '../login.php' : 'login.php';
}

function require_login(): void {
    if (!is_logged_in()) {
        $_SESSION['flash_error'] = 'Devam etmek için giriş yapmalısın.';
        header('Location: ' . dg_login_url()); exit;
    }
}
function require_admin(): void {
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        die('<h1>403 — Yetkisiz erişim</h1><p>Bu alana sadece yöneticiler girebilir.</p><a href="../index.php">Ana sayfaya dön</a>');
    }
}

/** Para formatı */
function tl_format($n): string {
    return number_format((float)$n, 2, ',', '.') . ' ₺';
}

/** Flash mesajlar */
function flash_set(string $msg, string $type = 'success'): void { $_SESSION['flash'] = ['msg' => $msg, 'type' => $type]; }
function flash_get(): ?array { $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f; }
function flash_error_set(string $msg): void { $_SESSION['flash_error'] = $msg; }
function flash_error_get(): ?string { $m = $_SESSION['flash_error'] ?? null; unset($_SESSION['flash_error']); return $m; }

/** Uygulamada kullanılan tarih formatı (eski DATETIME görünümüyle uyumlu). */
function dg_now(): string { return date('Y-m-d H:i:s'); }

/** Büyük/küçük harf duyarsız karşılaştırmalarda güvenli katlama. */
function dg_casefold(string $s): string {
    return function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
}

/** İlk kurulum verileri. Admin/oyuncu şifresi: Admin123! */
function dg_seed_data(): array {
    $now = dg_now();
    $hash = '$2y$10$4ZK5MehAkI2ZYLCA1tB5K.PgMrc8A7XLtbBzbIqYcbhXDILtI9Y9G';

    $items = [
        [
            'id' => 1, 'name' => 'Cyberpunk 2077 — Phantom Liberty (Steam)',
            'description' => 'Steam key. Anında teslimat. Hesabınıza tanımlamaya hazır.',
            'image_url' => 'https://images.unsplash.com/photo-1511512578047-dfb367046420?w=600&q=80',
            'price_tl' => 799.00, 'price_dcoin' => 120, 'stock' => 3, 'is_active' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ],
        [
            'id' => 2, 'name' => 'Elden Ring — Deluxe Edition',
            'description' => 'Bandai Namco resmi key. Tüm DLC’ler dahil.',
            'image_url' => 'https://images.unsplash.com/photo-1538481199705-c710c4e965fc?w=600&q=80',
            'price_tl' => 1299.00, 'price_dcoin' => 200, 'stock' => 2, 'is_active' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ],
        [
            'id' => 3, 'name' => 'Valorant 1000 VP',
            'description' => 'Riot hesabınıza anında yüklenir. Bölge: TR.',
            'image_url' => 'https://images.unsplash.com/photo-1542751371-adc38448a05e?w=600&q=80',
            'price_tl' => 349.00, 'price_dcoin' => 60, 'stock' => 4, 'is_active' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ],
        [
            'id' => 4, 'name' => 'Discord Nitro — 1 Ay',
            'description' => 'Hediye linki olarak teslim. Anında aktifleştirme.',
            'image_url' => 'https://images.unsplash.com/photo-1612287230202-1ff1d85d1bdf?w=600&q=80',
            'price_tl' => 149.00, 'price_dcoin' => 35, 'stock' => 2, 'is_active' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ],
        [
            'id' => 5, 'name' => 'SteelSeries Mousepad — Device Edition',
            'description' => 'Gamer mousepad, 400x450mm, kaymaz taban.',
            'image_url' => 'https://images.unsplash.com/photo-1600861194942-f883de0dfe96?w=600&q=80',
            'price_tl' => 299.00, 'price_dcoin' => 50, 'stock' => 1, 'is_active' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ],
    ];

    $keyRows = [];
    $keys = [
        1 => ['CP77-PH-XXXX-AAAA-0001', 'CP77-PH-XXXX-AAAA-0002', 'CP77-PH-XXXX-AAAA-0003'],
        2 => ['ELDEN-DLX-YYYY-BBBB-0001', 'ELDEN-DLX-YYYY-BBBB-0002'],
        3 => ['VP-1000-TR-CCCC-0001', 'VP-1000-TR-CCCC-0002', 'VP-1000-TR-CCCC-0003', 'VP-1000-TR-CCCC-0004'],
        4 => ['NITRO-1M-DDDD-0001', 'NITRO-1M-DDDD-0002'],
        5 => ['MOUSEPAD-DEV-EEEE-0001'],
    ];
    $kid = 1;
    foreach ($keys as $itemId => $codes) {
        foreach ($codes as $code) {
            $keyRows[] = [
                'id' => $kid++, 'store_item_id' => $itemId, 'key_code' => $code,
                'is_used' => 0, 'used_by' => null, 'used_at' => null, 'created_at' => $now,
            ];
        }
    }

    return [
        'meta' => ['version' => 1, 'created_at' => $now, 'updated_at' => $now],
        'counters' => [
            'users' => 3, 'ads' => 4, 'watch_logs' => 1, 'watch_sessions' => 1,
            'store_items' => 6, 'store_keys' => $kid, 'purchases' => 1,
        ],
        'settings' => ['daily_watch_limit' => 5, 'site_name' => 'DeviceGame'],
        'users' => [
            [
                'id' => 1, 'username' => 'admin', 'email' => 'admin@devicegame.com',
                'password_hash' => $hash, 'dcoin_balance' => 1000, 'is_admin' => 1, 'is_banned' => 0,
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'id' => 2, 'username' => 'oyuncu1', 'email' => 'oyuncu1@devicegame.com',
                'password_hash' => $hash, 'dcoin_balance' => 250, 'is_admin' => 0, 'is_banned' => 0,
                'created_at' => $now, 'updated_at' => $now,
            ],
        ],
        'ads' => [
            [
                'id' => 1, 'title' => 'Cyber Hunt — Tanıtım Fragmanı',
                'video_url' => 'https://storage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4',
                'video_path' => null, 'thumbnail_url' => null, 'reward_dcoin' => 15,
                'duration_sec' => 30, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'id' => 2, 'title' => 'Neon Drift — Oynanış Videosu',
                'video_url' => 'https://storage.googleapis.com/gtv-videos-bucket/sample/ElephantsDream.mp4',
                'video_path' => null, 'thumbnail_url' => null, 'reward_dcoin' => 20,
                'duration_sec' => 45, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'id' => 3, 'title' => 'Project Overcharge — Teaser',
                'video_url' => 'https://storage.googleapis.com/gtv-videos-bucket/sample/ForBiggerBlazes.mp4',
                'video_path' => null, 'thumbnail_url' => null, 'reward_dcoin' => 10,
                'duration_sec' => 20, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now,
            ],
        ],
        'watch_logs' => [],
        'watch_sessions' => [],
        'store_items' => $items,
        'store_keys' => $keyRows,
        'purchases' => [],
    ];
}

/** Eksik üst düzey alanları tamamlar; mevcut kullanıcı verisini asla seed ile ezmez. */
function dg_normalize_data(array $data): array {
    $seed = dg_seed_data();
    $collections = ['users', 'ads', 'watch_logs', 'watch_sessions', 'store_items', 'store_keys', 'purchases'];

    if (!isset($data['meta']) || !is_array($data['meta'])) $data['meta'] = [];
    $data['meta'] += ['version' => 1, 'created_at' => dg_now(), 'updated_at' => dg_now()];

    if (!isset($data['settings']) || !is_array($data['settings'])) $data['settings'] = [];
    $data['settings'] += $seed['settings'];

    foreach ($collections as $name) {
        if (!isset($data[$name]) || !is_array($data[$name])) $data[$name] = [];
        $data[$name] = array_values($data[$name]);
    }

    if (!isset($data['counters']) || !is_array($data['counters'])) $data['counters'] = [];
    foreach ($collections as $name) {
        $max = 0;
        foreach ($data[$name] as $row) {
            if (isset($row['id'])) $max = max($max, (int)$row['id']);
        }
        $next = isset($data['counters'][$name]) ? (int)$data['counters'][$name] : 0;
        $data['counters'][$name] = max(1, $next, $max + 1);
    }

    return $data;
}

/** JSON verisini aynı klasöre atomik şekilde yazar. */
function dg_write_data(array $data): void {
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('Veri JSON olarak yazılamadı: ' . json_last_error_msg());
    }

    $tmp = DG_DATA_FILE . '.tmp.' . getmypid() . '.' . bin2hex(random_bytes(4));
    if (@file_put_contents($tmp, $json . "\n") === false) {
        throw new RuntimeException('Geçici veri dosyası yazılamadı. storage/dg/ yazma izinlerini kontrol edin.');
    }
    @chmod($tmp, 0664);
    if (!@rename($tmp, DG_DATA_FILE)) {
        @unlink($tmp);
        throw new RuntimeException('Veri dosyası güncellenemedi. storage/dg/database.json yazma izinlerini kontrol edin.');
    }
    $GLOBALS['DG_DATA_CACHE'] = $data;
}

/** Veri dosyasını okur. Dosya yoksa seed verisi döner (bootstrap normalde dosyayı oluşturur). */
function dg_read_data(bool $fresh = false): array {
    if (!$fresh && isset($GLOBALS['DG_DATA_CACHE']) && is_array($GLOBALS['DG_DATA_CACHE'])) {
        return $GLOBALS['DG_DATA_CACHE'];
    }

    if (!is_file(DG_DATA_FILE)) {
        $GLOBALS['DG_DATA_CACHE'] = dg_normalize_data(dg_seed_data());
        return $GLOBALS['DG_DATA_CACHE'];
    }

    $raw = @file_get_contents(DG_DATA_FILE);
    if ($raw === false) {
        throw new RuntimeException('storage/dg/database.json okunamadı. Dosya izinlerini kontrol edin.');
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('storage/dg/database.json bozuk görünüyor. Dosyayı yedekleyip JSON sözdizimini kontrol edin; sistem veriyi kendiliğinden silmez.');
    }

    $GLOBALS['DG_DATA_CACHE'] = dg_normalize_data($decoded);
    return $GLOBALS['DG_DATA_CACHE'];
}

/**
 * Kilitli ve atomik değişiklik çalıştırır.
 * Callback: function(array &$data) { ... return $result; }
 */
function dg_mutate(callable $callback) {
    if (!is_dir(DG_STORAGE_DIR) && !@mkdir(DG_STORAGE_DIR, 0775, true) && !is_dir(DG_STORAGE_DIR)) {
        throw new RuntimeException('storage/dg/ klasörü oluşturulamadı. storage/ yazma iznini 755/775 yapın.');
    }

    $lock = @fopen(DG_LOCK_FILE, 'c+');
    if ($lock === false) {
        throw new RuntimeException('storage/dg/database.lock oluşturulamadı. storage/dg/ yazma izinlerini kontrol edin.');
    }

    try {
        if (!flock($lock, LOCK_EX)) {
            throw new RuntimeException('Veri dosyası kilitlenemedi. Lütfen tekrar deneyin.');
        }
        $data = dg_read_data(true);
        $result = $callback($data);
        $data = dg_normalize_data($data);
        $data['meta']['updated_at'] = dg_now();
        dg_write_data($data);
        return $result;
    } finally {
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }
}

/** İlk açılışta storage klasörünü ve veri dosyasını oluşturur. */
function dg_ensure_storage(): void {
    if (!is_dir(DG_STORAGE_DIR) && !@mkdir(DG_STORAGE_DIR, 0775, true) && !is_dir(DG_STORAGE_DIR)) {
        throw new RuntimeException('storage/dg/ klasörü oluşturulamadı. storage/ klasörüne yazma izni verin (755/775).');
    }
    if (!is_writable(DG_STORAGE_DIR)) {
        throw new RuntimeException('storage/dg/ yazılabilir değil. Klasör iznini 755/775 yapın.');
    }
    if (!is_dir(DG_UPLOAD_DIR)) @mkdir(DG_UPLOAD_DIR, 0775, true);

    $guard = DG_STORAGE_DIR . '/.htaccess';
    if (!is_file($guard)) {
        @file_put_contents($guard, "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Deny from all\n</IfModule>\n");
    }
    $index = DG_STORAGE_DIR . '/index.html';
    if (!is_file($index)) @file_put_contents($index, '');

    if (!is_file(DG_DATA_FILE)) {
        dg_mutate(function (array &$data): void {
            // Dosya yoksa dg_read_data(true) seed döner; kilit altında olduğu gibi yazılır.
        });
    } elseif (!is_readable(DG_DATA_FILE) || !is_writable(DG_DATA_FILE)) {
        throw new RuntimeException('storage/dg/database.json okunabilir/yazılabilir değil. Dosya iznini 644/664 yapın.');
    }
}

/** Koleksiyonu okur. */
function dg_all(string $collection): array {
    $data = dg_read_data();
    return isset($data[$collection]) && is_array($data[$collection]) ? $data[$collection] : [];
}

/** ID ile kayıt bulur. */
function dg_find_by_id(array $rows, int $id): ?array {
    foreach ($rows as $row) {
        if ((int)($row['id'] ?? 0) === $id) return $row;
    }
    return null;
}

/** ID'nin dizi içindeki indeksini bulur. */
function dg_index_of_id(array $rows, int $id): ?int {
    foreach ($rows as $i => $row) {
        if ((int)($row['id'] ?? 0) === $id) return (int)$i;
    }
    return null;
}

/** ID → kayıt haritası üretir. */
function dg_index_by_id(array $rows): array {
    $map = [];
    foreach ($rows as $row) {
        if (isset($row['id'])) $map[(int)$row['id']] = $row;
    }
    return $map;
}

/** Yeni ID üretir ve sayacı ilerletir. Yalnızca dg_mutate içinde kullanın. */
function dg_next_id(array &$data, string $collection): int {
    $id = (int)($data['counters'][$collection] ?? 1);
    if ($id < 1) $id = 1;
    foreach ($data[$collection] ?? [] as $row) {
        $id = max($id, (int)($row['id'] ?? 0) + 1);
    }
    $data['counters'][$collection] = $id + 1;
    return $id;
}

/** Kayıtları created_at (yoksa verilen alan) ve ID'ye göre yeniden eskiye sıralar. */
function dg_sort_newest(array $rows, string $dateField = 'created_at'): array {
    usort($rows, function (array $a, array $b) use ($dateField): int {
        $cmp = strcmp((string)($b[$dateField] ?? ''), (string)($a[$dateField] ?? ''));
        return $cmp !== 0 ? $cmp : ((int)($b['id'] ?? 0) <=> (int)($a['id'] ?? 0));
    });
    return $rows;
}

/** Kullanıcı kaydının oturum/arayüzde kullanılacak güvenli alanları. */
function dg_public_user(array $u): array {
    return [
        'id' => (int)$u['id'],
        'username' => (string)($u['username'] ?? ''),
        'email' => (string)($u['email'] ?? ''),
        'dcoin_balance' => (int)($u['dcoin_balance'] ?? 0),
        'is_admin' => (int)($u['is_admin'] ?? 0),
        'is_banned' => (int)($u['is_banned'] ?? 0),
        'created_at' => (string)($u['created_at'] ?? ''),
        'updated_at' => (string)($u['updated_at'] ?? ''),
    ];
}

function dg_find_user_by_id(int $id): ?array {
    $u = dg_find_by_id(dg_all('users'), $id);
    return $u ? dg_public_user($u) : null;
}

/** Giriş için hash dahil tam kullanıcı kaydı (yalnızca auth.php kullanır). */
function dg_find_user_for_login(string $login): ?array {
    $needle = dg_casefold(trim($login));
    foreach (dg_all('users') as $u) {
        if (dg_casefold((string)($u['username'] ?? '')) === $needle || dg_casefold((string)($u['email'] ?? '')) === $needle) {
            return $u;
        }
    }
    return null;
}

/** Kayıt işlemini kilit altında yapar; aynı kullanıcı adı/e-posta yarışını engeller. */
function dg_create_user(string $username, string $email, string $passwordHash): array {
    return dg_mutate(function (array &$data) use ($username, $email, $passwordHash): array {
        $uNeedle = dg_casefold($username);
        $eNeedle = dg_casefold($email);
        foreach ($data['users'] as $u) {
            if (dg_casefold((string)$u['username']) === $uNeedle || dg_casefold((string)$u['email']) === $eNeedle) {
                return ['ok' => false, 'error' => 'Bu kullanıcı adı veya e-posta zaten kayıtlı.'];
            }
        }

        $now = dg_now();
        $user = [
            'id' => dg_next_id($data, 'users'),
            'username' => $username,
            'email' => $email,
            'password_hash' => $passwordHash,
            'dcoin_balance' => 0,
            'is_admin' => 0,
            'is_banned' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $data['users'][] = $user;
        return ['ok' => true, 'user' => dg_public_user($user)];
    });
}

/** Kullanıcı alanlarını kilit altında günceller. */
function dg_update_user(int $id, array $fields): bool {
    return (bool)dg_mutate(function (array &$data) use ($id, $fields): bool {
        $i = dg_index_of_id($data['users'], $id);
        if ($i === null) return false;
        foreach ($fields as $k => $v) $data['users'][$i][$k] = $v;
        $data['users'][$i]['updated_at'] = dg_now();
        return true;
    });
}

/** Güncel kullanıcıyı veri dosyasından çeker (bakiye güncel olsun). */
function current_user(): ?array {
    if (!is_logged_in()) return null;

    $u = dg_find_user_by_id((int)$_SESSION['user_id']);
    if (!$u) {
        unset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['email'], $_SESSION['is_admin'], $_SESSION['dcoin_balance'], $_SESSION['dg_auth_v']);
        return null;
    }

    $_SESSION['username'] = $u['username'];
    $_SESSION['email'] = $u['email'];
    $_SESSION['dcoin_balance'] = (int)$u['dcoin_balance'];
    $_SESSION['is_admin'] = (int)$u['is_admin'];

    if ((int)$u['is_banned'] === 1) {
        $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
        $isApi = strpos($script, '/api/') !== false;
        if (!$isApi) {
            $_SESSION = ['flash_error' => 'Hesabınız engellenmiş. Destek ile iletişime geçin.'];
            if (!headers_sent()) {
                header('Location: ' . dg_login_url() . '?banned=1'); exit;
            }
            return null;
        }
    }

    return $u;
}

/** Günlük izleme limitini ayar dosyasından al. */
function daily_watch_limit(): int {
    $data = dg_read_data();
    $v = $data['settings']['daily_watch_limit'] ?? DG_DAILY_LIMIT_DEFAULT;
    return is_numeric($v) ? max(1, (int)$v) : DG_DAILY_LIMIT_DEFAULT;
}

function dg_set_daily_watch_limit(int $limit): void {
    $limit = max(1, min(50, $limit));
    dg_mutate(function (array &$data) use ($limit): void {
        $data['settings']['daily_watch_limit'] = $limit;
    });
}

/** Kullanıcının bugün kaç video izlediği. */
function today_watch_count(int $user_id, ?array $data = null): int {
    $data = $data ?? dg_read_data();
    $today = date('Y-m-d');
    $count = 0;
    foreach ($data['watch_logs'] ?? [] as $log) {
        if ((int)($log['user_id'] ?? 0) === $user_id && substr((string)($log['watched_at'] ?? ''), 0, 10) === $today) {
            $count++;
        }
    }
    return $count;
}

/** Bir ürün için boşta/kullanılmış key sayılarını hesaplar. */
function dg_store_key_counts(array $data, int $itemId): array {
    $avail = 0; $used = 0;
    foreach ($data['store_keys'] ?? [] as $key) {
        if ((int)($key['store_item_id'] ?? 0) !== $itemId) continue;
        if ((int)($key['is_used'] ?? 0) === 1) $used++; else $avail++;
    }
    return ['avail' => $avail, 'used' => $used, 'keys_left' => $avail];
}

/** Ürünlerin stock alanını gerçek key sayısına göre tazeler. */
function dg_refresh_store_stock(array &$data): void {
    foreach ($data['store_items'] as $i => $item) {
        $counts = dg_store_key_counts($data, (int)$item['id']);
        $data['store_items'][$i]['stock'] = $counts['avail'];
    }
}

/** Ürün listesine avail/used/keys_left ekler. */
function dg_enrich_store_items(array $data, array $items): array {
    $out = [];
    foreach ($items as $item) {
        $item += ['description' => '', 'image_url' => '', 'price_tl' => 0, 'price_dcoin' => 0, 'is_active' => 0];
        $item = array_merge($item, dg_store_key_counts($data, (int)$item['id']));
        $item['stock'] = $item['avail'];
        $out[] = $item;
    }
    return $out;
}

// ——— İlk kurulum ———
try {
    dg_ensure_storage();
    unset($GLOBALS['DG_STORAGE_ERROR']);
} catch (Throwable $e) {
    $GLOBALS['DG_STORAGE_ERROR'] = $e->getMessage();
    error_log('[DG STORAGE] ' . $e->getMessage());

    if (defined('DG_DIAGNOSE') && DG_DIAGNOSE) {
        throw $e;
    }
    if (PHP_SAPI !== 'cli' && !headers_sent()) {
        http_response_code(503);
        $safe = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        die('<!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Depolama Ayarı — DeviceGame</title>'
            . '<style>body{margin:0;background:#070A14;color:#EAF0FF;font-family:system-ui;padding:24px;line-height:1.65}.card{max-width:760px;margin:40px auto;background:#0F1428;border:1px solid rgba(0,229,255,.2);border-radius:18px;padding:24px}code{background:rgba(255,255,255,.08);padding:2px 7px;border-radius:6px}a{color:#00E5FF}pre{white-space:pre-wrap;background:#070A14;padding:14px;border-radius:12px}</style></head><body><div class="card">'
            . '<h1>◉ DeviceGame — Depolama klasörü hazır değil</h1>'
            . '<p>Bu sürüm MySQL kullanmaz. Sadece <code>storage/dg/</code> klasörünün PHP tarafından yazılabilmesi gerekir.</p>'
            . '<pre>' . $safe . '</pre>'
            . '<ol><li>File Manager/FTP ile <code>storage</code> ve varsa <code>storage/dg</code> izinlerini <b>755</b> veya <b>775</b> yapın.</li>'
            . '<li><code>storage/dg/database.json</code> varsa izinlerini <b>644</b> veya <b>664</b> yapın.</li>'
            . '<li><a href="diagnose.php">diagnose.php</a> sayfasını açıp kontrol edin.</li></ol>'
            . '</div></body></html>');
    }
}
