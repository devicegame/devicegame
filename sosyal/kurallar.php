<?php
require_once __DIR__ . '/_common.php';

$page_title = 'Topluluk Kuralları — DG Social';
$page_meta_desc = 'DG Social topluluk kuralları: güvenli oyun, saygılı iletişim, spam ve ticaret sınırları, etkinlikler, anketler, D-Coin ödülleri ve moderasyon süreci.';
$page_canonical = 'sosyal/kurallar.php';
$page_og_type = 'article';
$page_og_title = 'DG Social Topluluk Kuralları';
$page_og_desc = $page_meta_desc;
$page_published_time = strtotime('2026-10-27');
$page_modified_time = strtotime('2026-10-27');
$page_article_section = 'DG Social';
$page_css = ['shop.css', 'social.css'];
$body_class = 'shop-page social-page';
include __DIR__ . '/../header.php';
?>

<section class="social-shell is-narrow">
    <header class="social-page-head">
        <p class="eyebrow">DG Social · Beta</p>
        <h1>Topluluk kuralları.</h1>
        <p class="muted">DG Social herkesin okuyabildiği, DeviceGame hesabı olanların yazabildiği bir oyuncu topluluğudur. Bu sayfadaki kurallar gönderiler, yorumlar, profiller, görseller, anketler ve etkinlikler için geçerlidir.</p>
        <p class="muted"><strong>Son güncelleme:</strong> 27 Ekim 2026</p>
    </header>

    <?= social_subnav('rules') ?>
    <?= social_flash_html() ?>

    <nav class="social-rules-toc" aria-label="Kural başlıkları">
        <a href="#saygi-guvenlik">Saygı ve güvenlik</a>
        <a href="#guvenli-oyun">Güvenli oyun</a>
        <a href="#icerik-kalitesi">İçerik kalitesi</a>
        <a href="#spam-ticaret">Spam ve ticaret</a>
        <a href="#etkinlik-anket">Etkinlik ve anketler</a>
        <a href="#dcoin-oduller">D-Coin ödülleri</a>
        <a href="#moderasyon">Moderasyon</a>
        <a href="#itiraz">İtiraz</a>
    </nav>

    <article class="social-rules-document">
        <section id="saygi-guvenlik">
            <h2>1. Saygılı ve güvenli iletişim</h2>
            <ul>
                <li>Fikirleri tartış; kişilere hakaret etme, aşağılama, tehdit etme veya hedef gösterme.</li>
                <li>Nefret söylemi, taciz, ısrarlı rahatsız etme ve ayrımcı içerik yasaktır.</li>
                <li>Başkalarının gerçek adı, adresi, telefonu, e-postası, fotoğrafı veya özel yazışmalarını izinsiz paylaşma.</li>
                <li>Kendinin veya başkasının şifresini, tek kullanımlık kodlarını, ürün anahtarlarını, sipariş kodlarını ve ödeme bilgilerini herkese açık alanda paylaşma.</li>
                <li>Başkasını taklit etme, resmî ekip üyesi gibi davranma veya yanıltıcı profil kullanma.</li>
            </ul>
        </section>

        <section id="guvenli-oyun">
            <h2>2. Güvenli oyun ve hesap güvenliği</h2>
            <ul>
                <li>Hile, aimbot, duvar arkası görüş, açık sömürme (exploit), hesap çalma, zararlı yazılım, keygen veya crack anlatımı ve bağlantıları yasaktır.</li>
                <li>Üçüncü taraf araçların güvenliğini tartışabilirsin; ancak hile veya hizmet şartlarını atlatma yöntemlerini adım adım anlatma.</li>
                <li>DeviceGame ekibi DG Social üzerinden şifreni, ürün anahtarını veya ödeme bilgini istemez. Böyle bir mesaj veya gönderi görürsen şikâyet et.</li>
                <li>Sipariş ve hesap desteği için sosyal akış yerine sitenin resmî iletişim ve mağaza kanallarını kullan.</li>
            </ul>
        </section>

        <section id="icerik-kalitesi">
            <h2>3. İçerik kalitesi ve telif</h2>
            <ul>
                <li>Gönderini doğru konuda, açıklayıcı bir başlıkla ve mümkünse ilgili etiketlerle aç.</li>
                <li>Aynı gönderiyi farklı başlıklarla tekrarlama; mevcut tartışmaya yeni bilgiyle katıl.</li>
                <li>Spoiler içeren başlık kullanma; içerikte gerekiyorsa açık bir spoiler uyarısı ver.</li>
                <li>Yalnızca kullanma hakkına sahip olduğun metin ve görselleri yükle. Kısa alıntılarda kaynağı belirt.</li>
                <li>Yanıltıcı haber, sahte indirim, doğrulanmamış “kesin bilgi” veya başka birinin içeriğini kendi çalışman gibi sunma.</li>
            </ul>
        </section>

        <section id="spam-ticaret">
            <h2>4. Spam, tanıtım ve ticaret</h2>
            <ul>
                <li>Tekrar eden reklam, referans bağlantısı, çekiliş spam’i, yapay etkileşim ve alakasız bağlantı paylaşımı yasaktır.</li>
                <li>Hesap, oyun anahtarı, oyun içi eşya veya yasa dışı hizmet alışverişi DG Social üzerinden yapılamaz. DeviceGame ürünleri için mağazayı kullan.</li>
                <li>Kendi projeni ilgili konuda tanıtabilirsin; projeyle bağını açıkla ve aynı tanıtımı tekrar tekrar paylaşma.</li>
                <li>Sahte çekiliş, ödeme isteme, kripto/bahis yönlendirmesi ve dolandırıcılık şüphesi taşıyan içerikler kaldırılır.</li>
            </ul>
        </section>

        <section id="etkinlik-anket">
            <h2>5. Oyun arkadaşı, etkinlikler ve anketler</h2>
            <ul>
                <li>Oyun arkadaşı ve etkinlik ilanlarında açık adres, telefon veya özel konum paylaşma. Buluşma yeri olarak oyun içi lobi gibi herkese uygun bir alan belirt.</li>
                <li>Etkinlik sahibi tarih, oyun ve buluşma bilgisini güncel tutmalı; iptal veya değişiklikleri gönderide açıkça duyurmalıdır.</li>
                <li>“Katılıyorum” bir niyet bildirimidir, ücretli rezervasyon değildir. Resmî bir DeviceGame duyurusu olmadıkça katılımcılardan ücret veya depozito isteme.</li>
                <li>Anketlerde çoklu hesap, bot veya organize manipülasyon kullanma. Kişileri hedef alan, taciz veya ayrımcılık içeren anket seçenekleri yasaktır.</li>
                <li>Oy kullanılan bir anketin seçenekleri, sonucun bütünlüğünü korumak için sonradan değiştirilemez.</li>
            </ul>
        </section>

        <section id="dcoin-oduller">
            <h2>6. D-Coin ve Editör Ödülü</h2>
            <ul>
                <li>Editör Ödülü, yönetimin seçtiği faydalı bir gönderiye tek seferlik verilen isteğe bağlı bir D-Coin ödülüdür; düzenli kazanç veya ödeme garantisi değildir.</li>
                <li>Yöneticiler kendi gönderilerine ödül veremez. Spam, kopya içerik, çoklu hesap ve yapay etkileşim ödüle uygun değildir.</li>
                <li>Ödül suistimali tespit edilirse içerik kaldırılabilir, güven seviyesi düşürülebilir ve hesap site kuralları kapsamında kısıtlanabilir.</li>
                <li>Kullanıcılardan DG Social üzerinden D-Coin, ürün anahtarı veya hesap bilgisi isteme.</li>
            </ul>
        </section>

        <section id="moderasyon">
            <h2>7. Moderasyon nasıl çalışır?</h2>
            <p>Yeni veya düşük güven seviyesindeki hesapların ilk gönderi ve yorumları spam’i önlemek için yayın öncesi onaya gidebilir. Onaylanan içerikler güven oluşurken daha hızlı yayınlanır.</p>
            <ul>
                <li>Sorunlu bir içerikte tartışmayı büyütmek yerine <strong>Şikâyet et</strong> düğmesini kullan.</li>
                <li>İhlale göre içeriğin düzenlenmesi istenebilir; içerik gizlenebilir, silinebilir veya konu yorumlara kilitlenebilir.</li>
                <li>Tekrar eden ya da ciddi ihlallerde güven seviyesi düşürülebilir, hesap geçici veya kalıcı olarak kısıtlanabilir.</li>
                <li>Engelleme özelliği, istemediğin kullanıcının içeriklerini görmemek için kullanılabilir; bu işlem moderasyon şikâyetinin yerine geçmez.</li>
            </ul>
        </section>

        <section id="itiraz">
            <h2>8. İtiraz ve iletişim</h2>
            <p>Bir kararın hata sonucu verildiğini düşünüyorsan kullanıcı adını, içeriğin bağlantısını ve kısa açıklamanı <a href="../contact.php">iletişim sayfasından</a> gönder. Kaldırılan içeriği tekrar paylaşmak veya yeni hesapla kısıtlamayı aşmaya çalışmak itiraz sürecini olumsuz etkiler.</p>
        </section>

        <section id="degisiklik">
            <h2>9. Değişiklikler</h2>
            <p>Beta döneminde topluluk ihtiyaçlarına göre bu kurallar güncellenebilir. Önemli değişiklikler DG Social duyurularında paylaşılır; bu sayfadaki tarih güncellenir.</p>
            <div class="social-rules-actions">
                <a class="btn btn-primary" href="index.php#akis">Akışa dön</a>
                <a class="btn btn-ghost" href="etkinlikler.php">Etkinliklere bak</a>
            </div>
        </section>
    </article>
</section>

<script src="../assets/js/social.js" defer></script>
<?php include __DIR__ . '/../footer.php'; ?>
