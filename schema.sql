-- =========================================================
-- DEVICEGAME — Eksiksiz Veritabanı Şeması
-- MySQL 5.7+ / MariaDB 10+ uyumlu
-- Çalıştır: mysql -u root -p < schema.sql
-- =========================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

CREATE DATABASE IF NOT EXISTS devicegame CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE devicegame;

-- ---------------------------------------------------------
-- 1) KULLANICILAR
-- ---------------------------------------------------------
DROP TABLE IF EXISTS purchases;
DROP TABLE IF EXISTS user_watch_logs;
DROP TABLE IF EXISTS watch_sessions;
DROP TABLE IF EXISTS store_keys;
DROP TABLE IF EXISTS store_items;
DROP TABLE IF EXISTS ads;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username        VARCHAR(32)  NOT NULL,
    email           VARCHAR(190) NOT NULL,
    password_hash   VARCHAR(255) NOT NULL,
    dcoin_balance   INT UNSIGNED NOT NULL DEFAULT 0,
    is_admin        TINYINT(1)   NOT NULL DEFAULT 0,
    is_banned       TINYINT(1)   NOT NULL DEFAULT 0,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_username (username),
    UNIQUE KEY uq_email (email),
    KEY idx_is_admin (is_admin)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- 2) REKLAMLAR (Video Ads)
-- ---------------------------------------------------------
CREATE TABLE ads (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title           VARCHAR(180) NOT NULL,
    video_url       VARCHAR(500) NOT NULL COMMENT 'Yüklenen .mp4 yolu veya dış URL',
    video_path      VARCHAR(500) DEFAULT NULL COMMENT 'uploads/ altındaki dosya yolu',
    thumbnail_url   VARCHAR(500) DEFAULT NULL,
    reward_dcoin    INT UNSIGNED NOT NULL DEFAULT 10,
    duration_sec    INT UNSIGNED NOT NULL DEFAULT 30 COMMENT 'Video süresi saniye',
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- 3) İZLEME GEÇMİŞİ & LOGLAR
-- ---------------------------------------------------------
CREATE TABLE user_watch_logs (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    ad_id           INT UNSIGNED NOT NULL,
    earned_dcoin    INT UNSIGNED NOT NULL DEFAULT 0,
    watched_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip_address      VARCHAR(45)  DEFAULT NULL,
    user_agent      VARCHAR(255) DEFAULT NULL,
    KEY idx_user (user_id),
    KEY idx_ad (ad_id),
    KEY idx_watched_at (watched_at),
    CONSTRAINT fk_watch_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_watch_ad   FOREIGN KEY (ad_id)   REFERENCES ads(id)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- İzleme oturumları (anti-cheat: başlat → bitir doğrulaması)
CREATE TABLE watch_sessions (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    ad_id           INT UNSIGNED NOT NULL,
    token           CHAR(64)     NOT NULL,
    started_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at    DATETIME     DEFAULT NULL,
    is_completed    TINYINT(1)   NOT NULL DEFAULT 0,
    ip_address      VARCHAR(45)  DEFAULT NULL,
    UNIQUE KEY uq_token (token),
    KEY idx_user_ad (user_id, ad_id),
    CONSTRAINT fk_ws_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_ws_ad   FOREIGN KEY (ad_id)   REFERENCES ads(id)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- 4) MAĞAZA ÜRÜNLERİ
-- ---------------------------------------------------------
CREATE TABLE store_items (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(180) NOT NULL,
    description     TEXT         DEFAULT NULL,
    image_url       VARCHAR(500) DEFAULT NULL,
    price_tl        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    price_dcoin     INT UNSIGNED NOT NULL DEFAULT 0,
    stock           INT          NOT NULL DEFAULT 0 COMMENT 'Otomatik: kalan key sayısı',
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Her ürünün teslim edilebilir key kodları (ayrı tablo)
CREATE TABLE store_keys (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    store_item_id   INT UNSIGNED NOT NULL,
    key_code        VARCHAR(500) NOT NULL,
    is_used         TINYINT(1)   NOT NULL DEFAULT 0,
    used_by         INT UNSIGNED DEFAULT NULL,
    used_at         DATETIME     DEFAULT NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_item (store_item_id),
    KEY idx_is_used (is_used),
    CONSTRAINT fk_key_item FOREIGN KEY (store_item_id) REFERENCES store_items(id) ON DELETE CASCADE,
    CONSTRAINT fk_key_user FOREIGN KEY (used_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- 5) SATIN ALMALAR
-- ---------------------------------------------------------
CREATE TABLE purchases (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    store_item_id   INT UNSIGNED NOT NULL,
    store_key_id    INT UNSIGNED DEFAULT NULL,
    payment_method  ENUM('dcoin','cash') NOT NULL,
    price_paid_tl   DECIMAL(10,2) DEFAULT NULL,
    price_paid_dcoin INT UNSIGNED DEFAULT NULL,
    delivered_key   VARCHAR(500) DEFAULT NULL COMMENT 'Teslim edilen key kopyası',
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_user (user_id),
    KEY idx_item (store_item_id),
    KEY idx_method (payment_method),
    CONSTRAINT fk_pur_user FOREIGN KEY (user_id)       REFERENCES users(id)       ON DELETE CASCADE,
    CONSTRAINT fk_pur_item FOREIGN KEY (store_item_id) REFERENCES store_items(id) ON DELETE CASCADE,
    CONSTRAINT fk_pur_key  FOREIGN KEY (store_key_id)  REFERENCES store_keys(id)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- 6) AYARLAR (günlük limit vs.)
-- ---------------------------------------------------------
CREATE TABLE settings (
    skey   VARCHAR(64)  PRIMARY KEY,
    svalue VARCHAR(500) NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings (skey,svalue) VALUES
('daily_watch_limit','5'),
('site_name','DeviceGame');

SET FOREIGN_KEY_CHECKS=1;

-- =========================================================
-- ÖRNEK VERİLER
-- =========================================================

-- Admin kullanıcı (şifre: Admin123!  — bcrypt)
-- Üretmek için: password_hash('Admin123!', PASSWORD_BCRYPT)
-- NOT: Önceki sürümdeki hash yanlışlıkla "password" kelimesine aitti, düzeltildi.
-- Aşağıdaki hash gerçekten "Admin123!" şifresidir (password_verify ile doğrulandı).
INSERT INTO users (username,email,password_hash,dcoin_balance,is_admin) VALUES
('admin','admin@devicegame.com','$2y$10$4ZK5MehAkI2ZYLCA1tB5K.PgMrc8A7XLtbBzbIqYcbhXDILtI9Y9G',1000,1),
('oyuncu1','oyuncu1@devicegame.com','$2y$10$4ZK5MehAkI2ZYLCA1tB5K.PgMrc8A7XLtbBzbIqYcbhXDILtI9Y9G',250,0);

-- Örnek reklamlar (video_url dış link veya uploads/ yolu olabilir)
INSERT INTO ads (title, video_url, reward_dcoin, duration_sec, is_active) VALUES
('Cyber Hunt — Tanıtım Fragmanı','https://storage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4',15,30,1),
('Neon Drift — Oynanış Videosu','https://storage.googleapis.com/gtv-videos-bucket/sample/ElephantsDream.mp4',20,45,1),
('Project Overcharge — Teaser','https://storage.googleapis.com/gtv-videos-bucket/sample/ForBiggerBlazes.mp4',10,20,1);

-- Örnek mağaza ürünleri
INSERT INTO store_items (name, description, image_url, price_tl, price_dcoin, is_active) VALUES
('Cyberpunk 2077 — Phantom Liberty (Steam)','Steam key. Anında teslimat. Hesabınıza tanımlamaya hazır.', 'https://images.unsplash.com/photo-1511512578047-dfb367046420?w=600&q=80', 799.00, 120, 1),
('Elden Ring — Deluxe Edition','Bandai Namco resmi key. Tüm DLC’ler dahil.', 'https://images.unsplash.com/photo-1538481199705-c710c4e965fc?w=600&q=80', 1299.00, 200, 1),
('Valorant 1000 VP','Riot hesabınıza anında yüklenir. Bölge: TR.', 'https://images.unsplash.com/photo-1542751371-adc38448a05e?w=600&q=80', 349.00, 60, 1),
('Discord Nitro — 1 Ay','Hediye linki olarak teslim. Anında aktifleştirme.', 'https://images.unsplash.com/photo-1612287230202-1ff1d85d1bdf?w=600&q=80', 149.00, 35, 1),
('SteelSeries Mousepad — Device Edition','Gamer mousepad, 400x450mm, kaymaz taban.', 'https://images.unsplash.com/photo-1600861194942-f883de0dfe96?w=600&q=80', 299.00, 50, 1);

-- Her ürün için örnek keyler
INSERT INTO store_keys (store_item_id, key_code) VALUES
(1,'CP77-PH-XXXX-AAAA-0001'),(1,'CP77-PH-XXXX-AAAA-0002'),(1,'CP77-PH-XXXX-AAAA-0003'),
(2,'ELDEN-DLX-YYYY-BBBB-0001'),(2,'ELDEN-DLX-YYYY-BBBB-0002'),
(3,'VP-1000-TR-CCCC-0001'),(3,'VP-1000-TR-CCCC-0002'),(3,'VP-1000-TR-CCCC-0003'),(3,'VP-1000-TR-CCCC-0004'),
(4,'NITRO-1M-DDDD-0001'),(4,'NITRO-1M-DDDD-0002'),
(5,'MOUSEPAD-DEV-EEEE-0001');

-- Stokları güncelle (kullanılmamış key sayısı)
UPDATE store_items si SET stock = (SELECT COUNT(*) FROM store_keys sk WHERE sk.store_item_id=si.id AND sk.is_used=0);

-- =========================================================
-- NOTLAR
-- =========================================================
-- 1) Admin girişi: admin / Admin123!  (e-posta: admin@devicegame.com)
-- 2) Günlük limit: settings tablosundan daily_watch_limit
-- 3) D-Coin: users.dcoin_balance — atomik UPDATE ile artar/azalır
-- 4) Anti-cheat: watch_sessions.token ile süre doğrulaması
