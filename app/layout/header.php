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
                        <li><a class="dropdown-item" href="/settings"><i class="fas fa-cog me-2"></i>Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li class="dropdown-header">Case Exports</li>
                        <li id="case-export-empty"><span class="dropdown-item-text text-muted">No case exports yet.</span></li>
                        <li id="case-export-divider"><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="/logout"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const dropdownToggle = document.getElementById('userDropdown');
        const emptyItem = document.getElementById('case-export-empty');
        const dividerItem = document.getElementById('case-export-divider');

        function clearCaseExportItems() {
            if (!dividerItem) return;
            const menu = dividerItem.closest('.dropdown-menu');
            if (!menu) return;
            const existing = menu.querySelectorAll('.case-export-item');
            existing.forEach(item => item.remove());
        }

        function renderCaseExports(notifications) {
            if (!dividerItem || !emptyItem) return;
            const menu = dividerItem.closest('.dropdown-menu');
            if (!menu) return;

            clearCaseExportItems();

            if (!notifications || notifications.length === 0) {
                emptyItem.classList.remove('d-none');
                return;
            }

            emptyItem.classList.add('d-none');
            notifications.forEach(item => {
                const data = item.data || {};
                const groupName = data.group_name || 'Case';
                const createdAt = item.created_at ? new Date(item.created_at) : null;
                const timeText = createdAt ? createdAt.toLocaleString() : '';

                const li = document.createElement('li');
                li.className = 'case-export-item';

                if (item.update_type === 'case_export_ready' && data.download_url) {
                    const link = document.createElement('a');
                    link.className = 'dropdown-item';
                    link.href = data.download_url;
                    link.innerHTML = '';

                    const title = document.createElement('div');
                    title.className = 'fw-semibold';
                    title.textContent = `Export ready: ${groupName}`;

                    link.appendChild(title);
                    if (timeText) {
                        const time = document.createElement('small');
                        time.className = 'text-muted';
                        time.textContent = timeText;
                        link.appendChild(time);
                    }
                    li.appendChild(link);
                } else {
                    const text = document.createElement('div');
                    text.className = 'dropdown-item-text text-danger';
                    text.innerHTML = '';

                    const title = document.createElement('div');
                    title.className = 'fw-semibold';
                    title.textContent = `Export failed: ${groupName}`;
                    text.appendChild(title);

                    if (data.error_message) {
                        const detail = document.createElement('small');
                        detail.className = 'text-muted d-block';
                        detail.textContent = data.error_message;
                        text.appendChild(detail);
                    }
                    if (timeText) {
                        const time = document.createElement('small');
                        time.className = 'text-muted d-block';
                        time.textContent = timeText;
                        text.appendChild(time);
                    }

                    li.appendChild(text);
                }

                menu.insertBefore(li, dividerItem);
            });
        }

        function loadCaseExportNotifications() {
            fetch('/api/cases/exports/notifications')
                .then(response => response.json())
                .then(data => {
                    if (data && data.success && data.data && Array.isArray(data.data.notifications)) {
                        renderCaseExports(data.data.notifications);
                    }
                })
                .catch(() => {
                    // Ignore errors silently to avoid breaking dropdown
                });
        }

        if (dropdownToggle) {
            dropdownToggle.addEventListener('show.bs.dropdown', loadCaseExportNotifications);
        }
    });
    </script>
    <?php
}
