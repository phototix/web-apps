<?php

declare(strict_types=1);

function app_render_dashboard_header(array $user): void
{
    ?>
    <header class="dashboard-header">
        <nav class="navbar navbar-light bg-white border-bottom py-2">
            <div class="container-fluid d-flex justify-content-between align-items-center">
                <a class="navbar-brand" href="/welcome">
                    <img src="<?= htmlspecialchars(app_theme_asset('assets/img/logo.png'), ENT_QUOTES, 'UTF-8') ?>" alt="Logo" height="40">
                </a>
                
                <div class="dropdown">
                    <button class="btn btn-light dropdown-toggle d-flex align-items-center" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="me-2">
                            <div class="avatar-placeholder bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                <?= strtoupper(substr($user['name'], 0, 1)) ?>
                            </div>
                        </div>
                        <div class="text-start">
                            <div class="fw-bold"><?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?></div>
                            <small class="text-muted"><?= htmlspecialchars($user['role'], ENT_QUOTES, 'UTF-8') ?></small>
                        </div>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                        <li><a class="dropdown-item" href="#"><i class="fas fa-cog me-2"></i>Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="/logout"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>
    <?php
}