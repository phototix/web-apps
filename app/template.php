<?php

declare(strict_types=1);

function app_available_themes(): array
{
    return [
        'Sasoft-v2.0',
        'softing-v2.0',
        'Anada-v2.0',
    ];
}

function app_theme(): string
{
    $configuredTheme = app_env('APP_THEME', 'Sasoft-v2.0') ?? 'Sasoft-v2.0';

    foreach (app_available_themes() as $theme) {
        if (strcasecmp($theme, $configuredTheme) === 0) {
            return $theme;
        }
    }

    return 'Sasoft-v2.0';
}

function app_asset(string $path): string
{
    $assetUrl = '/assets/' . ltrim($path, '/');
    
    // Add cache busting version for CSS and JS files
    if (preg_match('/\.(css|js)$/i', $path)) {
        $assetUrl .= '?v=' . app_asset_version();
    }
    
    return $assetUrl;
}

function app_asset_version(): string
{
    static $version = null;
    
    if ($version === null) {
        $version = '20260416122609'; // Default version, updated by bump_version.php
    }
    
    return $version;
}

function app_theme_asset(string $path): string
{
    $assetUrl = '/themes/' . app_theme() . '/' . ltrim($path, '/');
    
    // Add cache busting version for CSS, JS, and image files
    if (preg_match('/\.(css|js|png|jpg|jpeg|gif|svg|ico)$/i', $path)) {
        $assetUrl .= '?v=' . app_asset_version();
    }
    
    return $assetUrl;
}

function app_redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function app_flash(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function app_pull_flash(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    return $flash;
}

function app_page_title(string $title): string
{
    return $title . ' | ERPSoft-WebbyCMS';
}

function app_render_head(string $title): void
{
    $theme = app_theme();
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Theme: <?= htmlspecialchars($theme, ENT_QUOTES, 'UTF-8') ?>">
    <title><?= htmlspecialchars(app_page_title($title), ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="shortcut icon" href="<?= htmlspecialchars(app_theme_asset('assets/img/favicon.png'), ENT_QUOTES, 'UTF-8') ?>" type="image/x-icon">

    <?php if ($theme === 'Sasoft-v2.0'): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars(app_theme_asset('assets/css/bootstrap.min.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(app_theme_asset('assets/css/font-awesome.min.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(app_theme_asset('assets/css/flaticon-set.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(app_theme_asset('assets/css/themify-icons.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(app_theme_asset('assets/css/magnific-popup.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(app_theme_asset('assets/css/owl.carousel.min.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(app_theme_asset('assets/css/owl.theme.default.min.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(app_theme_asset('assets/css/animate.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(app_theme_asset('assets/css/bootsnav.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(app_theme_asset('style.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(app_theme_asset('assets/css/responsive.css'), ENT_QUOTES, 'UTF-8') ?>">
    <?php elseif ($theme === 'softing-v2.0'): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars(app_theme_asset('assets/css/bootstrap.min.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(app_theme_asset('assets/css/font-awesome.min.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(app_theme_asset('assets/css/elegant-icons.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(app_theme_asset('assets/css/flaticon-set.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(app_theme_asset('assets/css/magnific-popup.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(app_theme_asset('assets/css/owl.carousel.min.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(app_theme_asset('assets/css/owl.theme.default.min.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(app_theme_asset('assets/css/animate.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(app_theme_asset('assets/css/helper.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(app_theme_asset('assets/css/validnavs.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(app_theme_asset('style.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(app_theme_asset('assets/css/responsive.css'), ENT_QUOTES, 'UTF-8') ?>">
    <?php else: ?>
    <link rel="stylesheet" href="<?= htmlspecialchars(app_theme_asset('assets/css/bootstrap.min.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(app_theme_asset('assets/css/font-awesome.min.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(app_theme_asset('assets/css/themify-icons.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(app_theme_asset('assets/css/flaticon-set.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(app_theme_asset('assets/css/magnific-popup.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(app_theme_asset('assets/css/owl.carousel.min.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(app_theme_asset('assets/css/owl.theme.default.min.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(app_theme_asset('assets/css/animate.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(app_theme_asset('assets/css/validnavs.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(app_theme_asset('assets/css/helper.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(app_theme_asset('style.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(app_theme_asset('assets/css/responsive.css'), ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    
    <!-- Dashboard Styles -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .dashboard-wrapper {
            display: flex;
            min-height: 100vh;
        }
        
        .dashboard-sidebar {
            width: 250px;
            min-height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 100;
            background-color: #2c3e50;
            color: #ecf0f1;
            box-shadow: 2px 0 5px rgba(0,0,0,.1);
        }
        
        .dashboard-content {
            flex: 1;
            margin-left: 250px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        /* Fix for chat-box height issue - remove bottom padding from dashboard .py-4 */
        .dashboard-content .py-4 {
            padding-bottom: 0 !important;
        }
        
        .dashboard-header {
            position: sticky;
            top: 0;
            z-index: 99;
            box-shadow: 0 2px 4px rgba(0,0,0,.1);
        }
        
        .dashboard-header .container-fluid {
            padding-left: 1rem;
            padding-right: 1rem;
        }
        
        .dashboard-header .dropdown {
            margin-left: auto;
        }
        
        .dashboard-main {
            flex: 1;
            background-color: #f8f9fa;
        }
        
        .sidebar-sticky {
            position: sticky;
            top: 0;
            height: calc(100vh - 48px);
            padding-top: .5rem;
            overflow-x: hidden;
            overflow-y: auto;
        }
        
        .sidebar-header {
            border-bottom: 1px solid rgba(255,255,255,.1);
            margin-bottom: .5rem;
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            color: #bdc3c7;
            padding: .75rem 1rem;
            text-decoration: none;
            border-left: 3px solid transparent;
            transition: all .2s ease;
            margin: 2px 0;
            width: 100%;
        }
        
        .nav-link:hover {
            color: #fff;
            background-color: rgba(255,255,255,.05);
            border-left-color: #3498db;
        }
        
        .nav-link.active {
            color: #fff;
            background-color: rgba(52, 152, 219, .2);
            border-left-color: #3498db;
            font-weight: 500;
        }
        
        .nav-link i {
            width: 24px;
            font-size: 16px;
            text-align: center;
            margin-right: 12px;
        }
        
        .nav-link span {
            flex: 1;
        }
        
        .nav-item {
            width: 100%;
        }
        
        .avatar-placeholder {
            font-size: 14px;
            font-weight: bold;
        }
        
        @media (max-width: 768px) {
            .dashboard-sidebar {
                width: 70px;
            }
            
            .dashboard-content {
                margin-left: 70px;
            }
            
            .sidebar-header,
            .nav-link span {
                display: none;
            }
            
            .nav-link i {
                margin-right: 0;
                font-size: 18px;
            }
            
            .nav-link {
                justify-content: center;
                padding: 1rem .5rem;
            }
        }
    </style>
</head>
<body>
    <?php
}

function app_render_flash(): void
{
    $flash = app_pull_flash();

    if ($flash === null) {
        return;
    }

    $class = $flash['type'] === 'success' ? 'alert-success' : 'alert-danger';
    ?>
    <div class="alert <?= htmlspecialchars($class, ENT_QUOTES, 'UTF-8') ?>" style="margin-bottom: 20px;">
        <?= htmlspecialchars((string) $flash['message'], ENT_QUOTES, 'UTF-8') ?>
    </div>
    <?php
}

function app_render_scripts(): void
{
    $theme = app_theme();

     if ($theme === 'Sasoft-v2.0') {
        ?>
    <script src="<?= htmlspecialchars(app_theme_asset('assets/js/jquery-3.7.1.min.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="<?= htmlspecialchars(app_theme_asset('assets/js/bootstrap.min.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="<?= htmlspecialchars(app_theme_asset('assets/js/jquery.magnific-popup.min.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="<?= htmlspecialchars(app_theme_asset('assets/js/owl.carousel.min.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="<?= htmlspecialchars(app_theme_asset('assets/js/bootsnav.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="<?= htmlspecialchars(app_theme_asset('assets/js/wow.min.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="<?= htmlspecialchars(app_theme_asset('assets/js/jquery.easing.min.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="<?= htmlspecialchars(app_theme_asset('assets/js/jquery.appear.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="<?= htmlspecialchars(app_theme_asset('assets/js/imagesloaded.pkgd.min.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="<?= htmlspecialchars(app_theme_asset('assets/js/isotope.pkgd.min.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="<?= htmlspecialchars(app_theme_asset('assets/js/progress-bar.min.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="<?= htmlspecialchars(app_theme_asset('assets/js/YTPlayer.min.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="<?= htmlspecialchars(app_theme_asset('assets/js/count-to.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="<?= htmlspecialchars(app_theme_asset('assets/js/main.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <!-- Category Management -->
    <script src="<?= htmlspecialchars(app_asset('js/category-management.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
        <?php

        return;
    }

     if ($theme === 'softing-v2.0') {
        ?>
    <script src="<?= htmlspecialchars(app_theme_asset('assets/js/jquery-3.6.0.min.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="<?= htmlspecialchars(app_theme_asset('assets/js/bootstrap.bundle.min.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="<?= htmlspecialchars(app_theme_asset('assets/js/jquery.appear.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="<?= htmlspecialchars(app_theme_asset('assets/js/jquery.easing.min.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="<?= htmlspecialchars(app_theme_asset('assets/js/jquery.magnific-popup.min.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="<?= htmlspecialchars(app_theme_asset('assets/js/owl.carousel.min.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="<?= htmlspecialchars(app_theme_asset('assets/js/wow.min.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="<?= htmlspecialchars(app_theme_asset('assets/js/count-to.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="<?= htmlspecialchars(app_theme_asset('assets/js/validnavs.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="<?= htmlspecialchars(app_theme_asset('assets/js/main.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <!-- Category Management -->
    <script src="<?= htmlspecialchars(app_asset('js/category-management.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
        <?php

        return;
    }
    ?>

    <script src="<?= htmlspecialchars(app_theme_asset('assets/js/jquery-3.6.0.min.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="<?= htmlspecialchars(app_theme_asset('assets/js/bootstrap.bundle.min.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="<?= htmlspecialchars(app_theme_asset('assets/js/jquery.appear.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="<?= htmlspecialchars(app_theme_asset('assets/js/jquery.easing.min.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="<?= htmlspecialchars(app_theme_asset('assets/js/magnific-popup.min.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="<?= htmlspecialchars(app_theme_asset('assets/js/owl.carousel.min.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="<?= htmlspecialchars(app_theme_asset('assets/js/wow.min.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="<?= htmlspecialchars(app_theme_asset('assets/js/progress-bar.min.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="<?= htmlspecialchars(app_theme_asset('assets/js/isotope.pkgd.min.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="<?= htmlspecialchars(app_theme_asset('assets/js/imagesloaded.pkgd.min.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="<?= htmlspecialchars(app_theme_asset('assets/js/count-to.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="<?= htmlspecialchars(app_theme_asset('assets/js/YTPlayer.min.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="<?= htmlspecialchars(app_theme_asset('assets/js/circle-progress.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="<?= htmlspecialchars(app_theme_asset('assets/js/validnavs.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="<?= htmlspecialchars(app_theme_asset('assets/js/main.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <!-- Category Management -->
    <script src="<?= htmlspecialchars(app_asset('js/category-management.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <?php
}

function app_render_footer(): void
{
    app_render_scripts();
    ?>
</body>
</html>
    <?php
}
