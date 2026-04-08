<?php

declare(strict_types=1);

// Include layout components
require_once __DIR__ . '/layout/header.php';
require_once __DIR__ . '/layout/sidebar.php';
require_once __DIR__ . '/layout/dashboard.php';

// Include WhatsApp pages
require_once __DIR__ . '/pages_whatsapp.php';

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

    app_render_head('Dashboard');

    // Start dashboard layout
    app_render_dashboard_start($user);
    
    // Render flash messages
    app_render_flash();
    
    // Dashboard content
    ?>
    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-header bg-white border-bottom-0">
                    <h4 class="mb-0">Welcome, <?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?></h4>
                    <p class="text-muted mb-0">Here's what's happening with your account today.</p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                User ID
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                #<?= htmlspecialchars((string) $user['id'], ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Email
                            </div>
                            <div class="h6 mb-0 font-weight-bold text-gray-800">
                                <?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-envelope fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Role
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= htmlspecialchars($user['role'], ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-tag fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Account Status
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                Active
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header bg-white">
                    <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <a href="#" class="btn btn-outline-primary btn-block">
                                <i class="fas fa-book me-2"></i>Access Library
                            </a>
                        </div>
                        <div class="col-md-6 mb-3">
                            <a href="#" class="btn btn-outline-success btn-block">
                                <i class="fas fa-folder me-2"></i>View Cases
                            </a>
                        </div>
                        <div class="col-md-6 mb-3">
                            <a href="#" class="btn btn-outline-info btn-block">
                                <i class="fas fa-comments me-2"></i>Open Chats
                            </a>
                        </div>
                        <div class="col-md-6 mb-3">
                            <a href="/logout" class="btn btn-outline-danger btn-block">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header bg-white">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Activity</h6>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <div class="list-group-item d-flex align-items-center">
                            <i class="fas fa-sign-in-alt text-success me-3"></i>
                            <div>
                                <div class="small text-muted">Today, 10:30 AM</div>
                                <div>You logged in successfully</div>
                            </div>
                        </div>
                        <div class="list-group-item d-flex align-items-center">
                            <i class="fas fa-user-check text-info me-3"></i>
                            <div>
                                <div class="small text-muted">Yesterday, 3:45 PM</div>
                                <div>Profile information updated</div>
                            </div>
                        </div>
                        <div class="list-group-item d-flex align-items-center">
                            <i class="fas fa-bell text-warning me-3"></i>
                            <div>
                                <div class="small text-muted">April 5, 2026</div>
                                <div>New notification received</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    
    // End dashboard layout
    app_render_dashboard_end();
    
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
    // This function is no longer used directly
    // The dashboard layout is now handled by app_page_welcome()
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
    // This function is no longer used directly
    // The dashboard layout is now handled by app_page_welcome()
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
    // This function is no longer used directly
    // The dashboard layout is now handled by app_page_welcome()
}

function app_render_not_found_anada(): void
{
    ?>
    <div class="login-area"><div class="container"><div class="row"><div class="col-lg-4 offset-lg-4"><div class="login-box"><div class="login"><div class="content"><h2>404 - Page Not Found</h2><p>The route does not exist.</p><a href="/" class="btn circle btn-theme effect btn-md">Back Home</a></div></div></div></div></div></div></div>
    <?php
}
