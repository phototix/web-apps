<?php

declare(strict_types=1);

function app_page_home(): void
{
    $user = null;

    try {
        $user = app_current_user();
    } catch (Throwable $exception) {
        $user = null;
    }

    if ($user !== null) {
        app_redirect('/welcome');
    }

    app_render_head('Home');

    $theme = app_theme();

    if ($theme === 'softing-v2.0') {
        app_render_home_softing();
    } elseif ($theme === 'Anada-v2.0') {
        app_render_home_anada();
    } else {
        app_render_home_sasoft();
    }

    app_render_footer();
}

function app_page_login(): void
{
    app_require_guest();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        try {
            if (app_attempt_login($email, $password)) {
                app_flash('success', 'Welcome back.');
                app_redirect('/welcome');
            }

            app_flash('error', 'Invalid email or password.');
        } catch (Throwable $exception) {
            app_flash('error', 'Database connection failed. Check your .env settings and import database/schema.sql first.');
        }

        app_redirect('/login');
    }

    app_render_head('Login');

    $theme = app_theme();

    if ($theme === 'softing-v2.0') {
        app_render_login_softing();
    } elseif ($theme === 'Anada-v2.0') {
        app_render_login_anada();
    } else {
        app_render_login_sasoft();
    }

    app_render_footer();
}

function app_page_register(): void
{
    app_require_guest();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = (string) ($_POST['name'] ?? '');
        $email = (string) ($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        if ($password !== $confirmPassword) {
            app_flash('error', 'Passwords do not match.');
            app_redirect('/register');
        }

        try {
            $result = app_register_user($name, $email, $password);
        } catch (Throwable $exception) {
            app_flash('error', 'Database connection failed. Check your .env settings and import database/schema.sql first.');
            app_redirect('/register');
        }

        if ($result['success'] === true) {
            app_flash('success', 'Your account is ready.');
            app_redirect('/welcome');
        }

        app_flash('error', $result['message']);
        app_redirect('/register');
    }

    app_render_head('Register');

    $theme = app_theme();

    if ($theme === 'softing-v2.0') {
        app_render_register_softing();
    } elseif ($theme === 'Anada-v2.0') {
        app_render_register_anada();
    } else {
        app_render_register_sasoft();
    }

    app_render_footer();
}

function app_page_welcome(): void
{
    app_require_auth();

    $user = app_current_user();

    app_render_head('Welcome');

    $theme = app_theme();

    if ($theme === 'softing-v2.0') {
        app_render_welcome_softing($user);
    } elseif ($theme === 'Anada-v2.0') {
        app_render_welcome_anada($user);
    } else {
        app_render_welcome_sasoft($user);
    }

    app_render_footer();
}

function app_page_logout(): void
{
    app_logout_user();
    session_start();
    app_flash('success', 'You have been logged out.');

    app_redirect('/login');
}

function app_page_not_found(): void
{
    http_response_code(404);
    app_render_head('Not Found');

    $theme = app_theme();

    if ($theme === 'softing-v2.0') {
        app_render_not_found_softing();
    } elseif ($theme === 'Anada-v2.0') {
        app_render_not_found_anada();
    } else {
        app_render_not_found_sasoft();
    }

    app_render_footer();
}

function app_render_home_sasoft(): void
{
    ?>
    <div class="se-pre-con"></div>
    <header>
        <nav class="navbar mobile-sidenav nav-border navbar-sticky navbar-default validnavs no-background navbar-fixed">
            <div class="container d-flex justify-content-between align-items-center">
                <div class="navbar-header">
                    <button type="button" class="navbar-toggle" data-toggle="collapse" data-target="#navbar-menu"><i class="fa fa-bars"></i></button>
                    <a class="navbar-brand" href="/"><img src="<?= htmlspecialchars(app_theme_asset('assets/img/logo.png'), ENT_QUOTES, 'UTF-8') ?>" class="logo" alt="Logo"></a>
                </div>
                <div class="collapse navbar-collapse" id="navbar-menu">
                    <ul class="nav navbar-nav navbar-right"><li><a href="/">Home</a></li><li><a href="/login">Login</a></li><li><a href="/register">Register</a></li></ul>
                </div>
                <div class="attr-right"><div class="attr-nav"><ul><li class="button"><a href="/register">Get Started</a></li></ul></div></div>
            </div>
        </nav>
    </header>
    <div class="banner-area responsive-top-pad circle-shape text-center auto-height bg-shape bg-gray" style="background-image: url(<?= htmlspecialchars(app_theme_asset('assets/img/shape/1.png'), ENT_QUOTES, 'UTF-8') ?>);">
        <div class="container"><div class="content-box"><div class="row align-center"><div class="col-lg-8 offset-lg-2 info"><h2>Theme Ready: <strong>Sasoft-v2.0</strong></h2><p>Switch templates using APP_THEME in your .env file.</p></div></div></div></div>
    </div>
    <?php
}

function app_render_login_sasoft(): void
{
    ?>
    <div class="login-area">
        <div class="container">
            <div class="row"><div class="col-lg-4 offset-lg-4"><div class="login-box"><div class="login"><div class="content">
                <a href="/"><img src="<?= htmlspecialchars(app_theme_asset('assets/img/logo.png'), ENT_QUOTES, 'UTF-8') ?>" alt="Logo"></a>
                <?php app_render_flash(); ?>
                <form action="/login" method="post">
                    <div class="row"><div class="col-lg-12 col-md-12"><div class="form-group"><i class="fas fa-envelope-open"></i> <input class="form-control" name="email" placeholder="Email*" type="email" required></div></div></div>
                    <div class="row"><div class="col-lg-12 col-md-12"><div class="form-group"><i class="fas fa-lock"></i> <input class="form-control" name="password" placeholder="Password*" type="password" required></div></div></div>
                    <div class="col-lg-12 col-md-12"><div class="row"><button type="submit">Login</button></div></div>
                </form>
                <div class="sign-up"><p>Don't have an account? <a href="/register">Sign up now</a></p></div>
            </div></div></div></div></div>
        </div>
    </div>
    <?php
}

function app_render_register_sasoft(): void
{
    ?>
    <div class="login-area">
        <div class="container">
            <div class="row"><div class="col-lg-4 offset-lg-4"><div class="login-box"><div class="login"><div class="content">
                <a href="/"><img src="<?= htmlspecialchars(app_theme_asset('assets/img/logo.png'), ENT_QUOTES, 'UTF-8') ?>" alt="Logo"></a>
                <?php app_render_flash(); ?>
                <form action="/register" method="post">
                    <div class="row"><div class="col-lg-12 col-md-12"><div class="form-group"><i class="fas fa-user"></i> <input class="form-control" name="name" placeholder="Name*" type="text" required></div></div></div>
                    <div class="row"><div class="col-lg-12 col-md-12"><div class="form-group"><i class="fas fa-envelope-open"></i> <input class="form-control" name="email" placeholder="Email*" type="email" required></div></div></div>
                    <div class="row"><div class="col-lg-12 col-md-12"><div class="form-group"><i class="fas fa-lock"></i> <input class="form-control" name="password" placeholder="Password*" type="password" required></div></div></div>
                    <div class="row"><div class="col-lg-12 col-md-12"><div class="form-group"><i class="fas fa-lock"></i> <input class="form-control" name="confirm_password" placeholder="Confirm Password*" type="password" required></div></div></div>
                    <div class="col-lg-12 col-md-12"><div class="row"><button type="submit">Register</button></div></div>
                </form>
                <div class="sign-up"><p>Already have an account? <a href="/login">Login Now</a></p></div>
            </div></div></div></div></div>
        </div>
    </div>
    <?php
}

function app_render_welcome_sasoft(array $user): void
{
    ?>
    <header><nav class="navbar mobile-sidenav nav-border navbar-sticky navbar-default validnavs"><div class="container d-flex justify-content-between align-items-center"><div class="navbar-header"><a class="navbar-brand" href="/welcome"><img src="<?= htmlspecialchars(app_theme_asset('assets/img/logo.png'), ENT_QUOTES, 'UTF-8') ?>" class="logo" alt="Logo"></a></div><div class="attr-right"><div class="attr-nav"><ul><li class="button"><a href="/logout">Logout</a></li></ul></div></div></div></nav></header>
    <div class="breadcrumb-area shadow dark bg-cover text-center text-light" style="background-image: url(<?= htmlspecialchars(app_theme_asset('assets/img/banner/1.jpg'), ENT_QUOTES, 'UTF-8') ?>);"><div class="container"><h1>Welcome, <?= htmlspecialchars((string) $user['name'], ENT_QUOTES, 'UTF-8') ?></h1></div></div>
    <div class="features-area default-padding bottom-less"><div class="container"><?php app_render_flash(); ?><div class="row"><div class="col-lg-4"><div class="item"><h4>User ID</h4><p>#<?= htmlspecialchars((string) $user['id'], ENT_QUOTES, 'UTF-8') ?></p></div></div><div class="col-lg-4"><div class="item"><h4>Email</h4><p><?= htmlspecialchars((string) $user['email'], ENT_QUOTES, 'UTF-8') ?></p></div></div><div class="col-lg-4"><div class="item"><h4>Role</h4><p><?= htmlspecialchars((string) $user['role'], ENT_QUOTES, 'UTF-8') ?></p></div></div></div><div class="text-center" style="margin-top: 24px;"><a href="/logout" class="btn circle btn-theme effect btn-md">Logout</a></div></div></div>
    <?php
}

function app_render_not_found_sasoft(): void
{
    ?>
    <div class="error-page-area default-padding"><div class="container"><div class="row align-center"><div class="col-lg-6"><div class="error-box"><h1>404</h1><h2>Sorry page was not found!</h2><a href="/" class="btn circle btn-theme effect btn-md">Back Home</a></div></div></div></div></div>
    <?php
}

function app_render_home_softing(): void
{
    ?>
    <header id="home">
        <nav class="navbar mobile-sidenav navbar-sticky navbar-default validnavs navbar-fixed dark no-background">
            <div class="container d-flex justify-content-between align-items-center">
                <div class="navbar-header"><a class="navbar-brand" href="/"><img src="<?= htmlspecialchars(app_theme_asset('assets/img/logo.png'), ENT_QUOTES, 'UTF-8') ?>" class="logo" alt="Logo"></a></div>
                <div class="collapse navbar-collapse" id="navbar-menu"><ul class="nav navbar-nav navbar-center"><li><a href="/">Home</a></li><li><a href="/login">Login</a></li><li><a href="/register">Register</a></li></ul></div>
                <div class="attr-right"><div class="attr-nav"><ul><li class="button"><a href="/register">try it free</a></li></ul></div></div>
            </div>
        </nav>
    </header>
    <div class="banner-area content-double shape-line bg-theme-small normal-text"><div class="box-table"><div class="box-cell"><div class="container"><div class="row align-center"><div class="col-lg-5 left-info"><div class="content"><h1>Theme Ready: <span>softing-v2.0</span></h1><p>Set APP_THEME in .env to switch templates instantly.</p><a class="btn circle btn-theme border btn-md" href="/login">Login</a></div></div><div class="col-lg-7 right-info"><img src="<?= htmlspecialchars(app_theme_asset('assets/img/app/app-4.png'), ENT_QUOTES, 'UTF-8') ?>" alt="Thumb"></div></div></div></div></div></div>
    <?php
}

function app_render_login_softing(): void
{
    ?>
    <div class="login-area">
        <div class="container"><div class="row"><div class="col-lg-4 offset-lg-4"><div class="login-box"><div class="login"><div class="content">
            <a href="/"><img src="<?= htmlspecialchars(app_theme_asset('assets/img/logo.png'), ENT_QUOTES, 'UTF-8') ?>" alt="Logo"></a>
            <?php app_render_flash(); ?>
            <form action="/login" method="post">
                <div class="row"><div class="col-lg-12"><div class="form-group"><input class="form-control" name="email" placeholder="Email*" type="email" required></div></div></div>
                <div class="row"><div class="col-lg-12"><div class="form-group"><input class="form-control" name="password" placeholder="Password*" type="password" required></div></div></div>
                <div class="row"><div class="col-lg-12"><button type="submit">Login</button></div></div>
            </form>
            <div class="sign-up"><p>Don't have an account? <a href="/register">Sign up now</a></p></div>
        </div></div></div></div></div></div>
    </div>
    <?php
}

function app_render_register_softing(): void
{
    ?>
    <div class="login-area">
        <div class="container"><div class="row"><div class="col-lg-4 offset-lg-4"><div class="login-box"><div class="login"><div class="content">
            <a href="/"><img src="<?= htmlspecialchars(app_theme_asset('assets/img/logo.png'), ENT_QUOTES, 'UTF-8') ?>" alt="Logo"></a>
            <?php app_render_flash(); ?>
            <form action="/register" method="post">
                <div class="row"><div class="col-lg-12"><div class="form-group"><input class="form-control" name="name" placeholder="Name" type="text" required></div></div></div>
                <div class="row"><div class="col-lg-12"><div class="form-group"><input class="form-control" name="email" placeholder="Email*" type="email" required></div></div></div>
                <div class="row"><div class="col-lg-12"><div class="form-group"><input class="form-control" name="password" placeholder="Password*" type="password" required></div></div></div>
                <div class="row"><div class="col-lg-12"><div class="form-group"><input class="form-control" name="confirm_password" placeholder="Confirm Password*" type="password" required></div></div></div>
                <div class="row"><div class="col-lg-12"><button type="submit">Register</button></div></div>
            </form>
            <div class="sign-up"><p>Already have an account? <a href="/login">Login now</a></p></div>
        </div></div></div></div></div></div>
    </div>
    <?php
}

function app_render_welcome_softing(array $user): void
{
    ?>
    <header id="home"><nav class="navbar mobile-sidenav navbar-sticky navbar-default validnavs navbar-fixed dark no-background"><div class="container d-flex justify-content-between align-items-center"><div class="navbar-header"><a class="navbar-brand" href="/welcome"><img src="<?= htmlspecialchars(app_theme_asset('assets/img/logo.png'), ENT_QUOTES, 'UTF-8') ?>" class="logo" alt="Logo"></a></div><div class="attr-right"><div class="attr-nav"><ul><li class="button"><a href="/logout">Logout</a></li></ul></div></div></div></nav></header>
    <div class="about-area default-padding"><div class="container"><?php app_render_flash(); ?><h2>Welcome, <?= htmlspecialchars((string) $user['name'], ENT_QUOTES, 'UTF-8') ?></h2><p><strong>Email:</strong> <?= htmlspecialchars((string) $user['email'], ENT_QUOTES, 'UTF-8') ?></p><p><strong>Role:</strong> <?= htmlspecialchars((string) $user['role'], ENT_QUOTES, 'UTF-8') ?></p><a href="/logout" class="btn circle btn-theme effect btn-md">Logout</a></div></div>
    <?php
}

function app_render_not_found_softing(): void
{
    ?>
    <div class="login-area"><div class="container"><div class="row"><div class="col-lg-4 offset-lg-4"><div class="login-box"><div class="login"><div class="content"><h2>404 - Page Not Found</h2><p>The route does not exist.</p><a href="/" class="btn circle btn-theme effect btn-md">Back Home</a></div></div></div></div></div></div></div>
    <?php
}

function app_render_home_anada(): void
{
    ?>
    <div class="se-pre-con"></div>
    <header>
        <nav class="navbar mobile-sidenav navbar-sticky navbar-default validnavs navbar-fixed dark no-background navbar-style-one">
            <div class="container d-flex justify-content-between align-items-center">
                <div class="navbar-header"><a class="navbar-brand" href="/"><img src="<?= htmlspecialchars(app_theme_asset('assets/img/logo-light.png'), ENT_QUOTES, 'UTF-8') ?>" class="logo logo-display" alt="Logo"><img src="<?= htmlspecialchars(app_theme_asset('assets/img/logo.png'), ENT_QUOTES, 'UTF-8') ?>" class="logo logo-scrolled" alt="Logo"></a></div>
                <div class="collapse navbar-collapse" id="navbar-menu"><ul class="nav navbar-nav navbar-center"><li><a href="/">Home</a></li><li><a href="/login">Login</a></li><li><a href="/register">Register</a></li></ul></div>
            </div>
        </nav>
    </header>
    <div class="banner-area text-combo top-pad-90 rectangular-shape bg-light-gradient"><div class="item"><div class="box-table"><div class="box-cell"><div class="container"><h1>Theme Ready: Anada-v2.0</h1><p>Data-style template is now integrated with your auth routes.</p><a class="btn circle btn-theme effect btn-md" href="/login">Login</a></div></div></div></div></div>
    <?php
}

function app_render_login_anada(): void
{
    ?>
    <div class="login-area">
        <div class="container"><div class="row"><div class="col-lg-4 offset-lg-4"><div class="login-box"><div class="login"><div class="content">
            <a href="/"><img src="<?= htmlspecialchars(app_theme_asset('assets/img/logo.png'), ENT_QUOTES, 'UTF-8') ?>" alt="Logo"></a>
            <?php app_render_flash(); ?>
            <form action="/login" method="post">
                <div class="row"><div class="col-lg-12 col-md-12"><div class="form-group"><i class="fas fa-envelope-open"></i> <input class="form-control" name="email" placeholder="Email*" type="email" required></div></div></div>
                <div class="row"><div class="col-lg-12 col-md-12"><div class="form-group"><i class="fas fa-lock"></i> <input class="form-control" name="password" placeholder="Password*" type="password" required></div></div></div>
                <div class="col-lg-12 col-md-12"><div class="row"><button type="submit">Login</button></div></div>
            </form>
            <div class="sign-up"><p>Don't have an account? <a href="/register">Sign up now</a></p></div>
        </div></div></div></div></div></div>
    </div>
    <?php
}

function app_render_register_anada(): void
{
    ?>
    <div class="login-area">
        <div class="container"><div class="row"><div class="col-lg-4 offset-lg-4"><div class="login-box"><div class="login"><div class="content">
            <a href="/"><img src="<?= htmlspecialchars(app_theme_asset('assets/img/logo.png'), ENT_QUOTES, 'UTF-8') ?>" alt="Logo"></a>
            <?php app_render_flash(); ?>
            <form action="/register" method="post">
                <div class="row"><div class="col-lg-12 col-md-12"><div class="form-group"><i class="fas fa-user"></i> <input class="form-control" name="name" placeholder="Name*" type="text" required></div></div></div>
                <div class="row"><div class="col-lg-12 col-md-12"><div class="form-group"><i class="fas fa-envelope-open"></i> <input class="form-control" name="email" placeholder="Email*" type="email" required></div></div></div>
                <div class="row"><div class="col-lg-12 col-md-12"><div class="form-group"><i class="fas fa-lock"></i> <input class="form-control" name="password" placeholder="Password*" type="password" required></div></div></div>
                <div class="row"><div class="col-lg-12 col-md-12"><div class="form-group"><i class="fas fa-lock"></i> <input class="form-control" name="confirm_password" placeholder="Confirm Password*" type="password" required></div></div></div>
                <div class="col-lg-12 col-md-12"><div class="row"><button type="submit">Register</button></div></div>
            </form>
            <div class="sign-up"><p>Already have an account? <a href="/login">Login now</a></p></div>
        </div></div></div></div></div></div>
    </div>
    <?php
}

function app_render_welcome_anada(array $user): void
{
    ?>
    <header><nav class="navbar mobile-sidenav navbar-sticky navbar-default validnavs navbar-fixed dark no-background navbar-style-one"><div class="container d-flex justify-content-between align-items-center"><div class="navbar-header"><a class="navbar-brand" href="/welcome"><img src="<?= htmlspecialchars(app_theme_asset('assets/img/logo.png'), ENT_QUOTES, 'UTF-8') ?>" class="logo" alt="Logo"></a></div><div class="attr-right"><div class="attr-nav"><ul><li class="button"><a href="/logout">Logout</a></li></ul></div></div></div></nav></header>
    <div class="about-area default-padding"><div class="container"><?php app_render_flash(); ?><h2>Welcome, <?= htmlspecialchars((string) $user['name'], ENT_QUOTES, 'UTF-8') ?></h2><p><strong>Email:</strong> <?= htmlspecialchars((string) $user['email'], ENT_QUOTES, 'UTF-8') ?></p><p><strong>Role:</strong> <?= htmlspecialchars((string) $user['role'], ENT_QUOTES, 'UTF-8') ?></p><a href="/logout" class="btn circle btn-theme effect btn-md">Logout</a></div></div>
    <?php
}

function app_render_not_found_anada(): void
{
    ?>
    <div class="login-area"><div class="container"><div class="row"><div class="col-lg-4 offset-lg-4"><div class="login-box"><div class="login"><div class="content"><h2>404 - Page Not Found</h2><p>The route does not exist.</p><a href="/" class="btn circle btn-theme effect btn-md">Back Home</a></div></div></div></div></div></div></div>
    <?php
}
