# DeviceGame — Full Stack Kurulum Rehberi

> **Tek seferde, hatasız, kopyala-çalıştır** — Vanilla PHP + PDO + MySQL, hantallık yok.

---

## 1) Gereksinimler

| Gereksinim | Not |
|---|---|
| PHP 8.1+ | `pdo_mysql`, `session`, `mbstring` açık olmalı |
| MySQL 5.7+ / MariaDB 10+ | `utf8mb4` desteklemeli |
| Apache veya Nginx | `mod_rewrite` önerilir ama zorunlu değil |
| Yazma izni | `uploads/` klasörü için |

---

## 2) Dosyaları Yükle

Tüm proje dosyalarını hostingindeki `public_html` (veya alan adının kök klasörü) içine at.
`shop.php`, `cart.php`, `admin.php` gibi eski mağaza dosyaları **duruyor** — yeni sistem onlarla çakışmaz.

---

## 3) Veritabanını Kur

### Seçenek A — phpMyAdmin ile (en kolay)
1. Hosting panelinden **phpMyAdmin** aç.
2. Soldan **kendi veritabanını seç** (cPanel'de oluşturduğun, örn: `devicegamer_devicegame`).
3. Üstte **İçe aktar → Dosya seç** de:
   - **Paylaşımlı hosting (cPanel) kullanıyorsan → `schema_hosting.sql`** yükle.
   - Kendi sunucun/local (root yetkin varsa) → `schema.sql` de olur.
4. **Git** de. Solda `users, ads, user_watch_logs, watch_sessions, store_items, store_keys, purchases, settings` tabloları görünmeli.

> ⚠️ Neden iki dosya? `schema.sql` içindeki `CREATE DATABASE` satırı cPanel kullanıcılarında
> yetki hatası verir ve içe aktarım **yarıda kalabilir** (sonuç: boş 500 hatası).
> `schema_hosting.sql` aynı şemadır ama `CREATE DATABASE`/`USE` satırları yoktur; seçili DB'ye kurar.
> Önce cPanel → MySQL Veritabanları'ndan veritabanını + kullanıcıyı oluşturup **kullanıcıyı DB'ye eklemeyi** unutma.

### Seçenek B — SSH ile
```bash
mysql -u KULLANICI -p VERITABANI_ADI < schema.sql
```

`schema.sql` şunları yapar:
- `devicegame` veritabanını oluşturur (varsa kullanır)
- `users`, `ads`, `user_watch_logs`, `watch_sessions`, `store_items`, `store_keys`, `purchases`, `settings` tablolarını kurar
- Örnek admin: **admin@devicegame.com / Admin123!**
- 3 örnek reklam + 5 örnek mağaza ürünü + stok keyleri ekler

---

## 4) Veritabanı Bağlantısını Ayarla

`db.php` dosyasını aç, en üstteki 4 satırı **kendi bilgilerinle** değiştir:

```php
$db_host = getenv('DB_HOST') ?: '127.0.0.1';
$db_name = getenv('DB_NAME') ?: 'devicegame';
$db_user = getenv('DB_USER') ?: 'root';
$db_pass = getenv('DB_PASS') ?: '';
```

### Daha güvenli yöntem (ortam değişkeni)
Hosting panelinde (cPanel → Ortam Değişkenleri) şunları tanımla:
```
DB_HOST=localhost
DB_NAME=uXXXX_devicegame
DB_USER=uXXXX_dguser
DB_PASS=güçlü-şifre
```
Bu durumda `db.php` içindeki varsayılanlar otomatik geçersiz olur, şifre kodda durmaz.

---

## 5) İzinleri Ver

```
uploads/   755 veya 775
storage/   755 veya 775 (varsa)
diğer dosyalar 644
klasörler 755
```

---

## 6) Test Et

| Adres | Beklenen |
|---|---|
| `/` (index.php) | Ana sayfa, video oynatıcı, mağaza |
| `/login.php` | Giriş formu |
| `/register.php` | Kayıt formu |
| `/admin/index.php` | Admin dashboard (sadece admin girebilir) |

### İlk giriş
- **Admin:** `admin@devicegame.com` / `Admin123!`
  - Giriş sonrası sağ üstteki kullanıcı adının yanında admin rozeti yoksa `users` tablosunda `is_admin=1` olduğunu kontrol et.
- **Normal kullanıcı:** `oyuncu1@devicegame.com` / `Admin123!` (veya kendin kayıt ol)

### Akışı dene
1. Giriş yap → Ana sayfada bir reklam seç → Oynat
2. Videoyu **sonuna kadar** izle (ileri sarma kapalı, sekme değiştirirsen durur)
3. Bittiğinde `+D-Coin` otomatik eklenir, bakiye sağ üstte canlı artar
4. Mağazadan **D-Coin ile Al** de → key anında teslim edilir
5. Admin panele gir → Genel Bakış’ta sayıların arttığını gör

---

## 7) Admin Paneli Modülleri

| Yol | Açıklama |
|---|---|
| `/admin/index.php` | Dashboard: toplam üye, dolaşımdaki D-Coin, izlenen reklam, satılan ürün |
| `/admin/ads.php` | Video reklam ekle/düzenle/sil, aktif/pasif toggle, dosya yükle (.mp4) |
| `/admin/users.php` | Kullanıcı ara, D-Coin ekle/çıkar, ban/unban, admin yap/al |
| `/admin/store.php` | Ürün ekle/düzenle/sil, TL + D-Coin fiyatı, stok key listesi yönetimi |
| `/admin/logs.php` | İzleme geçmişi + satın alma geçmişi, arama, CSV indir |

---

## 8) Günlük Limit

Varsayılan **günde 5 video**. Admin → Dashboard → “Hızlı Ayar”dan değiştir.
Değer `settings` tablosunda `daily_watch_limit` olarak tutulur.

---

## 9) Video Yükleme

- **Yöntem 1 — URL:** `admin/ads.php` → Video URL alanına dış link yapıştır (`https://...mp4`)
- **Yöntem 2 — Dosya:** Aynı formda “Video Dosyası Yükle” ile `.mp4/.webm` yükle (max 80MB, `uploads/` klasörüne gider)
- Video süresi alanını **doğru gir** — ödül sadece süre dolunca verilir

---

## 10) Güvenlik Notları

- Şifreler `password_hash` (bcrypt, cost 10) ile saklanır
- Tüm sorgular **prepared statement (PDO)** ile
- CSRF koruması her formda
- Oturum çerezi `HttpOnly` + `SameSite=Lax`
- `is_banned=1` olan kullanıcı otomatik atılır
- `is_admin=1` kontrolü her admin sayfasında
- Video anti-cheat: `visibilitychange` → pause, `seeking` → ileri sarma engeli, süre doğrulaması sunucuda (watch_sessions token)

---

## 11) Sık Karşılaşılan Hatalar

**“Veritabanı bağlantısı kurulamadı”**  
→ `db.php` içindeki `$db_host/$db_name/$db_user/$db_pass` bilgilerini kontrol et, `schema.sql` içe aktarılmış mı bak.

**“Günlük limit doldu”**  
→ Normal. Yarın 00:00’da sıfırlanır (MySQL `CURDATE()` ile).

**“Video oynatılmıyor”**  
→ URL’nin doğrudan `.mp4` olduğundan emin ol (YouTube linki değil). CORS engeli varsa videoyu `uploads/` klasörüne yükle.

**“D-Coin eklenmedi”**  
→ Videoyu sonuna kadar izledin mi? Sekme değiştirmedin mi? Günlük limit dolmuş olabilir. Logları `admin/logs.php` → İzleme Logları’ndan kontrol et.

**“Admin panele giremiyorum”**  
→ `users` tablosunda kendi kaydının `is_admin=1` olduğundan emin ol. Değilse phpMyAdmin’den elle `1` yap.

---

## 12) Canlıya Alma Checklist

- [ ] `db.php` bilgileri dolduruldu
- [ ] `schema.sql` içe aktarıldı
- [ ] `admin@devicegame.com` şifresi değiştirildi (veya yeni admin oluşturuldu)
- [ ] `display_errors` kapalı (canlıda kullanıcıya hata gösterilmemeli)
- [ ] `uploads/` yazılabilir ve `.htaccess` ile korunuyor
- [ ] `storage/` dışarıya kapalı (Apache’de `.htaccess` var, Nginx’te `location ^~ /storage/ { deny all; }`)
- [ ] HTTPS aktif (oturum çerezi `Secure` otomatik algılar)

---

---

## 13) Sorun Giderme: 500 Hatası ve `DB_CONN_FAILED`

### İlk iş: teşhis sayfası (10 saniye)
Site boş 500 veriyorsa guesswork yapma:
1. Tarayıcıda **`siteadın.com/diagnose.php`** aç — her adımı ✅/❌ olarak raporlar.
2. Gerçek PHP hatasını anlık görmek için: **`siteadın.com/index.php?debug=1`**
3. İşin bitince **`siteadın.com/diagnose.php?sil=1`** ile teşhis dosyasını sil.

### Bilinen 5 kök neden (yaşanmış senaryolar)

| Belirti / Hata mesajı | Kök neden | Çözüm |
|---|---|---|
| `Can't connect to server on '127.0.0.1' (115)` | Host yanlış — paylaşımlı hosting 127.0.0.1 TCP'yi kapatır | `db.php`'de host = **`localhost`** |
| `Access denied for user 'x'@'localhost' (using password: YES)` | Şifre yanlış **veya** kullanıcı DB'ye eklenmemiş | cPanel → MySQL: şifreyi sıfırla + "Kullanıcıyı Veritabanına Ekle" → Tüm ayrıcalıklar + `db.php` şifresini güncelle |
| Bağlantı "DB OK" ama site **boş 500** | Tablolar eksik/yanlış DB: sorgu exception fırlatır, eski sürüm bunu gizlerdi | phpMyAdmin'de `db.php`'deki DB'yi seç → İçe aktar → **`schema_hosting.sql`**; yeni `index.php` bu hatada 500 yerine uyarı gösterir |
| Her şey doğru ama **500 (boş)** | `.htaccess` içinde `php_flag`/`php_value` (FPM/CGI'da 500 yapar) **veya** PHP < 8.0 | Repodaki yeni `uploads/.htaccess`'i yükle; cPanel → MultiPHP → **PHP 8.1/8.2** seç |
| Admin girişi `Admin123!` ile olmuyor | Eski şemada seed hash'i yanlışlıkla `password` kelimesine aitti (düzeltildi) | Yeni `schema_hosting.sql`'i içe aktar veya `diagnose.php` 5. bölümde durumuna bak |

### `test_db.php` ile site farklı sonuç veriyorsa
`test_db.php` elle yazdığın şifreyi kullanır, `db.php`'den bağımsızdır. cPanel'de şifreyi sıfırladıysan
test dosyası eski şifreyle denemeye devam eder. **Doğru kaynak `db.php` + `diagnose.php`'dir**; test dosyalarını sil.

### Hâlâ çözülmediyse
cPanel → **Errors** bölümündeki (veya ana dizindeki `error_log` dosyasındaki) son `PHP Fatal error` satırı
gerçek sebeptir. `diagnose.php` 7. bölümde bu satırları otomatik gösterir — o satırı destekle paylaş.

---

**İyi oyunlar, iyi kazançlar! ◉ — DeviceGame**
