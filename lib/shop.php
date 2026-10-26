<?php
/**
 * Device Game — mağaza yardımcıları
 * Saf PHP. Veritabanı yok, framework yok. Oturum tabanlı sepet.
 */

if (!defined('DG_SHOP')) {
    define('DG_SHOP', true);

    require_once __DIR__ . '/art.php';

    if (!function_exists('e')) {
        function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
    }

    /* ------------------------------------------------------------------
     * Oturum
     * ---------------------------------------------------------------- */
    function shop_boot(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        if (PHP_SAPI === 'cli' && !headers_sent()) {
            // CLI önizleme/CLI testlerinde de oturum çalışsın
            ini_set('session.use_cookies', '0');
            session_id('dg-cli');
        }
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        @session_start();
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(16));
        }
    }

    function csrf_token(): string
    {
        return $_SESSION['csrf'] ?? '';
    }

    function csrf_valid(): bool
    {
        $sent = (string)($_POST['csrf'] ?? '');
        $kept = (string)($_SESSION['csrf'] ?? '');
        return $sent !== '' && $kept !== '' && hash_equals($kept, $sent);
    }

    /* ------------------------------------------------------------------
     * Depolama (storage/) — ürünler, slider ve siparişler burada saklanır
     * ---------------------------------------------------------------- */
    function shop_storage_dir(): string
    {
        return __DIR__ . '/../storage';
    }

    /** storage/ klasörünü hazırlar (yazma izni + web erişimine kapalı). */
    function shop_storage_guard(): bool
    {
        $dir = shop_storage_dir();
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return false;
        }
        // Dosyalar web'den okunamasın (Apache / IIS).
        if (!is_file($dir . '/.htaccess')) {
            @file_put_contents($dir . '/.htaccess', "Require all denied\nDeny from all\n");
        }
        if (!is_file($dir . '/index.html')) {
            @file_put_contents($dir . '/index.html', '');
        }
        return true;
    }

    /** JSON dosyasını UTF-8 olarak yazar. */
    function shop_write_json(string $file, array $data): bool
    {
        if (!shop_storage_guard()) {
            return false;
        }
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        return $json !== false && @file_put_contents($file, $json . PHP_EOL, LOCK_EX) !== false;
    }

    function shop_read_json(string $file): ?array
    {
        if (!is_file($file)) {
            return null;
        }
        $data = json_decode((string)@file_get_contents($file), true);
        return is_array($data) ? $data : null;
    }

    /* ------------------------------------------------------------------
     * Katalog — admin panelinden yönetilir (storage/products.json)
     * ---------------------------------------------------------------- */
    function shop_products_file(): string
    {
        return shop_storage_dir() . '/products.json';
    }

    /** Eksik alanları tamamlayarak ürün kaydını güvenli hale getirir. */
    function shop_normalize_product(array $p): array
    {
        $categories = shop_categories();
        $category = (string)($p['category'] ?? 'key');
        if (!isset($categories[$category])) {
            $category = 'key';
        }
        $pattern = (string)($p['pattern'] ?? 'grid');
        if (!in_array($pattern, ['grid', 'lines', 'rings', 'dots'], true)) {
            $pattern = 'grid';
        }
        $features = [];
        foreach ((array)($p['features'] ?? []) as $feature) {
            $feature = trim((string)$feature);
            if ($feature !== '') {
                $features[] = $feature;
            }
        }
        $old = $p['old_price'] ?? null;

        return [
            'slug'      => (string)($p['slug'] ?? ''),
            'name'      => (string)($p['name'] ?? ''),
            'subtitle'  => (string)($p['subtitle'] ?? ''),
            'category'  => $category,
            'price'     => (int)($p['price'] ?? 0),
            'old_price' => ($old === null || $old === '') ? null : (int)$old,
            'platform'  => (string)($p['platform'] ?? 'PC'),
            'badge'     => empty($p['badge']) ? null : (string)$p['badge'],
            'glyph'     => (string)($p['glyph'] ?? 'DG'),
            'c1'        => (string)($p['c1'] ?? '#0F3B2C'),
            'c2'        => (string)($p['c2'] ?? '#2FA377'),
            'accent'    => (string)($p['accent'] ?? '#D6FFE9'),
            'pattern'   => $pattern,
            'delivery'  => (string)($p['delivery'] ?? 'Anında teslimat'),
            'stock'     => (int)($p['stock'] ?? 10),
            'order'     => (int)($p['order'] ?? 0),
            'added_at'  => (string)($p['added_at'] ?? date('Y-m-d')),
            'desc'      => (string)($p['desc'] ?? ''),
            'features'  => $features,
            'image'     => (string)($p['image'] ?? ''),
        ];
    }

    function shop_products_load(): array
    {
        $products = shop_read_json(shop_products_file());
        if ($products === null) {
            // İlk açılış: varsayılan kataloğu storage'a kopyala.
            $products = require __DIR__ . '/../data/products.php';
            shop_write_json(shop_products_file(), array_map('shop_normalize_product', $products));
        }
        $normalized = [];
        foreach ($products as $product) {
            if (is_array($product) && ($product['slug'] ?? '') !== '' && ($product['name'] ?? '') !== '') {
                $normalized[] = shop_normalize_product($product);
            }
        }
        return $normalized;
    }

    function shop_products_flush(): void
    {
        shop_products(true);
    }

    function shop_products(bool $reload = false): array
    {
        static $products = null;
        if ($products === null || $reload) {
            $products = shop_products_load();
        }
        return $products;
    }

    function shop_categories(): array
    {
        return [
            'key'     => 'Oyun Keyleri',
            'account' => 'Premium Hesaplar',
            'gear'    => 'Ekipman & Baskı',
        ];
    }

    function shop_product(string $slug): ?array
    {
        foreach (shop_products() as $product) {
            if ($product['slug'] === $slug) {
                return $product;
            }
        }
        return null;
    }

    function shop_catalog(string $category = '', string $query = '', string $sort = ''): array
    {
        $items = shop_products();

        if ($category !== '' && isset(shop_categories()[$category])) {
            $items = array_values(array_filter($items, fn($p) => $p['category'] === $category));
        }

        if ($query !== '') {
            $needle = shop_fold($query);
            $labels = shop_categories();
            $items = array_values(array_filter($items, function ($p) use ($needle, $labels) {
                $haystack = shop_fold(
                    $p['name'] . ' ' . $p['subtitle'] . ' ' . $p['platform'] . ' '
                    . ($labels[$p['category']] ?? '') . ' ' . implode(' ', $p['features'])
                );
                return str_contains($haystack, $needle);
            }));
        }

        switch ($sort) {
            case 'ucuz':
                usort($items, fn($a, $b) => $a['price'] <=> $b['price']);
                break;
            case 'pahali':
                usort($items, fn($a, $b) => $b['price'] <=> $a['price']);
                break;
            case 'yeni':
                usort($items, fn($a, $b) => ($b['added_at'] ?? '') <=> ($a['added_at'] ?? ''));
                break;
            default:
                usort($items, fn($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));
        }

        return $items;
    }

    /** Türkçe karakterleri sadeleştirir (arama için). */
    function shop_fold(string $text): string
    {
        $map = [
            'ç' => 'c', 'ğ' => 'g', 'ı' => 'i', 'i̇' => 'i', 'ö' => 'o', 'ş' => 's', 'ü' => 'u',
            'Ç' => 'c', 'Ğ' => 'g', 'İ' => 'i', 'Ö' => 'o', 'Ş' => 's', 'Ü' => 'u',
        ];
        return mb_strtolower(strtr($text, $map), 'UTF-8');
    }

    /* ------------------------------------------------------------------
     * Sepet
     * ---------------------------------------------------------------- */
    function cart_raw(): array
    {
        $cart = $_SESSION['cart'] ?? [];
        return is_array($cart) ? $cart : [];
    }

    function cart_items(): array
    {
        $items = [];
        foreach (cart_raw() as $slug => $qty) {
            $product = shop_product((string)$slug);
            $qty = (int)$qty;
            if (!$product || $qty < 1) {
                continue;
            }
            $qty = min($qty, 10);
            $items[] = [
                'product' => $product,
                'qty'     => $qty,
                'line'    => $product['price'] * $qty,
            ];
        }
        return $items;
    }

    function cart_count(): int
    {
        $count = 0;
        foreach (cart_raw() as $qty) {
            $count += (int)$qty;
        }
        return $count;
    }

    function cart_subtotal(): int
    {
        $total = 0;
        foreach (cart_items() as $item) {
            $total += $item['line'];
        }
        return $total;
    }

    function cart_add(string $slug, int $qty = 1): void
    {
        $product = shop_product($slug);
        if (!$product) {
            return;
        }
        $qty = max(1, min($qty, 10));
        $current = (int)($_SESSION['cart'][$slug] ?? 0);
        $_SESSION['cart'][$slug] = max(1, min($current + $qty, 10));
    }

    function cart_set(string $slug, int $qty): void
    {
        if (!shop_product($slug)) {
            return;
        }
        if ($qty < 1) {
            cart_remove($slug);
            return;
        }
        $_SESSION['cart'][$slug] = min($qty, 10);
    }

    function cart_remove(string $slug): void
    {
        unset($_SESSION['cart'][$slug]);
    }

    function cart_clear(): void
    {
        $_SESSION['cart'] = [];
    }

    /* ------------------------------------------------------------------
     * Sipariş
     * ---------------------------------------------------------------- */
    function shop_order_id(): string
    {
        return 'DG-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
    }

    /**
     * Siparişi storage/orders.jsonl dosyasına ekler. Yazma izni yoksa
     * sipariş akışını bozmaz; sadece false döner.
     */
    function shop_save_order(array $order): bool
    {
        if (!shop_storage_guard()) {
            return false;
        }
        $file = shop_storage_dir() . '/orders.jsonl';
        $line = json_encode($order, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($line === false) {
            return false;
        }
        $saved = @file_put_contents($file, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
        return $saved !== false;
    }

    /** WhatsApp için sipariş metni. */
    function shop_order_message(array $order): string
    {
        $lines = [];
        $lines[] = '*YENİ SİPARİŞ · ' . $order['id'] . '*';
        $lines[] = 'devicegame.com · ' . date('d.m.Y H:i');
        $lines[] = '';
        $lines[] = '*Ürünler*';

        foreach ($order['items'] as $item) {
            $lines[] = '• ' . $item['qty'] . ' × ' . $item['name']
                . ' — ' . money($item['line']);
        }

        $lines[] = '';
        $lines[] = '*Ara toplam:* ' . money($order['subtotal']);
        $lines[] = '*Teslimat:* ' . $order['delivery_label'];
        $lines[] = '';
        $lines[] = '*Müşteri*';
        $lines[] = 'Ad: ' . $order['name'];
        if (!empty($order['email'])) {
            $lines[] = 'E-posta: ' . $order['email'];
        }
        $lines[] = 'Ödeme: ' . ($order['payment'] ?? 'Havale / EFT');
        if (!empty($order['note'])) {
            $lines[] = 'Not: ' . $order['note'];
        }
        $lines[] = '';
        $lines[] = 'Siparişi onaylıyorum.';

        return implode("\n", $lines);
    }

    /* ------------------------------------------------------------------
     * Biçimlendirme & URL
     * ---------------------------------------------------------------- */
    function money(int|float $amount): string
    {
        return '₺' . number_format((float)$amount, 0, ',', '.');
    }

    function wa_link(string $phone, string $text): string
    {
        return 'https://wa.me/' . preg_replace('/\D+/', '', $phone) . '?text=' . rawurlencode($text);
    }

    function url_with(array $params): string
    {
        $query = array_filter($params, fn($v) => $v !== '' && $v !== null);
        return 'shop.php' . ($query ? '?' . http_build_query($query) : '');
    }

    function shop_delivery_options(): array
    {
        // Tek teslimat şekli: dijital teslimat.
        return [
            'dijital' => 'Dijital teslimat (anında, WhatsApp üzerinden)',
        ];
    }

    function shop_payment_options(): array
    {
        // Tek ödeme tercihi: havale / EFT.
        return [
            'havale' => 'Havale / EFT',
        ];
    }

    /* ------------------------------------------------------------------
     * Slider (mağaza vitrini) — admin panelinden yönetilir
     * ---------------------------------------------------------------- */
    function shop_slides_file(): string
    {
        return shop_storage_dir() . '/slides.json';
    }

    function shop_default_slides(): array
    {
        return [
            [
                'id'     => 'vitrin-fc26',
                'tag'    => 'Çok satan',
                'title'  => 'FC 26 sahaya çıkmaya hazır',
                'text'   => 'Yeni sezonun en çok satan futbol oyunu, ödeme onayından sonra dakikalar içinde WhatsApp\'ta.',
                'button' => 'Ürüne göz at',
                'link'   => 'product.php?slug=ea-sports-fc-26',
                'image'  => '',
                'c1'     => '#0F3B2C',
                'c2'     => '#2FA377',
                'active' => true,
                'order'  => 1,
            ],
            [
                'id'     => 'vitrin-hesaplar',
                'tag'    => 'Fırsat',
                'title'  => 'Premium hesaplarda kaçırılmayacak fiyatlar',
                'text'   => 'Steam, PlayStation ve Xbox hesapları tek çatı altında. Hepsinde anında dijital teslimat.',
                'button' => 'Hesapları incele',
                'link'   => 'shop.php?kategori=account',
                'image'  => '',
                'c1'     => '#231A3B',
                'c2'     => '#B79CFF',
                'active' => true,
                'order'  => 2,
            ],
            [
                'id'     => 'vitrin-ekipman',
                'tag'    => 'Stüdyo imzası',
                'title'  => 'Masana stüdyo dokunuşu',
                'text'   => 'Device Game imzalı mousepad ve baskılı ürünler, Türkiye\'de üretim.',
                'button' => 'Ekipmanlara bak',
                'link'   => 'shop.php?kategori=gear',
                'image'  => '',
                'c1'     => '#3B2A0A',
                'c2'     => '#E0B44F',
                'active' => true,
                'order'  => 3,
            ],
        ];
    }

    function shop_normalize_slide(array $s, int $fallbackOrder = 0): array
    {
        $id = trim((string)($s['id'] ?? ''));
        if ($id === '') {
            $id = 'slide-' . substr(bin2hex(random_bytes(4)), 0, 8);
        }
        // Güvenli bağlantı: yalnızca göreceli sayfa/çapa veya http(s) URL.
        $link = trim((string)($s['link'] ?? ''));
        if ($link !== '' && !preg_match('~^(https?://|[a-z0-9_.-]+\.php([?#].*)?|/[^/\\\\]|/|#[^\s]*)$~i', $link)) {
            $link = '';
        }
        $image = trim((string)($s['image'] ?? ''));
        if ($image !== '' && !preg_match('~^(https?://|[a-z0-9_./-]+\.(png|jpe?g|webp|gif|svg))$~i', $image)) {
            $image = '';
        }
        return [
            'id'     => $id,
            'tag'    => (string)($s['tag'] ?? ''),
            'title'  => (string)($s['title'] ?? ''),
            'text'   => (string)($s['text'] ?? ''),
            'button' => (string)($s['button'] ?? ''),
            'link'   => $link,
            'image'  => $image,
            'c1'     => (string)($s['c1'] ?? '#0F3B2C'),
            'c2'     => (string)($s['c2'] ?? '#2FA377'),
            'active' => !empty($s['active']),
            'order'  => (int)($s['order'] ?? $fallbackOrder),
        ];
    }

    /** Tüm slaytlar (admin listesi), sıra numarasına göre. */
    function shop_slides_all(): array
    {
        $slides = shop_read_json(shop_slides_file());
        if ($slides === null) {
            $slides = shop_default_slides();
            shop_write_json(shop_slides_file(), array_map('shop_normalize_slide', $slides));
        }
        $normalized = [];
        foreach (array_values($slides) as $i => $slide) {
            if (is_array($slide)) {
                $normalized[] = shop_normalize_slide($slide, $i + 1);
            }
        }
        usort($normalized, fn($a, $b) => $a['order'] <=> $b['order']);
        return $normalized;
    }

    /** Mağazada gösterilen aktif slaytlar. */
    function shop_slides(): array
    {
        return array_values(array_filter(shop_slides_all(), fn($s) => $s['active'] && $s['title'] !== ''));
    }

    /* ------------------------------------------------------------------
     * Blog — admin panelinden yönetilir (storage/blog.json)
     * ---------------------------------------------------------------- */
    function blog_file(): string
    {
        return shop_storage_dir() . '/blog.json';
    }

    function blog_default_posts(): array
    {
        return [
            [
                'slug'      => 'project-nexus-tanitimi',
                'title'     => 'Project: Nexus\'a hoş geldiniz',
                'tag'       => 'Duyuru',
                'date'      => '2026-10-12',
                'excerpt'   => 'Karanlık sokaklarda kendi yolunu bulacağın yeni nesil RPG deneyimimizi nihayet anlatıyoruz. İşte Nexus\'un dünyası ve bizim hikayemiz.',
                'content'   => "Uzun süredir üzerinde çalıştığımız projemizi nihayet açığa çıkarıyoruz: Project: Nexus.\n\nNexus; neonla yıkanmış bir metropolde geçen, verdiğin her kararın şehri ve seni değiştirdiği bir aksiyon RPG'si. Amacımız büyük vaatlerde bulunmak değil; her sokakta, her konuşmada ve her seçimde hissedilen bir dünya kurmak.\n\n## Bu blogda ne olacak?\n\n- Geliştirici günlükleri: tasarım kararlarının perde arkası\n- Prototip videoları ve erken görseller\n- Topluluk test duyuruları ve etkinlikler\n\n## İlk hedefimiz\n\nŞu an üretim aşamasındayız: dünya yazıldı, çekirdek oynanış prototipleri ayakta. Sıradaki adımımız oynanışın elde nasıl hissettiğini geniş bir test grubuyla görmek.\n\nBizi izlemeye devam edin — Nexus yakında daha fazlasını anlatacak.",
                'image'     => '',
                'c1'        => '#0F3B2C',
                'c2'        => '#2FA377',
                'published' => true,
                'order'     => 1,
            ],
            [
                'slug'      => 'atmosfer-nasil-kurulur',
                'title'     => 'Karanlık sokakların atmosferi nasıl kurulur?',
                'tag'       => 'Geliştirici günlüğü',
                'date'      => '2026-09-28',
                'excerpt'   => 'Işık, ses ve ritim: Nexus\'un şehrine nefes aldıran üç katman. Sanat ekibimizin atmosfer üzerine notları.',
                'content'   => "Bir oyun dünyasını unutulmaz kılan şey genelde hikayesi değil, nasıl hissettirdiğidir. Nexus'ta atmosferi üç katman üzerine kuruyoruz: ışık, ses ve ritim.\n\n## Işık\n\nŞehrimizde neredeyse hiç düz ışık yok. Her sokak lambasının bir karakteri var; neon tabelalar sadece dekor değil, mahallelerin kimliği. Yağmur sonrası yansıyan ışıklar bile oynanışın bir parçası: saklanmanı da, yakalanmanı da kolaylaştırır.\n\n## Ses\n\nUzak tramvay sesleri, duvar diplerindeki rüzgar, bir kafenin içeriden sızan müziği... Ses tasarımı 'arka plan' değil, anlatının kendisi. Kulaklıkla oynayanların fark edeceği pek çok katman saklıyoruz.\n\n## Ritim\n\nŞehir nefes alır gibi hissettirmeli. Kalabalıklar, trafik ve hava durumu bir döngüde akar; oyuncunun bu döngüye girmesi, dünyanın canlı olduğuna dair en güçlü işaret.\n\nBu üçünü bir araya getiren ilk sahnelerimiz hazır. Erken testte aldığımız geri bildirimlerin bir kısmı tam da bu konuda oldu: 'Karanlık ama boğucu değil, gizemli.' — duymak istediğimiz şey buydu.",
                'image'     => '',
                'c1'        => '#231A3B',
                'c2'        => '#B79CFF',
                'published' => true,
                'order'     => 2,
            ],
            [
                'slug'      => 'prototipten-uretime',
                'title'     => 'Prototipten üretime: stüdyodan notlar',
                'tag'       => 'Stüdyo',
                'date'      => '2026-09-10',
                'excerpt'   => 'Bir fikrin oyun olma yolculuğu: kısa döngüler, erken prototipler ve bolca oynanış testi. Sürecimizi şeffafça paylaşıyoruz.',
                'content'   => "Stüdyoda en çok duyulan cümlemiz şu: 'Önce oynanabilir olsun, sonra güzel olsun.'\n\nBir fikir masada ne kadar iyi görünürse görünsün, ancak prototipte ayağa kalkabilir. Bu yüzden döngülerimizi kısa tutuyoruz: bir mekanik fikri en geç bir hafta içinde oynanabilir hale geliyor.\n\n## Elediğimiz kadar, sakladığımız da var\n\nHer prototip üretime geçmiyor elbette. Ama elenen her prototip bir sonrakini daha keskin yapıyor. Nexus'un çekirdek dövüşü de böyle doğdu: üç başarısız denemeden sonra dördüncüde 'ellerde hissedilen' o hissi yakaladık.\n\n## Topluluk nerede devreye giriyor?\n\n- Erken testlerde oynanış hissi ve tempo\n- Hikaye seçimlerinin anlaşılırlığı\n- Performans ve erişilebilirlik notları\n\nÖnümüzdeki haftalarda topluluk testlerimizi genişleteceğiz. Duyurular bu blogda ve WhatsApp hattımızda olacak.\n\nOyun dünyasının anahtarı; sabır, prototip ve bolca kahve.",
                'image'     => '',
                'c1'        => '#3B2A0A',
                'c2'        => '#E0B44F',
                'published' => true,
                'order'     => 3,
            ],
        ];
    }

    function blog_normalize_post(array $p, int $fallbackOrder = 0): array
    {
        $slug = trim((string)($p['slug'] ?? ''));
        $slug = strtolower((string)preg_replace('/[^a-zA-Z0-9-]+/', '-', $slug));
        $slug = trim($slug, '-');
        if ($slug === '') {
            $slug = 'yazi-' . substr(bin2hex(random_bytes(4)), 0, 8);
        }
        $date = trim((string)($p['date'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || strtotime($date) === false) {
            $date = date('Y-m-d');
        }
        $image = trim((string)($p['image'] ?? ''));
        if ($image !== '' && !preg_match('~^(https?://|[a-z0-9_./-]+\.(png|jpe?g|webp|gif|svg))$~i', $image)) {
            $image = '';
        }

        return [
            'slug'      => $slug,
            'title'     => (string)($p['title'] ?? ''),
            'tag'       => (string)($p['tag'] ?? ''),
            'date'      => $date,
            'excerpt'   => (string)($p['excerpt'] ?? ''),
            'content'   => (string)($p['content'] ?? ''),
            'image'     => $image,
            'c1'        => (string)($p['c1'] ?? '#0F3B2C'),
            'c2'        => (string)($p['c2'] ?? '#2FA377'),
            'published' => !empty($p['published']),
            'order'     => (int)($p['order'] ?? $fallbackOrder),
        ];
    }

    /** Blog kapak görseli adresini blog alt sayfalarında da doğru çöz. */
    function blog_image_url(string $image): string
    {
        $image = trim($image);
        if ($image === '') {
            return '';
        }
        // Harici URL'ler ve site-kökünden başlayan yollar aynen korunur.
        if (preg_match('~^https?://~i', $image) || str_starts_with($image, '/')) {
            return $image;
        }
        // img/blog.png gibi yollar /blog/img/blog.png değil /img/blog.png olmalı.
        return '/' . ltrim($image, './');
    }

    /** Blog yazıları, en yeniden eskiye. $only_published = yayında olmayanları gizle. */
    function blog_posts(bool $only_published = true): array
    {
        $posts = shop_read_json(blog_file());
        if ($posts === null) {
            $posts = blog_default_posts();
            shop_write_json(blog_file(), array_map('blog_normalize_post', $posts));
        }
        $normalized = [];
        foreach (array_values($posts) as $i => $post) {
            if (!is_array($post) || ($post['title'] ?? '') === '') {
                continue;
            }
            $post = blog_normalize_post($post, $i + 1);
            if ($only_published && !$post['published']) {
                continue;
            }
            $normalized[] = $post;
        }
        usort($normalized, fn($a, $b) => [$b['date'], $a['order']] <=> [$a['date'], $b['order']]);
        return $normalized;
    }

    function blog_post(string $slug, bool $only_published = true): ?array
    {
        foreach (blog_posts($only_published) as $post) {
            if ($post['slug'] === $slug) {
                return $post;
            }
        }
        return null;
    }

    /**
     * Blog metnini HTML'e çevirir. Güvenli sade biçimlendirme:
     * boş satır = paragraf ayracı, "## " = başlık, "- " = liste.
     */
    function blog_render(string $text): string
    {
        // Yeni editörün HTML çıktısını yalnızca güvenli biçimlendirme etiketleriyle göster.
        if (preg_match('/<\/?(p|h2|h3|strong|b|em|i|u|ul|ol|li|blockquote|a|br)\b/i', $text)) {
            $text = preg_replace('~<(script|style|iframe|object|embed|form)\b[^>]*>.*?</\1\s*>~is', '', $text) ?? '';
            $text = strip_tags($text, '<p><h2><h3><strong><b><em><i><u><ul><ol><li><blockquote><a><br>');
            $text = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $text) ?? $text;
            $text = preg_replace('/(href\s*=\s*)(["\']?)\s*javascript:[^"\'\s>]*/i', '$1"#"', $text) ?? $text;
            // Linklerde yalnızca http(s), mailto veya site içi göreli adresleri bırak.
            $text = preg_replace_callback('/<a\b([^>]*)>/i', static function ($m) {
                $attrs = $m[1];
                if (preg_match('/href\s*=\s*(["\'])(.*?)\1/i', $attrs, $href)) {
                    $url = trim($href[2]);
                    if (!preg_match('~^(https?://|mailto:|/|#)~i', $url)) return '<a>';
                    return '<a href="' . e($url) . '" rel="nofollow noopener">';
                }
                return '<a>';
            }, $text) ?? $text;
            return $text;
        }
        $html = '';
        $para = [];
        $in_list = false;

        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
            $trim = trim($line);
            if ($trim === '') {
                if ($para) {
                    $html .= '<p>' . e(implode(' ', $para)) . '</p>' . "\n";
                    $para = [];
                }
                if ($in_list) {
                    $html .= '</ul>' . "\n";
                    $in_list = false;
                }
                continue;
            }
            if (str_starts_with($trim, '## ')) {
                if ($para) {
                    $html .= '<p>' . e(implode(' ', $para)) . '</p>' . "\n";
                    $para = [];
                }
                if ($in_list) {
                    $html .= '</ul>' . "\n";
                    $in_list = false;
                }
                $html .= '<h2>' . e(trim(substr($trim, 3))) . '</h2>' . "\n";
                continue;
            }
            if (str_starts_with($trim, '- ')) {
                if ($para) {
                    $html .= '<p>' . e(implode(' ', $para)) . '</p>' . "\n";
                    $para = [];
                }
                if (!$in_list) {
                    $html .= '<ul>' . "\n";
                    $in_list = true;
                }
                $html .= '<li>' . e(trim(substr($trim, 2))) . '</li>' . "\n";
                continue;
            }
            if ($in_list) {
                $html .= '</ul>' . "\n";
                $in_list = false;
            }
            $para[] = $trim;
        }
        if ($para) {
            $html .= '<p>' . e(implode(' ', $para)) . '</p>' . "\n";
        }
        if ($in_list) {
            $html .= '</ul>' . "\n";
        }
        return $html;
    }
}
