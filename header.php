<?php
include_once __DIR__ . '/config.php';

if (!function_exists('e')) {
    function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

// Alt klasör sayfaları (ör. blog/) bu değeri '../' olarak ayarlar.
$root = $root ?? '';
$current = ltrim(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$full_title = isset($page_title) && $page_title !== ''
    ? $page_title . ' | ' . $company_name
    : $company_name . ' | ' . $slogan;

$page_meta_desc = $page_meta_desc ?? $meta_desc;
$cart_total_count = function_exists('cart_count') ? cart_count() : 0;

// D-Coin hesabı eski menünün içinde gösterilir. db.php yüklenmeden açılan
// eski sayfalarda da oturumdaki güncel bakiye bilgisi yeterli olur.
$dg_auth_version = defined('DG_AUTH_VERSION') ? (int)DG_AUTH_VERSION : 3;
$dg_logged = !empty($_SESSION['user_id'])
    && (int)($_SESSION['dg_auth_v'] ?? 0) === $dg_auth_version;
$dg_username = $dg_logged ? (string)($_SESSION['username'] ?? 'Hesabım') : '';
$dg_balance = $dg_logged ? (int)($_SESSION['dcoin_balance'] ?? 0) : 0;
$dg_is_admin = $dg_logged && (int)($_SESSION['is_admin'] ?? 0) === 1;

function nav_link($file, $label, $current, $root = '') {
    $active = ($current === $file) ? ' aria-current="page"' : '';
    echo '<a href="' . htmlspecialchars($root . $file, ENT_QUOTES, 'UTF-8') . '" class="nav-item"' . $active . '>' . $label . '</a>';
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($full_title); ?></title>
    <meta name="description" content="<?php echo e($page_meta_desc); ?>">
    <meta name="theme-color" content="#0A1A15">
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?php echo e($full_title); ?>">
    <meta property="og:description" content="<?php echo e($page_meta_desc); ?>">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='8' fill='%230A1A15'/%3E%3Ccircle cx='16' cy='16' r='6' fill='%234FE0A0'/%3E%3C/svg%3E">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500..800&family=Instrument+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo e($root); ?>style.css">
    <?php if (!empty($page_css)): ?>
        <?php foreach ((array)$page_css as $css): ?>
            <link rel="stylesheet" href="<?php echo e($root . $css); ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body class="<?php echo e($body_class ?? ''); ?>">
<a class="skip-link" href="#main">İçeriğe geç</a>

<header id="site-nav">
    <div class="nav-container">
        <a href="<?php echo e($root); ?>index.php" class="brand-logo"><?php echo e($company_name); ?><span class="dot">.</span></a>
        <nav class="nav-links" aria-label="Ana menü">
            <?php nav_link('index.php', 'Ana sayfa', $current, $root); ?>
            <?php nav_link('shop.php', 'Mağaza', $current, $root); ?>
            <a href="<?php echo e($root); ?>index.php#dcoin" class="nav-item">D-Coin</a>
            <?php nav_link('blog/index.php', 'Blog', $current, $root); ?>
            <?php nav_link('sosyal/index.php', 'Sosyal', $current, $root); ?>
            <?php nav_link('about.php', 'Vizyonumuz', $current, $root); ?>
            <?php nav_link('contact.php', 'İletişim', $current, $root); ?>
            <?php if ($dg_logged): ?>
                <?php if ($dg_is_admin): ?>
                    <a href="<?php echo e($root); ?>admin/index.php" class="nav-item">Yönetim</a>
                <?php endif; ?>
                <span class="nav-account">
                    <span class="nav-user"><?php echo e($dg_username); ?></span>
                    <span class="nav-balance"><span data-live-balance><?php echo number_format($dg_balance, 0, ',', '.'); ?></span> D-Coin</span>
                </span>
                <a href="<?php echo e($root); ?>logout.php" class="nav-item">Çıkış</a>
            <?php else: ?>
                <a href="<?php echo e($root); ?>login.php" class="nav-item">Giriş</a>
                <a href="<?php echo e($root); ?>register.php" class="nav-item">Kayıt ol</a>
            <?php endif; ?>
        </nav>
        <a href="<?php echo e($root); ?>cart.php" class="nav-cta nav-cart<?php echo $current === 'cart.php' ? ' is-current' : ''; ?>">
            Sepet
            <span class="nav-cart-count" data-cart-count<?php echo $cart_total_count > 0 ? '' : ' hidden'; ?>><?php echo (int)$cart_total_count; ?></span>
        </a>
    </div>
</header>

<?php if (!empty($_SESSION['flash'])): ?>
    <?php
        $flash_value = $_SESSION['flash'];
        unset($_SESSION['flash']);
        $flash_text = is_array($flash_value)
            ? (string)($flash_value['msg'] ?? '')
            : (string)$flash_value;
    ?>
    <div class="flash" role="status" data-flash>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m4 12 5 5L20 6"/></svg>
        <?php echo e($flash_text); ?>
    </div>
<?php endif; ?>

<main id="main">
