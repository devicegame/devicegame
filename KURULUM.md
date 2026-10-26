# DeviceGame — Veritabansız Kurulum Rehberi

> **Dosyaları yükle, siteyi aç, çalışsın.** MySQL, phpMyAdmin, SQL içe aktarma ve veritabanı şifresi yok.

DeviceGame'in D-Coin sistemi artık **Vanilla PHP + JSON dosya depolama** ile çalışır. Tüm uygulama verisi tek dosyada tutulur:

```text
storage/dg/database.json
```

Bu dosya ilk sayfa açılışında otomatik oluşturulur. Kullanıcı ve video kayıtlarının yanında, eski mağazanın `storage/products.json` içindeki gerçek ürün kataloğu D-Coin mağazasına otomatik aktarılır; uydurma örnek ürün kullanılmaz.

---

## 1) Gereksinimler

| Gereksinim | Not |
|---|---|
| PHP 8.1+ önerilir | PHP 8.0 da çalışır |
| PHP `json` eklentisi | Zorunlu (neredeyse her hostingde açık) |
| PHP `session` | Zorunlu |
| `mbstring` | Zorunlu değil; yoksa fallback kullanılır |
| Yazma izni | `storage/dg/` ve `uploads/` için |
| Apache veya Nginx | Apache'de hazır `.htaccess` koruması var |

**Gerekmez:** MySQL, MariaDB, PDO, `pdo_mysql`, Composer, Node.js.

---

## 2) Dosyaları Yükle

Proje dosyalarını hostingindeki `public_html` (veya alan adının kök klasörü) içine yükle.

Eski tasarım ve dosya tabanlı mağaza (`config.php`, `header.php`, `style.css`, `shop.php`, `product.php`, `cart.php`, `admin.php` vb.) sitenin temelidir. D-Coin katmanı aynı tasarım ve SEO bilgileriyle bu yapının üzerine eklenir; eski katalog değiştiğinde ürünler D-Coin vitrinine de eşitlenir.

### Eski MySQL sürümünden geliyorsan

Şunlar artık kullanılmaz ve hostingden silinebilir:

```text
schema.sql
schema_hosting.sql
db_config.php
db_config.example.php
test_db.php
hata_gor.php
hata2.php
```

> ⚠️ Eski MySQL veritabanındaki kullanıcılar/bakiyeler otomatik taşınmaz. Bu sürüm temiz kurulumla başlar. Canlıda korunması gereken eski verin varsa önce yedeğini al.

---

## 3) Klasör İzinlerini Ver

```text
uploads/      755 veya 775
storage/      755 veya 775
storage/dg/   755 veya 775
```

`storage/dg/database.json` ilk açılışta oluşur. Dosya oluşmuşsa izni genellikle:

```text
644 veya 664
```

PHP'nin bu dosyayı okuyup yazabilmesi yeterlidir.

---

## 4) Siteyi Aç

Tarayıcıda ana sayfayı aç:

| Adres | Beklenen |
|---|---|
| `/` veya `/index.php` | Ana sayfa, video oynatıcı ve mağaza |
| `/login.php` | Giriş formu |
| `/register.php` | Kayıt formu |
| `/admin/index.php` | Admin paneli (yalnızca admin) |
| `/diagnose.php` | Kurulum teşhisi |

İlk açılışta `storage/dg/database.json` yoksa sistem otomatik oluşturur. Ek bir kurulum sihirbazı yoktur.

---

## 5) İlk Hesaplar

| Rol | E-posta | Şifre | Başlangıç bakiyesi |
|---|---|---|---:|
| Admin | `admin@devicegame.com` | `Admin123!` | 1000 D-Coin |
| Oyuncu | `oyuncu1@devicegame.com` | `Admin123!` | 250 D-Coin |

### Kendi admin hesabını kullan (önerilen)

Örnek admin hesabı herkese açık repoda yer aldığı için canlı kullanımda şu adımları uygula:

1. Kendi kullanıcı hesabını `/register.php` üzerinden oluştur.
2. Örnek admin ile giriş yap.
3. `/admin/users.php` içinde kendi hesabını **Admin Yap**.
4. Kendi hesabınla giriş yap.
5. Kullanıcı yönetiminde örnek `admin` hesabını **Ban**la.

---

## 6) Akışı Test Et

1. Giriş yap.
2. Ana sayfada bir reklam seçip oynat.
3. Videoyu sonuna kadar izle. İleri sarma kapalıdır; sekme değiştirirsen video durur.
4. Video bitince D-Coin otomatik eklenir ve sağ üst bakiye güncellenir.
5. Mağazadan **D-Coin ile Al** de. Key stoklu ürünlerde key teslim edilir; eski katalogdan adet stokla gelen ürünlerde teslimatta kullanılacak bir sipariş kodu oluşturulur.
6. `/admin/index.php` içinde istatistiklerin değiştiğini kontrol et.

Varsayılan günlük izleme limiti **5**'tir. Admin → Dashboard → Hızlı Ayar bölümünden değiştirilir.

---

## 7) Admin Paneli

| Yol | Açıklama |
|---|---|
| `/admin/index.php` | Toplam kullanıcı, dolaşımdaki/dağıtılan D-Coin, izlenen reklam, satılan ürün |
| `/admin/ads.php` | Reklam ekle/düzenle/sil, aktif/pasif yap, URL veya `.mp4/.webm` yükle |
| `/admin/users.php` | Kullanıcı ara, D-Coin ekle/çıkar, ban/unban, admin yap/al |
| `/admin/store.php` | Eski katalog ürünleri + D-Coin ürünleri, TL/D-Coin fiyatı, **adet stok veya tek tek key** yönetimi |
| `/admin/logs.php` | İzleme ve satın alma logları, arama, CSV export |

---

## 8) Veri Dosyası ve Yedekleme

Tüm D-Coin sistemi verisi şu dosyadadır:

```text
storage/dg/database.json
```

İçinde şu koleksiyonlar bulunur:

- `users`
- `ads`
- `watch_logs`
- `watch_sessions`
- `store_items`
- `store_keys`
- `purchases`
- `settings`

### Yedek almak

`database.json` dosyasını kopyalaman yeterlidir. Geri yüklemek için aynı yola geri koy.

### Güncelleme yaparken dikkat

Kod dosyalarını güncellerken hostingdeki mevcut şu dosyayı ezme/silme:

```text
storage/dg/database.json
```

Repo bu dosyayı takip etmez; her hosting kurulumunun kendi canlı verisi ayrıdır.

### Sıfırdan başlamak

1. Önce `database.json` dosyasının yedeğini al.
2. Dosyayı sil.
3. Siteyi aç. Sistem başlangıç kullanıcı/video verileriyle yeni dosya oluşturur ve eski mağaza kataloğunu yeniden aktarır.

> Dosyayı elle düzenlersen geçerli JSON bıraktığından emin ol. Bozuk JSON'da sistem veriyi kendiliğinden silmez; hata gösterir.

---

## 9) Güvenlik

- Kullanıcı şifreleri düz metin tutulmaz; `password_hash` (bcrypt) kullanılır.
- `database.json` şifre hash'lerini ve ürün keylerini içerir; webden erişilemez olmalıdır.
- Apache için `storage/.htaccess` ve `storage/dg/.htaccess` tüm istekleri reddeder.
- Nginx kullanıyorsan şu kuralı ekle:

```nginx
location ^~ /storage/ {
    deny all;
    return 404;
}
```

- Eşzamanlı bakiye/stok işlemleri `flock()` ile kilitlenir.
- Yazma işlemi geçici dosyaya yazılıp atomik `rename()` ile tamamlanır; yarım JSON bırakılmaz.
- Formlarda CSRF koruması vardır.
- Oturum çerezi `HttpOnly` + `SameSite=Lax` kullanır; HTTPS'de `Secure` otomatik algılanır.
- Video anti-cheat: ileri sarma engeli, `visibilitychange` ile pause ve sunucu tarafında token + süre doğrulaması.

---

## 10) Sorun Giderme

### Önce teşhis sayfası

```text
siteadın.com/diagnose.php
```

Bu sayfa PHP sürümünü, dosyaları, `storage/dg` izinlerini, JSON bütünlüğünü, admin hesabını ve `error_log` son satırlarını kontrol eder.

İşin bitince sil:

```text
siteadın.com/diagnose.php?sil=1
```

### "Depolama klasörü hazır değil"

`storage/`, `storage/dg/` ve `uploads/` izinlerini 755/775 yap. `database.json` varsa 644/664 dene.

### "database.json bozuk görünüyor"

Dosyayı önce yedekle, JSON sözdizimini düzelt. Sıfırlamak istersen yedekten sonra dosyayı sil; site başlangıç verileriyle yeniden oluşturur ve eski ürün kataloğunu tekrar bağlar.

### Hâlâ boş 500

Şu adres gerçek PHP hatasını gösterir:

```text
siteadın.com/index.php?debug=1
```

Ayrıca cPanel → Errors bölümündeki veya `error_log` dosyasındaki son `PHP Fatal error` satırı gerçek sebeptir.

### `uploads/.htaccess` uyarısı

Yeni teşhis yalnızca yorum olmayan gerçek PHP direktiflerini arar. Dosyadaki açıklama metinleri 500'e neden olmaz. Gerçek direktif uyarısı alırsan repodaki güncel `uploads/.htaccess` dosyasını yükle.

---

## 11) Ölçek Notu

Dosya tabanlı sistem küçük/orta ölçekli ve tek sunuculu kurulum için en temiz çözümdür. Her değişiklik tek JSON dosyasını kilitleyip atomik olarak günceller. Çok yüksek eşzamanlı trafik, birden fazla sunucu veya çok büyük log hacmi oluşursa ileride yeniden ilişkisel veritabanına geçiş planlanmalıdır.

---

**İyi oyunlar, iyi kazançlar! ◉ — DeviceGame**
