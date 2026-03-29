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
    return '/assets/' . ltrim($path, '/');
}

function app_theme_asset(string $path): string
{
    return '/themes/' . app_theme() . '/' . ltrim($path, '/');
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
    return $title . ' | ' . app_theme() . ' PHP App';
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
        <?php

        return;
    }

    if ($theme === 'softing-v2.0') {
        ?>
    <script src="<?= htmlspecialchars(app_theme_asset('assets/js/jquery-3.6.0.min.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="<?= htmlspecialchars(app_theme_asset('assets/js/bootstrap.bundle.min.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="<?= htmlspecialchars(app_theme_asset('assets/js/jquery.appear.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="<?= htmlspecialchars(app_theme_asset('assets/js/jquery.easing.min.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="<?= htmlspecialchars(app_theme_asset('assets/js/jquery.magnific-popup.min.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="<?= htmlspecialchars(app_theme_asset('assets/js/owl.carousel.min.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="<?= htmlspecialchars(app_theme_asset('assets/js/wow.min.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="<?= htmlspecialchars(app_theme_asset('assets/js/count-to.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="<?= htmlspecialchars(app_theme_asset('assets/js/validnavs.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="<?= htmlspecialchars(app_theme_asset('assets/js/main.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
        <?php

        return;
    }

    ?>
    <script src="<?= htmlspecialchars(app_theme_asset('assets/js/jquery-3.6.0.min.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
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
