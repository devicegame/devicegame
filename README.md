# Device Game — Mağaza

Siteyle aynı tasarım dilinde, **saf PHP** ile yazılmış mağaza katmanı.
Veritabanı, framework, build adımı yok. Sepet oturumda tutulur, sipariş
WhatsApp'ta hazır bir mesaj olarak açılır.

> **D-Coin sistemi de veritabansızdır:** Kullanıcılar, reklam ödülleri,
> bakiyeler, tek çatı mağaza ve loglar `storage/dg/database.json` içinde
> tutulur. Kurulum ve yedekleme adımları için **[KURULUM.md](KURULUM.md)**
> dosyasına bakın. MySQL/phpMyAdmin gerekmez. D-Coin arayüzleri bu
> sitenin mevcut tasarımını ve `config.php` içindeki SEO bilgilerini
> kullanır; `storage/products.json` içindeki mağaza ürünleri D-Coin
> vitrinine otomatik eşitlenir.

## Gereksinim

- PHP 8.1 veya üzeri (mbstring, session) — kod 8.0 ile de uyumlu
- Yazma izni (sipariş kaydı için `storage/` klasörü otomatik oluşturulur)

Paylaşımlı hostinge kurulum adımları için **[DEPLOY.md](DEPLOY.md)** dosyasına bak.

## Çalıştırma

```bash
php -S 0.0.0.0:8000
```

Ardından `http://localhost:8000/shop.php` adresini aç.
Apache/Nginx üzerinde klasörü doğrudan yayına almak da yeterli.

### PHP kurulu değilse (yerel önizleme)

`tools/` altında PHP'yi WebAssembly içinde çalıştıran küçük bir önizleme
sunucusu var. Bu **sadece geliştirme içindir**, canlı sunucuda gerekmez:

```bash
cd tools && npm install && npm start
```

## Dosyalar

| Dosya | Görev |
| --- | --- |
| `shop.php` | Mağaza vitrini: haftanın seçkisi, arama, kategori filtreleri, sıralama, son bakılanlar, SSS |
| `product.php` | Ürün detayı (`?slug=...`), adet seçimi, benzer ürünler |
| `cart.php` | Sepet, adet güncelleme, sipariş formu, sepete ekleme işlemleri |
| `checkout.php` | Siparişi doğrular, kaydeder, WhatsApp mesajına yönlendirir |
| `order.php` | Sipariş özeti: numara, kalemler, mesajı yeniden açma |
| `blog/index.php` | **Blog** — yazı listesi (`/blog`) |
| `blog/yazi.php` | Blog yazısı (`?slug=...`) |
| `blog.css` | Blog sayfalarına özel stiller |
| `partials/product-card.php` | Tek ürün kartı (vitrin, arama, benzer ürünler) |
| `partials/slider.php` | Mağaza vitrini slider'ı (ana sayfa + mağaza) |
| `partials/dcoin-storefront.php` | Mağaza/ürün sayfalarında ortak D-Coin fiyatı ve satın alma düğmeleri |
| `partials/dcoin-purchase-modal.php` | Satın alma sonucu, sipariş kodu ve WhatsApp paylaşım penceresi |
| `data/products.php` | **Varsayılan ürün kataloğu** (ilk açılışta `storage/products.json`'a kopyalanır) |
| `lib/shop.php` | Sepet, fiyat, CSRF, sipariş, slider ve WhatsApp yardımcıları |
| `shop.css` | Mağazaya özel stiller (mevcut `style.css` üzerine) |
| `config.php` | WhatsApp numarası, adres, panel şifresi, meta bilgileri |
| `admin.php` | Yönetim paneli (şifreli): siparişler, **ürün ekle/sil**, **slider yönetimi**, CSV |
| `lib/admin.php` | Panel yardımcıları: ürün/slider kayıtları, sipariş durumları |
| `lib/art.php` | Ürün posterleri için prosedürel SVG kapak sanatı |

## Sipariş akışı

1. Ürün sepete eklenir (oturum tabanlı, `$_SESSION['cart']`).
2. `cart.php` içindeki formda ad ve e-posta alınır. **Teslimat şekli tektir:
   dijital teslimat.** **Ödeme tercihi tektir: Havale / EFT.**
3. `checkout.php` girdileri ve CSRF jetonunu doğrular.
4. Sipariş `storage/orders.jsonl` dosyasına yazılır (tek satır = tek sipariş).
5. Kullanıcı, sipariş özetini içeren hazır mesajla WhatsApp'a yönlendirilir.

Ödeme sunucuda alınmaz; Havale / EFT bilgileri WhatsApp konuşmasında paylaşılır,
teslimat dijital olarak aynı konuşma üzerinden yapılır.
Kullanıcı WhatsApp'a dönmek isterse `order.php` son siparişin özetini ve
hazır mesaj bağlantısını gösterir.

## Yönetim paneli

`admin.php` adresine panel şifresiyle girilir (`config.php` → `$admin_password`
ya da `DG_ADMIN_PASSWORD` ortam değişkeni). Dört bölüm vardır:

- **Siparişler** — liste, durum güncelleme, arama, CSV indirme.
- **Ürünler** — kataloğa ürün ekleme, düzenleme ve silme. Kayıtlar
  `storage/products.json` dosyasında tutulur; `data/products.php` yalnızca
  ilk açılıştaki varsayılan katalogdur.
- **Slider** — mağaza sayfasındaki vitrin slider'ı: slayt ekleme, düzenleme,
  sıralama, yayına alma/gizleme ve silme. Kayıtlar `storage/slides.json`
  dosyasındadır.
- **Blog** — `/blog` adresinde yayınlanan yazılar: ekleme, düzenleme,
  yayına alma/gizleme ve silme. Kayıtlar `storage/blog.json` dosyasındadır.
  Metin biçimi: boş satır = paragraf, `## ` = başlık, `- ` = liste.
  Aynı yazılar D-Coin yönetim panelindeki `/admin/blog.php` bölümünden
  zengin metin editörüyle de yönetilebilir; iki panel aynı veriyi kullanır.

Mobilde ürün ve sepet sayfalarında sabit bir işlem çubuğu (fiyat + sepete
ekle / siparişi onayla) görünür; WhatsApp balonu bu sayfalarda gizlenir.
`storage/` klasörü `.gitignore` içindedir ve Apache için `.htaccess` ile
dışarıya kapatılır. Nginx kullanıyorsan `location ^~ /storage/ { deny all; }`
satırını ekle ya da klasörü web kökünün dışına taşı.

## Sipariş paneli

`admin.php` adresini aç, şifreyi gir.

- Şifre: `config.php` → `$admin_password` (varsayılan `devicegame`).
  Yayına almadan **mutlaka** değiştir. Alternatif olarak sunucuda
  `DG_ADMIN_PASSWORD` ortam değişkenini tanımla; bu durumda dosyadaki değer
  yok sayılır.
- Panel; toplam sipariş, bekleyen sipariş, ciro ve günün özetini gösterir.
- Her sipariş kartında müşteri bilgisi, ürün kalemleri, teslimat/ödeme tercihi
  ve not bulunur.
- Durumlar: **Yeni → Hazırlanıyor → Tamamlandı / İptal**. Durumlar
  `storage/order-status.json` içinde tutulur, sipariş kaydı değiştirilmez.
- `CSV indir` ile tüm siparişler Excel uyumlu (UTF-8 BOM) dosya olarak iner.
- Sayfa `noindex` ile işaretlidir ve oturum çerezi `HttpOnly` + `SameSite=Lax`.

## Ürün görselleri

Ürünler varsayılan olarak `lib/art.php` ile üretilen SVG kapak sanatını
kullanır (dış dosya gerekmez). Gerçek key-art eklemek istersen:

1. Görseli `img/` klasörüne koy (örn. `img/ea-sports-fc-26.jpg`).
2. `data/products.php` içindeki kayda `'image' => 'img/ea-sports-fc-26.jpg'` ekle.

Dosya yoksa otomatik olarak SVG sanatına geri döner, yani kırık görsel oluşmaz.

## Ürün eklemek / düzenlemek

`data/products.php` içindeki diziye yeni bir kayıt ekle:

```php
[
    'slug'      => 'yeni-urun',
    'name'      => 'Ürün adı',
    'subtitle'  => 'Kısa açıklama',
    'category'  => 'key',        // key | account | gear
    'price'     => 899,           // TL, tam sayı
    'old_price' => 1099,          // yoksa null
    'platform'  => 'Steam',
    'badge'     => 'Yeni',        // yoksa null
    'glyph'     => 'YN',          // poster üzerindeki kısa harf
    'c1'        => '#0F3B2C',     // poster gradyan başlangıcı
    'c2'        => '#2FA377',     // poster gradyan bitişi
    'accent'    => '#D6FFE9',
    'pattern'   => 'grid',        // grid | dots | lines | rings
    'delivery'  => 'Anında teslimat',
    'stock'     => 10,
    'order'     => 13,
    'added_at'  => '2026-10-20',
    'desc'      => 'Uzun açıklama.',
    'features'  => ['Madde 1', 'Madde 2'],
    // 'image'  => 'img/yeni-urun.jpg', // opsiyonel
],
```

WhatsApp numarası `config.php` içindeki `$whatsapp` değişkeninde
(`905519578350`, ülke koduyla, boşluksuz).

## Notlar

- Tüm fiyatlar KDV dahil ve TL olarak tam sayı tutulur (`money()` biçimlendirir).
- Sepette ürün başına üst sınır 10 adet.
- Formlar CSRF jetonu ile korunur; oturum çerezi `HttpOnly` + `SameSite=Lax`.
- JavaScript kapalıyken de sepet ve sipariş akışı çalışır (form gönderimleri).
