        <div class="wrap">
            <div class="cta" data-reveal>
                <div>
                    <p class="eyebrow">İletişim</p>
                    <h2>Birlikte bir evren kuralım.</h2>
                    <p>
                        Yayıncı, sanatçı, geliştirici ya da sadece iyi bir fikri olan biri — kapımız açık.
                        Projeni anlat; 48 saat içinde dönelim.
                    </p>
                </div>
                <div class="cta-actions">
                    <a class="btn btn-primary"
                       href="https://wa.me/905519578350?text=Merhaba%2C%20Device%20Game%20Studio%20ile%20ileti%C5%9Fime%20ge%C3%A7mek%20istiyorum."
                       target="_blank" rel="noopener">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12.04 2c-5.46 0-9.9 4.44-9.9 9.9 0 1.75.46 3.45 1.32 4.95L2 22l5.3-1.39a9.86 9.86 0 0 0 4.73 1.2h.01c5.46 0 9.9-4.44 9.9-9.9 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2Zm4.52 13.86c-.25-.12-1.47-.72-1.69-.81-.23-.08-.39-.12-.56.13-.17.25-.64.81-.79.97-.14.17-.29.19-.54.06-.25-.12-1.05-.39-2-1.23-.74-.66-1.24-1.47-1.38-1.72-.14-.25-.02-.38.11-.51.11-.11.25-.29.37-.43.12-.14.17-.25.25-.41.08-.17.04-.31-.02-.43-.06-.12-.56-1.34-.76-1.84-.2-.48-.4-.42-.56-.43h-.48c-.17 0-.43.06-.66.31-.22.25-.86.85-.86 2.07 0 1.22.89 2.4 1.01 2.56.12.17 1.75 2.67 4.23 3.74.59.26 1.05.41 1.41.52.59.19 1.13.16 1.56.1.47-.07 1.47-.6 1.68-1.18.21-.58.21-1.08.14-1.18-.06-.11-.22-.17-.47-.29Z"/></svg>
                        WhatsApp'tan yaz
                    </a>
                    <a class="btn btn-ghost" href="mailto:info@devicegame.com">E-posta gönder</a>
                    <a class="btn btn-ghost" href="https://devicegame.com/shop.php" target="_blank" rel="noopener">Mağazayı ziyaret et</a>
                </div>
            </div>
        </div>
    </section>

</main>

<footer class="site-footer">
    <div class="wrap">
        <div class="footer-grid">
            <div class="footer-brand">
                <a href="#" class="brand">device game<span class="dot">.</span><span class="studio-tag">studio</span></a>
                <p>Oyun dünyasının anahtarı. Sınırları zorlayan hikayeler, yeni nesil evrenler.</p>
            </div>

            <nav class="footer-col" aria-label="Site">
                <h2>Studio</h2>
                <a href="#studio">Stüdyo</a>
                <a href="#projeler">Projeler</a>
                <a href="#surec">Süreç</a>
                <a href="#iletisim">İletişim</a>
            </nav>

            <nav class="footer-col" aria-label="Device Game dünyası">
                <h2>Device Game</h2>
                <a href="https://devicegame.com" target="_blank" rel="noopener">Ana site</a>
                <a href="https://devicegame.com/shop.php" target="_blank" rel="noopener">Mağaza</a>
                <a href="https://devicegame.com/about.php" target="_blank" rel="noopener">Vizyonumuz</a>
                <a href="https://devicegame.com/contact.php" target="_blank" rel="noopener">İletişim</a>
            </nav>

            <div class="footer-col">
                <h2>Bize ulaşın</h2>
                <a href="mailto:info@devicegame.com">info@devicegame.com</a>
                <a href="https://wa.me/905519578350" target="_blank" rel="noopener">WhatsApp ile yaz</a>
                <span>Torbalı, İzmir</span>
            </div>
        </div>

        <div class="footer-bottom">
            <p>© <span id="year">2026</span> Device Game Studio. Tüm hakları saklıdır.</p>
            <p>Stüdyo İzmir'de · Oyunlar her yerde</p>
        </div>
    </div>
</footer>

<script>
    // Menü: kaydırınca buzlu cam
    (function () {
        var nav = document.getElementById('site-nav');
        if (!nav) return;
        function onScroll() {
            nav.classList.toggle('scrolled', window.scrollY > 24);
        }
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
    })();

    // Hero: imleci takip eden ışık (yalnızca fare)
    (function () {
        var hero = document.querySelector('.hero');
        if (!hero || !window.matchMedia('(hover: hover)').matches) return;
        var frame = null;
        hero.addEventListener('pointermove', function (ev) {
            if (frame) return;
            frame = requestAnimationFrame(function () {
                var r = hero.getBoundingClientRect();
                hero.style.setProperty('--mx', (ev.clientX - r.left) + 'px');
                hero.style.setProperty('--my', (ev.clientY - r.top) + 'px');
                frame = null;
            });
        });
    })();

    // Kaydırınca beliren içerik
    (function () {
        var items = document.querySelectorAll('[data-reveal]');
        if (!items.length) return;
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches ||
            !('IntersectionObserver' in window)) {
            items.forEach(function (el) { el.classList.add('in'); });
            return;
        }
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('in');
                    io.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -6% 0px' });
        items.forEach(function (el) { io.observe(el); });
    })();

    // Yıl
    (function () {
        var year = document.getElementById('year');
        if (year) year.textContent = String(new Date().getFullYear());
    })();
</script>
</body>
</html>