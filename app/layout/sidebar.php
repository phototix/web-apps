<?php

declare(strict_types=1);

function app_render_dashboard_sidebar(): void
{
    $currentPath = $_SERVER['REQUEST_URI'] ?? '/welcome';
    $user = app_current_user();
    ?>
    <aside class="dashboard-sidebar">
        <div class="sidebar-sticky pt-3">
            <div class="sidebar-menu">
                <div class="sidebar-header px-3 py-2">
                    <h6 class="text-uppercase text-muted mb-0">Main Menu</h6>
                </div>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link <?= strpos($currentPath, '/welcome') !== false ? 'active' : '' ?>" href="/welcome">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= strpos($currentPath, '/cases') !== false ? 'active' : '' ?>" href="/cases">
                            <i class="fas fa-folder"></i>
                            <span>Cases</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= strpos($currentPath, '/groups') !== false ? 'active' : '' ?>" href="/groups">
                            <i class="fas fa-users"></i>
                            <span>Groups Chats</span>
                        </a>
                    </li>
                    <?php if ($user && $user['role'] !== 'users'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= strpos($currentPath, '/whatsapp-connect') !== false ? 'active' : '' ?>" href="/whatsapp-connect">
                            <i class="fab fa-whatsapp"></i>
                            <span>WhatsApp Connect</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if ($user && in_array($user['role'], ['admin', 'superadmin'])): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= strpos($currentPath, '/admin/my-users') !== false ? 'active' : '' ?>" href="/admin/my-users">
                            <i class="fas fa-user-friends"></i>
                            <span>My Users</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if ($user && $user['role'] === 'superadmin'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= strpos($currentPath, '/admin/users') !== false ? 'active' : '' ?>" href="/admin/users">
                            <i class="fas fa-user-cog"></i>
                            <span>Users</span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            
            <div class="mt-auto p-3">
                <div class="text-muted small">
                    <i class="fas fa-info-circle me-1"></i>
                    ERP.ezy.chat v1.0
                </div>
            </div>
        </div>
    </aside>
    <?php
}