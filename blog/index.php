<?php
require_once __DIR__ . '/../config.php';

$root = '../';
$page_title = 'Blog';
$page_meta_desc = 'Device Game haberleri, geliştirici günlükleri ve stüdyo notları. Project: Nexus yolculuğu ve mağaza duyuruları.';
$page_css = ['shop.css', 'blog.css'];
$body_class = 'shop-page';
include __DIR__ . '/../header.php';

$posts = blog_posts();
$featured = $posts ? $posts[0] : null;
$rest = array_slice($posts, 1);
?>

<section class="shop-section blog-page">
    <header class="blog-head">
        <p class="eyebrow">Blog</p>
        <h1>Haberler &amp; geliştirici günlükleri.</h1>
        <p class="muted">Stüdyodan notlar, proje duyuruları ve perde arkası. Yeni yazılardan haberdar olmak için ara sıra uğra.</p>
    </header>

    <?php if (!$posts): ?>
        <div class="empty-state">
            <h3>Henüz yazı yayınlanmadı.</h3>
            <p>İlk yazılar yakında burada olacak. O zamana kadar mağazaya göz atabilirsin.</p>
            <a class="btn btn-primary" href="<?php echo e($root); ?>shop.php">Mağazaya git</a>
        </div>
    <?php else: ?>
        <a class="blog-feature" href="yazi.php?slug=<?php echo urlencode($featured['slug']); ?>">
            <div class="blog-cover" style="--c1:<?php echo e($featured['c1']); ?>;--c2:<?php echo e($featured['c2']); ?>">
                <?php if ($featured['image'] !== ''): ?>
                    <img class="blog-cover-img" src="<?php echo e(blog_image_url($featured['image'])); ?>" alt="" loading="lazy" decoding="async">
                <?php endif; ?>
                <span class="blog-cover-tag"><?php echo e($featured['tag'] !== '' ? $featured['tag'] : 'Yazı'); ?></span>
                <span class="blog-cover-mark" aria-hidden="true">DG</span>
            </div>
            <div class="blog-feature-body">
                <p class="blog-meta">
                    <span><?php echo e(date('d.m.Y', strtotime($featured['date']))); ?></span>
                    <span class="dot-sep" aria-hidden="true">·</span>
                    <span><?php echo e($featured['tag'] !== '' ? $featured['tag'] : 'Blog'); ?></span>
                </p>
                <h2><?php echo e($featured['title']); ?></h2>
                <p class="muted"><?php echo e($featured['excerpt']); ?></p>
                <span class="text-link">
                    Yazıyı oku
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </span>
            </div>
        </a>

        <?php if ($rest): ?>
            <div class="blog-grid">
                <?php foreach ($rest as $post): ?>
                    <a class="blog-card" href="yazi.php?slug=<?php echo urlencode($post['slug']); ?>">
                        <div class="blog-cover" style="--c1:<?php echo e($post['c1']); ?>;--c2:<?php echo e($post['c2']); ?>">
                            <?php if ($post['image'] !== ''): ?>
                                <img class="blog-cover-img" src="<?php echo e(blog_image_url($post['image'])); ?>" alt="" loading="lazy" decoding="async">
                            <?php endif; ?>
                            <span class="blog-cover-tag"><?php echo e($post['tag'] !== '' ? $post['tag'] : 'Yazı'); ?></span>
                        </div>
                        <div class="blog-card-body">
                            <p class="blog-meta"><span><?php echo e(date('d.m.Y', strtotime($post['date']))); ?></span></p>
                            <h3><?php echo e($post['title']); ?></h3>
                            <p class="muted"><?php echo e($post['excerpt']); ?></p>
                            <span class="text-link">Devamını oku</span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php include __DIR__ . '/../footer.php'; ?>
