<?php
/**
 * Device Game Social — veritabansız forum + akış çekirdeği.
 *
 * Veriler tek bir dev JSON dosyasında tutulmaz:
 * - Profiller, gönderiler ve tepkiler küçük JSON belgeleridir.
 * - Yorumlar ve şikâyetler gönderi/ay bazlı JSONL kayıtlarıdır.
 * - Akışlar, özet indeks dosyalarından beslenir.
 *
 * Bütün yazma işlemleri tek bir sosyal kilidi altında yapılır; JSON belgeleri
 * geçici dosyaya yazılıp rename() ile atomik olarak değiştirilir.
 */
if (!defined('DG_SOCIAL')) {
    define('DG_SOCIAL', true);

    function social_base_dir(): string
    {
        return dirname(__DIR__);
    }

    function social_storage_dir(): string
    {
        return social_base_dir() . '/storage/social';
    }

    function social_upload_dir(): string
    {
        return social_base_dir() . '/uploads/social';
    }

    function social_dir(string $name = ''): string
    {
        return social_storage_dir() . ($name !== '' ? '/' . trim($name, '/') : '');
    }

    function social_lock_file(): string
    {
        return social_storage_dir() . '/social.lock';
    }

    function social_meta_file(): string
    {
        return social_storage_dir() . '/meta.json';
    }

    function social_topics_file(): string
    {
        return social_storage_dir() . '/topics.json';
    }

    function social_bucket(int $id): string
    {
        return str_pad((string)(abs($id) % 100), 2, '0', STR_PAD_LEFT);
    }

    function social_profile_file(int $userId): string
    {
        return social_dir('profiles') . '/' . $userId . '.json';
    }

    function social_post_file(int $postId): string
    {
        return social_dir('posts') . '/' . social_bucket($postId) . '/' . $postId . '.json';
    }

    function social_comments_file(int $postId): string
    {
        return social_dir('comments') . '/' . social_bucket($postId) . '/' . $postId . '.jsonl';
    }

    function social_reactions_file(int $postId): string
    {
        return social_dir('reactions') . '/' . social_bucket($postId) . '/' . $postId . '.json';
    }

    function social_follows_file(int $userId): string
    {
        return social_dir('follows') . '/' . $userId . '.json';
    }

    function social_saves_file(int $userId): string
    {
        return social_dir('saves') . '/' . $userId . '.json';
    }

    function social_notifications_file(int $userId): string
    {
        return social_dir('notifications') . '/' . $userId . '.json';
    }

    function social_rate_file(int $userId): string
    {
        return social_dir('ratelimits') . '/' . $userId . '.json';
    }

    function social_reports_file(int $timestamp): string
    {
        return social_dir('reports') . '/' . date('Y-m', $timestamp) . '.jsonl';
    }

    function social_index_file(string $name): string
    {
        $name = preg_replace('/[^a-z0-9_.-]+/i', '-', $name) ?? 'index';
        return social_dir('indexes') . '/' . trim($name, '-') . '.json';
    }

    function social_fold(string $text): string
    {
        $text = strtr($text, [
            'İ' => 'i', 'I' => 'ı', 'Ğ' => 'ğ', 'Ü' => 'ü', 'Ş' => 'ş', 'Ö' => 'ö', 'Ç' => 'ç',
        ]);
        return function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
    }

    function social_slugify(string $text): string
    {
        $slug = social_fold(trim($text));
        $slug = strtr($slug, [
            'ğ' => 'g', 'ü' => 'u', 'ş' => 's', 'ı' => 'i', 'ö' => 'o', 'ç' => 'c',
            'â' => 'a', 'î' => 'i', 'û' => 'u',
        ]);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        return trim($slug, '-');
    }

    function social_text_len(string $text): int
    {
        return function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
    }

    function social_text_sub(string $text, int $start, ?int $length = null): string
    {
        if (function_exists('mb_substr')) {
            return $length === null ? mb_substr($text, $start, null, 'UTF-8') : mb_substr($text, $start, $length, 'UTF-8');
        }
        return $length === null ? substr($text, $start) : substr($text, $start, $length);
    }

    function social_clean_text(string $text, int $maxLength): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", trim($text));
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? $text;
        if (social_text_len($text) > $maxLength) {
            $text = rtrim(social_text_sub($text, 0, $maxLength));
        }
        return $text;
    }

    function social_excerpt(string $text, int $limit = 240): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)) ?? $text);
        if (social_text_len($text) > $limit) {
            return rtrim(social_text_sub($text, 0, $limit)) . '…';
        }
        return $text;
    }

    function social_time_ago(int $timestamp): string
    {
        $diff = max(0, time() - $timestamp);
        if ($diff < 60) return 'şimdi';
        if ($diff < 3600) return (int)floor($diff / 60) . ' dk önce';
        if ($diff < 86400) return (int)floor($diff / 3600) . ' sa önce';
        if ($diff < 604800) return (int)floor($diff / 86400) . ' g önce';
        return date('d.m.Y', $timestamp);
    }

    function social_format_date(int $timestamp): string
    {
        return date('d.m.Y H:i', $timestamp);
    }

    function social_time_until(int $timestamp): string
    {
        $diff = $timestamp - time();
        if ($diff <= 0) return 'başladı';
        if ($diff < 3600) return max(1, (int)ceil($diff / 60)) . ' dk sonra';
        if ($diff < 86400) return (int)ceil($diff / 3600) . ' sa sonra';
        if ($diff < 604800) return (int)ceil($diff / 86400) . ' g sonra';
        return date('d.m.Y', $timestamp);
    }

    function social_create_dirs(): void
    {
        $dirs = [
            social_storage_dir(),
            social_dir('profiles'),
            social_dir('posts'),
            social_dir('comments'),
            social_dir('reactions'),
            social_dir('follows'),
            social_dir('saves'),
            social_dir('notifications'),
            social_dir('reports'),
            social_dir('indexes'),
            social_dir('ratelimits'),
            social_upload_dir(),
        ];
        for ($i = 0; $i < 100; $i++) {
            $bucket = str_pad((string)$i, 2, '0', STR_PAD_LEFT);
            $dirs[] = social_dir('posts') . '/' . $bucket;
            $dirs[] = social_dir('comments') . '/' . $bucket;
            $dirs[] = social_dir('reactions') . '/' . $bucket;
        }
        foreach ($dirs as $dir) {
            if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new RuntimeException('Sosyal depolama klasörü oluşturulamadı: ' . $dir);
            }
        }
        $guard = social_storage_dir() . '/.htaccess';
        if (!is_file($guard)) {
            @file_put_contents($guard, "Require all denied\nDeny from all\n");
        }
        $index = social_storage_dir() . '/index.html';
        if (!is_file($index)) {
            @file_put_contents($index, '');
        }
        $uploadIndex = social_upload_dir() . '/index.html';
        if (!is_file($uploadIndex)) {
            @file_put_contents($uploadIndex, '');
        }
    }

    function social_with_lock(callable $callback)
    {
        static $depth = 0;
        if ($depth > 0) {
            return $callback();
        }
        if (!is_dir(social_storage_dir())) {
            social_create_dirs();
        }
        $lock = @fopen(social_lock_file(), 'c+');
        if ($lock === false) {
            throw new RuntimeException('Sosyal veri kilidi oluşturulamadı. storage/social yazma iznini kontrol edin.');
        }
        try {
            if (!flock($lock, LOCK_EX)) {
                throw new RuntimeException('Sosyal veri dosyası kilitlenemedi. Lütfen tekrar deneyin.');
            }
            $depth++;
            return $callback();
        } finally {
            $depth = max(0, $depth - 1);
            @flock($lock, LOCK_UN);
            @fclose($lock);
        }
    }

    function social_read_json(string $file, array $default = []): array
    {
        if (!is_file($file)) {
            return $default;
        }
        $raw = @file_get_contents($file);
        if ($raw === false) {
            throw new RuntimeException('Sosyal veri dosyası okunamadı: ' . basename($file));
        }
        if (trim($raw) === '') {
            return $default;
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new RuntimeException('Sosyal veri dosyası bozuk görünüyor: ' . basename($file));
        }
        return $data;
    }

    function social_write_json_atomic(string $file, array $data, bool $pretty = true): void
    {
        $dir = dirname($file);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Sosyal veri klasörü oluşturulamadı.');
        }
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | ($pretty ? JSON_PRETTY_PRINT : 0);
        $json = json_encode($data, $flags);
        if ($json === false) {
            throw new RuntimeException('Sosyal verisi JSON olarak yazılamadı: ' . json_last_error_msg());
        }
        $tmp = $file . '.tmp.' . getmypid() . '.' . bin2hex(random_bytes(4));
        if (@file_put_contents($tmp, $json . "\n") === false) {
            throw new RuntimeException('Sosyal geçici dosyası yazılamadı.');
        }
        @chmod($tmp, 0664);
        if (!@rename($tmp, $file)) {
            @unlink($tmp);
            throw new RuntimeException('Sosyal veri dosyası güncellenemedi: ' . basename($file));
        }
    }

    function social_append_jsonl(string $file, array $record): void
    {
        $dir = dirname($file);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Sosyal kayıt klasörü oluşturulamadı.');
        }
        $line = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($line === false || @file_put_contents($file, $line . "\n", FILE_APPEND | LOCK_EX) === false) {
            throw new RuntimeException('Sosyal kaydı eklenemedi.');
        }
        @chmod($file, 0664);
    }

    function social_read_jsonl(string $file): array
    {
        if (!is_file($file)) {
            return [];
        }
        $handle = @fopen($file, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Sosyal kayıt dosyası okunamadı: ' . basename($file));
        }
        $rows = [];
        try {
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);
                if ($line === '') continue;
                $row = json_decode($line, true);
                if (is_array($row)) $rows[] = $row;
            }
        } finally {
            @fclose($handle);
        }
        return $rows;
    }

    function social_default_topics(): array
    {
        return [
            ['slug' => 'genel-sohbet', 'name' => 'Genel Sohbet', 'description' => 'Oyun gündemi ve günlük sohbet.', 'icon' => '💬', 'official_only' => false, 'active' => true, 'order' => 1],
            ['slug' => 'duyurular', 'name' => 'Duyurular', 'description' => 'DeviceGame ekibinden resmî haberler.', 'icon' => '📣', 'official_only' => true, 'active' => true, 'order' => 2],
            ['slug' => 'oyun-onerileri', 'name' => 'Oyun Önerileri', 'description' => 'Ne oynasam diyenlere topluluk önerileri.', 'icon' => '🎮', 'official_only' => false, 'active' => true, 'order' => 3],
            ['slug' => 'oyun-arkadasi', 'name' => 'Oyun Arkadaşı', 'description' => 'Birlikte oynayacak ekip arkadaşları bul.', 'icon' => '🤝', 'official_only' => false, 'active' => true, 'order' => 4],
            ['slug' => 'yardim', 'name' => 'Yardım & Destek', 'description' => 'Teknik sorular, hesap ve mağaza yardımı.', 'icon' => '🛠', 'official_only' => false, 'active' => true, 'order' => 5],
            ['slug' => 'project-overcharge', 'name' => 'Project: Overcharge', 'description' => 'Stüdyo projemiz hakkında gelişmeler ve fikirler.', 'icon' => '🚀', 'official_only' => false, 'active' => true, 'order' => 6],
            ['slug' => 'rehberler', 'name' => 'Rehberler', 'description' => 'Topluluk tarafından hazırlanan oyun rehberleri.', 'icon' => '📚', 'official_only' => false, 'active' => true, 'order' => 7],
            ['slug' => 'magaza-firsatlari', 'name' => 'Mağaza & Fırsatlar', 'description' => 'İndirimler, stoklar ve D-Coin fırsatları.', 'icon' => '🏷', 'official_only' => false, 'active' => true, 'order' => 8],
            ['slug' => 'etkinlikler', 'name' => 'Etkinlikler', 'description' => 'Oyun geceleri, topluluk buluşmaları ve haftalık etkinlik planları.', 'icon' => '🗓', 'official_only' => false, 'active' => true, 'order' => 9],
        ];
    }

    function social_normalize_topic(array $topic, int $fallbackOrder = 99): array
    {
        $slug = social_slugify((string)($topic['slug'] ?? $topic['name'] ?? ''));
        if ($slug === '') $slug = 'genel-sohbet';
        $name = trim((string)($topic['name'] ?? ''));
        if ($name === '') $name = ucwords(str_replace('-', ' ', $slug));
        return [
            'slug' => $slug,
            'name' => social_clean_text($name, 60),
            'description' => social_clean_text((string)($topic['description'] ?? ''), 180),
            'icon' => social_clean_text((string)($topic['icon'] ?? '💬'), 12) ?: '💬',
            'official_only' => !empty($topic['official_only']),
            'active' => !isset($topic['active']) || !empty($topic['active']),
            'order' => (int)($topic['order'] ?? $fallbackOrder),
        ];
    }

    function social_default_meta(): array
    {
        $now = time();
        return [
            'schema_version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'counters' => ['posts' => 1, 'comments' => 1, 'reports' => 1, 'notifications' => 1],
            'migrations' => [],
            'last_backup_at' => 0,
            'last_backup_by' => 0,
        ];
    }

    function social_official_profile(): array
    {
        $now = time();
        return social_normalize_profile([
            'user_id' => 0,
            'username' => 'devicegame',
            'display_name' => 'DeviceGame',
            'bio' => 'DeviceGame resmî topluluk hesabı. Duyurular, geliştirici günlükleri ve topluluk etkinlikleri burada.',
            'platforms' => ['PC'],
            'favorite_games' => ['Project: Overcharge'],
            'show_balance' => false,
            'trust_level' => 2,
            'is_official' => true,
            'joined_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    function social_ensure_storage(): void
    {
        static $done = false;
        static $running = false;
        if ($done || $running) return;
        $running = true;
        try {
            social_create_dirs();
            social_with_lock(static function (): void {
                $metaExists = is_file(social_meta_file());
                $hasExistingPosts = !empty(glob(social_dir('posts') . '/*/*.json') ?: []);
                // Meta dosyası yanlışlıkla silinmişse başlangıç gönderileriyle mevcut kayıtların üzerine yazma.
                $first = !$metaExists && !$hasExistingPosts;
                if (!$metaExists) {
                    social_write_json_atomic(social_meta_file(), social_default_meta());
                }
                if (!is_file(social_topics_file())) {
                    social_write_json_atomic(social_topics_file(), social_default_topics());
                }
                $meta = social_meta_locked();
                if (!is_array($meta['migrations'] ?? null)) $meta['migrations'] = [];
                if (empty($meta['migrations']['events_topic_v1'])) {
                    $topics = [];
                    foreach (social_read_json(social_topics_file(), social_default_topics()) as $i => $topic) {
                        if (is_array($topic)) $topics[] = social_normalize_topic($topic, $i + 1);
                    }
                    $hasEventsTopic = false;
                    foreach ($topics as $topic) if ((string)$topic['slug'] === 'etkinlikler') $hasEventsTopic = true;
                    if (!$hasEventsTopic) {
                        foreach (social_default_topics() as $topic) {
                            if ((string)$topic['slug'] === 'etkinlikler') $topics[] = social_normalize_topic($topic, 9);
                        }
                        usort($topics, static fn(array $a, array $b): int => [$a['order'], social_fold($a['name'])] <=> [$b['order'], social_fold($b['name'])]);
                        social_write_json_atomic(social_topics_file(), $topics);
                    }
                    if (!is_file(social_index_file('topic-etkinlikler'))) {
                        social_write_json_atomic(social_index_file('topic-etkinlikler'), [], false);
                    }
                    $meta['migrations']['events_topic_v1'] = time();
                    $meta['updated_at'] = time();
                    social_write_json_atomic(social_meta_file(), $meta);
                }
                foreach (['latest', 'moderation-comments'] as $index) {
                    $file = social_index_file($index);
                    if (!is_file($file)) social_write_json_atomic($file, [], false);
                }
                if (!is_file(social_index_file('users'))) {
                    social_write_json_atomic(social_index_file('users'), ['map' => [], 'summaries' => []], false);
                }
                if (!is_file(social_profile_file(0))) {
                    $official = social_official_profile();
                    social_write_json_atomic(social_profile_file(0), $official);
                    social_update_users_index_locked($official);
                }
                if ($first) {
                    $now = time();
                    $seeds = [
                        [
                            'topic_slug' => 'duyurular',
                            'title' => 'DG Social’a hoş geldin!',
                            'body' => "DeviceGame’in kendi topluluk alanı açıldı.\n\nBurada oyun önerileri paylaşabilir, oyun arkadaşı bulabilir, Project: Overcharge hakkında fikir verebilir ve diğer oyuncularla sohbet edebilirsin.\n\nİlk olarak kendini tanıtmak ister misin? Hangi platformda oynuyorsun ve şu sıralar hangi oyundasın?",
                            'tags' => ['hos-geldin', 'topluluk'],
                            'pinned' => true,
                            'created_at' => $now,
                        ],
                        [
                            'topic_slug' => 'genel-sohbet',
                            'title' => 'Hafta sonu ne oynuyorsun?',
                            'body' => "Hafta sonu planı belli mi?\n\nYeni bir oyuna mı başlayacaksın, yarım kalan hikâyeyi mi bitireceksin, yoksa arkadaşlarla rekabetçi maçlar mı var? Oynadığın oyunu ve şu ana kadarki izlenimini yaz.",
                            'tags' => ['hafta-sonu', 'sohbet'],
                            'pinned' => false,
                            'created_at' => $now - 60,
                        ],
                        [
                            'topic_slug' => 'oyun-arkadasi',
                            'title' => 'Oyun arkadaşı ilanı nasıl açılır?',
                            'body' => "İyi bir ekip ilanı için oyunu, platformunu, rankını, mikrofon durumunu ve genelde hangi saatlerde oynadığını yaz.\n\nBu konudaki şablonu yeni gönderi formunda doldurman yeterli. Kişisel telefon numarası veya şifre gibi özel bilgilerini asla paylaşma.",
                            'tags' => ['lfg', 'rehber'],
                            'pinned' => false,
                            'created_at' => $now - 120,
                            'meta' => ['game' => 'Tüm oyunlar', 'platform' => 'PC / Konsol', 'rank' => 'Herkes', 'mic' => 'İsteğe bağlı', 'play_time' => 'Akşam', 'needed' => 'Ekip'],
                        ],
                    ];
                    foreach ($seeds as $seed) {
                        social_seed_post_locked($seed);
                    }
                }
            });
            $done = true;
        } finally {
            $running = false;
        }
    }

    function social_meta_locked(): array
    {
        $meta = social_read_json(social_meta_file(), social_default_meta());
        $meta += social_default_meta();
        if (!is_array($meta['counters'] ?? null)) $meta['counters'] = [];
        $meta['counters'] += ['posts' => 1, 'comments' => 1, 'reports' => 1, 'notifications' => 1];
        if (!is_array($meta['migrations'] ?? null)) $meta['migrations'] = [];
        $meta['last_backup_at'] = max(0, (int)($meta['last_backup_at'] ?? 0));
        $meta['last_backup_by'] = max(0, (int)($meta['last_backup_by'] ?? 0));
        return $meta;
    }

    function social_next_id_locked(string $type): int
    {
        $meta = social_meta_locked();
        $id = max(1, (int)($meta['counters'][$type] ?? 1));
        $meta['counters'][$type] = $id + 1;
        $meta['updated_at'] = time();
        social_write_json_atomic(social_meta_file(), $meta);
        return $id;
    }

    function social_topics(bool $activeOnly = true): array
    {
        social_ensure_storage();
        $topics = [];
        foreach (social_read_json(social_topics_file(), social_default_topics()) as $i => $topic) {
            if (!is_array($topic)) continue;
            $topic = social_normalize_topic($topic, $i + 1);
            if ($activeOnly && empty($topic['active'])) continue;
            $topics[] = $topic;
        }
        usort($topics, static fn(array $a, array $b): int => [$a['order'], social_fold($a['name'])] <=> [$b['order'], social_fold($b['name'])]);
        return $topics;
    }

    function social_topic_map(bool $activeOnly = false): array
    {
        $map = [];
        foreach (social_topics($activeOnly) as $topic) $map[$topic['slug']] = $topic;
        return $map;
    }

    function social_topic(string $slug, bool $activeOnly = false): ?array
    {
        return social_topic_map($activeOnly)[social_slugify($slug)] ?? null;
    }

    function social_normalize_profile(array $profile, ?array $user = null): array
    {
        $userId = (int)($profile['user_id'] ?? $user['id'] ?? 0);
        $username = trim((string)($profile['username'] ?? $user['username'] ?? ''));
        if ($username === '' && $userId === 0) $username = 'devicegame';
        $display = trim((string)($profile['display_name'] ?? ''));
        if ($display === '') $display = $username !== '' ? $username : 'DeviceGame Oyuncusu';
        $joined = (int)($profile['joined_at'] ?? 0);
        if ($joined <= 0 && !empty($user['created_at'])) {
            $parsed = strtotime((string)$user['created_at']);
            $joined = $parsed ?: time();
        }
        if ($joined <= 0) $joined = time();
        $cleanList = static function ($value, int $limit, int $length): array {
            if (is_string($value)) $value = preg_split('/[\n,]+/u', $value) ?: [];
            if (!is_array($value)) return [];
            $out = [];
            foreach ($value as $item) {
                $item = social_clean_text((string)$item, $length);
                if ($item !== '' && !in_array($item, $out, true)) $out[] = $item;
                if (count($out) >= $limit) break;
            }
            return $out;
        };
        return [
            'schema_version' => 1,
            'user_id' => $userId,
            'username' => $username,
            'display_name' => social_clean_text($display, 50),
            'bio' => social_clean_text((string)($profile['bio'] ?? ''), 500),
            'platforms' => $cleanList($profile['platforms'] ?? [], 6, 30),
            'favorite_games' => $cleanList($profile['favorite_games'] ?? [], 10, 60),
            'show_balance' => !empty($profile['show_balance']),
            'trust_level' => max(0, min(2, (int)($profile['trust_level'] ?? 0))),
            'is_official' => !empty($profile['is_official']) || $userId === 0,
            'follower_count' => max(0, (int)($profile['follower_count'] ?? 0)),
            'following_count' => max(0, (int)($profile['following_count'] ?? 0)),
            'post_count' => max(0, (int)($profile['post_count'] ?? 0)),
            'comment_count' => max(0, (int)($profile['comment_count'] ?? 0)),
            'approved_posts' => max(0, (int)($profile['approved_posts'] ?? $profile['post_count'] ?? 0)),
            'approved_comments' => max(0, (int)($profile['approved_comments'] ?? $profile['comment_count'] ?? 0)),
            'joined_at' => $joined,
            'created_at' => (int)($profile['created_at'] ?? $joined),
            'updated_at' => (int)($profile['updated_at'] ?? time()),
        ];
    }

    function social_default_profile(array $user): array
    {
        return social_normalize_profile([
            'user_id' => (int)($user['id'] ?? 0),
            'username' => (string)($user['username'] ?? ''),
            'display_name' => (string)($user['username'] ?? ''),
            'bio' => '',
            'created_at' => time(),
            'updated_at' => time(),
        ], $user);
    }

    function social_update_users_index_locked(array $profile): void
    {
        $file = social_index_file('users');
        $index = social_read_json($file, ['map' => [], 'summaries' => []]);
        if (!is_array($index['map'] ?? null)) $index['map'] = [];
        if (!is_array($index['summaries'] ?? null)) $index['summaries'] = [];
        $id = (int)$profile['user_id'];
        foreach ($index['map'] as $name => $mappedId) {
            if ((int)$mappedId === $id && social_fold((string)$name) !== social_fold((string)$profile['username'])) {
                unset($index['map'][$name]);
            }
        }
        $index['map'][social_fold((string)$profile['username'])] = $id;
        $index['summaries'][(string)$id] = [
            'user_id' => $id,
            'username' => (string)$profile['username'],
            'display_name' => (string)$profile['display_name'],
            'bio' => social_excerpt((string)$profile['bio'], 160),
            'is_official' => !empty($profile['is_official']),
            'trust_level' => (int)$profile['trust_level'],
            'follower_count' => (int)$profile['follower_count'],
            'post_count' => (int)$profile['post_count'],
        ];
        social_write_json_atomic($file, $index, false);
    }

    function social_users_index(): array
    {
        social_ensure_storage();
        $index = social_read_json(social_index_file('users'), ['map' => [], 'summaries' => []]);
        if (!is_array($index['map'] ?? null)) $index['map'] = [];
        if (!is_array($index['summaries'] ?? null)) $index['summaries'] = [];
        return $index;
    }

    function social_get_profile(int $userId, ?array $user = null): ?array
    {
        social_ensure_storage();
        if ($userId === 0) {
            $profile = social_read_json(social_profile_file(0), []);
            return $profile ? social_normalize_profile($profile) : social_official_profile();
        }
        if ($user === null && function_exists('dg_find_user_by_id')) {
            $user = dg_find_user_by_id($userId);
        }
        $file = social_profile_file($userId);
        if (is_file($file)) {
            $profile = social_normalize_profile(social_read_json($file), $user ?: []);
            if ($user && (string)($user['username'] ?? '') !== '') {
                $profile['username'] = (string)$user['username'];
            }
            return $profile;
        }
        return $user ? social_default_profile($user) : null;
    }

    function social_ensure_profile(array $user): array
    {
        social_ensure_storage();
        return social_with_lock(static function () use ($user): array {
            $id = (int)($user['id'] ?? 0);
            if ($id === 0) return social_official_profile();
            $file = social_profile_file($id);
            $exists = is_file($file);
            $profile = $exists
                ? social_normalize_profile(social_read_json($file), $user)
                : social_default_profile($user);
            $username = (string)($user['username'] ?? $profile['username']);
            $trust = !empty($user['is_admin']) ? max(1, (int)$profile['trust_level']) : (int)$profile['trust_level'];
            if (!$exists || $profile['username'] !== $username || (int)$profile['trust_level'] !== $trust) {
                $profile['username'] = $username;
                $profile['trust_level'] = $trust;
                $profile['updated_at'] = time();
                social_write_json_atomic($file, $profile);
                social_update_users_index_locked($profile);
            }
            return $profile;
        });
    }

    function social_profile_by_username(string $username, bool $create = false): ?array
    {
        social_ensure_storage();
        $key = social_fold(trim($username));
        if ($key === '') return null;
        $index = social_users_index();
        $id = isset($index['map'][$key]) ? (int)$index['map'][$key] : null;
        if ($id !== null) {
            return social_get_profile($id);
        }
        if (function_exists('dg_all')) {
            foreach (dg_all('users') as $user) {
                if (social_fold((string)($user['username'] ?? '')) === $key) {
                    $public = function_exists('dg_public_user') ? dg_public_user($user) : $user;
                    return $create ? social_ensure_profile($public) : social_get_profile((int)$public['id'], $public);
                }
            }
        }
        return null;
    }

    function social_profiles_by_ids(array $ids): array
    {
        $users = [];
        if (function_exists('dg_all')) {
            foreach (dg_all('users') as $user) {
                $users[(int)($user['id'] ?? 0)] = function_exists('dg_public_user') ? dg_public_user($user) : $user;
            }
        }
        $profiles = [];
        foreach (array_unique(array_map('intval', $ids)) as $id) {
            $profile = social_get_profile($id, $users[$id] ?? null);
            if ($profile) $profiles[$id] = $profile;
        }
        return $profiles;
    }

    function social_profile_badges(array $profile, ?array $user = null): array
    {
        $badges = [];
        if (!empty($profile['is_official'])) $badges[] = 'Resmî Hesap';
        if (!empty($user['is_admin'])) $badges[] = 'Yönetici';
        if ((int)($profile['user_id'] ?? 0) > 0 && (int)$profile['user_id'] <= 100) $badges[] = 'Erken Üye';
        if ((int)($profile['trust_level'] ?? 0) >= 1) $badges[] = 'Güvenilir Üye';
        if ((int)($profile['approved_posts'] ?? 0) >= 5) $badges[] = 'İçerik Üreticisi';
        if ((int)($profile['approved_comments'] ?? 0) >= 25) $badges[] = 'Yardımsever';
        return array_values(array_unique($badges));
    }

    function social_is_trusted(array $profile, ?array $user = null): bool
    {
        return !empty($user['is_admin'])
            || (int)($profile['trust_level'] ?? 0) >= 1
            || (int)($profile['approved_posts'] ?? 0) >= 3
            || (int)($profile['approved_comments'] ?? 0) >= 5;
    }

    function social_maybe_promote_locked(array $profile): array
    {
        if ((int)$profile['trust_level'] < 1 && ((int)$profile['approved_posts'] >= 3 || (int)$profile['approved_comments'] >= 5)) {
            $profile['trust_level'] = 1;
        }
        $profile['updated_at'] = time();
        social_write_json_atomic(social_profile_file((int)$profile['user_id']), $profile);
        social_update_users_index_locked($profile);
        return $profile;
    }

    function social_update_profile(array $user, array $input): array
    {
        social_ensure_storage();
        return social_with_lock(static function () use ($user, $input): array {
            $profile = social_get_profile((int)$user['id'], $user) ?: social_default_profile($user);
            $errors = [];
            $display = social_clean_text((string)($input['display_name'] ?? ''), 50);
            if (social_text_len($display) < 2) $errors[] = 'Görünen ad en az 2 karakter olmalı.';
            if (empty($user['is_admin']) && social_slugify($display) === 'devicegame') $errors[] = 'Bu görünen ad DeviceGame resmî hesabı için ayrılmıştır.';
            if ($errors) return ['errors' => $errors, 'profile' => $profile];
            $profile['display_name'] = $display;
            $profile['bio'] = social_clean_text((string)($input['bio'] ?? ''), 500);
            $profile['platforms'] = social_normalize_profile(['platforms' => (string)($input['platforms'] ?? '')])['platforms'];
            $profile['favorite_games'] = social_normalize_profile(['favorite_games' => (string)($input['favorite_games'] ?? '')])['favorite_games'];
            $profile['show_balance'] = !empty($input['show_balance']);
            $profile['updated_at'] = time();
            $profile = social_normalize_profile($profile, $user);
            social_write_json_atomic(social_profile_file((int)$user['id']), $profile);
            social_update_users_index_locked($profile);
            social_sync_author_summaries_locked($profile);
            return ['errors' => [], 'profile' => $profile];
        });
    }

    function social_set_trust(int $userId, int $level, array $admin): array
    {
        if (empty($admin['is_admin'])) return ['errors' => ['Bu işlem için yönetici yetkisi gerekir.']];
        $user = function_exists('dg_find_user_by_id') ? dg_find_user_by_id($userId) : null;
        if (!$user) return ['errors' => ['Kullanıcı bulunamadı.']];
        $profile = social_ensure_profile($user);
        return social_with_lock(static function () use ($profile, $level): array {
            $profile['trust_level'] = max(0, min(2, $level));
            $profile['updated_at'] = time();
            social_write_json_atomic(social_profile_file((int)$profile['user_id']), $profile);
            social_update_users_index_locked($profile);
            return ['errors' => [], 'profile' => $profile];
        });
    }

    function social_follows(int $userId): array
    {
        social_ensure_storage();
        $data = social_read_json(social_follows_file($userId), []);
        $clean = static function ($value): array {
            if (!is_array($value)) return [];
            return array_values(array_unique(array_filter(array_map('intval', $value), static fn(int $id): bool => $id >= 0)));
        };
        return [
            'following' => $clean($data['following'] ?? []),
            'followers' => $clean($data['followers'] ?? []),
            'blocked' => $clean($data['blocked'] ?? []),
            'blocked_by' => $clean($data['blocked_by'] ?? []),
            'updated_at' => (int)($data['updated_at'] ?? 0),
        ];
    }

    function social_write_follows_locked(int $userId, array $follows): void
    {
        $follows['updated_at'] = time();
        social_write_json_atomic(social_follows_file($userId), $follows, false);
        $profile = social_get_profile($userId);
        if ($profile) {
            $profile['following_count'] = count($follows['following']);
            $profile['follower_count'] = count($follows['followers']);
            $profile['updated_at'] = time();
            social_write_json_atomic(social_profile_file($userId), $profile);
            social_update_users_index_locked($profile);
        }
    }

    function social_blocked_ids(?array $user): array
    {
        if (!$user) return [];
        $follows = social_follows((int)$user['id']);
        // Engelleme çift yönlü görünürlük uygular: engellediklerim ve beni engelleyenler.
        return array_values(array_unique(array_map('intval', array_merge($follows['blocked'], $follows['blocked_by']))));
    }

    function social_toggle_follow(array $actor, int $targetId): array
    {
        social_ensure_storage();
        $actorId = (int)$actor['id'];
        if ($actorId === $targetId) return ['errors' => ['Kendini takip edemezsin.'], 'following' => false];
        $targetUser = $targetId === 0 ? ['id' => 0, 'username' => 'devicegame'] : (function_exists('dg_find_user_by_id') ? dg_find_user_by_id($targetId) : null);
        if (!$targetUser) return ['errors' => ['Kullanıcı bulunamadı.'], 'following' => false];
        social_ensure_profile($actor);
        if ($targetId > 0) social_ensure_profile($targetUser);
        return social_with_lock(static function () use ($actor, $actorId, $targetId): array {
            $actorFollows = social_follows($actorId);
            $targetFollows = social_follows($targetId);
            if (in_array($targetId, $actorFollows['blocked'], true)) return ['errors' => ['Önce bu kullanıcının engelini kaldır.'], 'following' => false];
            if (in_array($actorId, $targetFollows['blocked'], true)) return ['errors' => ['Bu kullanıcı takip edilemiyor.'], 'following' => false];
            $following = in_array($targetId, $actorFollows['following'], true);
            if ($following) {
                $actorFollows['following'] = array_values(array_filter($actorFollows['following'], static fn(int $id): bool => $id !== $targetId));
                $targetFollows['followers'] = array_values(array_filter($targetFollows['followers'], static fn(int $id): bool => $id !== $actorId));
            } else {
                $actorFollows['following'][] = $targetId;
                $targetFollows['followers'][] = $actorId;
            }
            social_write_follows_locked($actorId, $actorFollows);
            social_write_follows_locked($targetId, $targetFollows);
            if (!$following && $targetId > 0) {
                social_notify_locked($targetId, $actorId, 'follow', 0, 0, '@' . (string)$actor['username'] . ' seni takip etmeye başladı.', 'profil.php?kullanici=' . rawurlencode((string)$actor['username']));
            }
            return ['errors' => [], 'following' => !$following];
        });
    }

    function social_toggle_block(array $actor, int $targetId): array
    {
        $actorId = (int)$actor['id'];
        if ($actorId === $targetId || $targetId === 0) return ['errors' => ['Bu kullanıcı engellenemez.'], 'blocked' => false];
        $targetUser = function_exists('dg_find_user_by_id') ? dg_find_user_by_id($targetId) : null;
        if (!$targetUser) return ['errors' => ['Kullanıcı bulunamadı.'], 'blocked' => false];
        social_ensure_profile($actor);
        social_ensure_profile($targetUser);
        return social_with_lock(static function () use ($actorId, $targetId): array {
            $actorFollows = social_follows($actorId);
            $targetFollows = social_follows($targetId);
            $blocked = in_array($targetId, $actorFollows['blocked'], true);
            if ($blocked) {
                $actorFollows['blocked'] = array_values(array_filter($actorFollows['blocked'], static fn(int $id): bool => $id !== $targetId));
                $targetFollows['blocked_by'] = array_values(array_filter($targetFollows['blocked_by'], static fn(int $id): bool => $id !== $actorId));
            } else {
                $actorFollows['blocked'][] = $targetId;
                $targetFollows['blocked_by'][] = $actorId;
                $actorFollows['following'] = array_values(array_filter($actorFollows['following'], static fn(int $id): bool => $id !== $targetId));
                $actorFollows['followers'] = array_values(array_filter($actorFollows['followers'], static fn(int $id): bool => $id !== $targetId));
                $targetFollows['following'] = array_values(array_filter($targetFollows['following'], static fn(int $id): bool => $id !== $actorId));
                $targetFollows['followers'] = array_values(array_filter($targetFollows['followers'], static fn(int $id): bool => $id !== $actorId));
            }
            social_write_follows_locked($actorId, $actorFollows);
            social_write_follows_locked($targetId, $targetFollows);
            return ['errors' => [], 'blocked' => !$blocked];
        });
    }

    function social_rate_limit_locked(int $userId, string $bucket, array $limits, bool $record = true): bool
    {
        if ($userId <= 0) return true;
        $file = social_rate_file($userId);
        $data = social_read_json($file, []);
        $now = time();
        $times = [];
        foreach ((array)($data[$bucket] ?? []) as $time) {
            $time = (int)$time;
            if ($time > $now - 86400) $times[] = $time;
        }
        foreach ($limits as [$window, $max]) {
            $count = 0;
            foreach ($times as $time) if ($time > $now - (int)$window) $count++;
            if ($count >= (int)$max) {
                $data[$bucket] = $times;
                social_write_json_atomic($file, $data, false);
                return false;
            }
        }
        if ($record) $times[] = $now;
        $data[$bucket] = $times;
        $data['updated_at'] = $now;
        social_write_json_atomic($file, $data, false);
        return true;
    }

    function social_parse_tags(string $input): array
    {
        $parts = preg_split('/[\s,]+/u', $input) ?: [];
        $tags = [];
        foreach ($parts as $part) {
            $tag = social_slugify(ltrim(trim((string)$part), '#'));
            if ($tag !== '' && !in_array($tag, $tags, true)) $tags[] = social_text_sub($tag, 0, 30);
            if (count($tags) >= 5) break;
        }
        return $tags;
    }

    function social_url_count(string $text): int
    {
        return (int)preg_match_all('~https?://[^\s<]+~iu', $text);
    }

    function social_normalize_poll($poll): ?array
    {
        if (!is_array($poll) || !is_array($poll['options'] ?? null)) return null;
        $options = [];
        $seenLabels = [];
        $seenIds = [];
        foreach ((array)$poll['options'] as $rawOption) {
            $label = is_array($rawOption)
                ? social_clean_text((string)($rawOption['label'] ?? ''), 60)
                : social_clean_text((string)$rawOption, 60);
            $folded = social_fold($label);
            if ($label === '' || isset($seenLabels[$folded])) continue;
            $id = is_array($rawOption) ? strtolower(trim((string)($rawOption['id'] ?? ''))) : '';
            if (!preg_match('/^o[1-5]$/', $id) || isset($seenIds[$id])) {
                $id = 'o' . (count($options) + 1);
                while (isset($seenIds[$id]) && count($options) < 5) {
                    $id = 'o' . ((int)substr($id, 1) + 1);
                }
            }
            if (isset($seenIds[$id])) continue;
            $seenLabels[$folded] = true;
            $seenIds[$id] = true;
            $options[] = [
                'id' => $id,
                'label' => $label,
                'votes' => is_array($rawOption) ? max(0, (int)($rawOption['votes'] ?? 0)) : 0,
            ];
            if (count($options) >= 5) break;
        }
        if (count($options) < 2) return null;
        $total = 0;
        foreach ($options as $option) $total += (int)$option['votes'];
        return [
            'options' => $options,
            'closes_at' => max(0, (int)($poll['closes_at'] ?? 0)),
            'total_votes' => $total,
            'allow_change' => !isset($poll['allow_change']) || !empty($poll['allow_change']),
        ];
    }

    function social_poll_closed(array $poll): bool
    {
        return (int)($poll['closes_at'] ?? 0) > 0 && time() >= (int)$poll['closes_at'];
    }

    function social_poll_with_counts(array $poll, array $reactions): array
    {
        $poll = social_normalize_poll($poll) ?: $poll;
        $counts = [];
        foreach ((array)($poll['options'] ?? []) as $option) {
            if (is_array($option)) $counts[(string)$option['id']] = 0;
        }
        foreach ((array)($reactions['poll_votes'] ?? []) as $optionId) {
            $optionId = (string)$optionId;
            if (isset($counts[$optionId])) $counts[$optionId]++;
        }
        $total = 0;
        foreach ((array)($poll['options'] ?? []) as $i => $option) {
            if (!is_array($option)) continue;
            $poll['options'][$i]['votes'] = (int)($counts[(string)$option['id']] ?? 0);
            $total += (int)$poll['options'][$i]['votes'];
        }
        $poll['total_votes'] = $total;
        return $poll;
    }

    function social_poll_payload(array $post, ?array $viewer = null, ?array $reactions = null): ?array
    {
        $poll = social_normalize_poll($post['poll'] ?? null);
        if (!$poll) return null;
        if ($reactions !== null) $poll = social_poll_with_counts($poll, $reactions);
        $total = max(0, (int)($poll['total_votes'] ?? 0));
        foreach ($poll['options'] as $i => $option) {
            $poll['options'][$i]['percent'] = $total > 0 ? (int)round(((int)$option['votes'] * 100) / $total) : 0;
        }
        $selected = '';
        if ($viewer && $reactions !== null) {
            $selected = (string)(($reactions['poll_votes'] ?? [])[(string)(int)$viewer['id']] ?? '');
        }
        $poll['selected'] = $selected;
        $poll['closed'] = social_poll_closed($poll);
        $poll['can_vote'] = $viewer && ($post['status'] ?? '') === 'published' && !$poll['closed'];
        return $poll;
    }

    function social_normalize_award($award): ?array
    {
        if (!is_array($award)) return null;
        $amount = (int)($award['amount'] ?? 0);
        if ($amount <= 0 || $amount > 100000) return null;
        return [
            'amount' => $amount,
            'reason' => social_clean_text((string)($award['reason'] ?? ''), 180),
            'awarded_by' => max(0, (int)($award['awarded_by'] ?? 0)),
            'awarded_at' => max(0, (int)($award['awarded_at'] ?? 0)),
        ];
    }

    function social_is_event_post(array $post): bool
    {
        return (string)($post['topic_slug'] ?? '') === 'etkinlikler'
            || (int)(($post['meta'] ?? [])['event_at'] ?? 0) > 0;
    }

    function social_event_is_open(array $post): bool
    {
        if (!social_is_event_post($post)) return false;
        $startsAt = (int)(($post['meta'] ?? [])['event_at'] ?? 0);
        return $startsAt > 0 && ($startsAt + 21600) >= time();
    }

    function social_normalize_post(array $post): array
    {
        $created = (int)($post['created_at'] ?? time());
        $meta = [];
        if (is_array($post['meta'] ?? null)) {
            foreach (['game', 'platform', 'rank', 'mic', 'play_time', 'needed', 'event_place'] as $key) {
                $meta[$key] = social_clean_text((string)($post['meta'][$key] ?? ''), 60);
            }
            $meta['event_at'] = max(0, (int)($post['meta']['event_at'] ?? 0));
            $meta['event_capacity'] = max(0, min(1000, (int)($post['meta']['event_capacity'] ?? 0)));
        }
        return [
            'schema_version' => 1,
            'id' => (int)($post['id'] ?? 0),
            'user_id' => (int)($post['user_id'] ?? 0),
            'author_username' => (string)($post['author_username'] ?? ''),
            'author_display' => (string)($post['author_display'] ?? ''),
            'topic_slug' => social_slugify((string)($post['topic_slug'] ?? 'genel-sohbet')) ?: 'genel-sohbet',
            'title' => social_clean_text((string)($post['title'] ?? ''), 140),
            'body' => social_clean_text((string)($post['body'] ?? ''), 5000),
            'excerpt' => social_excerpt((string)($post['excerpt'] ?? $post['body'] ?? ''), 240),
            'tags' => array_values(array_filter(array_map(static fn($tag): string => social_slugify((string)$tag), (array)($post['tags'] ?? [])))),
            'meta' => $meta,
            'poll' => social_normalize_poll($post['poll'] ?? null),
            'award' => social_normalize_award($post['award'] ?? null),
            'rsvp_count' => max(0, (int)($post['rsvp_count'] ?? 0)),
            'image' => trim((string)($post['image'] ?? '')),
            'status' => in_array(($post['status'] ?? 'published'), ['published', 'pending', 'hidden', 'deleted'], true) ? (string)$post['status'] : 'published',
            'pinned' => !empty($post['pinned']),
            'locked' => !empty($post['locked']),
            'comment_count' => max(0, (int)($post['comment_count'] ?? 0)),
            'gg_count' => max(0, (int)($post['gg_count'] ?? 0)),
            'mentions_notified' => !empty($post['mentions_notified']),
            'created_at' => $created,
            'updated_at' => (int)($post['updated_at'] ?? $created),
            'last_activity_at' => (int)($post['last_activity_at'] ?? $created),
            'edited_at' => (int)($post['edited_at'] ?? 0),
        ];
    }

    function social_post_summary(array $post): array
    {
        $post = social_normalize_post($post);
        return [
            'id' => $post['id'],
            'user_id' => $post['user_id'],
            'author_username' => $post['author_username'],
            'author_display' => $post['author_display'],
            'topic_slug' => $post['topic_slug'],
            'title' => $post['title'],
            'excerpt' => $post['excerpt'],
            'tags' => $post['tags'],
            'meta' => $post['meta'],
            'poll' => $post['poll'],
            'award' => $post['award'],
            'rsvp_count' => $post['rsvp_count'],
            'image' => $post['image'],
            'status' => $post['status'],
            'pinned' => $post['pinned'],
            'locked' => $post['locked'],
            'comment_count' => $post['comment_count'],
            'gg_count' => $post['gg_count'],
            'created_at' => $post['created_at'],
            'updated_at' => $post['updated_at'],
            'last_activity_at' => $post['last_activity_at'],
        ];
    }

    function social_sort_summaries(array $items): array
    {
        usort($items, static function (array $a, array $b): int {
            $pin = (int)!empty($b['pinned']) <=> (int)!empty($a['pinned']);
            if ($pin !== 0) return $pin;
            $activity = (int)($b['last_activity_at'] ?? 0) <=> (int)($a['last_activity_at'] ?? 0);
            return $activity !== 0 ? $activity : ((int)($b['id'] ?? 0) <=> (int)($a['id'] ?? 0));
        });
        return array_values($items);
    }

    function social_index_upsert_one_locked(string $file, array $summary, bool $insert, int $cap = 0): void
    {
        $items = social_read_json($file, []);
        $items = array_values(array_filter($items, static fn($item): bool => is_array($item) && (int)($item['id'] ?? 0) !== (int)$summary['id']));
        if ($insert) $items[] = $summary;
        $items = social_sort_summaries($items);
        if ($cap > 0) $items = array_slice($items, 0, $cap);
        social_write_json_atomic($file, $items, false);
    }

    function social_index_upsert_locked(array $summary): void
    {
        $insert = ($summary['status'] ?? '') !== 'deleted';
        social_index_upsert_one_locked(social_index_file('latest'), $summary, $insert, 1000);
        social_index_upsert_one_locked(social_index_file('month-' . date('Y-m', (int)$summary['created_at'])), $summary, $insert);
        social_index_upsert_one_locked(social_index_file('user-' . (int)$summary['user_id']), $summary, $insert, 1000);
        foreach (social_topics(false) as $topic) {
            $slug = (string)$topic['slug'];
            social_index_upsert_one_locked(
                social_index_file('topic-' . $slug),
                $summary,
                $insert && $slug === (string)$summary['topic_slug'],
                1000
            );
        }
    }

    function social_sync_profile_post_counts_locked(int $userId): void
    {
        $profile = social_get_profile($userId);
        if (!$profile) return;
        $items = social_read_json(social_index_file('user-' . $userId), []);
        $published = 0;
        foreach ($items as $item) if (($item['status'] ?? '') === 'published') $published++;
        $profile['post_count'] = $published;
        $profile['approved_posts'] = $published;
        social_maybe_promote_locked($profile);
    }

    function social_sync_author_summaries_locked(array $profile): void
    {
        $items = social_read_json(social_index_file('user-' . (int)$profile['user_id']), []);
        foreach ($items as $summary) {
            $post = social_get_post((int)($summary['id'] ?? 0), true);
            if (!$post) continue;
            $post['author_username'] = (string)$profile['username'];
            $post['author_display'] = (string)$profile['display_name'];
            social_write_json_atomic(social_post_file((int)$post['id']), $post, false);
            social_index_upsert_locked(social_post_summary($post));
        }
    }

    function social_seed_post_locked(array $seed): void
    {
        $official = social_official_profile();
        $id = social_next_id_locked('posts');
        $created = (int)($seed['created_at'] ?? time());
        $post = social_normalize_post([
            'id' => $id,
            'user_id' => 0,
            'author_username' => $official['username'],
            'author_display' => $official['display_name'],
            'topic_slug' => (string)$seed['topic_slug'],
            'title' => (string)$seed['title'],
            'body' => (string)$seed['body'],
            'tags' => (array)($seed['tags'] ?? []),
            'meta' => (array)($seed['meta'] ?? []),
            'status' => 'published',
            'pinned' => !empty($seed['pinned']),
            'mentions_notified' => true,
            'created_at' => $created,
            'updated_at' => $created,
            'last_activity_at' => $created,
        ]);
        social_write_json_atomic(social_post_file($id), $post, false);
        social_write_json_atomic(social_reactions_file($id), ['gg' => [], 'poll_votes' => [], 'rsvp' => []], false);
        social_index_upsert_locked(social_post_summary($post));
        social_sync_profile_post_counts_locked(0);
    }

    function social_get_post(int $postId, bool $includeDeleted = false): ?array
    {
        social_ensure_storage();
        if ($postId <= 0) return null;
        $file = social_post_file($postId);
        if (!is_file($file)) return null;
        $post = social_normalize_post(social_read_json($file));
        if (!$includeDeleted && $post['status'] === 'deleted') return null;
        return $post;
    }

    function social_post_visible(array $post, ?array $viewer, array $blockedIds = []): bool
    {
        if (($post['status'] ?? '') === 'deleted') return false;
        $isAdmin = !empty($viewer['is_admin']);
        if (!$isAdmin && in_array((int)($post['user_id'] ?? -1), $blockedIds, true)) return false;
        if (($post['status'] ?? '') === 'published') return true;
        if ($isAdmin) return true;
        return $viewer && (int)$viewer['id'] === (int)($post['user_id'] ?? 0);
    }

    function social_extract_mentions(string $text): array
    {
        if (!preg_match_all('/(?<![A-Za-z0-9_.-])@([A-Za-z0-9_.-]{3,32})/', $text, $matches)) return [];
        $index = social_users_index();
        $ids = [];
        $coreMap = null;
        foreach ($matches[1] as $username) {
            $key = social_fold(rtrim((string)$username, '.-'));
            if (isset($index['map'][$key])) {
                $ids[] = (int)$index['map'][$key];
                continue;
            }
            // Sosyal sayfayı henüz açmamış bir DeviceGame hesabı da bildirim alabilsin.
            if ($coreMap === null) {
                $coreMap = [];
                if (function_exists('dg_all')) {
                    foreach (dg_all('users') as $coreUser) {
                        if (is_array($coreUser)) $coreMap[social_fold((string)($coreUser['username'] ?? ''))] = (int)($coreUser['id'] ?? 0);
                    }
                }
            }
            if (isset($coreMap[$key])) $ids[] = (int)$coreMap[$key];
        }
        return array_values(array_unique($ids));
    }

    function social_notify_locked(int $targetId, int $actorId, string $type, int $postId, int $commentId, string $message, string $url): void
    {
        if ($targetId <= 0 || $targetId === $actorId) return;
        $targetFollows = social_follows($targetId);
        if (in_array($actorId, $targetFollows['blocked'], true)) return;
        $file = social_notifications_file($targetId);
        $items = social_read_json($file, []);
        array_unshift($items, [
            'id' => social_next_id_locked('notifications'),
            'type' => $type,
            'actor_id' => $actorId,
            'post_id' => $postId,
            'comment_id' => $commentId,
            'message' => social_clean_text($message, 240),
            'url' => $url,
            'read' => false,
            'created_at' => time(),
        ]);
        $items = array_slice(array_values($items), 0, 100);
        social_write_json_atomic($file, $items, false);
    }

    function social_notify_mentions_locked(array $post, int $actorId, string $text, int $commentId = 0): void
    {
        $actor = social_get_profile($actorId);
        $actorName = $actor['display_name'] ?? 'Bir oyuncu';
        foreach (social_extract_mentions($text) as $targetId) {
            $url = 'gonderi.php?id=' . (int)$post['id'] . ($commentId > 0 ? '#yorum-' . $commentId : '');
            social_notify_locked($targetId, $actorId, 'mention', (int)$post['id'], $commentId, $actorName . ' senden bahsetti: “' . (string)$post['title'] . '”', $url);
        }
    }

    function social_notifications(int $userId): array
    {
        social_ensure_storage();
        $items = social_read_json(social_notifications_file($userId), []);
        return array_values(array_filter($items, 'is_array'));
    }

    function social_unread_notification_count(int $userId): int
    {
        $count = 0;
        foreach (social_notifications($userId) as $item) if (empty($item['read'])) $count++;
        return $count;
    }

    function social_mark_notifications_read(int $userId, int $notificationId = 0): void
    {
        social_with_lock(static function () use ($userId, $notificationId): void {
            $items = social_notifications($userId);
            foreach ($items as $i => $item) {
                if ($notificationId === 0 || (int)($item['id'] ?? 0) === $notificationId) $items[$i]['read'] = true;
            }
            social_write_json_atomic(social_notifications_file($userId), $items, false);
        });
    }

    function social_parse_poll_input(array $input, ?array $existing = null): array
    {
        $existingPoll = $existing ? social_normalize_poll($existing['poll'] ?? null) : null;
        $hasPollInput = array_key_exists('poll_enabled', $input)
            || array_key_exists('poll_options', $input)
            || array_key_exists('poll_days', $input);
        if (!$hasPollInput) {
            return ['errors' => [], 'poll' => $existingPoll];
        }
        if (empty($input['poll_enabled'])) {
            if ($existingPoll && (int)$existingPoll['total_votes'] > 0) {
                return ['errors' => ['Oy kullanılan anket kaldırılamaz.'], 'poll' => $existingPoll];
            }
            return ['errors' => [], 'poll' => null];
        }

        $rawOptions = $input['poll_options'] ?? [];
        if (is_string($rawOptions)) {
            $rawOptions = preg_split('/\r\n|\r|\n/', $rawOptions) ?: [];
        }
        if (!is_array($rawOptions)) $rawOptions = [];
        $labels = [];
        $seen = [];
        $duplicate = false;
        foreach ($rawOptions as $rawOption) {
            $label = social_clean_text(is_array($rawOption) ? (string)($rawOption['label'] ?? '') : (string)$rawOption, 60);
            if ($label === '') continue;
            $folded = social_fold($label);
            if (isset($seen[$folded])) {
                $duplicate = true;
                continue;
            }
            $seen[$folded] = true;
            $labels[] = $label;
        }
        $errors = [];
        if ($duplicate) $errors[] = 'Anket seçenekleri birbirinden farklı olmalı.';
        if (count($labels) < 2) $errors[] = 'Anket için en az 2 seçenek yaz.';
        if (count($labels) > 5) $errors[] = 'Anket en fazla 5 seçenek içerebilir.';
        if ($errors) return ['errors' => $errors, 'poll' => $existingPoll];

        if ($existingPoll) {
            $existingLabels = array_map(static fn(array $option): string => social_fold((string)$option['label']), (array)$existingPoll['options']);
            $submittedLabels = array_map(static fn(string $label): string => social_fold($label), $labels);
            if ((int)$existingPoll['total_votes'] > 0 && $existingLabels !== $submittedLabels) {
                return ['errors' => ['Oy kullanılan anket seçenekleri değiştirilemez.'], 'poll' => $existingPoll];
            }
            $options = [];
            foreach ($labels as $i => $label) {
                $options[] = [
                    'id' => 'o' . ($i + 1),
                    'label' => $label,
                    'votes' => (int)(($existingPoll['options'][$i] ?? [])['votes'] ?? 0),
                ];
            }
            return ['errors' => [], 'poll' => social_normalize_poll([
                'options' => $options,
                'closes_at' => (int)$existingPoll['closes_at'],
                'allow_change' => true,
            ])];
        }

        $days = (int)($input['poll_days'] ?? 7);
        if (!in_array($days, [0, 1, 3, 7, 14, 30], true)) $days = 7;
        $options = [];
        foreach ($labels as $i => $label) {
            $options[] = ['id' => 'o' . ($i + 1), 'label' => $label, 'votes' => 0];
        }
        return ['errors' => [], 'poll' => social_normalize_poll([
            'options' => $options,
            'closes_at' => $days > 0 ? time() + ($days * 86400) : 0,
            'allow_change' => true,
        ])];
    }

    function social_validate_post_input(array $input, array $user, ?array $existing = null): array
    {
        $errors = [];
        $topicSlug = social_slugify((string)($input['topic_slug'] ?? $input['topic'] ?? ''));
        $topic = social_topic($topicSlug, true);
        if (!$topic) {
            $errors[] = 'Geçerli bir forum konusu seç.';
            $topic = ['slug' => $topicSlug, 'official_only' => false];
        }
        if (!empty($topic['official_only']) && empty($user['is_admin'])) {
            $errors[] = 'Duyurular konusunda yalnızca DeviceGame ekibi gönderi açabilir.';
        }
        $title = social_clean_text((string)($input['title'] ?? ''), 140);
        $body = social_clean_text((string)($input['body'] ?? ''), 5000);
        if (social_text_len($title) < 4) $errors[] = 'Başlık en az 4 karakter olmalı.';
        if (social_text_len($body) < 10) $errors[] = 'Gönderin en az 10 karakter olmalı.';
        $tags = social_parse_tags((string)($input['tags'] ?? ''));
        $meta = [];
        if ($topicSlug === 'oyun-arkadasi') {
            foreach (['game', 'platform', 'rank', 'mic', 'play_time', 'needed'] as $key) {
                $meta[$key] = social_clean_text((string)($input['lfg_' . $key] ?? $input[$key] ?? ''), 60);
            }
            if ($meta['game'] === '') $errors[] = 'Oyun arkadaşı ilanında oyun adını yaz.';
            if ($meta['platform'] === '') $errors[] = 'Oyun arkadaşı ilanında platformu seç/yaz.';
        }
        if ($topicSlug === 'etkinlikler') {
            $meta['game'] = social_clean_text((string)($input['event_game'] ?? $input['lfg_game'] ?? $input['game'] ?? ''), 60);
            $meta['platform'] = social_clean_text((string)($input['event_platform'] ?? $input['lfg_platform'] ?? $input['platform'] ?? ''), 60);
            $meta['event_place'] = social_clean_text((string)($input['event_place'] ?? ''), 60);
            $meta['event_capacity'] = max(0, min(1000, (int)($input['event_capacity'] ?? 0)));
            $rawEventAt = trim((string)($input['event_at'] ?? ''));
            $eventAt = 0;
            if ($rawEventAt !== '') {
                $eventAt = is_numeric($rawEventAt) ? (int)$rawEventAt : (strtotime($rawEventAt) ?: 0);
            }
            $meta['event_at'] = $eventAt;
            $existingEventAt = $existing ? (int)(($existing['meta'] ?? [])['event_at'] ?? 0) : 0;
            if ($meta['game'] === '') $errors[] = 'Etkinlikte oynanacak oyunu yaz.';
            if ($meta['event_place'] === '') $errors[] = 'Etkinliğin buluşma yerini yaz.';
            if ($eventAt <= 0) {
                $errors[] = 'Geçerli bir etkinlik tarih ve saati seç.';
            } elseif ($eventAt < time() - 3600 && $eventAt !== $existingEventAt) {
                $errors[] = 'Etkinlik tarihi geçmişte olamaz.';
            } elseif ($eventAt > time() + (366 * 86400)) {
                $errors[] = 'Etkinlik en fazla bir yıl ileriye planlanabilir.';
            }
        }
        $pollResult = social_parse_poll_input($input, $existing);
        $errors = array_merge($errors, (array)$pollResult['errors']);
        return [
            'errors' => $errors,
            'data' => [
                'topic_slug' => $topicSlug,
                'title' => $title,
                'body' => $body,
                'tags' => $tags,
                'meta' => $meta,
                'poll' => $pollResult['poll'],
            ],
        ];
    }

    function social_can_upload_image(array $profile, array $user): bool
    {
        return !empty($user['is_admin']) || (int)($profile['trust_level'] ?? 0) >= 1 || (int)($profile['approved_posts'] ?? 0) >= 3;
    }

    function social_handle_post_image(array $file, int $postId): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE || (string)($file['name'] ?? '') === '') {
            return ['path' => '', 'error' => ''];
        }
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return ['path' => '', 'error' => 'Görsel yüklenemedi. Daha küçük bir dosyayla tekrar dene.'];
        }
        if ((int)($file['size'] ?? 0) > 4 * 1024 * 1024) {
            return ['path' => '', 'error' => 'Görsel en fazla 4 MB olabilir.'];
        }
        $tmp = (string)($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return ['path' => '', 'error' => 'Görsel dosyası doğrulanamadı.'];
        }
        $info = @getimagesize($tmp);
        $mime = is_array($info) ? (string)($info['mime'] ?? '') : '';
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($extensions[$mime])) {
            return ['path' => '', 'error' => 'Yalnızca JPG, PNG veya WebP görsel yükleyebilirsin.'];
        }
        $sourceWidth = is_array($info) ? (int)($info[0] ?? 0) : 0;
        $sourceHeight = is_array($info) ? (int)($info[1] ?? 0) : 0;
        if ($sourceWidth < 1 || $sourceHeight < 1 || $sourceWidth > 8000 || $sourceHeight > 8000 || $sourceWidth * $sourceHeight > 40000000) {
            return ['path' => '', 'error' => 'Görsel çözünürlüğü çok yüksek. En fazla 8000×8000 ve 40 megapiksel yükleyebilirsin.'];
        }
        $relativeDir = date('Y/m');
        $dir = social_upload_dir() . '/' . $relativeDir;
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return ['path' => '', 'error' => 'Görsel klasörü oluşturulamadı.'];
        }
        if (!is_file($dir . '/index.html')) @file_put_contents($dir . '/index.html', '');
        $base = 'post-' . $postId . '-' . bin2hex(random_bytes(6));
        $saved = false;
        $extension = $extensions[$mime];
        $destination = $dir . '/' . $base . '.' . $extension;

        if (function_exists('imagecreatetruecolor')) {
            $source = null;
            if ($mime === 'image/jpeg' && function_exists('imagecreatefromjpeg')) $source = @imagecreatefromjpeg($tmp);
            if ($mime === 'image/png' && function_exists('imagecreatefrompng')) $source = @imagecreatefrompng($tmp);
            if ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) $source = @imagecreatefromwebp($tmp);
            if ($source) {
                $width = imagesx($source);
                $height = imagesy($source);
                $scale = min(1, 1600 / max(1, $width), 1400 / max(1, $height));
                $newWidth = max(1, (int)round($width * $scale));
                $newHeight = max(1, (int)round($height * $scale));
                $canvas = imagecreatetruecolor($newWidth, $newHeight);
                if ($canvas) {
                    imagealphablending($canvas, false);
                    imagesavealpha($canvas, true);
                    $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
                    imagefilledrectangle($canvas, 0, 0, $newWidth, $newHeight, $transparent);
                    imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                    if (function_exists('imagewebp')) {
                        $extension = 'webp';
                        $destination = $dir . '/' . $base . '.webp';
                        $saved = @imagewebp($canvas, $destination, 84);
                    } elseif (function_exists('imagejpeg')) {
                        $extension = 'jpg';
                        $destination = $dir . '/' . $base . '.jpg';
                        $saved = @imagejpeg($canvas, $destination, 86);
                    }
                    imagedestroy($canvas);
                }
                imagedestroy($source);
            }
        }
        if (!$saved) {
            $destination = $dir . '/' . $base . '.' . $extensions[$mime];
            $saved = @move_uploaded_file($tmp, $destination);
            $extension = $extensions[$mime];
        }
        if (!$saved) {
            return ['path' => '', 'error' => 'Görsel kaydedilemedi. uploads/social yazma iznini kontrol et.'];
        }
        @chmod($destination, 0664);
        return ['path' => 'uploads/social/' . $relativeDir . '/' . $base . '.' . $extension, 'error' => ''];
    }

    function social_delete_upload(string $path): void
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        if (!str_starts_with($path, 'uploads/social/')) return;
        $full = social_base_dir() . '/' . $path;
        if (is_file($full)) @unlink($full);
    }

    function social_create_post(array $user, array $input, array $files = []): array
    {
        social_ensure_storage();
        $profile = social_ensure_profile($user);
        $validation = social_validate_post_input($input, $user);
        if ($validation['errors']) return ['errors' => $validation['errors'], 'id' => 0, 'status' => ''];
        $hasImage = !empty($files['image']['name']);
        if ($hasImage && !social_can_upload_image($profile, $user)) {
            return ['errors' => ['Görsel yükleme, güvenilir üye olduktan sonra açılır. İlk gönderilerini metin olarak paylaş.'], 'id' => 0, 'status' => ''];
        }
        return social_with_lock(static function () use ($user, $profile, $validation, $files): array {
            $userId = (int)$user['id'];
            if (empty($user['is_admin']) && !social_rate_limit_locked($userId, 'posts', [[3600, 5], [86400, 20]])) {
                return ['errors' => ['Çok hızlı gönderi açıyorsun. Biraz bekleyip tekrar dene.'], 'id' => 0, 'status' => ''];
            }
            $data = $validation['data'];
            $postId = social_next_id_locked('posts');
            $imagePath = '';
            if (!empty($files['image'])) {
                $image = social_handle_post_image((array)$files['image'], $postId);
                if ($image['error'] !== '') return ['errors' => [$image['error']], 'id' => 0, 'status' => ''];
                $imagePath = (string)$image['path'];
            }
            $trusted = social_is_trusted($profile, $user);
            $status = (!empty($user['is_admin']) || ($trusted && social_url_count((string)$data['body']) < 3)) ? 'published' : 'pending';
            $now = time();
            $isEvent = ($data['topic_slug'] ?? '') === 'etkinlikler' || (int)(($data['meta'] ?? [])['event_at'] ?? 0) > 0;
            $post = social_normalize_post([
                'id' => $postId,
                'user_id' => $userId,
                'author_username' => (string)$profile['username'],
                'author_display' => (string)$profile['display_name'],
                'topic_slug' => $data['topic_slug'],
                'title' => $data['title'],
                'body' => $data['body'],
                'tags' => $data['tags'],
                'meta' => $data['meta'],
                'poll' => $data['poll'] ?? null,
                'rsvp_count' => $isEvent ? 1 : 0,
                'image' => $imagePath,
                'status' => $status,
                'created_at' => $now,
                'updated_at' => $now,
                'last_activity_at' => $now,
            ]);
            social_write_json_atomic(social_post_file($postId), $post, false);
            social_write_json_atomic(social_reactions_file($postId), [
                'gg' => [],
                'poll_votes' => [],
                'rsvp' => $isEvent ? [$userId] : [],
            ], false);
            social_index_upsert_locked(social_post_summary($post));
            social_sync_profile_post_counts_locked($userId);
            if ($status === 'published') {
                social_notify_mentions_locked($post, $userId, (string)$post['body']);
                $post['mentions_notified'] = true;
                social_write_json_atomic(social_post_file($postId), $post, false);
            }
            return ['errors' => [], 'id' => $postId, 'status' => $status];
        });
    }

    function social_edit_post(array $actor, int $postId, array $input, array $files = []): array
    {
        $post = social_get_post($postId, true);
        if (!$post || $post['status'] === 'deleted') return ['errors' => ['Gönderi bulunamadı.'], 'id' => $postId, 'status' => ''];
        $isAdmin = !empty($actor['is_admin']);
        if (!$isAdmin && (int)$actor['id'] !== (int)$post['user_id']) return ['errors' => ['Bu gönderiyi düzenleyemezsin.'], 'id' => $postId, 'status' => (string)$post['status']];
        if (!$isAdmin && $post['status'] === 'hidden') return ['errors' => ['Gizlenmiş gönderi düzenlenemez.'], 'id' => $postId, 'status' => 'hidden'];
        $validation = social_validate_post_input($input, $actor, $post);
        if ($validation['errors']) return ['errors' => $validation['errors'], 'id' => $postId, 'status' => (string)$post['status']];
        $author = $post['user_id'] === 0 ? social_official_profile() : social_get_profile((int)$post['user_id']);
        $hasImage = !empty($files['image']['name']);
        if ($hasImage && $author && !social_can_upload_image($author, $actor)) {
            return ['errors' => ['Bu hesap henüz görsel yükleyemiyor.'], 'id' => $postId, 'status' => (string)$post['status']];
        }
        return social_with_lock(static function () use ($actor, $post, $validation, $files, $input, $author, $isAdmin): array {
            $data = $validation['data'];
            $oldStatus = (string)$post['status'];
            $reactions = social_reactions((int)$post['id']);
            $eventAfter = ($data['topic_slug'] ?? '') === 'etkinlikler' || (int)(($data['meta'] ?? [])['event_at'] ?? 0) > 0;
            $rsvpCount = 0;
            if ($eventAfter) {
                $authorId = (int)$post['user_id'];
                $prospectiveRsvps = $reactions['rsvp'];
                if ($authorId > 0 && !in_array($authorId, $prospectiveRsvps, true)) $prospectiveRsvps[] = $authorId;
                $capacity = (int)(($data['meta'] ?? [])['event_capacity'] ?? 0);
                if ($capacity > 0 && count($prospectiveRsvps) > $capacity) {
                    return ['errors' => ['Etkinlik kapasitesi, mevcut katılımcı sayısının altında olamaz.'], 'id' => (int)$post['id'], 'status' => $oldStatus];
                }
                if ($prospectiveRsvps !== $reactions['rsvp']) {
                    $reactions['rsvp'] = $prospectiveRsvps;
                    social_write_json_atomic(social_reactions_file((int)$post['id']), $reactions, false);
                }
                $rsvpCount = count($prospectiveRsvps);
            }
            $imagePath = (string)$post['image'];
            if (!empty($input['remove_image'])) {
                social_delete_upload($imagePath);
                $imagePath = '';
            }
            if (!empty($files['image'])) {
                $image = social_handle_post_image((array)$files['image'], (int)$post['id']);
                if ($image['error'] !== '') return ['errors' => [$image['error']], 'id' => (int)$post['id'], 'status' => $oldStatus];
                if ((string)$image['path'] !== '') {
                    if ($imagePath !== '') social_delete_upload($imagePath);
                    $imagePath = (string)$image['path'];
                }
            }
            $status = $oldStatus;
            if (!$isAdmin && $oldStatus === 'published' && $author && !social_is_trusted($author, $actor) && social_url_count((string)$data['body']) > 0) {
                $status = 'pending';
            }
            $post = social_normalize_post(array_merge($post, $data, [
                'rsvp_count' => $rsvpCount,
                'image' => $imagePath,
                'status' => $status,
                'updated_at' => time(),
                'edited_at' => time(),
                'last_activity_at' => max((int)$post['last_activity_at'], time()),
            ]));
            social_write_json_atomic(social_post_file((int)$post['id']), $post, false);
            social_index_upsert_locked(social_post_summary($post));
            social_sync_profile_post_counts_locked((int)$post['user_id']);
            if ($status === 'published' && empty($post['mentions_notified'])) {
                social_notify_mentions_locked($post, (int)$actor['id'], (string)$post['body']);
                $post['mentions_notified'] = true;
                social_write_json_atomic(social_post_file((int)$post['id']), $post, false);
            }
            return ['errors' => [], 'id' => (int)$post['id'], 'status' => $status];
        });
    }

    function social_set_post_status(array $actor, int $postId, string $status): array
    {
        if (!in_array($status, ['published', 'pending', 'hidden', 'deleted'], true)) return ['errors' => ['Geçersiz gönderi durumu.']];
        $post = social_get_post($postId, true);
        if (!$post) return ['errors' => ['Gönderi bulunamadı.']];
        $isAdmin = !empty($actor['is_admin']);
        $isAuthor = (int)$actor['id'] === (int)$post['user_id'];
        if (!$isAdmin && !($isAuthor && $status === 'deleted')) return ['errors' => ['Bu işlem için yetkin yok.']];
        return social_with_lock(static function () use ($actor, $post, $status): array {
            $wasPublished = $post['status'] === 'published';
            if ($status === 'deleted' && (string)($post['image'] ?? '') !== '') {
                social_delete_upload((string)$post['image']);
                $post['image'] = '';
            }
            $post['status'] = $status;
            $post['updated_at'] = time();
            if ($status === 'published') $post['last_activity_at'] = max((int)$post['last_activity_at'], time());
            $post = social_normalize_post($post);
            social_write_json_atomic(social_post_file((int)$post['id']), $post, false);
            social_index_upsert_locked(social_post_summary($post));
            social_sync_profile_post_counts_locked((int)$post['user_id']);
            if (!$wasPublished && $status === 'published' && empty($post['mentions_notified'])) {
                // Onaylayan yönetici değil, gönderinin yazarı bahsetmiş sayılır.
                social_notify_mentions_locked($post, (int)$post['user_id'], (string)$post['body']);
                $post['mentions_notified'] = true;
                social_write_json_atomic(social_post_file((int)$post['id']), $post, false);
            }
            return ['errors' => [], 'status' => $status];
        });
    }

    function social_set_post_flag(array $actor, int $postId, string $flag): array
    {
        if (empty($actor['is_admin']) || !in_array($flag, ['pinned', 'locked'], true)) return ['errors' => ['Bu işlem için yönetici yetkisi gerekir.'], 'value' => false];
        $post = social_get_post($postId, true);
        if (!$post || $post['status'] === 'deleted') return ['errors' => ['Gönderi bulunamadı.'], 'value' => false];
        return social_with_lock(static function () use ($post, $flag): array {
            $post[$flag] = empty($post[$flag]);
            $post['updated_at'] = time();
            $post = social_normalize_post($post);
            social_write_json_atomic(social_post_file((int)$post['id']), $post, false);
            social_index_upsert_locked(social_post_summary($post));
            return ['errors' => [], 'value' => (bool)$post[$flag]];
        });
    }

    function social_award_post(array $admin, int $postId, int $amount, string $reason): array
    {
        if (empty($admin['is_admin'])) return ['errors' => ['Bu işlem için yönetici yetkisi gerekir.']];
        $amount = (int)$amount;
        $reason = social_clean_text($reason, 180);
        if ($amount < 5 || $amount > 1000) return ['errors' => ['Ödül 5 ile 1000 D-Coin arasında olmalı.']];
        if (social_text_len($reason) < 3) return ['errors' => ['Ödül gerekçesini kısaca yaz.']];
        $post = social_get_post($postId, true);
        if (!$post || $post['status'] !== 'published') return ['errors' => ['Yalnızca yayındaki bir gönderiye ödül verilebilir.']];
        if ((int)$post['user_id'] <= 0) return ['errors' => ['Resmî hesaba D-Coin ödülü verilemez.']];
        if ((int)$admin['id'] === (int)$post['user_id']) return ['errors' => ['Kendi gönderine ödül veremezsin.']];
        if (!empty($post['award'])) return ['errors' => ['Bu gönderi zaten Editör Ödülü aldı.']];
        $authorUser = function_exists('dg_find_user_by_id') ? dg_find_user_by_id((int)$post['user_id']) : null;
        if (!$authorUser || (int)($authorUser['is_banned'] ?? 0) === 1) {
            return ['errors' => ['Gönderi sahibi ödül almaya uygun değil.']];
        }
        if (!function_exists('dg_adjust_user_balance')) {
            return ['errors' => ['D-Coin çekirdeği yüklenemedi.']];
        }

        return social_with_lock(static function () use ($admin, $postId, $amount, $reason): array {
            $fresh = social_get_post($postId, true);
            if (!$fresh || $fresh['status'] !== 'published' || !empty($fresh['award'])) {
                return ['errors' => ['Bu gönderi artık ödüle uygun değil veya zaten ödül aldı.']];
            }
            $balanceResult = dg_adjust_user_balance((int)$fresh['user_id'], $amount);
            if (empty($balanceResult['ok'])) {
                return ['errors' => [(string)($balanceResult['error'] ?? 'Bakiye güncellenemedi.')]];
            }
            try {
                $award = [
                    'amount' => $amount,
                    'reason' => $reason,
                    'awarded_by' => (int)$admin['id'],
                    'awarded_at' => time(),
                ];
                $fresh['award'] = $award;
                $fresh['updated_at'] = time();
                $fresh = social_normalize_post($fresh);
                social_write_json_atomic(social_post_file($postId), $fresh, false);
                social_index_upsert_locked(social_post_summary($fresh));
                social_notify_locked(
                    (int)$fresh['user_id'],
                    (int)$admin['id'],
                    'award',
                    $postId,
                    0,
                    'Tebrikler! “' . (string)$fresh['title'] . '” Editör Ödülü aldı ve hesabına +' . $amount . ' D-Coin eklendi.',
                    'gonderi.php?id=' . $postId
                );
                return ['errors' => [], 'award' => $fresh['award'], 'balance' => (int)($balanceResult['balance'] ?? 0)];
            } catch (Throwable $exception) {
                try {
                    dg_adjust_user_balance((int)$fresh['user_id'], -$amount);
                } catch (Throwable) {
                    // Geri alma denemesi başarısızsa asıl hata loglanır; tekrar denemek
                    // çift ödüle yol açabileceği için işlem burada durdurulur.
                }
                error_log('[social award] ' . $exception->getMessage());
                return ['errors' => ['Ödül kaydedilemedi; D-Coin işlemi geri alındı.']];
            }
        });
    }

    function social_user_award_stats(int $userId): array
    {
        social_ensure_storage();
        $count = 0;
        $total = 0;
        foreach (social_read_json(social_index_file('user-' . $userId), []) as $item) {
            if (!is_array($item) || ($item['status'] ?? '') !== 'published') continue;
            $award = social_normalize_award($item['award'] ?? null);
            if (!$award) continue;
            $count++;
            $total += (int)$award['amount'];
        }
        return ['count' => $count, 'total' => $total];
    }

    function social_list_posts(array $options = [], ?array $viewer = null): array
    {
        social_ensure_storage();
        $page = max(1, (int)($options['page'] ?? 1));
        $perPage = max(1, min(50, (int)($options['per_page'] ?? 15)));
        $topic = social_slugify((string)($options['topic'] ?? ''));
        $authorId = isset($options['author_id']) ? (int)$options['author_id'] : null;
        $feed = (string)($options['feed'] ?? 'latest');
        $query = trim((string)($options['q'] ?? ''));
        $sort = (string)($options['sort'] ?? 'activity');
        $includeModeration = !empty($options['include_moderation']) && !empty($viewer['is_admin']);
        $blocked = $includeModeration ? [] : social_blocked_ids($viewer);
        $followingIds = null;
        if ($feed === 'following' && $viewer) {
            $followingIds = social_follows((int)$viewer['id'])['following'];
            $followingIds[] = (int)$viewer['id'];
        }
        $savedIds = ($feed === 'saved' && $viewer) ? social_saved_ids((int)$viewer['id']) : null;
        $items = [];

        if ($feed === 'saved' && $viewer) {
            foreach (social_saved_ids((int)$viewer['id']) as $id) {
                $post = social_get_post((int)$id);
                if ($post) $items[] = social_post_summary($post);
            }
        } elseif ($query !== '') {
            $monthFiles = glob(social_dir('indexes') . '/month-*.json') ?: [];
            rsort($monthFiles);
            foreach ($monthFiles as $file) {
                foreach (social_read_json($file, []) as $item) if (is_array($item)) $items[] = $item;
            }
            if (!$items) $items = social_read_json(social_index_file('latest'), []);
        } elseif ($authorId !== null) {
            $items = social_read_json(social_index_file('user-' . $authorId), []);
        } elseif ($topic !== '') {
            $items = social_read_json(social_index_file('topic-' . $topic), []);
        } else {
            $items = social_read_json(social_index_file('latest'), []);
            if ($feed === 'following' && $viewer) {
                $following = social_follows((int)$viewer['id'])['following'];
                $following[] = (int)$viewer['id'];
                $items = array_values(array_filter($items, static fn($item): bool => is_array($item) && in_array((int)($item['user_id'] ?? -1), $following, true)));
            }
        }

        $foldQuery = social_fold($query);
        $isTagQuery = str_starts_with($query, '#');
        if ($isTagQuery) $foldQuery = social_slugify($query);
        $scannedBodies = 0;
        $filtered = [];
        foreach ($items as $item) {
            if (!is_array($item)) continue;
            $item = social_post_summary($item);
            if (!$includeModeration && !social_post_visible($item, $viewer, $blocked)) continue;
            if ($includeModeration && $item['status'] === 'deleted') continue;
            if (!empty($options['status']) && $item['status'] !== (string)$options['status']) continue;
            // Kaynak indeks hangi öncelikle seçilirse seçilsin filtreler kesişim olarak uygulanır.
            if ($topic !== '' && (string)$item['topic_slug'] !== $topic) continue;
            if ($authorId !== null && (int)$item['user_id'] !== $authorId) continue;
            if ($followingIds !== null && !in_array((int)$item['user_id'], $followingIds, true)) continue;
            if ($savedIds !== null && !in_array((int)$item['id'], $savedIds, true)) continue;
            if ($query !== '') {
                $matched = false;
                if ($isTagQuery) {
                    $matched = in_array($foldQuery, $item['tags'], true);
                    if (!$matched && $scannedBodies < 750) {
                        $scannedBodies++;
                        $full = social_get_post((int)$item['id']);
                        if ($full && preg_match_all('/#([A-Za-z0-9_\-\x{00C0}-\x{024F}]+)/u', (string)$full['body'], $tagMatches)) {
                            foreach ($tagMatches[1] as $bodyTag) {
                                if (social_slugify((string)$bodyTag) === $foldQuery) { $matched = true; break; }
                            }
                        }
                    }
                } else {
                    $pollLabels = [];
                    foreach ((array)(($item['poll'] ?? [])['options'] ?? []) as $option) {
                        if (is_array($option)) $pollLabels[] = (string)($option['label'] ?? '');
                    }
                    $haystack = social_fold(implode(' ', [
                        $item['title'],
                        $item['excerpt'],
                        $item['author_username'],
                        $item['author_display'],
                        implode(' ', $item['tags']),
                        implode(' ', array_map('strval', (array)$item['meta'])),
                        implode(' ', $pollLabels),
                        (string)(($item['award'] ?? [])['reason'] ?? ''),
                    ]));
                    $matched = str_contains($haystack, $foldQuery);
                    if (!$matched && $scannedBodies < 750) {
                        $scannedBodies++;
                        $full = social_get_post((int)$item['id']);
                        if ($full) $matched = str_contains(social_fold((string)$full['body']), $foldQuery);
                    }
                }
                if (!$matched) continue;
            }
            $filtered[] = $item;
        }

        if ($sort === 'new') {
            usort($filtered, static fn(array $a, array $b): int => [(int)!empty($b['pinned']), (int)$b['created_at'], (int)$b['id']] <=> [(int)!empty($a['pinned']), (int)$a['created_at'], (int)$a['id']]);
        } elseif ($sort === 'popular') {
            $score = static function (array $item): float {
                $ageHours = max(0, (time() - (int)$item['created_at']) / 3600);
                $engagement = (int)$item['gg_count'] * 4
                    + (int)$item['comment_count'] * 3
                    + (int)(($item['poll'] ?? [])['total_votes'] ?? 0) * 2
                    + (int)($item['rsvp_count'] ?? 0) * 2
                    + (!empty($item['award']) ? 6 : 0)
                    + 1;
                return $engagement / pow($ageHours + 2, 0.35);
            };
            usort($filtered, static fn(array $a, array $b): int => [$score($b), (int)$b['last_activity_at']] <=> [$score($a), (int)$a['last_activity_at']]);
        } else {
            $filtered = social_sort_summaries($filtered);
        }

        $total = count($filtered);
        $pages = max(1, (int)ceil($total / $perPage));
        $page = min($page, $pages);
        $slice = array_slice($filtered, ($page - 1) * $perPage, $perPage);
        return [
            'items' => array_values($slice),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => $pages,
        ];
    }

    function social_normalize_comment(array $comment): array
    {
        $created = (int)($comment['created_at'] ?? time());
        return [
            'schema_version' => 1,
            'id' => (int)($comment['id'] ?? 0),
            'post_id' => (int)($comment['post_id'] ?? 0),
            'parent_id' => (int)($comment['parent_id'] ?? 0),
            'user_id' => (int)($comment['user_id'] ?? 0),
            'author_username' => (string)($comment['author_username'] ?? ''),
            'author_display' => (string)($comment['author_display'] ?? ''),
            'body' => social_clean_text((string)($comment['body'] ?? ''), 1500),
            'status' => in_array(($comment['status'] ?? 'published'), ['published', 'pending', 'hidden', 'deleted'], true) ? (string)$comment['status'] : 'published',
            'notified' => !empty($comment['notified']),
            'created_at' => $created,
            'updated_at' => (int)($comment['updated_at'] ?? $created),
            'edited_at' => (int)($comment['edited_at'] ?? 0),
        ];
    }

    function social_comment_map(int $postId): array
    {
        $map = [];
        foreach (social_read_jsonl(social_comments_file($postId)) as $row) {
            $comment = social_normalize_comment($row);
            if ($comment['id'] > 0 && $comment['post_id'] === $postId) $map[$comment['id']] = $comment;
        }
        return $map;
    }

    function social_comment_summary(array $comment, array $post): array
    {
        return [
            'id' => (int)$comment['id'],
            'post_id' => (int)$comment['post_id'],
            'post_title' => (string)($post['title'] ?? ''),
            'parent_id' => (int)$comment['parent_id'],
            'user_id' => (int)$comment['user_id'],
            'author_username' => (string)$comment['author_username'],
            'author_display' => (string)$comment['author_display'],
            'excerpt' => social_excerpt((string)$comment['body'], 220),
            'status' => (string)$comment['status'],
            'created_at' => (int)$comment['created_at'],
            'updated_at' => (int)$comment['updated_at'],
        ];
    }

    function social_comment_index_upsert_locked(array $comment, array $post): void
    {
        $summary = social_comment_summary($comment, $post);
        $remove = $comment['status'] === 'deleted';
        $userFile = social_index_file('user-comments-' . (int)$comment['user_id']);
        $items = array_values(array_filter(social_read_json($userFile, []), static fn($item): bool => is_array($item) && (int)($item['id'] ?? 0) !== (int)$comment['id']));
        if (!$remove) $items[] = $summary;
        usort($items, static fn(array $a, array $b): int => [(int)$b['created_at'], (int)$b['id']] <=> [(int)$a['created_at'], (int)$a['id']]);
        social_write_json_atomic($userFile, array_slice($items, 0, 500), false);

        $modFile = social_index_file('moderation-comments');
        $items = array_values(array_filter(social_read_json($modFile, []), static fn($item): bool => is_array($item) && (int)($item['id'] ?? 0) !== (int)$comment['id']));
        if (in_array($comment['status'], ['pending', 'hidden'], true)) $items[] = $summary;
        usort($items, static fn(array $a, array $b): int => [(int)$b['created_at'], (int)$b['id']] <=> [(int)$a['created_at'], (int)$a['id']]);
        social_write_json_atomic($modFile, array_slice($items, 0, 1000), false);
    }

    function social_sync_profile_comment_counts_locked(int $userId): void
    {
        $profile = social_get_profile($userId);
        if (!$profile) return;
        $items = social_read_json(social_index_file('user-comments-' . $userId), []);
        $published = 0;
        foreach ($items as $item) if (($item['status'] ?? '') === 'published') $published++;
        $profile['comment_count'] = $published;
        $profile['approved_comments'] = $published;
        social_maybe_promote_locked($profile);
    }

    function social_refresh_post_comment_count_locked(array $post): array
    {
        $count = 0;
        $latest = (int)$post['created_at'];
        foreach (social_comment_map((int)$post['id']) as $comment) {
            if ($comment['status'] === 'published') {
                $count++;
                $latest = max($latest, (int)$comment['created_at']);
            }
        }
        $post['comment_count'] = $count;
        if ($count > 0) $post['last_activity_at'] = max((int)$post['last_activity_at'], $latest);
        $post = social_normalize_post($post);
        social_write_json_atomic(social_post_file((int)$post['id']), $post, false);
        social_index_upsert_locked(social_post_summary($post));
        return $post;
    }

    function social_get_comment(int $postId, int $commentId): ?array
    {
        social_ensure_storage();
        return social_comment_map($postId)[$commentId] ?? null;
    }

    function social_comment_visible(array $comment, ?array $viewer, array $blockedIds = []): bool
    {
        if (($comment['status'] ?? '') === 'deleted') return false;
        $isAdmin = !empty($viewer['is_admin']);
        if (!$isAdmin && in_array((int)($comment['user_id'] ?? -1), $blockedIds, true)) return false;
        if (($comment['status'] ?? '') === 'published') return true;
        if ($isAdmin) return true;
        return $viewer && (int)$viewer['id'] === (int)($comment['user_id'] ?? 0);
    }

    function social_comments(int $postId, ?array $viewer = null): array
    {
        social_ensure_storage();
        $blocked = social_blocked_ids($viewer);
        $all = [];
        foreach (social_comment_map($postId) as $comment) {
            if (social_comment_visible($comment, $viewer, $blocked)) $all[$comment['id']] = $comment;
        }
        $roots = [];
        $replies = [];
        foreach ($all as $comment) {
            $parentId = (int)$comment['parent_id'];
            if ($parentId > 0 && isset($all[$parentId]) && (int)$all[$parentId]['parent_id'] === 0) {
                $replies[$parentId][] = $comment;
            } else {
                $comment['parent_id'] = 0;
                $roots[] = $comment;
            }
        }
        $sort = static fn(array $a, array $b): int => [(int)$a['created_at'], (int)$a['id']] <=> [(int)$b['created_at'], (int)$b['id']];
        usort($roots, $sort);
        foreach ($roots as $i => $root) {
            $items = $replies[(int)$root['id']] ?? [];
            usort($items, $sort);
            $roots[$i]['replies'] = $items;
        }
        return $roots;
    }

    function social_notify_comment_locked(array $post, array $comment): void
    {
        $actorId = (int)$comment['user_id'];
        $actorName = (string)($comment['author_display'] ?: $comment['author_username']);
        $url = 'gonderi.php?id=' . (int)$post['id'] . '#yorum-' . (int)$comment['id'];
        $parentAuthorId = 0;
        if ((int)$comment['parent_id'] > 0) {
            $parent = social_get_comment((int)$post['id'], (int)$comment['parent_id']);
            if ($parent) {
                $parentAuthorId = (int)$parent['user_id'];
                social_notify_locked($parentAuthorId, $actorId, 'reply', (int)$post['id'], (int)$comment['id'], $actorName . ' yorumuna cevap verdi: “' . (string)$post['title'] . '”', $url);
            }
        }
        // Cevap verilen kişi gönderi sahibiyse aynı olay için ikinci bildirim üretme.
        if ((int)$post['user_id'] !== $parentAuthorId) {
            social_notify_locked((int)$post['user_id'], $actorId, 'comment', (int)$post['id'], (int)$comment['id'], $actorName . ' gönderine yorum yaptı: “' . (string)$post['title'] . '”', $url);
        }
        social_notify_mentions_locked($post, $actorId, (string)$comment['body'], (int)$comment['id']);
    }

    function social_create_comment(array $user, int $postId, array $input): array
    {
        social_ensure_storage();
        $post = social_get_post($postId);
        if (!$post) return ['errors' => ['Gönderi bulunamadı.'], 'id' => 0, 'status' => ''];
        if (!social_post_visible($post, $user, social_blocked_ids($user))) return ['errors' => ['Bu gönderiye yorum yapamazsın.'], 'id' => 0, 'status' => ''];
        if (!empty($post['locked']) && empty($user['is_admin'])) return ['errors' => ['Bu konu yorumlara kilitlenmiş.'], 'id' => 0, 'status' => ''];
        $body = social_clean_text((string)($input['body'] ?? ''), 1500);
        if (social_text_len($body) < 1) return ['errors' => ['Yorum boş olamaz.'], 'id' => 0, 'status' => ''];
        $parentId = (int)($input['parent_id'] ?? 0);
        if ($parentId > 0) {
            $parent = social_get_comment($postId, $parentId);
            if (!$parent || !social_comment_visible($parent, $user, social_blocked_ids($user))) {
                return ['errors' => ['Cevap verilecek yorum bulunamadı.'], 'id' => 0, 'status' => ''];
            }
            if ((int)$parent['parent_id'] > 0) $parentId = (int)$parent['parent_id'];
        }
        $profile = social_ensure_profile($user);
        return social_with_lock(static function () use ($user, $profile, $post, $body, $parentId): array {
            $userId = (int)$user['id'];
            if (empty($user['is_admin']) && !social_rate_limit_locked($userId, 'comments', [[3600, 30], [86400, 150]])) {
                return ['errors' => ['Çok hızlı yorum yapıyorsun. Biraz bekleyip tekrar dene.'], 'id' => 0, 'status' => ''];
            }
            $trusted = social_is_trusted($profile, $user) || (int)$profile['approved_comments'] >= 5;
            $status = (!empty($user['is_admin']) || ($trusted && social_url_count($body) < 3)) ? 'published' : 'pending';
            $now = time();
            $comment = social_normalize_comment([
                'id' => social_next_id_locked('comments'),
                'post_id' => (int)$post['id'],
                'parent_id' => $parentId,
                'user_id' => $userId,
                'author_username' => (string)$profile['username'],
                'author_display' => (string)$profile['display_name'],
                'body' => $body,
                'status' => $status,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            social_append_jsonl(social_comments_file((int)$post['id']), $comment);
            social_comment_index_upsert_locked($comment, $post);
            social_sync_profile_comment_counts_locked($userId);
            $freshPost = social_refresh_post_comment_count_locked(social_get_post((int)$post['id'], true) ?: $post);
            if ($status === 'published') {
                social_notify_comment_locked($freshPost, $comment);
                $comment['notified'] = true;
                $comment['updated_at'] = time();
                social_append_jsonl(social_comments_file((int)$post['id']), $comment);
                social_comment_index_upsert_locked($comment, $freshPost);
            }
            return ['errors' => [], 'id' => (int)$comment['id'], 'status' => $status];
        });
    }

    function social_edit_comment(array $actor, int $postId, int $commentId, array $input): array
    {
        $comment = social_get_comment($postId, $commentId);
        $post = social_get_post($postId, true);
        if (!$comment || !$post || $comment['status'] === 'deleted') return ['errors' => ['Yorum bulunamadı.'], 'status' => ''];
        $isAdmin = !empty($actor['is_admin']);
        if (!$isAdmin && (int)$actor['id'] !== (int)$comment['user_id']) return ['errors' => ['Bu yorumu düzenleyemezsin.'], 'status' => (string)$comment['status']];
        $body = social_clean_text((string)($input['body'] ?? ''), 1500);
        if (social_text_len($body) < 1) return ['errors' => ['Yorum boş olamaz.'], 'status' => (string)$comment['status']];
        return social_with_lock(static function () use ($comment, $post, $body, $isAdmin, $actor): array {
            $status = (string)$comment['status'];
            if (!$isAdmin && $status === 'published') {
                $profile = social_get_profile((int)$comment['user_id']);
                if ($profile && !social_is_trusted($profile, $actor) && social_url_count($body) > 0) $status = 'pending';
            }
            $comment['body'] = $body;
            $comment['status'] = $status;
            $comment['updated_at'] = time();
            $comment['edited_at'] = time();
            $comment = social_normalize_comment($comment);
            social_append_jsonl(social_comments_file((int)$post['id']), $comment);
            social_comment_index_upsert_locked($comment, $post);
            social_sync_profile_comment_counts_locked((int)$comment['user_id']);
            social_refresh_post_comment_count_locked(social_get_post((int)$post['id'], true) ?: $post);
            return ['errors' => [], 'status' => $status];
        });
    }

    function social_set_comment_status(array $actor, int $postId, int $commentId, string $status): array
    {
        if (!in_array($status, ['published', 'pending', 'hidden', 'deleted'], true)) return ['errors' => ['Geçersiz yorum durumu.']];
        $comment = social_get_comment($postId, $commentId);
        $post = social_get_post($postId, true);
        if (!$comment || !$post) return ['errors' => ['Yorum bulunamadı.']];
        $isAdmin = !empty($actor['is_admin']);
        $isAuthor = (int)$actor['id'] === (int)$comment['user_id'];
        if (!$isAdmin && !($isAuthor && $status === 'deleted')) return ['errors' => ['Bu işlem için yetkin yok.']];
        return social_with_lock(static function () use ($comment, $post, $status): array {
            $wasPublished = $comment['status'] === 'published';
            $comment['status'] = $status;
            $comment['updated_at'] = time();
            $comment = social_normalize_comment($comment);
            social_append_jsonl(social_comments_file((int)$post['id']), $comment);
            social_comment_index_upsert_locked($comment, $post);
            social_sync_profile_comment_counts_locked((int)$comment['user_id']);
            $freshPost = social_refresh_post_comment_count_locked(social_get_post((int)$post['id'], true) ?: $post);
            if (!$wasPublished && $status === 'published' && empty($comment['notified'])) {
                social_notify_comment_locked($freshPost, $comment);
                $comment['notified'] = true;
                $comment['updated_at'] = time();
                social_append_jsonl(social_comments_file((int)$post['id']), $comment);
                social_comment_index_upsert_locked($comment, $freshPost);
            }
            return ['errors' => [], 'status' => $status];
        });
    }

    function social_reactions(int $postId): array
    {
        social_ensure_storage();
        $data = social_read_json(social_reactions_file($postId), ['gg' => [], 'poll_votes' => [], 'rsvp' => []]);
        $gg = array_values(array_unique(array_map('intval', (array)($data['gg'] ?? []))));
        $pollVotes = [];
        foreach ((array)($data['poll_votes'] ?? []) as $userId => $optionId) {
            $userId = (int)$userId;
            $optionId = strtolower(trim((string)$optionId));
            if ($userId > 0 && preg_match('/^o[1-5]$/', $optionId)) {
                $pollVotes[(string)$userId] = $optionId;
            }
        }
        $rsvp = array_values(array_unique(array_filter(
            array_map('intval', (array)($data['rsvp'] ?? [])),
            static fn(int $id): bool => $id >= 0
        )));
        return ['gg' => $gg, 'poll_votes' => $pollVotes, 'rsvp' => $rsvp];
    }

    function social_toggle_gg(array $user, int $postId): array
    {
        $post = social_get_post($postId);
        if (!$post || !social_post_visible($post, $user, social_blocked_ids($user))) return ['errors' => ['Gönderi bulunamadı.'], 'active' => false, 'count' => 0];
        if ((int)$user['id'] === (int)$post['user_id']) return ['errors' => ['Kendi gönderine GG veremezsin.'], 'active' => false, 'count' => (int)$post['gg_count']];
        social_ensure_profile($user);
        return social_with_lock(static function () use ($user, $post): array {
            $userId = (int)$user['id'];
            $reactions = social_reactions((int)$post['id']);
            $active = in_array($userId, $reactions['gg'], true);
            $reactions['gg'] = $active
                ? array_values(array_filter($reactions['gg'], static fn(int $id): bool => $id !== $userId))
                : array_merge($reactions['gg'], [$userId]);
            social_write_json_atomic(social_reactions_file((int)$post['id']), $reactions, false);
            $fresh = social_get_post((int)$post['id'], true) ?: $post;
            $fresh['gg_count'] = count($reactions['gg']);
            $fresh = social_normalize_post($fresh);
            social_write_json_atomic(social_post_file((int)$post['id']), $fresh, false);
            social_index_upsert_locked(social_post_summary($fresh));
            return ['errors' => [], 'active' => !$active, 'count' => count($reactions['gg'])];
        });
    }

    function social_saved_ids(int $userId): array
    {
        social_ensure_storage();
        $data = social_read_json(social_saves_file($userId), []);
        return array_values(array_unique(array_map('intval', (array)($data['post_ids'] ?? []))));
    }

    function social_toggle_save(array $user, int $postId): array
    {
        $post = social_get_post($postId);
        if (!$post || !social_post_visible($post, $user, social_blocked_ids($user))) return ['errors' => ['Gönderi bulunamadı.'], 'active' => false];
        return social_with_lock(static function () use ($user, $postId): array {
            $ids = social_saved_ids((int)$user['id']);
            $active = in_array($postId, $ids, true);
            $ids = $active ? array_values(array_filter($ids, static fn(int $id): bool => $id !== $postId)) : array_merge([$postId], $ids);
            social_write_json_atomic(social_saves_file((int)$user['id']), ['post_ids' => array_slice($ids, 0, 500), 'updated_at' => time()], false);
            return ['errors' => [], 'active' => !$active];
        });
    }

    function social_vote_poll(array $user, int $postId, string $optionId): array
    {
        $optionId = strtolower(trim($optionId));
        $post = social_get_post($postId);
        if (!$post || !social_post_visible($post, $user, social_blocked_ids($user))) {
            return ['errors' => ['Gönderi bulunamadı.'], 'poll' => null];
        }
        $poll = social_normalize_poll($post['poll'] ?? null);
        if (!$poll) return ['errors' => ['Bu gönderide anket yok.'], 'poll' => null];
        if (($post['status'] ?? '') !== 'published') return ['errors' => ['Anket yayına girdikten sonra oy kullanılabilir.'], 'poll' => social_poll_payload($post, $user, social_reactions($postId))];
        if (social_poll_closed($poll)) return ['errors' => ['Bu anket kapandı.'], 'poll' => social_poll_payload($post, $user, social_reactions($postId))];
        $valid = false;
        foreach ($poll['options'] as $option) if ((string)$option['id'] === $optionId) $valid = true;
        if (!$valid) return ['errors' => ['Geçerli bir anket seçeneği seç.'], 'poll' => social_poll_payload($post, $user, social_reactions($postId))];
        social_ensure_profile($user);

        return social_with_lock(static function () use ($user, $postId, $optionId): array {
            $fresh = social_get_post($postId, true);
            if (!$fresh || ($fresh['status'] ?? '') !== 'published') return ['errors' => ['Anket şu anda oy kullanmaya kapalı.'], 'poll' => null];
            $freshPoll = social_normalize_poll($fresh['poll'] ?? null);
            $reactions = social_reactions($postId);
            if (!$freshPoll || social_poll_closed($freshPoll)) {
                return ['errors' => ['Bu anket kapandı.'], 'poll' => $freshPoll ? social_poll_payload($fresh, $user, $reactions) : null];
            }
            $valid = false;
            foreach ($freshPoll['options'] as $option) if ((string)$option['id'] === $optionId) $valid = true;
            if (!$valid) return ['errors' => ['Geçerli bir anket seçeneği seç.'], 'poll' => social_poll_payload($fresh, $user, $reactions)];

            $userKey = (string)(int)$user['id'];
            $previous = (string)($reactions['poll_votes'][$userKey] ?? '');
            if ($previous !== $optionId) {
                if (empty($user['is_admin']) && !social_rate_limit_locked((int)$user['id'], 'poll_votes', [[3600, 60], [86400, 300]])) {
                    return ['errors' => ['Çok sık oy değiştirdin. Biraz bekleyip tekrar dene.'], 'poll' => social_poll_payload($fresh, $user, $reactions)];
                }
                $reactions['poll_votes'][$userKey] = $optionId;
                social_write_json_atomic(social_reactions_file($postId), $reactions, false);
                $fresh['poll'] = social_poll_with_counts($freshPoll, $reactions);
                $fresh = social_normalize_post($fresh);
                social_write_json_atomic(social_post_file($postId), $fresh, false);
                social_index_upsert_locked(social_post_summary($fresh));
            }
            return ['errors' => [], 'poll' => social_poll_payload($fresh, $user, $reactions), 'selected' => $optionId];
        });
    }

    function social_event_attendee_ids(int $postId): array
    {
        return social_reactions($postId)['rsvp'];
    }

    function social_event_attendees(int $postId, ?array $viewer = null, int $limit = 12): array
    {
        $blocked = !empty($viewer['is_admin']) ? [] : social_blocked_ids($viewer);
        $ids = array_values(array_filter(
            social_event_attendee_ids($postId),
            static fn(int $id): bool => !in_array($id, $blocked, true)
        ));
        $profiles = social_profiles_by_ids(array_slice($ids, 0, max(1, $limit)));
        $out = [];
        foreach (array_slice($ids, 0, max(1, $limit)) as $id) {
            if (isset($profiles[$id])) $out[] = $profiles[$id];
        }
        return $out;
    }

    function social_toggle_event_rsvp(array $user, int $postId): array
    {
        $post = social_get_post($postId);
        if (!$post || !social_post_visible($post, $user, social_blocked_ids($user))) {
            return ['errors' => ['Etkinlik bulunamadı.'], 'active' => false, 'count' => 0];
        }
        if (!social_is_event_post($post)) return ['errors' => ['Bu gönderi bir etkinlik değil.'], 'active' => false, 'count' => 0];
        if (($post['status'] ?? '') !== 'published') return ['errors' => ['Etkinlik yayına girdikten sonra katılım açılır.'], 'active' => false, 'count' => (int)$post['rsvp_count']];
        social_ensure_profile($user);

        return social_with_lock(static function () use ($user, $postId): array {
            $fresh = social_get_post($postId, true);
            if (!$fresh || !social_is_event_post($fresh) || ($fresh['status'] ?? '') !== 'published') {
                return ['errors' => ['Etkinlik şu anda katılıma kapalı.'], 'active' => false, 'count' => 0];
            }
            $userId = (int)$user['id'];
            $reactions = social_reactions($postId);
            $active = in_array($userId, $reactions['rsvp'], true);
            $capacity = (int)(($fresh['meta'] ?? [])['event_capacity'] ?? 0);
            if (!$active) {
                if (!social_event_is_open($fresh)) {
                    return ['errors' => ['Bu etkinliğin katılım süresi doldu.'], 'active' => false, 'count' => count($reactions['rsvp'])];
                }
                if ($capacity > 0 && count($reactions['rsvp']) >= $capacity) {
                    return ['errors' => ['Etkinlik kapasitesi doldu.'], 'active' => false, 'count' => count($reactions['rsvp'])];
                }
                if (empty($user['is_admin']) && !social_rate_limit_locked($userId, 'rsvps', [[3600, 30], [86400, 100]])) {
                    return ['errors' => ['Çok sık katılım değiştirdin. Biraz bekleyip tekrar dene.'], 'active' => false, 'count' => count($reactions['rsvp'])];
                }
                $reactions['rsvp'][] = $userId;
            } else {
                $reactions['rsvp'] = array_values(array_filter($reactions['rsvp'], static fn(int $id): bool => $id !== $userId));
            }
            social_write_json_atomic(social_reactions_file($postId), $reactions, false);
            $fresh['rsvp_count'] = count($reactions['rsvp']);
            $fresh = social_normalize_post($fresh);
            social_write_json_atomic(social_post_file($postId), $fresh, false);
            social_index_upsert_locked(social_post_summary($fresh));
            if (!$active && (int)$fresh['user_id'] > 0 && (int)$fresh['user_id'] !== $userId) {
                $profile = social_get_profile($userId, $user);
                $name = (string)($profile['display_name'] ?? $user['username'] ?? 'Bir oyuncu');
                social_notify_locked((int)$fresh['user_id'], $userId, 'event_rsvp', $postId, 0, $name . ' etkinliğine katılıyor: “' . (string)$fresh['title'] . '”', 'gonderi.php?id=' . $postId . '#etkinlik');
            }
            return [
                'errors' => [],
                'active' => !$active,
                'count' => count($reactions['rsvp']),
                'capacity' => $capacity,
                'event_at' => (int)(($fresh['meta'] ?? [])['event_at'] ?? 0),
            ];
        });
    }

    function social_post_states(array $postIds, ?array $viewer): array
    {
        $states = [];
        $saved = $viewer ? array_fill_keys(social_saved_ids((int)$viewer['id']), true) : [];
        foreach ($postIds as $id) {
            $id = (int)$id;
            $reactions = $viewer ? social_reactions($id) : ['gg' => [], 'poll_votes' => [], 'rsvp' => []];
            $viewerId = $viewer ? (int)$viewer['id'] : 0;
            $states[$id] = [
                'gg' => $viewer ? in_array($viewerId, $reactions['gg'], true) : false,
                'saved' => isset($saved[$id]),
                'poll_vote' => $viewer ? (string)($reactions['poll_votes'][(string)$viewerId] ?? '') : '',
                'rsvp' => $viewer ? in_array($viewerId, $reactions['rsvp'], true) : false,
            ];
        }
        return $states;
    }

    function social_user_comments(int $userId, ?array $viewer = null, int $limit = 20): array
    {
        social_ensure_storage();
        $blocked = social_blocked_ids($viewer);
        $out = [];
        foreach (social_read_json(social_index_file('user-comments-' . $userId), []) as $item) {
            if (!is_array($item) || ($item['status'] ?? '') === 'deleted') continue;
            $isOwner = $viewer && (int)$viewer['id'] === $userId;
            $isAdmin = !empty($viewer['is_admin']);
            $post = social_get_post((int)($item['post_id'] ?? 0), $isAdmin || $isOwner);
            if (!$post || ($post['status'] ?? '') === 'deleted') continue;
            if (!$isOwner && !$isAdmin) {
                if (($item['status'] ?? '') !== 'published') continue;
                if (in_array($userId, $blocked, true)) continue;
                // Gizlenmiş/engellenmiş bir gönderinin başlığını ve yorum özetini profilden sızdırma.
                if (!social_post_visible($post, $viewer, $blocked)) continue;
            }
            $out[] = $item;
            if (count($out) >= $limit) break;
        }
        return $out;
    }

    function social_moderation_comments(int $limit = 100): array
    {
        social_ensure_storage();
        return array_slice(array_values(array_filter(social_read_json(social_index_file('moderation-comments'), []), 'is_array')), 0, $limit);
    }

    function social_normalize_report(array $report): array
    {
        $created = (int)($report['created_at'] ?? time());
        return [
            'id' => (int)($report['id'] ?? 0),
            'reporter_id' => (int)($report['reporter_id'] ?? 0),
            'reporter_username' => (string)($report['reporter_username'] ?? ''),
            'target_type' => in_array(($report['target_type'] ?? 'post'), ['post', 'comment'], true) ? (string)$report['target_type'] : 'post',
            'target_id' => (int)($report['target_id'] ?? 0),
            'post_id' => (int)($report['post_id'] ?? 0),
            'target_author_id' => (int)($report['target_author_id'] ?? 0),
            'target_title' => social_clean_text((string)($report['target_title'] ?? ''), 180),
            'target_excerpt' => social_excerpt((string)($report['target_excerpt'] ?? ''), 260),
            'reason' => social_clean_text((string)($report['reason'] ?? 'Diğer'), 60),
            'details' => social_clean_text((string)($report['details'] ?? ''), 600),
            'status' => in_array(($report['status'] ?? 'open'), ['open', 'resolved', 'dismissed'], true) ? (string)$report['status'] : 'open',
            'created_at' => $created,
            'updated_at' => (int)($report['updated_at'] ?? $created),
        ];
    }

    function social_reports(): array
    {
        social_ensure_storage();
        $map = [];
        foreach (glob(social_dir('reports') . '/*.jsonl') ?: [] as $file) {
            foreach (social_read_jsonl($file) as $row) {
                $report = social_normalize_report($row);
                if ($report['id'] > 0) $map[$report['id']] = $report;
            }
        }
        $items = array_values($map);
        usort($items, static fn(array $a, array $b): int => [(int)($b['status'] === 'open'), (int)$b['created_at'], (int)$b['id']] <=> [(int)($a['status'] === 'open'), (int)$a['created_at'], (int)$a['id']]);
        return $items;
    }

    function social_create_report(array $user, array $input): array
    {
        social_ensure_storage();
        $type = (string)($input['target_type'] ?? 'post');
        $postId = (int)($input['post_id'] ?? 0);
        $targetId = (int)($input['target_id'] ?? ($type === 'post' ? $postId : 0));
        $post = social_get_post($postId, true);
        if (!$post) return ['errors' => ['Şikâyet edilecek içerik bulunamadı.']];
        $targetAuthor = (int)$post['user_id'];
        $targetTitle = (string)$post['title'];
        $targetExcerpt = (string)$post['excerpt'];
        if ($type === 'comment') {
            $comment = social_get_comment($postId, $targetId);
            if (!$comment) return ['errors' => ['Şikâyet edilecek yorum bulunamadı.']];
            $targetAuthor = (int)$comment['user_id'];
            $targetExcerpt = (string)$comment['body'];
        } elseif ($type !== 'post') {
            return ['errors' => ['Geçersiz şikâyet türü.']];
        }
        if ($targetAuthor === (int)$user['id']) return ['errors' => ['Kendi içeriğini şikâyet etmene gerek yok; düzenleyebilir veya silebilirsin.']];
        $reason = social_clean_text((string)($input['reason'] ?? 'Diğer'), 60);
        if (!in_array($reason, ['Spam', 'Hakaret / Taciz', 'Dolandırıcılık', 'Uygunsuz içerik', 'Telif', 'Diğer'], true)) $reason = 'Diğer';
        $details = social_clean_text((string)($input['details'] ?? ''), 600);
        return social_with_lock(static function () use ($user, $type, $postId, $targetId, $targetAuthor, $targetTitle, $targetExcerpt, $reason, $details): array {
            if (empty($user['is_admin']) && !social_rate_limit_locked((int)$user['id'], 'reports', [[3600, 10], [86400, 30]])) {
                return ['errors' => ['Çok fazla şikâyet gönderdin. Biraz bekleyip tekrar dene.']];
            }
            foreach (social_reports() as $report) {
                if ($report['status'] === 'open' && (int)$report['reporter_id'] === (int)$user['id'] && $report['target_type'] === $type && (int)$report['target_id'] === $targetId) {
                    return ['errors' => ['Bu içerik için zaten açık bir şikâyetin var.']];
                }
            }
            $now = time();
            $report = social_normalize_report([
                'id' => social_next_id_locked('reports'),
                'reporter_id' => (int)$user['id'],
                'reporter_username' => (string)$user['username'],
                'target_type' => $type,
                'target_id' => $targetId,
                'post_id' => $postId,
                'target_author_id' => $targetAuthor,
                'target_title' => $targetTitle,
                'target_excerpt' => $targetExcerpt,
                'reason' => $reason,
                'details' => $details,
                'status' => 'open',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            social_append_jsonl(social_reports_file($now), $report);
            return ['errors' => [], 'id' => (int)$report['id']];
        });
    }

    function social_set_report_status(array $admin, int $reportId, string $status): array
    {
        if (empty($admin['is_admin']) || !in_array($status, ['open', 'resolved', 'dismissed'], true)) return ['errors' => ['Bu işlem için yönetici yetkisi gerekir.']];
        $found = null;
        foreach (social_reports() as $report) if ((int)$report['id'] === $reportId) $found = $report;
        if (!$found) return ['errors' => ['Şikâyet bulunamadı.']];
        return social_with_lock(static function () use ($found, $status): array {
            $found['status'] = $status;
            $found['updated_at'] = time();
            social_append_jsonl(social_reports_file(time()), social_normalize_report($found));
            return ['errors' => []];
        });
    }

    function social_save_topic(array $admin, array $input): array
    {
        if (empty($admin['is_admin'])) return ['errors' => ['Bu işlem için yönetici yetkisi gerekir.'], 'slug' => ''];
        social_ensure_storage();
        return social_with_lock(static function () use ($input): array {
            $existingSlug = social_slugify((string)($input['edit_slug'] ?? ''));
            $topics = [];
            foreach (social_read_json(social_topics_file(), social_default_topics()) as $i => $topic) {
                if (is_array($topic)) $topics[] = social_normalize_topic($topic, $i + 1);
            }
            $index = null;
            foreach ($topics as $i => $topic) if ($topic['slug'] === $existingSlug) $index = $i;
            $name = social_clean_text((string)($input['name'] ?? ''), 60);
            if (social_text_len($name) < 2) return ['errors' => ['Konu adı en az 2 karakter olmalı.'], 'slug' => $existingSlug];
            $slug = $existingSlug !== '' ? $existingSlug : social_slugify((string)($input['slug'] ?? '') ?: $name);
            if ($slug === '') return ['errors' => ['Konu adresi oluşturulamadı.'], 'slug' => ''];
            foreach ($topics as $i => $topic) {
                if ($i !== $index && ($topic['slug'] === $slug || social_fold($topic['name']) === social_fold($name))) {
                    return ['errors' => ['Bu konu adı veya adresi kullanılıyor.'], 'slug' => $existingSlug];
                }
            }
            $topic = social_normalize_topic([
                'slug' => $slug,
                'name' => $name,
                'description' => (string)($input['description'] ?? ''),
                'icon' => (string)($input['icon'] ?? '💬'),
                'official_only' => !empty($input['official_only']),
                'active' => !empty($input['active']),
                'order' => (int)($input['order'] ?? ($index !== null ? $topics[$index]['order'] : count($topics) + 1)),
            ], count($topics) + 1);
            if ($index !== null) $topics[$index] = $topic; else $topics[] = $topic;
            usort($topics, static fn(array $a, array $b): int => [$a['order'], social_fold($a['name'])] <=> [$b['order'], social_fold($b['name'])]);
            social_write_json_atomic(social_topics_file(), $topics);
            if (!is_file(social_index_file('topic-' . $slug))) social_write_json_atomic(social_index_file('topic-' . $slug), [], false);
            return ['errors' => [], 'slug' => $slug];
        });
    }

    function social_delete_topic(array $admin, string $slug): array
    {
        if (empty($admin['is_admin'])) return ['errors' => ['Bu işlem için yönetici yetkisi gerekir.']];
        $slug = social_slugify($slug);
        if ($slug === 'genel-sohbet') return ['errors' => ['Genel Sohbet konusu silinemez.']];
        return social_with_lock(static function () use ($slug): array {
            $items = social_read_json(social_index_file('topic-' . $slug), []);
            if ($items) return ['errors' => ['İçinde gönderi bulunan konu silinemez; önce pasife alabilirsin.']];
            $topics = [];
            $found = false;
            foreach (social_read_json(social_topics_file(), social_default_topics()) as $i => $topic) {
                if (!is_array($topic)) continue;
                $topic = social_normalize_topic($topic, $i + 1);
                if ($topic['slug'] === $slug) { $found = true; continue; }
                $topics[] = $topic;
            }
            if (!$found) return ['errors' => ['Konu bulunamadı.']];
            social_write_json_atomic(social_topics_file(), $topics);
            $index = social_index_file('topic-' . $slug);
            if (is_file($index)) @unlink($index);
            return ['errors' => []];
        });
    }

    function social_search_profiles(string $query, ?array $viewer = null, int $limit = 8): array
    {
        $query = social_fold(trim($query));
        if ($query === '') return [];
        $blocked = social_blocked_ids($viewer);
        $candidates = [];
        $official = social_official_profile();
        $candidates[0] = $official;
        if (function_exists('dg_all')) {
            foreach (dg_all('users') as $user) {
                $public = function_exists('dg_public_user') ? dg_public_user($user) : $user;
                $profile = social_get_profile((int)$public['id'], $public);
                if ($profile) $candidates[(int)$public['id']] = $profile;
            }
        }
        $out = [];
        foreach ($candidates as $id => $profile) {
            if (in_array((int)$id, $blocked, true)) continue;
            $haystack = social_fold(implode(' ', [$profile['username'], $profile['display_name'], $profile['bio'], implode(' ', $profile['favorite_games'])]));
            if (str_contains($haystack, $query)) $out[] = $profile;
            if (count($out) >= $limit) break;
        }
        return $out;
    }

    function social_trending_tags(int $limit = 10): array
    {
        $counts = [];
        foreach (social_read_json(social_index_file('latest'), []) as $item) {
            if (!is_array($item) || ($item['status'] ?? '') !== 'published') continue;
            foreach ((array)($item['tags'] ?? []) as $tag) {
                $tag = social_slugify((string)$tag);
                if ($tag !== '') $counts[$tag] = ($counts[$tag] ?? 0) + 1;
            }
        }
        arsort($counts);
        return array_slice($counts, 0, $limit, true);
    }

    function social_suggested_profiles(?array $viewer = null, int $limit = 4): array
    {
        social_ensure_storage();
        $viewerId = $viewer ? (int)$viewer['id'] : -1;
        $blocked = social_blocked_ids($viewer);
        $following = $viewer ? social_follows($viewerId)['following'] : [];
        $candidates = [];
        foreach ((array)(social_users_index()['summaries'] ?? []) as $summary) {
            if (!is_array($summary)) continue;
            $id = (int)($summary['user_id'] ?? -1);
            if ($id === $viewerId || in_array($id, $blocked, true) || in_array($id, $following, true)) continue;
            $profile = social_get_profile($id);
            if ($profile) $candidates[] = $profile;
        }
        usort($candidates, static function (array $a, array $b): int {
            $official = (int)!empty($b['is_official']) <=> (int)!empty($a['is_official']);
            if ($official !== 0) return $official;
            return [
                (int)($b['trust_level'] ?? 0),
                (int)($b['follower_count'] ?? 0),
                (int)($b['post_count'] ?? 0),
                (int)($b['comment_count'] ?? 0),
            ] <=> [
                (int)($a['trust_level'] ?? 0),
                (int)($a['follower_count'] ?? 0),
                (int)($a['post_count'] ?? 0),
                (int)($a['comment_count'] ?? 0),
            ];
        });
        return array_slice($candidates, 0, max(1, $limit));
    }

    function social_onboarding_status(array $user, ?array $profile = null): array
    {
        $userId = (int)($user['id'] ?? 0);
        if ($userId <= 0) {
            return ['steps' => [], 'completed' => 0, 'total' => 0, 'percent' => 0, 'complete' => true, 'should_show' => false];
        }
        $profile = $profile ?: social_ensure_profile($user);
        $profileComplete = trim((string)($profile['bio'] ?? '')) !== ''
            && (!empty($profile['platforms']) || !empty($profile['favorite_games']));
        $followsOfficial = in_array(0, social_follows($userId)['following'], true);
        $ownPosts = social_list_posts(['author_id' => $userId, 'per_page' => 1, 'sort' => 'new'], $user);
        $hasPost = (int)($ownPosts['total'] ?? 0) > 0;
        $hasComment = !empty(social_user_comments($userId, $user, 1));
        $steps = [
            [
                'key' => 'profile',
                'label' => 'Profilini tamamla',
                'description' => 'Kısa bir biyografi ile platform veya favori oyunlarını ekle.',
                'complete' => $profileComplete,
                'url' => 'profil.php?kullanici=' . rawurlencode((string)$profile['username']) . '#profil-duzenle',
                'action' => 'Profili düzenle',
            ],
            [
                'key' => 'official',
                'label' => 'DeviceGame’i takip et',
                'description' => 'Duyurular ve topluluk etkinliklerini kaçırma.',
                'complete' => $followsOfficial,
                'url' => 'profil.php?kullanici=devicegame',
                'action' => 'Resmî hesabı aç',
            ],
            [
                'key' => 'intro',
                'label' => 'Kendini tanıt',
                'description' => 'Hangi platformda, son zamanlarda hangi oyunu oynuyorsun?',
                'complete' => $hasPost,
                'url' => 'yeni.php?konu=genel-sohbet&sablon=tanisma',
                'action' => 'Tanışma gönderisi',
            ],
            [
                'key' => 'comment',
                'label' => 'İlk yorumunu yap',
                'description' => 'Bir tartışmaya katılıp toplulukla tanış.',
                'complete' => $hasComment,
                'url' => 'index.php#akis',
                'action' => 'Akışa git',
            ],
        ];
        $completed = count(array_filter($steps, static fn(array $step): bool => !empty($step['complete'])));
        $complete = $completed === count($steps);
        $isRecent = (int)($profile['joined_at'] ?? time()) >= time() - (90 * 86400);
        $lowActivity = ((int)($profile['approved_posts'] ?? 0) + (int)($profile['approved_comments'] ?? 0)) < 3;
        return [
            'steps' => $steps,
            'completed' => $completed,
            'total' => count($steps),
            'percent' => count($steps) ? (int)round(($completed * 100) / count($steps)) : 100,
            'complete' => $complete,
            'should_show' => !$complete && empty($user['is_admin']) && ($isRecent || $lowActivity),
        ];
    }

    function social_events(?array $viewer = null, int $limit = 100): array
    {
        $items = [];
        $page = 1;
        $pages = 1;
        do {
            $result = social_list_posts([
                'topic' => 'etkinlikler',
                'per_page' => 50,
                'page' => $page,
                'sort' => 'new',
            ], $viewer);
            foreach ((array)$result['items'] as $item) {
                if (is_array($item) && social_is_event_post($item)) $items[] = $item;
            }
            $pages = (int)($result['pages'] ?? 1);
            $page++;
        } while (count($items) < $limit && $page <= $pages && $page <= 4);

        $upcoming = [];
        $past = [];
        foreach ($items as $item) {
            if (social_event_is_open($item)) $upcoming[] = $item;
            else $past[] = $item;
        }
        usort($upcoming, static fn(array $a, array $b): int => [
            (int)(($a['meta'] ?? [])['event_at'] ?? 0), (int)($a['id'] ?? 0),
        ] <=> [
            (int)(($b['meta'] ?? [])['event_at'] ?? 0), (int)($b['id'] ?? 0),
        ]);
        usort($past, static fn(array $a, array $b): int => [
            (int)(($b['meta'] ?? [])['event_at'] ?? 0), (int)($b['id'] ?? 0),
        ] <=> [
            (int)(($a['meta'] ?? [])['event_at'] ?? 0), (int)($a['id'] ?? 0),
        ]);
        return [
            'upcoming' => array_slice($upcoming, 0, max(1, $limit)),
            'past' => array_slice($past, 0, max(1, $limit)),
        ];
    }

    function social_upcoming_events(?array $viewer = null, int $limit = 3): array
    {
        return array_slice(social_events($viewer, max(10, $limit))['upcoming'], 0, max(1, $limit));
    }

    function social_weekly_highlights(?array $viewer = null, int $limit = 3): array
    {
        social_ensure_storage();
        $blocked = social_blocked_ids($viewer);
        $since = time() - (7 * 86400);
        $items = [];
        foreach (social_read_json(social_index_file('latest'), []) as $item) {
            if (!is_array($item)) continue;
            $item = social_post_summary($item);
            if (!social_post_visible($item, $viewer, $blocked)) continue;
            if ((int)$item['last_activity_at'] < $since && (int)$item['created_at'] < $since) continue;
            $score = (int)$item['gg_count'] * 4
                + (int)$item['comment_count'] * 3
                + (int)(($item['poll'] ?? [])['total_votes'] ?? 0) * 2
                + (int)$item['rsvp_count'] * 2
                + (!empty($item['award']) ? 6 : 0);
            if ($score <= 0) continue;
            $item['_highlight_score'] = $score;
            $items[] = $item;
        }
        usort($items, static fn(array $a, array $b): int => [
            (int)($b['_highlight_score'] ?? 0), (int)($b['last_activity_at'] ?? 0), (int)($b['id'] ?? 0),
        ] <=> [
            (int)($a['_highlight_score'] ?? 0), (int)($a['last_activity_at'] ?? 0), (int)($a['id'] ?? 0),
        ]);
        $items = array_slice($items, 0, max(1, $limit));
        foreach ($items as $i => $item) unset($items[$i]['_highlight_score']);
        return array_values($items);
    }

    function social_sitemap_posts(int $limit = 5000): array
    {
        social_ensure_storage();
        $byId = [];
        $monthFiles = glob(social_dir('indexes') . '/month-*.json') ?: [];
        rsort($monthFiles);
        foreach ($monthFiles as $file) {
            foreach (social_read_json($file, []) as $item) {
                if (!is_array($item)) continue;
                $summary = social_post_summary($item);
                if (($summary['status'] ?? '') === 'published' && (int)$summary['id'] > 0) {
                    $byId[(int)$summary['id']] = $summary;
                }
            }
        }
        if (!$byId) {
            foreach (social_read_json(social_index_file('latest'), []) as $item) {
                if (!is_array($item)) continue;
                $summary = social_post_summary($item);
                if (($summary['status'] ?? '') === 'published' && (int)$summary['id'] > 0) {
                    $byId[(int)$summary['id']] = $summary;
                }
            }
        }
        $items = array_values($byId);
        usort($items, static fn(array $a, array $b): int => [
            max((int)$b['updated_at'], (int)$b['last_activity_at']), (int)$b['id'],
        ] <=> [
            max((int)$a['updated_at'], (int)$a['last_activity_at']), (int)$a['id'],
        ]);
        return array_slice($items, 0, max(1, $limit));
    }

    function social_sitemap_profiles(int $limit = 5000): array
    {
        social_ensure_storage();
        $profiles = [];
        foreach ((array)(social_users_index()['summaries'] ?? []) as $summary) {
            if (!is_array($summary)) continue;
            $id = (int)($summary['user_id'] ?? -1);
            if ($id < 0) continue;
            $profile = social_get_profile($id);
            if (!$profile) continue;
            $hasContent = !empty($profile['is_official'])
                || (int)($profile['post_count'] ?? 0) > 0
                || (int)($profile['comment_count'] ?? 0) > 0
                || trim((string)($profile['bio'] ?? '')) !== '';
            if ($hasContent) $profiles[] = $profile;
        }
        usort($profiles, static fn(array $a, array $b): int => [
            (int)($b['updated_at'] ?? 0), (int)($b['post_count'] ?? 0), (int)($b['user_id'] ?? 0),
        ] <=> [
            (int)($a['updated_at'] ?? 0), (int)($a['post_count'] ?? 0), (int)($a['user_id'] ?? 0),
        ]);
        return array_slice($profiles, 0, max(1, $limit));
    }

    function social_stats(bool $admin = false): array
    {
        social_ensure_storage();
        $latest = social_read_json(social_index_file('latest'), []);
        $published = $pending = $hidden = 0;
        $polls = $upcomingEvents = $awards = $awardDcoin = 0;
        foreach ($latest as $item) {
            if (!is_array($item)) continue;
            if (($item['status'] ?? '') === 'published') {
                $published++;
                $summary = social_post_summary($item);
                if (!empty($summary['poll'])) $polls++;
                if (social_event_is_open($summary)) $upcomingEvents++;
                if (!empty($summary['award'])) {
                    $awards++;
                    $awardDcoin += (int)$summary['award']['amount'];
                }
            }
            if (($item['status'] ?? '') === 'pending') $pending++;
            if (($item['status'] ?? '') === 'hidden') $hidden++;
        }
        $users = social_users_index();
        $memberCount = 0;
        foreach ((array)($users['summaries'] ?? []) as $summary) {
            if (is_array($summary) && (int)($summary['user_id'] ?? 0) > 0) $memberCount++;
        }
        // Sosyal sayfayı henüz hiç açmamış DeviceGame hesapları da üyedir.
        // Çekirdek kullanıcı listesi okunabiliyorsa gerçek hesap sayısını esas al.
        if (function_exists('dg_read_data')) {
            try {
                $coreData = dg_read_data(true);
                $coreMembers = 0;
                foreach ((array)($coreData['users'] ?? []) as $coreUser) {
                    if (is_array($coreUser) && (int)($coreUser['is_banned'] ?? 0) !== 1) $coreMembers++;
                }
                $memberCount = max($memberCount, $coreMembers);
            } catch (Throwable) {
                // Çekirdek depolama okunamazsa sosyal indeks sayısı kullanılır.
            }
        }
        $stats = [
            'topics' => count(social_topics(true)),
            'posts' => $published,
            'members' => $memberCount,
            'pending_posts' => $pending,
            'hidden_posts' => $hidden,
            'pending_comments' => 0,
            'open_reports' => 0,
            'polls' => $polls,
            'upcoming_events' => $upcomingEvents,
            'awards' => $awards,
            'award_dcoin' => $awardDcoin,
        ];
        if ($admin) {
            foreach (social_moderation_comments(1000) as $comment) if (($comment['status'] ?? '') === 'pending') $stats['pending_comments']++;
            foreach (social_reports() as $report) if (($report['status'] ?? '') === 'open') $stats['open_reports']++;
        }
        return $stats;
    }

    function social_rebuild_indexes(): array
    {
        social_ensure_storage();
        return social_with_lock(static function (): array {
            $latest = [];
            $topics = [];
            $users = [];
            $months = [];
            $moderationComments = [];
            $userComments = [];
            $postCount = 0;
            $maxPostId = 0;
            $maxCommentId = 0;
            foreach (glob(social_dir('posts') . '/*/*.json') ?: [] as $file) {
                $post = social_normalize_post(social_read_json($file));
                if ($post['id'] <= 0) continue;
                $maxPostId = max($maxPostId, (int)$post['id']);
                foreach (social_comment_map((int)$post['id']) as $knownComment) {
                    $maxCommentId = max($maxCommentId, (int)($knownComment['id'] ?? 0));
                }
                if ($post['status'] === 'deleted') continue;
                $commentCount = 0;
                $latestComment = (int)$post['created_at'];
                foreach (social_comment_map((int)$post['id']) as $comment) {
                    if ($comment['status'] === 'published') {
                        $commentCount++;
                        $latestComment = max($latestComment, (int)$comment['created_at']);
                    }
                    if ($comment['status'] !== 'deleted') {
                        $summary = social_comment_summary($comment, $post);
                        $userComments[(int)$comment['user_id']][] = $summary;
                        if (in_array($comment['status'], ['pending', 'hidden'], true)) $moderationComments[] = $summary;
                    }
                }
                $post['comment_count'] = $commentCount;
                $reactions = social_reactions((int)$post['id']);
                $post['gg_count'] = count($reactions['gg']);
                if (!empty($post['poll'])) $post['poll'] = social_poll_with_counts((array)$post['poll'], $reactions);
                $post['rsvp_count'] = social_is_event_post($post) ? count($reactions['rsvp']) : 0;
                if ($commentCount > 0) $post['last_activity_at'] = max((int)$post['last_activity_at'], $latestComment);
                $post = social_normalize_post($post);
                social_write_json_atomic($file, $post, false);
                $summary = social_post_summary($post);
                $latest[] = $summary;
                $topics[(string)$post['topic_slug']][] = $summary;
                $users[(int)$post['user_id']][] = $summary;
                $months[date('Y-m', (int)$post['created_at'])][] = $summary;
                $postCount++;
            }
            $latest = social_sort_summaries($latest);
            social_write_json_atomic(social_index_file('latest'), array_slice($latest, 0, 1000), false);
            foreach (glob(social_dir('indexes') . '/topic-*.json') ?: [] as $file) @unlink($file);
            foreach (glob(social_dir('indexes') . '/user-*.json') ?: [] as $file) @unlink($file);
            foreach (glob(social_dir('indexes') . '/month-*.json') ?: [] as $file) @unlink($file);
            foreach ($topics as $slug => $items) social_write_json_atomic(social_index_file('topic-' . $slug), array_slice(social_sort_summaries($items), 0, 1000), false);
            foreach ($users as $id => $items) social_write_json_atomic(social_index_file('user-' . $id), array_slice(social_sort_summaries($items), 0, 1000), false);
            foreach ($months as $month => $items) social_write_json_atomic(social_index_file('month-' . $month), social_sort_summaries($items), false);
            foreach ($userComments as $id => $items) {
                usort($items, static fn(array $a, array $b): int => [(int)$b['created_at'], (int)$b['id']] <=> [(int)$a['created_at'], (int)$a['id']]);
                social_write_json_atomic(social_index_file('user-comments-' . $id), array_slice($items, 0, 500), false);
            }
            usort($moderationComments, static fn(array $a, array $b): int => [(int)$b['created_at'], (int)$b['id']] <=> [(int)$a['created_at'], (int)$a['id']]);
            social_write_json_atomic(social_index_file('moderation-comments'), array_slice($moderationComments, 0, 1000), false);
            // Kullanıcı indeksini de profil dosyalarından sıfırdan kur; böylece
            // silinmiş/eski özetler kalmaz ve içeriksiz profillerin sayaçları da sıfırlanır.
            $profileIds = [];
            foreach (glob(social_dir('profiles') . '/*.json') ?: [] as $profileFile) {
                $profileId = basename($profileFile, '.json');
                if (ctype_digit($profileId)) $profileIds[] = (int)$profileId;
            }
            sort($profileIds);
            social_write_json_atomic(social_index_file('users'), ['map' => [], 'summaries' => []], false);
            foreach ($profileIds as $id) {
                social_sync_profile_post_counts_locked((int)$id);
                social_sync_profile_comment_counts_locked((int)$id);
            }
            $maxReportId = 0;
            foreach (social_reports() as $report) $maxReportId = max($maxReportId, (int)($report['id'] ?? 0));
            $maxNotificationId = 0;
            foreach (glob(social_dir('notifications') . '/*.json') ?: [] as $notificationFile) {
                foreach (social_read_json($notificationFile, []) as $notification) {
                    if (is_array($notification)) $maxNotificationId = max($maxNotificationId, (int)($notification['id'] ?? 0));
                }
            }
            $meta = social_meta_locked();
            $meta['counters']['posts'] = max((int)($meta['counters']['posts'] ?? 1), $maxPostId + 1);
            $meta['counters']['comments'] = max((int)($meta['counters']['comments'] ?? 1), $maxCommentId + 1);
            $meta['counters']['reports'] = max((int)($meta['counters']['reports'] ?? 1), $maxReportId + 1);
            $meta['counters']['notifications'] = max((int)($meta['counters']['notifications'] ?? 1), $maxNotificationId + 1);
            $meta['updated_at'] = time();
            social_write_json_atomic(social_meta_file(), $meta);
            return ['errors' => [], 'posts' => $postCount, 'comments' => array_sum(array_map('count', $userComments))];
        });
    }

    function social_storage_stats(): array
    {
        social_ensure_storage();
        $files = 0;
        $bytes = 0;
        foreach ([social_storage_dir(), social_upload_dir()] as $dir) {
            if (!is_dir($dir)) continue;
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $files++;
                    $bytes += (int)$file->getSize();
                }
            }
        }
        return ['files' => $files, 'bytes' => $bytes];
    }

    function social_directory_stats(string $dir): array
    {
        $files = 0;
        $bytes = 0;
        $latestMtime = 0;
        if (is_dir($dir)) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if (!$file->isFile()) continue;
                $files++;
                $bytes += (int)$file->getSize();
                $latestMtime = max($latestMtime, (int)$file->getMTime());
            }
        }
        return ['files' => $files, 'bytes' => $bytes, 'latest_mtime' => $latestMtime];
    }

    function social_health_report(): array
    {
        social_ensure_storage();
        $checks = [];
        $addCheck = static function (string $label, bool $ok, string $detail, string $level = 'error') use (&$checks): void {
            $checks[] = ['label' => $label, 'ok' => $ok, 'detail' => $detail, 'level' => $ok ? 'ok' : $level];
        };

        $storageDir = social_storage_dir();
        $uploadDir = social_upload_dir();
        $addCheck('Sosyal veri klasörü', is_dir($storageDir) && is_writable($storageDir), is_dir($storageDir) ? (is_writable($storageDir) ? 'storage/social yazılabilir.' : 'storage/social yazılamıyor.') : 'storage/social bulunamıyor.');
        $addCheck('Görsel klasörü', is_dir($uploadDir) && is_writable($uploadDir), is_dir($uploadDir) ? (is_writable($uploadDir) ? 'uploads/social yazılabilir.' : 'uploads/social yazılamıyor.') : 'uploads/social bulunamıyor.');
        $lockFile = social_lock_file();
        $addCheck('Veri kilidi', is_file($lockFile) ? is_writable($lockFile) : is_writable($storageDir), is_file($lockFile) ? 'social.lock mevcut.' : 'Kilit ilk yazmada oluşur.');

        $meta = [];
        try {
            $meta = social_meta_locked();
            $addCheck('Meta dosyası', true, 'meta.json okunabiliyor.');
        } catch (Throwable $exception) {
            $addCheck('Meta dosyası', false, $exception->getMessage());
        }

        $topicCount = 0;
        try {
            $topicCount = count(social_topics(false));
            $addCheck('Konu listesi', $topicCount > 0, $topicCount . ' konu tanımlı.');
        } catch (Throwable $exception) {
            $addCheck('Konu listesi', false, $exception->getMessage());
        }

        $indexEntries = 0;
        try {
            $indexEntries = count(social_read_json(social_index_file('latest'), []));
            $addCheck('Akış indeksi', true, $indexEntries . ' özet kaydı var.');
        } catch (Throwable $exception) {
            $addCheck('Akış indeksi', false, $exception->getMessage());
        }

        $postFiles = glob(social_dir('posts') . '/*/*.json') ?: [];
        $invalidPosts = 0;
        $referencedImages = [];
        foreach ($postFiles as $file) {
            $raw = @file_get_contents($file);
            $decoded = $raw === false ? null : json_decode($raw, true);
            if (!is_array($decoded)) {
                $invalidPosts++;
                continue;
            }
            $post = social_normalize_post($decoded);
            if (($post['status'] ?? '') !== 'deleted' && trim((string)$post['image']) !== '') {
                $referencedImages[ltrim(str_replace('\\', '/', (string)$post['image']), '/')] = true;
            }
        }
        $addCheck('Gönderi JSON bütünlüğü', $invalidPosts === 0, count($postFiles) . ' gönderi dosyası, ' . $invalidPosts . ' bozuk kayıt.');

        $uploadFiles = [];
        $orphanImages = 0;
        $orphanBytes = 0;
        if (is_dir($uploadDir)) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploadDir, FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if (!$file->isFile() || $file->getFilename() === 'index.html') continue;
                $relative = 'uploads/social/' . str_replace('\\', '/', substr($file->getPathname(), strlen($uploadDir) + 1));
                $uploadFiles[$relative] = (int)$file->getSize();
                if (!isset($referencedImages[$relative])) {
                    $orphanImages++;
                    $orphanBytes += (int)$file->getSize();
                }
            }
        }
        $missingImages = 0;
        foreach (array_keys($referencedImages) as $relative) {
            if (!isset($uploadFiles[$relative]) && !is_file(social_base_dir() . '/' . $relative)) $missingImages++;
        }
        $addCheck('Görsel başvuruları', $missingImages === 0, $missingImages . ' eksik, ' . $orphanImages . ' sahipsiz görsel.', $missingImages > 0 ? 'error' : 'warning');

        $freeBytes = @disk_free_space($storageDir);
        $freeBytes = $freeBytes === false ? 0 : (int)$freeBytes;
        $addCheck('Boş disk', $freeBytes === 0 || $freeBytes > 10 * 1024 * 1024, $freeBytes > 0 ? number_format($freeBytes / 1048576, 1, ',', '.') . ' MB boş alan.' : 'Boş alan okunamadı.', 'warning');

        $lastBackupAt = (int)($meta['last_backup_at'] ?? 0);
        $backupAgeDays = $lastBackupAt > 0 ? (int)floor((time() - $lastBackupAt) / 86400) : null;
        $addCheck(
            'Yedek işareti',
            $lastBackupAt > 0 && $backupAgeDays !== null && $backupAgeDays <= 7,
            $lastBackupAt > 0 ? 'Son işaret: ' . date('d.m.Y H:i', $lastBackupAt) . ' (' . $backupAgeDays . ' gün önce).' : 'Henüz yedek alındığı işaretlenmemiş.',
            'warning'
        );

        $breakdown = [];
        foreach (['profiles', 'posts', 'comments', 'reactions', 'follows', 'saves', 'notifications', 'reports', 'indexes', 'ratelimits'] as $name) {
            $breakdown['storage/social/' . $name] = social_directory_stats(social_dir($name));
        }
        $breakdown['uploads/social'] = social_directory_stats($uploadDir);

        $healthy = true;
        foreach ($checks as $check) {
            if (!$check['ok'] && $check['level'] === 'error') $healthy = false;
        }
        return [
            'healthy' => $healthy,
            'checks' => $checks,
            'breakdown' => $breakdown,
            'totals' => social_storage_stats(),
            'topics' => $topicCount,
            'post_files' => count($postFiles),
            'invalid_posts' => $invalidPosts,
            'index_entries' => $indexEntries,
            'missing_images' => $missingImages,
            'orphan_images' => $orphanImages,
            'orphan_bytes' => $orphanBytes,
            'free_bytes' => $freeBytes,
            'last_backup_at' => $lastBackupAt,
            'last_backup_by' => (int)($meta['last_backup_by'] ?? 0),
            'meta_updated_at' => (int)($meta['updated_at'] ?? 0),
            'latest_index_mtime' => is_file(social_index_file('latest')) ? (int)@filemtime(social_index_file('latest')) : 0,
        ];
    }

    function social_mark_backup(array $admin): array
    {
        if (empty($admin['is_admin'])) return ['errors' => ['Bu işlem için yönetici yetkisi gerekir.']];
        social_ensure_storage();
        return social_with_lock(static function () use ($admin): array {
            $meta = social_meta_locked();
            $meta['last_backup_at'] = time();
            $meta['last_backup_by'] = (int)$admin['id'];
            $meta['updated_at'] = time();
            social_write_json_atomic(social_meta_file(), $meta);
            return ['errors' => [], 'last_backup_at' => (int)$meta['last_backup_at']];
        });
    }

    function social_render_text(string $text): string
    {
        $escaped = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        static $users = null;
        if ($users === null) {
            $users = social_users_index()['map'] ?? [];
            // Profili henüz indekslenmemiş mevcut DeviceGame hesaplarının bahsetmelerini de bağla.
            if (function_exists('dg_all')) {
                foreach (dg_all('users') as $coreUser) {
                    if (!is_array($coreUser)) continue;
                    $key = social_fold((string)($coreUser['username'] ?? ''));
                    if ($key !== '' && !isset($users[$key])) $users[$key] = (int)($coreUser['id'] ?? 0);
                }
            }
        }
        $linked = preg_replace_callback(
            '~(https?://[^\s<]+)|(?<![A-Za-z0-9_.-])@([A-Za-z0-9_.-]{3,32})|(?<![A-Za-z0-9_])#([A-Za-z0-9_\-\x{00C0}-\x{024F}]{2,30})~u',
            static function (array $m) use ($users): string {
                if (($m[1] ?? '') !== '') {
                    $url = $m[1];
                    return '<a href="' . $url . '" target="_blank" rel="nofollow ugc noopener">' . $url . '</a>';
                }
                if (($m[2] ?? '') !== '') {
                    $username = rtrim((string)$m[2], '.-');
                    if (isset($users[social_fold($username)])) {
                        return '<a href="profil.php?kullanici=' . rawurlencode($username) . '">@' . htmlspecialchars($username, ENT_QUOTES, 'UTF-8') . '</a>' . htmlspecialchars(substr((string)$m[2], strlen($username)), ENT_QUOTES, 'UTF-8');
                    }
                    return $m[0];
                }
                if (($m[3] ?? '') !== '') {
                    $tag = social_slugify((string)$m[3]);
                    return '<a href="index.php?q=' . rawurlencode('#' . $tag) . '#akis">#' . htmlspecialchars($tag, ENT_QUOTES, 'UTF-8') . '</a>';
                }
                return $m[0];
            },
            $escaped
        ) ?? $escaped;
        $paragraphs = preg_split('/\n\s*\n/', trim($linked)) ?: [];
        $html = [];
        foreach ($paragraphs as $paragraph) {
            if (trim($paragraph) !== '') $html[] = '<p>' . nl2br($paragraph) . '</p>';
        }
        return implode("\n", $html);
    }

    function social_avatar_initials(string $name): string
    {
        $name = trim($name);
        if ($name === '') return 'DG';
        $parts = preg_split('/\s+/u', $name) ?: [];
        $initials = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $initials .= function_exists('mb_strtoupper') ? mb_strtoupper(mb_substr($part, 0, 1, 'UTF-8'), 'UTF-8') : strtoupper(substr($part, 0, 1));
        }
        return $initials ?: 'DG';
    }

    function social_avatar_hue(int $userId): int
    {
        return (abs($userId) * 47 + 145) % 360;
    }
}
