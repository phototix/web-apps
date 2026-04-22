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
        $otp = trim((string) ($_POST['otp'] ?? ''));

        try {
            if (app_attempt_login($email, $password, $otp)) {
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
        $inviteCode = (string) ($_POST['invite_code'] ?? '');

        if ($password !== $confirmPassword) {
            app_flash('error', 'Passwords do not match.');
            app_redirect('/register');
        }

        try {
            // Pass invite code to registration function
            // The function will handle role assignment based on invite code
            $result = app_register_user($name, $email, $password, 'admin', $inviteCode);
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

function app_page_webbycloud_connect(): void
{
    $config = app_webbycloud_config();
    if (!app_webbycloud_validate_config($config)) {
        app_flash('error', 'WebbyCloud configuration is missing.');
        app_redirect('/login');
    }

    $state = bin2hex(random_bytes(16));
    $_SESSION['webbycloud_oauth_state'] = $state;

    $user = app_current_user();
    if ($user !== null) {
        $_SESSION['webbycloud_link_user_id'] = (int) $user['id'];
        unset($_SESSION['webbycloud_login']);
    } else {
        $_SESSION['webbycloud_login'] = true;
        unset($_SESSION['webbycloud_link_user_id']);
    }

    $authorizeUrl = app_webbycloud_authorize_url($config, $state);
    app_redirect($authorizeUrl);
}

function app_page_webbycloud_callback(): void
{
    $config = app_webbycloud_config();
    if (!app_webbycloud_validate_config($config)) {
        app_flash('error', 'WebbyCloud configuration is missing.');
        app_redirect('/login');
    }

    $expectedState = (string) ($_SESSION['webbycloud_oauth_state'] ?? '');
    unset($_SESSION['webbycloud_oauth_state']);

    $state = trim((string) ($_GET['state'] ?? ''));
    $code = trim((string) ($_GET['code'] ?? ''));

    if ($state === '' || $expectedState === '' || !hash_equals($expectedState, $state)) {
        app_flash('error', 'Invalid OAuth state. Please try again.');
        app_redirect('/login');
    }

    if ($code === '') {
        app_flash('error', 'Authorization failed. Please try again.');
        app_redirect('/login');
    }

    try {
        $tokenResponse = app_webbycloud_exchange_code($config, $code);
        $accessToken = (string) ($tokenResponse['access_token'] ?? '');
        $refreshToken = (string) ($tokenResponse['refresh_token'] ?? '');
        $expiresIn = (int) ($tokenResponse['expires_in'] ?? 0);
        $profile = app_webbycloud_fetch_user($config, $accessToken);
    } catch (Throwable $exception) {
        app_log('WebbyCloud OAuth error: ' . $exception->getMessage(), 'ERROR');
        app_flash('error', 'Unable to connect to WebbyCloud.');
        app_redirect('/login');
    }

    $email = strtolower(trim((string) ($profile['email'] ?? '')));
    if ($email === '') {
        app_flash('error', 'WebbyCloud did not return an email address.');
        app_redirect('/login');
    }

    $currentUser = app_current_user();
    $isLoginFlow = $currentUser === null;
    $targetUser = null;

    if ($currentUser !== null) {
        $currentEmail = strtolower(trim((string) ($currentUser['email'] ?? '')));
        if ($currentEmail === '' || $currentEmail !== $email) {
            app_flash('error', 'WebbyCloud email does not match this account.');
            app_redirect('/settings?page=connects');
        }
        $targetUser = $currentUser;
    } else {
        $targetUser = app_find_user_by_email(app_db(), $email);
        if ($targetUser === null) {
            app_flash('error', 'No account found for this email. Please sign up first, then connect your WebbyCloud account.');
            app_redirect('/register');
        }
    }

    $settings = [];
    if (!empty($targetUser['settings'])) {
        $decodedSettings = json_decode($targetUser['settings'], true);
        if (is_array($decodedSettings)) {
            $settings = $decodedSettings;
        }
    }

    $settings['webbycloud'] = [
        'connected' => true,
        'email' => $email,
        'user_id' => (string) ($profile['id'] ?? ''),
        'display_name' => (string) ($profile['display_name'] ?? ''),
        'access_token' => $accessToken,
        'refresh_token' => $refreshToken,
        'expires_at' => $expiresIn > 0 ? date('Y-m-d H:i:s', time() + $expiresIn) : null,
        'connected_at' => date('Y-m-d H:i:s'),
    ];

    try {
        $encodedSettings = json_encode($settings);
        if ($encodedSettings === false) {
            throw new RuntimeException('Failed to encode settings.');
        }

        $db = app_db();
        $stmt = $db->prepare('UPDATE users SET settings = :settings WHERE id = :id');
        $stmt->execute([
            'settings' => $encodedSettings,
            'id' => (int) $targetUser['id'],
        ]);
    } catch (Throwable $exception) {
        app_log('Failed to save WebbyCloud settings: ' . $exception->getMessage(), 'ERROR');
        app_flash('error', 'Unable to save WebbyCloud connection.');
        app_redirect('/settings?page=connects');
    }

    if ($currentUser === null) {
        app_login_user($targetUser);
        app_log_auth($email, 'login', true);
        app_log_audit('login', [], $targetUser);
    }

    app_log_audit('webbycloud_connect', ['email' => $email], $targetUser);
    app_flash('success', 'WebbyCloud account connected.');
    $redirectPath = $isLoginFlow ? '/' : '/settings?page=connects';
    app_redirect($redirectPath);
}

function app_page_webbycloud_files(): void
{
    app_require_auth();
    $user = app_current_user();

    $settings = [];
    if (!empty($user['settings'])) {
        $decodedSettings = json_decode($user['settings'], true);
        if (is_array($decodedSettings)) {
            $settings = $decodedSettings;
        }
    }

    $webbycloud = $settings['webbycloud'] ?? [];
    if (empty($webbycloud['connected']) || empty($webbycloud['access_token'])) {
        app_flash('error', 'WebbyCloud is not connected.');
        app_redirect('/settings?page=connects');
    }

    $config = app_webbycloud_config();
    $accessToken = (string) $webbycloud['access_token'];
    $cloudUserId = (string) ($webbycloud['user_id'] ?? '');

    $files = [];
    $errorMessage = '';
    try {
        $files = app_webbycloud_list_files($config, $accessToken, $cloudUserId);
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
        app_log('WebbyCloud files error: ' . $errorMessage, 'ERROR');
    }

    app_render_head('WebbyCloud Files');
    app_render_dashboard_start($user);
    app_render_flash();
    ?>
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-white border-bottom-0">
                    <h5 class="mb-0">WebbyCloud Files</h5>
                </div>
                <div class="card-body">
                    <?php if ($errorMessage): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php elseif (empty($files)): ?>
                        <p class="text-muted">No files found or no file access permission.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Size</th>
                                        <th>Modified</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($files as $file): $size = (int) ($file['size'] ?? 0); ?>
                                        <tr>
                                            <td><?= htmlspecialchars((string) ($file['name'] ?? 'Untitled'), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= $size > 0 ? number_format($size) . ' B' : '-' ?></td>
                                            <td><?= htmlspecialchars((string) ($file['modified'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                    <a href="/settings?page=connects" class="btn btn-outline-secondary mt-3">Back to Connects</a>
                </div>
            </div>
        </div>
    </div>
    <?php
    app_render_dashboard_end();
    app_render_footer();
}

function app_page_files(): void
{
    app_require_auth();
    $user = app_current_user();
    $effectiveUser = $user ? app_get_effective_user($user) : $user;
    $effectiveUserId = (int) ($effectiveUser['id'] ?? 0);

    $settings = [];
    if (!empty($effectiveUser['settings'])) {
        $decodedSettings = json_decode($effectiveUser['settings'], true);
        if (is_array($decodedSettings)) {
            $settings = $decodedSettings;
        }
    }

    $webbycloud = $settings['webbycloud'] ?? [];
    $webbycloudConnected = !empty($webbycloud['connected']);
    $cloudFiles = [];
    $cloudFolders = [];
    $cloudError = '';
    $cloudBaseUrl = '';

    if ($webbycloudConnected && !empty($webbycloud['access_token'])) {
        try {
            $config = app_webbycloud_config();
            $cloudUserId = (string) ($webbycloud['user_id'] ?? '');
            if ($cloudUserId !== '') {
                $cloudBaseUrl = rtrim(preg_replace('#/ocs/v2.php/.*#', '', $config['user_info_url']), '/')
                    . '/remote.php/dav/files/' . $cloudUserId . '/';
            }
            $cloudFolder = trim($_GET['cloud_folder'] ?? '');
            
            $rootFiles = app_webbycloud_list_files($config, (string) $webbycloud['access_token'], $cloudUserId);
            if ($cloudFolder) {
                $cloudFiles = app_webbycloud_list_files($config, (string) $webbycloud['access_token'], $cloudUserId, $cloudFolder);
            } else {
                $cloudFiles = $rootFiles;
            }
            
            $skipPaths = ['remote.php', 'dav', 'files', ''];
            $cloudFolders = [];
            
            foreach ($rootFiles as $cf) {
                $path = $cf['path'] ?? '';
                $type = $cf['type'] ?? 'file';
                $name = $cf['name'] ?? '';
                
                if (!$name || $name === '/') continue;
                
                $pathParts = explode('/', trim($path, '/'));
                $firstPart = $pathParts[0] ?? '';
                
                if ($firstPart && !in_array($firstPart, $skipPaths) && $type === 'folder') {
                    $key = $name;
                    if (!isset($cloudFolders[$key])) {
                        $cloudFolders[$key] = [
                            'name' => $name,
                            'path' => $path,
                            'type' => $type
                        ];
                    }
                }
            }
        } catch (Throwable $e) {
            $cloudError = $e->getMessage();
        }
    }

    $localFiles = [];
    $categoryTree = [];
    $allCategories = [];
    $groupFilterOptions = [];
    $currentGroupId = trim((string) ($_GET['group_id'] ?? ''));
    try {
        $db = app_db();
        $categoryStmt = $db->prepare('
            SELECT id, name, parent_id, color 
            FROM categories 
            WHERE user_id = :user_id
            ORDER BY parent_id, name
        ');
        $categoryStmt->execute(['user_id' => $effectiveUserId]);
        $allCategoriesRaw = $categoryStmt->fetchAll();

        $categoryById = [];
        $categoryChildren = [];
        $rootCategories = [];
        foreach ($allCategoriesRaw as $cat) {
            $catId = (int) $cat['id'];
            $categoryById[$catId] = $cat;
            $parentId = (int) ($cat['parent_id'] ?? 0);
            if ($parentId > 0) {
                if (!isset($categoryChildren[$parentId])) {
                    $categoryChildren[$parentId] = [];
                }
                $categoryChildren[$parentId][] = $cat;
            } else {
                $rootCategories[] = $cat;
            }
        }
        $allCategories = $categoryById;

        $groupStmt = $db->prepare('
            SELECT DISTINCT gm.group_id, wg.name
            FROM group_messages gm
            INNER JOIN whatsapp_sessions ws ON gm.session_id = ws.id
            LEFT JOIN whatsapp_groups wg ON gm.session_id = wg.session_id AND gm.group_id = wg.group_id
            WHERE ws.user_id = :user_id
              AND gm.media_url IS NOT NULL
              AND gm.media_url != ""
            ORDER BY COALESCE(wg.name, gm.group_id) ASC
        ');
        $groupStmt->execute(['user_id' => $effectiveUserId]);
        $groupFilterOptions = $groupStmt->fetchAll(PDO::FETCH_ASSOC);

        $mediaStmt = $db->prepare('
            SELECT gm.category_id, gm.group_id, gm.message_type, gm.media_type, gm.media_url, gm.media_caption, gm.sender_name, gm.created_at
            FROM group_messages gm
            INNER JOIN whatsapp_sessions ws ON gm.session_id = ws.id
            WHERE ws.user_id = :user_id 
              AND gm.media_url IS NOT NULL 
              AND gm.media_url != ""
            ORDER BY gm.created_at DESC
            LIMIT 500
        ');
        $mediaStmt->execute(['user_id' => $effectiveUserId]);
        $mediaMessages = $mediaStmt->fetchAll();

        $filesByCategory = [];
        foreach ($mediaMessages as $msg) {
            $categoryId = (int) ($msg['category_id'] ?? 0);
            if ($categoryId <= 0) {
                $categoryId = 0;
            }
            if (!isset($filesByCategory[$categoryId])) {
                $filesByCategory[$categoryId] = [];
            }
            $filesByCategory[$categoryId][] = [
                'name' => $msg['media_caption'] ?: basename((string) $msg['media_url']),
                'path' => $msg['media_url'],
                'type' => $msg['message_type'],
                'media_type' => $msg['media_type'] ?? '',
                'media_caption' => $msg['media_caption'] ?? '',
                'created_at' => $msg['created_at'],
                'sender' => $msg['sender_name'] ?? '',
                'group_id' => $msg['group_id'] ?? '',
                'source' => 'local',
            ];
        }
        $localFiles = $filesByCategory;

        $categoryTree = [];
        foreach ($rootCategories as $cat) {
            $catId = (int) $cat['id'];
            $categoryTree[$catId] = [
                'id' => $catId,
                'name' => $cat['name'],
                'color' => $cat['color'] ?? '#f1c40f',
                'parent_id' => $cat['parent_id'],
                'children' => app_build_category_tree($catId, $categoryChildren, $allCategories),
                'depth' => 0
            ];
        }

        function app_build_category_tree(int $parentId, array $categoryChildren, array $allCategories): array {
            $result = [];
            $children = $categoryChildren[$parentId] ?? [];
            foreach ($children as $cat) {
                $catId = (int) $cat['id'];
                $result[$catId] = [
                    'id' => $catId,
                    'name' => $cat['name'],
                    'color' => $cat['color'] ?? '#f1c40f',
                    'parent_id' => $cat['parent_id'],
                    'children' => app_build_category_tree($catId, $categoryChildren, $allCategories),
                    'depth' => 0
                ];
            }
            return $result;
        }
    } catch (Throwable $e) {
        app_log('Files page error: ' . $e->getMessage(), 'ERROR');
    }

    function app_get_category_ids(int $categoryId, array $allCategories, array $categoryChildren): array {
        $ids = [$categoryId];
        $children = $categoryChildren[$categoryId] ?? [];
        foreach ($children as $child) {
            $ids = array_merge($ids, app_get_category_ids((int) $child['id'], $allCategories, $categoryChildren));
        }
        return $ids;
    }

    $currentCategoryId = (int) ($_GET['category'] ?? 0);
    $currentCloudFolder = trim($_GET['cloud_folder'] ?? '');
    $viewMode = $_GET['view'] ?? 'grid';
    $isCloudView = isset($_GET['cloud_folder']) && $webbycloudConnected;

    $currentCategoryIds = [];
    if ($currentCategoryId > 0 && !empty($categoryChildren)) {
        $currentCategoryIds = app_get_category_ids($currentCategoryId, $allCategories, $categoryChildren);
    } elseif ($currentCategoryId > 0) {
        $currentCategoryIds = [$currentCategoryId];
    }

    app_render_head('Files');
    app_render_dashboard_start($user);
    app_render_flash();
    
    $categoryMap = [];
    foreach ($allCategories as $cat) {
        $categoryMap[(int) $cat['id']] = $cat['name'];
    }
    ?>
    <div class="row">
        <div class="col-md-3">
            <div class="card mb-3">
                <div class="card-header bg-white border-bottom-0">
                    <h6 class="mb-0">Directories</h6>
                </div>
                <div class="card-body p-0">
                    <ul class="nav flex-column nav-pills">
                        <li class="nav-item">
                            <a class="nav-link <?= $currentCategoryId === 0 && !isset($_GET['cloud_folder']) ? 'active' : '' ?>" href="/files">
                                <i class="fas fa-folder me-2 text-warning"></i>
                                All Files
                            </a>
                            <?php if (!empty($rootCategories)): ?>
                            <ul class="nav flex-column nav-pills ms-3 border-start border-2 ps-2">
                                <?php foreach ($rootCategories as $cat): ?>
                                <?php $catId = (int)$cat['id']; ?>
                                <li class="nav-item">
                                    <a class="nav-link <?= $currentCategoryId === $catId ? 'active' : '' ?>" href="/files?category=<?= $catId ?>">
                                        <i class="fas fa-folder me-2" style="color: <?= htmlspecialchars((string)($cat['color'] ?? '#f1c40f')) ?>"></i>
                                        <?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>
                                    </a>
                                    <?php if (isset($categoryChildren[$catId])): ?>
                                    <ul class="nav flex-column nav-pills ms-3 border-start border-2 ps-2">
                                        <?php foreach ($categoryChildren[$catId] as $child): ?>
                                        <?php $childId = (int)$child['id']; ?>
                                        <li class="nav-item">
                                            <a class="nav-link <?= $currentCategoryId === $childId ? 'active' : '' ?>" href="/files?category=<?= $childId ?>">
                                                <i class="fas fa-folder me-2" style="color: <?= htmlspecialchars((string)($child['color'] ?? '#f1c40f')) ?>"></i>
                                                <?= htmlspecialchars($child['name'], ENT_QUOTES, 'UTF-8') ?>
                                            </a>
                                        </li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <?php endif; ?>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php endif; ?>
                        </li>
                        <?php if ($webbycloudConnected): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= isset($_GET['cloud_folder']) ? 'active' : '' ?>" href="/files?cloud_folder=">
                                <i class="fas fa-cloud me-2 text-info"></i>
                                WebbyCloud
                            </a>
                            <ul class="nav flex-column nav-pills ms-3 border-start border-2 ps-2">
                                <?php foreach ($cloudFolders as $folder): ?>
                                <li class="nav-item">
                                    <a class="nav-link <?= $currentCloudFolder === $folder['path'] ? 'active' : '' ?>" href="/files?cloud_folder=<?= urlencode($folder['path']) ?>">
                                        <i class="fas <?= ($folder['type'] ?? 'file') === 'folder' ? 'fa-folder' : 'fa-file' ?> me-2 text-warning"></i>
                                        <?= htmlspecialchars($folder['name'], ENT_QUOTES, 'UTF-8') ?>
                                    </a>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-md-9">
            <div class="card">
                <div class="card-header bg-white border-bottom-0 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <?php if ($currentCloudFolder !== ''): ?>
                            <?= htmlspecialchars(basename($currentCloudFolder), ENT_QUOTES, 'UTF-8') ?>
                        <?php elseif ($currentCategoryId > 0): ?>
                            <?= htmlspecialchars($categoryMap[$currentCategoryId] ?? 'Files', ENT_QUOTES, 'UTF-8') ?>
                        <?php else: ?>
                            All Files
                        <?php endif; ?>
                    </h5>
                    <div class="d-flex align-items-center gap-2">
                        <?php if (!$isCloudView): ?>
                            <div class="input-group input-group-sm" style="width: 220px;">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" class="form-control" id="files-grid-search" placeholder="Search files..." aria-label="Search files">
                            </div>
                            <?php if ($currentCategoryId === 0): ?>
                                <select class="form-select form-select-sm" id="files-group-filter" style="width: 200px;" aria-label="Filter by group">
                                    <option value="">All Groups</option>
                                    <?php foreach ($groupFilterOptions as $groupOption): ?>
                                        <?php
                                        $optionGroupId = (string) ($groupOption['group_id'] ?? '');
                                        if ($optionGroupId === '') {
                                            continue;
                                        }
                                        $optionGroupName = trim((string) ($groupOption['name'] ?? ''));
                                        if ($optionGroupName === '') {
                                            $optionGroupName = $optionGroupId;
                                        }
                                        ?>
                                        <option value="<?= htmlspecialchars($optionGroupId, ENT_QUOTES, 'UTF-8') ?>" <?= $currentGroupId === $optionGroupId ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($optionGroupName, ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        <?php endif; ?>
                        <div class="btn-group btn-group-sm">
                            <a href="/files?<?= http_build_query(array_merge($_GET, ['view' => 'grid'])) ?>" class="btn <?= $viewMode === 'grid' ? 'btn-primary' : 'btn-outline-primary' ?>">
                                <i class="fas fa-th"></i>
                            </a>
                            <a href="/files?<?= http_build_query(array_merge($_GET, ['view' => 'list'])) ?>" class="btn <?= $viewMode === 'list' ? 'btn-primary' : 'btn-outline-primary' ?>">
                                <i class="fas fa-list"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <?php if ($cloudError): ?>
                        <div class="alert alert-warning">Cloud error: <?= htmlspecialchars($cloudError, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>

<?php
                    $displayFiles = [];
                    
                    if ($isCloudView) {
                        $displayFiles = $cloudFiles;
                    } else {
                        if ($currentCategoryId === 0) {
                            foreach ($localFiles as $catFiles) {
                                $displayFiles = array_merge($displayFiles, $catFiles);
                            }
                        } elseif (!empty($currentCategoryIds)) {
                            foreach ($currentCategoryIds as $catId) {
                                if (!empty($localFiles[$catId])) {
                                    $displayFiles = array_merge($displayFiles, $localFiles[$catId]);
                                }
                            }
                        } else {
                            $displayFiles = $localFiles[$currentCategoryId] ?? [];
                        }
                    }

                    // Add breadcrumb for cloud navigation
                    if ($currentCloudFolder !== '' && $webbycloudConnected): ?>
                    <div class="mb-3">
                        <a href="/files?cloud_folder=" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Back to WebbyCloud
                        </a>
                    </div>
                    <?php endif; ?>

                    <?php if ($currentCloudFolder !== '' && !$webbycloudConnected): ?>
                        <div class="alert alert-info">
                            <a href="/settings?page=connects">Connect WebbyCloud</a> to access cloud files.
                        </div>
                    <?php elseif (empty($displayFiles)): ?>
                        <p class="text-muted">No files found.</p>
                    <?php elseif ($viewMode === 'grid'): ?>
                        <div class="row g-3" id="files-grid">
                            <?php foreach ($displayFiles as $file): $msgType = strtolower($file['type'] ?? 'document'); $mediaTypeField = strtolower($file['media_type'] ?? ''); $mediaType = ($mediaTypeField && $mediaTypeField !== $msgType) ? $mediaTypeField : $msgType; ?>
                            <?php $isFolder = $isCloudView && ($file['type'] ?? '') === 'folder'; ?>
                            <?php $rawPath = (string) ($file['path'] ?? ''); $resolvedPath = $isCloudView && $cloudBaseUrl !== '' ? $cloudBaseUrl . $rawPath : $rawPath; ?>
                            <?php $filePath = htmlspecialchars($resolvedPath, ENT_QUOTES, 'UTF-8'); $fileName = htmlspecialchars($file['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                            <?php
                                $searchData = '';
                                $groupId = '';
                                if (!$isCloudView) {
                                    $searchParts = [
                                        (string) ($file['name'] ?? ''),
                                        (string) ($file['media_caption'] ?? ''),
                                        (string) ($file['media_type'] ?? ''),
                                        (string) ($file['type'] ?? ''),
                                        (string) ($file['sender'] ?? ''),
                                        (string) ($file['created_at'] ?? ''),
                                    ];
                                    $searchData = trim(implode(' ', array_filter($searchParts)));
                                    $groupId = (string) ($file['group_id'] ?? '');
                                }
                            ?>
                            <div class="col-6 col-md-4 col-lg-3"<?= $searchData !== '' ? ' data-file-search="' . htmlspecialchars($searchData, ENT_QUOTES, 'UTF-8') . '"' : '' ?><?= $groupId !== '' ? ' data-group-id="' . htmlspecialchars($groupId, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
                                <?php if ($isFolder): ?>
                                <a class="card h-100 text-center p-3 d-flex flex-column text-decoration-none" href="/files?<?= http_build_query(array_merge($_GET, ['cloud_folder' => $rawPath])) ?>">
                                    <div class="flex-grow-1 d-flex align-items-center justify-content-center mb-2">
                                        <i class="fas fa-folder fa-3x text-warning"></i>
                                    </div>
                                    <p class="small mb-1 text-truncate text-dark"><?= $fileName ?></p>
                                    <small class="text-muted"><?= htmlspecialchars($file['modified'] ?? '-', ENT_QUOTES, 'UTF-8') ?></small>
                                </a>
                                <?php elseif (in_array($msgType, ['image', 'photo']) || in_array($mediaType, ['image', 'photo'])): ?>
                                <div class="card h-100 text-center p-3 d-flex flex-column file-card" style="cursor:pointer" data-src="<?= $filePath ?>" data-type="image">
                                    <div class="flex-grow-1 d-flex align-items-center justify-content-center mb-2">
                                        <i class="fas fa-image fa-3x text-success"></i>
                                    </div>
                                    <p class="small mb-1 text-truncate"><?= $fileName ?></p>
                                    <small class="text-muted"><?= htmlspecialchars($file['modified'] ?? ($file['created_at'] ?? '' ), ENT_QUOTES, 'UTF-8') ?></small>
                                </div>
                                <?php elseif ($msgType === 'document'): ?>
                                <?php $isPdf = strpos($mediaType, 'pdf') !== false; ?>
                                <div class="card h-100 text-center p-3 d-flex flex-column <?= $isPdf ? 'file-card' : '' ?>" <?= $isPdf ? 'style="cursor:pointer" data-src="'.$filePath.'" data-type="pdf"' : '' ?>>
                                    <div class="flex-grow-1 d-flex align-items-center justify-content-center mb-2">
                                        <i class="fas <?= $isPdf ? 'fa-file-pdf' : 'fa-file' ?> fa-3x <?= $isPdf ? 'text-danger' : 'text-secondary' ?>"></i>
                                    </div>
                                    <p class="small mb-1 text-truncate"><?= $fileName ?></p>
                                    <small class="text-muted"><?= htmlspecialchars($file['modified'] ?? ($file['created_at'] ?? '' ), ENT_QUOTES, 'UTF-8') ?></small>
                                </div>
                                <?php elseif ($msgType === 'video' || $mediaType === 'video'): ?>
                                <div class="card h-100 text-center p-3 d-flex flex-column">
                                    <div class="flex-grow-1 d-flex align-items-center justify-content-center mb-2">
                                        <i class="fas fa-video fa-3x text-danger"></i>
                                    </div>
                                    <p class="small mb-1 text-truncate"><?= $fileName ?></p>
                                    <small class="text-muted"><?= htmlspecialchars($file['modified'] ?? ($file['created_at'] ?? '' ), ENT_QUOTES, 'UTF-8') ?></small>
                                </div>
                                <?php elseif ($msgType === 'audio'): ?>
                                <div class="card h-100 text-center p-3 d-flex flex-column">
                                    <div class="flex-grow-1 d-flex align-items-center justify-content-center mb-2">
                                        <i class="fas fa-music fa-3x text-info"></i>
                                    </div>
                                    <p class="small mb-1 text-truncate"><?= $fileName ?></p>
                                    <small class="text-muted"><?= htmlspecialchars($file['modified'] ?? ($file['created_at'] ?? '' ), ENT_QUOTES, 'UTF-8') ?></small>
                                </div>
                                <?php else: ?>
                                <div class="card h-100 text-center p-3 d-flex flex-column">
                                    <div class="flex-grow-1 d-flex align-items-center justify-content-center mb-2">
                                        <i class="fas fa-file fa-3x text-secondary"></i>
                                    </div>
                                    <p class="small mb-1 text-truncate"><?= $fileName ?></p>
                                    <small class="text-muted"><?= htmlspecialchars($file['modified'] ?? ($file['created_at'] ?? '' ), ENT_QUOTES, 'UTF-8') ?></small>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if (!$isCloudView): ?>
                            <div id="files-grid-empty" class="text-muted text-center mt-3" style="display:none;">
                                No matches found.
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Type</th>
                                        <th>Date</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                            <?php foreach ($displayFiles as $file): ?>
                                    <?php $msgType = strtolower($file['type'] ?? 'document'); $mediaTypeField = strtolower($file['media_type'] ?? ''); $mediaType = ($mediaTypeField && $mediaTypeField !== $msgType) ? $mediaTypeField : $msgType; ?>
                                    <?php $isFolder = $isCloudView && ($file['type'] ?? '') === 'folder'; ?>
                                    <?php $rawPath = (string) ($file['path'] ?? ''); $resolvedPath = $isCloudView && $cloudBaseUrl !== '' ? $cloudBaseUrl . $rawPath : $rawPath; ?>
                                    <?php $filePath = htmlspecialchars($resolvedPath, ENT_QUOTES, 'UTF-8'); ?>
                                    <?php
                                        $searchData = '';
                                        $groupId = '';
                                        $rowAttributes = '';
                                        if (!$isCloudView) {
                                            $searchParts = [
                                                (string) ($file['name'] ?? ''),
                                                (string) ($file['media_caption'] ?? ''),
                                                (string) ($file['media_type'] ?? ''),
                                                (string) ($file['type'] ?? ''),
                                                (string) ($file['sender'] ?? ''),
                                                (string) ($file['created_at'] ?? ''),
                                            ];
                                            $searchData = trim(implode(' ', array_filter($searchParts)));
                                            $groupId = (string) ($file['group_id'] ?? '');
                                            $attributeParts = [];
                                            if ($searchData !== '') {
                                                $attributeParts[] = 'data-file-search="' . htmlspecialchars($searchData, ENT_QUOTES, 'UTF-8') . '"';
                                            }
                                            if ($groupId !== '') {
                                                $attributeParts[] = 'data-group-id="' . htmlspecialchars($groupId, ENT_QUOTES, 'UTF-8') . '"';
                                            }
                                            if (!empty($attributeParts)) {
                                                $rowAttributes = ' ' . implode(' ', $attributeParts);
                                            }
                                        }
                                    ?>
                                    <?php if ($isFolder): ?>
                                    <tr>
                                        <td>
                                            <i class="fas fa-folder text-warning me-1"></i>
                                            <a href="/files?<?= http_build_query(array_merge($_GET, ['cloud_folder' => $rawPath])) ?>" class="text-decoration-none">
                                                <?= htmlspecialchars($file['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                            </a>
                                        </td>
                                        <td>folder</td>
                                        <td><?= htmlspecialchars(substr($file['modified'] ?? '-', 0, 10), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td></td>
                                    </tr>
                                    <?php elseif (in_array($msgType, ['image', 'photo']) || in_array($mediaType, ['image', 'photo'])): ?>
                                    <tr style="cursor:pointer" class="file-row" data-src="<?= $filePath ?>" data-type="image"<?= $rowAttributes ?>>
                                        <td><?= htmlspecialchars($file['name'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($file['type'] ?? 'document', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars(substr($file['modified'] ?? ($file['created_at'] ?? '-'), 0, 10), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <a href="<?= $filePath ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a>
                                        </td>
                                    </tr>
                                    <?php elseif (in_array($msgType, ['document']) && $mediaType === 'application/pdf'): ?>
                                    <tr style="cursor:pointer" class="file-row" data-src="<?= $filePath ?>" data-type="pdf"<?= $rowAttributes ?>>
                                        <td><?= htmlspecialchars($file['name'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($file['type'] ?? 'document', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars(substr($file['modified'] ?? ($file['created_at'] ?? '-'), 0, 10), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <a href="<?= $filePath ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a>
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <tr<?= $rowAttributes ?>>
                                        <td><?= htmlspecialchars($file['name'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <?php if (in_array($msgType, ['video']) || in_array($mediaType, ['video'])): ?>
                                            <i class="fas fa-video text-danger me-1"></i>
                                            <?php elseif (in_array($msgType, ['audio']) || in_array($mediaType, ['audio'])): ?>
                                            <i class="fas fa-music text-info me-1"></i>
                                            <?php endif; ?>
                                            <?= htmlspecialchars($file['type'] ?? 'document', ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td><?= htmlspecialchars(substr($file['modified'] ?? ($file['created_at'] ?? '-'), 0, 10), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <?php if (!empty($file['path'])): ?>
                                            <a href="<?= htmlspecialchars($file['path'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-download"></i>
                                            </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div id="imageModal" class="modal fade" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body p-0 position-relative">
                    <button type="button" class="btn-close position-absolute top-0 end-0 m-3" data-bs-dismiss="modal" style="z-index:1;"></button>
                    <img id="imageModalSrc" src="" class="img-fluid w-100" style="max-height:85vh;object-fit:contain;">
                </div>
            </div>
        </div>
    </div>

    <div id="pdfModal" class="modal fade" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-dialog-centered modal-fullscreen">
            <div class="modal-content bg-dark border-0">
                <div class="modal-header border-0">
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <iframe id="pdfModalSrc" src="" class="w-100 h-100 border-0"></iframe>
                </div>
            </div>
        </div>
    </div>

    <script src="<?= htmlspecialchars(app_asset('js/realtime.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var imageModal = new bootstrap.Modal(document.getElementById('imageModal'));
        var pdfModal = new bootstrap.Modal(document.getElementById('pdfModal'));
        
        document.querySelectorAll('.file-card, .file-row').forEach(function(el) {
            el.addEventListener('click', function(e) {
                if (el.tagName === 'TR') {
                    var link = el.querySelector('a');
                    if (link && link.contains(e.target)) return;
                }
                var type = this.getAttribute('data-type');
                var src = this.getAttribute('data-src');
                if (type === 'image') {
                    document.getElementById('imageModalSrc').src = src;
                    imageModal.show();
                } else if (type === 'pdf') {
                    document.getElementById('pdfModalSrc').src = src;
                    pdfModal.show();
                }
            });
        });

        var filesGridSearch = document.getElementById('files-grid-search');
        var filesGroupFilter = document.getElementById('files-group-filter');
        if (filesGridSearch || filesGroupFilter) {
            var filterItems = document.querySelectorAll('[data-file-search]');
            var emptyState = document.getElementById('files-grid-empty');

            var filterFilesGrid = function() {
                var query = filesGridSearch ? filesGridSearch.value.toLowerCase().trim() : '';
                var selectedGroup = filesGroupFilter ? filesGroupFilter.value : '';
                var visibleCount = 0;

                filterItems.forEach(function(item) {
                    var haystack = (item.getAttribute('data-file-search') || '').toLowerCase();
                    var groupId = item.getAttribute('data-group-id') || '';
                    var matchesQuery = query === '' || haystack.indexOf(query) !== -1;
                    var matchesGroup = selectedGroup === '' || groupId === selectedGroup;
                    var isMatch = matchesQuery && matchesGroup;
                    item.style.display = isMatch ? '' : 'none';
                    if (isMatch) {
                        visibleCount += 1;
                    }
                });

                if (emptyState) {
                    emptyState.style.display = (query !== '' || selectedGroup !== '') && visibleCount === 0 ? '' : 'none';
                }
            };

            if (filesGridSearch) {
                filesGridSearch.addEventListener('input', filterFilesGrid);
            }
            if (filesGroupFilter) {
                filesGroupFilter.addEventListener('change', filterFilesGrid);
            }
            filterFilesGrid();
        }
    });
    </script>
    <?php
    app_render_dashboard_end();
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
        <?php if (($user['role'] ?? '') === 'admin'): ?>
        <div class="col-xl-3 col-md-6 mb-4">
            <?php
            $expiryDate = $user['expiry_date'] ?? null;
            $expiryLabel = 'Not set';
            $daysUntilExpiry = null;
            $expiryAccent = 'primary';
            if (!empty($expiryDate)) {
                $expiryTimestamp = strtotime($expiryDate);
                $expiryLabel = date('M d, Y', $expiryTimestamp);
                $daysUntilExpiry = (int) floor(($expiryTimestamp - strtotime('today')) / 86400);
                if ($daysUntilExpiry < 0) {
                    $expiryAccent = 'danger';
                } elseif ($daysUntilExpiry <= 7) {
                    $expiryAccent = 'warning';
                } else {
                    $expiryAccent = 'primary';
                }
            }
            ?>
            <div class="card border-left-<?= $expiryAccent ?> shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-<?= $expiryAccent ?> text-uppercase mb-1">
                                Expiry Date
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= htmlspecialchars($expiryLabel, ENT_QUOTES, 'UTF-8') ?>
                            </div>
                            <?php if ($daysUntilExpiry !== null): ?>
                                <div class="small text-muted">Days until expiry: <?= $daysUntilExpiry ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-calendar-alt fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
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
    </div>
    <?php
    
    // End dashboard layout
    app_render_dashboard_end();
    
    app_render_footer();
}

function app_page_settings(): void
{
    app_require_auth();

    $user = app_current_user();
    $effectiveUser = $user ? app_get_effective_user($user) : $user;
    $effectiveUserId = $effectiveUser['id'] ?? 0;
    $settings = [];
    if ($effectiveUser && !empty($effectiveUser['settings'])) {
        $decodedSettings = json_decode($effectiveUser['settings'], true);
        if (is_array($decodedSettings)) {
            $settings = $decodedSettings;
        }
    }
    $defaultCurrency = $settings['default_currency'] ?? 'USD';
    $includeUserNameOnChat = !empty($settings['include_user_name_on_chat']);
    $settings = [];
    if ($effectiveUser && !empty($effectiveUser['settings'])) {
        $decodedSettings = json_decode($effectiveUser['settings'], true);
        if (is_array($decodedSettings)) {
            $settings = $decodedSettings;
        }
    }
    $defaultCurrency = $settings['default_currency'] ?? 'USD';
    $settings = [];
    if ($effectiveUser && !empty($effectiveUser['settings'])) {
        $decodedSettings = json_decode($effectiveUser['settings'], true);
        if (is_array($decodedSettings)) {
            $settings = $decodedSettings;
        }
    }
    $defaultCurrency = $settings['default_currency'] ?? 'USD';
    $settings = [];
    if ($effectiveUser && !empty($effectiveUser['settings'])) {
        $decodedSettings = json_decode($effectiveUser['settings'], true);
        if (is_array($decodedSettings)) {
            $settings = $decodedSettings;
        }
    }
    $defaultCurrency = $settings['default_currency'] ?? 'USD';
    $settings = [];
    if ($effectiveUser && !empty($effectiveUser['settings'])) {
        $decodedSettings = json_decode($effectiveUser['settings'], true);
        if (is_array($decodedSettings)) {
            $settings = $decodedSettings;
        }
    }
    $defaultCurrency = $settings['default_currency'] ?? 'USD';
    $settings = [];
    if ($effectiveUser && !empty($effectiveUser['settings'])) {
        $decodedSettings = json_decode($effectiveUser['settings'], true);
        if (is_array($decodedSettings)) {
            $settings = $decodedSettings;
        }
    }
    $currencyOptions = [
        'USD' => 'USD - US Dollar',
        'SGD' => 'SGD - Singapore Dollar',
        'MYR' => 'MYR - Malaysian Ringgit',
        'THB' => 'THB - Thai Baht',
        'IDR' => 'IDR - Indonesian Rupiah',
        'PHP' => 'PHP - Philippine Peso',
        'VND' => 'VND - Vietnamese Dong',
        'BND' => 'BND - Brunei Dollar',
        'KHR' => 'KHR - Cambodian Riel',
        'LAK' => 'LAK - Lao Kip',
        'MMK' => 'MMK - Myanmar Kyat'
    ];
    $defaultCurrency = $settings['default_currency'] ?? 'USD';
    $fileHandlingCategoryAssignment = (int) ($effectiveUser['file_handling_category_assignment'] ?? 1);
    if (!in_array($fileHandlingCategoryAssignment, [1, 2, 3], true)) {
        $fileHandlingCategoryAssignment = 1;
    }

    $mfaSecret = (string) ($settings['mfa_secret'] ?? '');
    $mfaEnabled = !empty($settings['mfa_enabled']);
    $passwordLastChangedAt = $settings['password_last_changed_at'] ?? null;
    $lastPasswordChangedLabel = 'Never';
    if (!empty($passwordLastChangedAt) && strtotime((string) $passwordLastChangedAt) !== false) {
        $lastPasswordChangedLabel = date('M d, Y', strtotime((string) $passwordLastChangedAt));
    }
    $webbycloud = $settings['webbycloud'] ?? [];
    if (!is_array($webbycloud)) {
        $webbycloud = [];
    }
    $webbycloudConnected = !empty($webbycloud['connected']);
    $webbycloudEmail = (string) ($webbycloud['email'] ?? '');
    $webbycloudDisplayName = (string) ($webbycloud['display_name'] ?? '');
    $issuer = 'ERP Ezy Chat';
    $accountLabel = $effectiveUser['email'] ?? 'user';
    $otpAuthUrl = '';
    $qrCodeUrl = '';
    if ($mfaSecret !== '') {
        $otpLabel = rawurlencode($issuer . ':' . $accountLabel);
        $otpAuthUrl = 'otpauth://totp/' . $otpLabel . '?secret=' . $mfaSecret . '&issuer=' . rawurlencode($issuer);
        $qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($otpAuthUrl);
    }
    
    // Get current page from query parameter
    $currentPage = $_GET['page'] ?? 'account';
    $validPages = ['account', 'global', 'category', 'security', 'connects'];
    if (($user['role'] ?? '') === 'users') {
        $validPages = ['account', 'category', 'security'];
    }
    
    if (!in_array($currentPage, $validPages, true)) {
        $currentPage = 'account';
    }

    switch ($currentPage) {
        case 'security':
            $pageTitle = 'Security';
            $pageDescription = 'Protect your account with MFA and password controls';
            break;
        case 'global':
            $pageTitle = 'Global Settings';
            $pageDescription = 'Configure default currency and display settings';
            break;
        case 'category':
            $pageTitle = 'Category Management';
            $pageDescription = 'Manage your categories and group organization';
            break;
        case 'connects':
            $pageTitle = 'Connects';
            $pageDescription = 'Link external accounts and manage connections';
            break;
        case 'account':
        default:
            $pageTitle = 'Account Settings';
            $pageDescription = 'Manage your account information and preferences';
            break;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = (string) ($_POST['settings_action'] ?? '');

        if ($action === 'download_settings') {
            $export = [
                'exported_at' => date('c'),
                'user' => [
                    'id' => $effectiveUserId,
                    'email' => $effectiveUser['email'] ?? '',
                    'role' => $effectiveUser['role'] ?? ''
                ],
                'settings' => $settings
            ];

            $json = json_encode($export, JSON_PRETTY_PRINT);
            if ($json === false) {
                app_flash('error', 'Failed to prepare settings export.');
                app_redirect('/settings?page=account');
            }

            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="settings-' . $effectiveUserId . '.json"');
            header('Content-Length: ' . strlen($json));
            echo $json;
            exit;
        }

        if ($action === 'delete_account') {
            $role = $user['role'] ?? '';
            if (!in_array($role, ['admin', 'superadmin'], true)) {
                app_flash('error', 'Only admin accounts can be deleted here.');
                app_redirect('/settings?page=account');
            }

            $currentPassword = (string) ($_POST['current_password'] ?? '');
            $confirmText = trim((string) ($_POST['delete_confirm'] ?? ''));

            if ($currentPassword === '') {
                app_flash('error', 'Current password is required.');
                app_redirect('/settings?page=account');
            }

            if ($confirmText !== 'DELETE') {
                app_flash('error', 'Type DELETE to confirm account deletion.');
                app_redirect('/settings?page=account');
            }

            try {
                $db = app_db();
                $stmt = $db->prepare('SELECT password_hash FROM users WHERE id = :id');
                $stmt->execute(['id' => $effectiveUserId]);
                $row = $stmt->fetch();

                if (!$row || !password_verify($currentPassword, $row['password_hash'] ?? '')) {
                    app_flash('error', 'Current password is incorrect.');
                    app_redirect('/settings?page=account');
                }

                require_once __DIR__ . '/whatsapp/sessions.php';

                $stmt = $db->prepare('SELECT id FROM users WHERE parent_id = :parent_id');
                $stmt->execute(['parent_id' => $effectiveUserId]);
                $childIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);

                foreach ($childIds as $childId) {
                    $childSessions = app_whatsapp_get_user_sessions($childId);
                    foreach ($childSessions as $session) {
                        app_whatsapp_delete_session((int) ($session['id'] ?? 0));
                    }

                    $stmt = $db->prepare('DELETE FROM users WHERE id = :id');
                    $stmt->execute(['id' => $childId]);
                }

                $adminSessions = app_whatsapp_get_user_sessions($effectiveUserId);
                foreach ($adminSessions as $session) {
                    app_whatsapp_delete_session((int) ($session['id'] ?? 0));
                }

                $stmt = $db->prepare('DELETE FROM users WHERE id = :id');
                $stmt->execute(['id' => $effectiveUserId]);

                app_log_audit('delete_account', [
                    'target_user_id' => $effectiveUserId,
                    'deleted_child_users' => count($childIds)
                ], $user);

                app_logout_user();
                app_redirect('/login?account_deleted=1');
            } catch (Exception $e) {
                app_log('Failed to delete account: ' . $e->getMessage(), 'ERROR');
                app_flash('error', 'Failed to delete account.');
                app_redirect('/settings?page=account');
            }
        }

        if ($action === 'update_global_settings') {
            $requestedCurrency = strtoupper(trim((string) ($_POST['default_currency'] ?? '')));
            $requestedIncludeUserName = strtolower(trim((string) ($_POST['include_user_name_on_chat'] ?? 'no')));
            $requestedFileHandling = (int) ($_POST['file_handling_category_assignment'] ?? 1);
            if (!array_key_exists($requestedCurrency, $currencyOptions)) {
                app_flash('error', 'Invalid currency selection.');
                app_redirect('/settings?page=global');
            }

            if (!in_array($requestedIncludeUserName, ['yes', 'no'], true)) {
                app_flash('error', 'Invalid chat name setting.');
                app_redirect('/settings?page=global');
            }

            if (!in_array($requestedFileHandling, [1, 2, 3], true)) {
                app_flash('error', 'Invalid file handling selection.');
                app_redirect('/settings?page=global');
            }

            $settings['default_currency'] = $requestedCurrency;
            $settings['include_user_name_on_chat'] = ($requestedIncludeUserName === 'yes');

            try {
                $encodedSettings = json_encode($settings);
                if ($encodedSettings === false) {
                    app_flash('error', 'Failed to save settings.');
                    app_redirect('/settings?page=global');
                }

                $db = app_db();
                $stmt = $db->prepare('UPDATE users SET settings = :settings, file_handling_category_assignment = :file_handling_category_assignment WHERE id = :id');
                $stmt->execute([
                    'settings' => $encodedSettings,
                    'file_handling_category_assignment' => $requestedFileHandling,
                    'id' => $effectiveUserId
                ]);
                app_flash('success', 'Global settings updated.');
            } catch (Exception $e) {
                app_log('Failed to update settings: ' . $e->getMessage(), 'ERROR');
                app_flash('error', 'Failed to save settings.');
            }

            app_redirect('/settings?page=global');
        }

        if ($action === 'disconnect_webbycloud') {
            if (isset($settings['webbycloud'])) {
                unset($settings['webbycloud']);
            }

            try {
                $encodedSettings = json_encode($settings);
                if ($encodedSettings === false) {
                    app_flash('error', 'Failed to disconnect WebbyCloud.');
                    app_redirect('/settings?page=connects');
                }

                $db = app_db();
                $stmt = $db->prepare('UPDATE users SET settings = :settings WHERE id = :id');
                $stmt->execute([
                    'settings' => $encodedSettings,
                    'id' => $effectiveUserId
                ]);
                app_flash('success', 'WebbyCloud disconnected.');
                app_log_audit('webbycloud_disconnect', [], $user);
            } catch (Exception $e) {
                app_log('Failed to disconnect WebbyCloud: ' . $e->getMessage(), 'ERROR');
                app_flash('error', 'Failed to disconnect WebbyCloud.');
            }

            app_redirect('/settings?page=connects');
        }

        if ($action === 'generate_mfa_secret') {
            $settings['mfa_secret'] = app_generate_mfa_secret();
            $settings['mfa_enabled'] = false;
            $settings['mfa_enabled_at'] = null;

            try {
                $encodedSettings = json_encode($settings);
                if ($encodedSettings === false) {
                    app_flash('error', 'Failed to generate MFA secret.');
                    app_redirect('/settings?page=security');
                }

                $db = app_db();
                $stmt = $db->prepare('UPDATE users SET settings = :settings WHERE id = :id');
                $stmt->execute([
                    'settings' => $encodedSettings,
                    'id' => $effectiveUserId
                ]);
                app_flash('success', 'New MFA secret generated.');
            } catch (Exception $e) {
                app_log('Failed to update settings: ' . $e->getMessage(), 'ERROR');
                app_flash('error', 'Failed to generate MFA secret.');
            }

            app_redirect('/settings?page=security');
        }

        if ($action === 'enable_mfa') {
            $otp = trim((string) ($_POST['otp'] ?? ''));
            $secret = (string) ($settings['mfa_secret'] ?? '');

            if ($secret === '') {
                app_flash('error', 'Generate a secret key before enabling MFA.');
                app_redirect('/settings?page=security');
            }

            if ($otp === '' || !app_validate_mfa_otp($secret, $otp)) {
                app_flash('error', 'Invalid MFA code.');
                app_redirect('/settings?page=security');
            }

            $settings['mfa_enabled'] = true;
            $settings['mfa_enabled_at'] = date('Y-m-d H:i:s');

            try {
                $encodedSettings = json_encode($settings);
                if ($encodedSettings === false) {
                    app_flash('error', 'Failed to enable MFA.');
                    app_redirect('/settings?page=security');
                }

                $db = app_db();
                $stmt = $db->prepare('UPDATE users SET settings = :settings WHERE id = :id');
                $stmt->execute([
                    'settings' => $encodedSettings,
                    'id' => $effectiveUserId
                ]);
                app_flash('success', 'MFA enabled successfully.');
                app_log_audit('enable_mfa', [], $user);
            } catch (Exception $e) {
                app_log('Failed to update settings: ' . $e->getMessage(), 'ERROR');
                app_flash('error', 'Failed to enable MFA.');
            }

            app_redirect('/settings?page=security');
        }

        if ($action === 'disable_mfa') {
            $settings['mfa_enabled'] = false;
            $settings['mfa_enabled_at'] = null;

            try {
                $encodedSettings = json_encode($settings);
                if ($encodedSettings === false) {
                    app_flash('error', 'Failed to disable MFA.');
                    app_redirect('/settings?page=security');
                }

                $db = app_db();
                $stmt = $db->prepare('UPDATE users SET settings = :settings WHERE id = :id');
                $stmt->execute([
                    'settings' => $encodedSettings,
                    'id' => $effectiveUserId
                ]);
                app_flash('success', 'MFA disabled.');
                app_log_audit('disable_mfa', [], $user);
            } catch (Exception $e) {
                app_log('Failed to update settings: ' . $e->getMessage(), 'ERROR');
                app_flash('error', 'Failed to disable MFA.');
            }

            app_redirect('/settings?page=security');
        }

        if ($action === 'update_account_name') {
            $requestedName = trim((string) ($_POST['account_name'] ?? ''));
            if ($requestedName === '' || mb_strlen($requestedName) > 100) {
                app_flash('error', 'Name is required and must be 100 characters or less.');
                app_redirect('/settings?page=account');
            }

            try {
                $db = app_db();
                $stmt = $db->prepare('UPDATE users SET name = :name WHERE id = :id');
                $stmt->execute([
                    'name' => $requestedName,
                    'id' => (int) ($user['id'] ?? 0)
                ]);
                app_flash('success', 'Name updated.');
                app_log_audit('update_account_name', ['name' => $requestedName], $user);
            } catch (Exception $e) {
                app_log('Failed to update name: ' . $e->getMessage(), 'ERROR');
                app_flash('error', 'Failed to update name.');
            }

            app_redirect('/settings?page=account');
        }

        if ($action === 'update_password') {
            $currentPassword = (string) ($_POST['current_password'] ?? '');
            $newPassword = (string) ($_POST['new_password'] ?? '');
            $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

            if ($newPassword === '' || strlen($newPassword) < 8) {
                app_flash('error', 'Password must be at least 8 characters.');
                app_redirect('/settings?page=security');
            }

            if ($newPassword !== $confirmPassword) {
                app_flash('error', 'New password and confirmation do not match.');
                app_redirect('/settings?page=security');
            }

            try {
                $db = app_db();
                $stmt = $db->prepare('SELECT password_hash FROM users WHERE id = :id');
                $stmt->execute(['id' => $effectiveUserId]);
                $row = $stmt->fetch();

                if (!$row || !password_verify($currentPassword, $row['password_hash'] ?? '')) {
                    app_flash('error', 'Current password is incorrect.');
                    app_redirect('/settings?page=security');
                }

                $stmt = $db->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id');
                $stmt->execute([
                    'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
                    'id' => $effectiveUserId
                ]);

                $settings['password_last_changed_at'] = date('Y-m-d H:i:s');
                $encodedSettings = json_encode($settings);
                if ($encodedSettings !== false) {
                    $stmt = $db->prepare('UPDATE users SET settings = :settings WHERE id = :id');
                    $stmt->execute([
                        'settings' => $encodedSettings,
                        'id' => $effectiveUserId
                    ]);
                }

                app_flash('success', 'Password updated.');
                app_log_audit('change_password', [], $user);
            } catch (Exception $e) {
                app_log('Failed to update password: ' . $e->getMessage(), 'ERROR');
                app_flash('error', 'Failed to update password.');
            }

            app_redirect('/settings?page=security');
        }
    }

    app_render_head('Settings');

    // Start dashboard layout
    app_render_dashboard_start($user);
    
    // Render flash messages
    app_render_flash();
    
    // Settings content with sidebar layout
    ?>
    <div class="row">
        <!-- Left Sidebar for Settings Navigation -->
        <div class="col-lg-3 col-md-4">
            <div class="card mb-4">
                <div class="card-header bg-white border-bottom-0">
                    <h5 class="mb-0">Settings</h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <a href="/settings?page=account" 
                           class="list-group-item list-group-item-action d-flex align-items-center <?= $currentPage === 'account' ? 'active' : '' ?>">
                            <i class="fas fa-user me-3"></i>
                            <div>
                                <div class="fw-medium">Account</div>
                                <small class="text-muted">Profile and preferences</small>
                            </div>
                        </a>
                        <a href="/settings?page=security" 
                           class="list-group-item list-group-item-action d-flex align-items-center <?= $currentPage === 'security' ? 'active' : '' ?>">
                            <i class="fas fa-shield-alt me-3"></i>
                            <div>
                                <div class="fw-medium">Security</div>
                                <small class="text-muted">MFA and password</small>
                            </div>
                        </a>
                        <?php if (($user['role'] ?? '') !== 'users'): ?>
                            <a href="/settings?page=global" 
                               class="list-group-item list-group-item-action d-flex align-items-center <?= $currentPage === 'global' ? 'active' : '' ?>">
                                <i class="fas fa-globe me-3"></i>
                                <div>
                                    <div class="fw-medium">Global Settings</div>
                                    <small class="text-muted">Defaults for currency and display</small>
                                </div>
                            </a>
                        <?php endif; ?>
                        <a href="/settings?page=category" 
                           class="list-group-item list-group-item-action d-flex align-items-center <?= $currentPage === 'category' ? 'active' : '' ?>">
                            <i class="fas fa-tags me-3"></i>
                            <div>
                                <div class="fw-medium">Category Management</div>
                                <small class="text-muted">Organize messages and groups</small>
                            </div>
                        </a>
                        <?php if (($user['role'] ?? '') !== 'users'): ?>
                            <a href="/settings?page=connects" 
                               class="list-group-item list-group-item-action d-flex align-items-center <?= $currentPage === 'connects' ? 'active' : '' ?>">
                                <i class="fas fa-plug me-3"></i>
                                <div>
                                    <div class="fw-medium">Connects</div>
                                    <small class="text-muted">Link external accounts</small>
                                </div>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-footer bg-white border-top-0 pt-0">
                    <div class="text-muted small">
                        <i class="fas fa-info-circle me-1"></i>
                        Manage your account settings
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Right Content Area -->
        <div class="col-lg-9 col-md-8">
                <div class="card mb-4">
                <div class="card-header bg-white border-bottom-0">
                    <h4 class="mb-0">
                        <?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?>
                    </h4>
                    <p class="text-muted mb-0">
                        <?= htmlspecialchars($pageDescription, ENT_QUOTES, 'UTF-8') ?>
                    </p>
                </div>
                <div class="card-body">
                    <?php if ($currentPage === 'account'): ?>
                        <!-- Account Settings Content -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <h5 class="mb-0">Account Information</h5>
                                    </div>
                                    <div class="card-body">
                                        <form method="post" action="/settings?page=account">
                                            <input type="hidden" name="settings_action" value="update_account_name">
                                            <div class="mb-3">
                                                <label class="form-label" for="account_name">Name</label>
                                                <input type="text" class="form-control" id="account_name" name="account_name" value="<?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?>" required maxlength="100">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Email</label>
                                                <input type="email" class="form-control" value="<?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?>" readonly>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Role</label>
                                                <input type="text" class="form-control" value="<?= htmlspecialchars($user['role'], ENT_QUOTES, 'UTF-8') ?>" readonly>
                                            </div>
                                            <button type="submit" class="btn btn-primary">Save Name</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <h5 class="mb-0">Preferences</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label">Theme</label>
                                            <select class="form-select" disabled>
                                                <option><?= htmlspecialchars(app_theme(), ENT_QUOTES, 'UTF-8') ?></option>
                                            </select>
                                            <small class="text-muted">Theme can be changed in .env file</small>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Notifications</label>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="notifications" checked disabled>
                                                <label class="form-check-label" for="notifications">
                                                    Receive email notifications
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-4">
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <h5 class="mb-0">Export Settings</h5>
                                    </div>
                                    <div class="card-body">
                                        <p class="text-muted">Download your current settings as a JSON file.</p>
                                        <form method="post" action="/settings?page=account">
                                            <input type="hidden" name="settings_action" value="download_settings">
                                            <button type="submit" class="btn btn-outline-primary">
                                                <i class="fas fa-download me-2"></i>Download Settings
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php if (in_array($user['role'] ?? '', ['admin', 'superadmin'], true)): ?>
                                <div class="col-md-6">
                                    <div class="card mb-3 border-danger">
                                        <div class="card-header text-danger">
                                            <h5 class="mb-0">Danger Zone</h5>
                                        </div>
                                        <div class="card-body">
                                            <p class="text-muted">Delete the admin account and all child users, sessions, and settings.</p>
                                            <form method="post" action="/settings?page=account" onsubmit="return confirm('This will permanently delete your account and all associated data. Continue?');">
                                                <input type="hidden" name="settings_action" value="delete_account">
                                                <div class="mb-3">
                                                    <label class="form-label" for="delete_current_password">Current Password</label>
                                                    <input type="password" class="form-control" id="delete_current_password" name="current_password" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label" for="delete_confirm">Type DELETE to confirm</label>
                                                    <input type="text" class="form-control" id="delete_confirm" name="delete_confirm" required>
                                                </div>
                                                <button type="submit" class="btn btn-danger">
                                                    <i class="fas fa-trash-alt me-2"></i>Delete Account
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php elseif ($currentPage === 'security'): ?>
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <h5 class="mb-0">Multi-Factor Authentication</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <span class="badge <?= $mfaEnabled ? 'bg-success' : 'bg-secondary' ?>">
                                                <?= $mfaEnabled ? 'Enabled' : 'Not Enabled' ?>
                                            </span>
                                        </div>
                                        <p class="text-muted">Scan the QR code with your Authenticator app and enter the 6-digit code to enable MFA.</p>

                                        <?php if ($mfaSecret === ''): ?>
                                            <form method="post" action="/settings?page=security">
                                                <input type="hidden" name="settings_action" value="generate_mfa_secret">
                                                <button type="submit" class="btn btn-outline-primary">Generate Secret</button>
                                            </form>
                                        <?php else: ?>
                                            <div class="mb-3">
                                                <label class="form-label">Secret Key</label>
                                                <input type="text" class="form-control" value="<?= htmlspecialchars($mfaSecret, ENT_QUOTES, 'UTF-8') ?>" readonly>
                                                <small class="text-muted">Store this key securely. You can use it to restore MFA.</small>
                                            </div>
                                            <div class="mb-3 text-center">
                                                <img src="<?= htmlspecialchars($qrCodeUrl, ENT_QUOTES, 'UTF-8') ?>" alt="MFA QR Code" class="img-fluid" style="max-width: 200px;">
                                            </div>
                                            <?php if (!$mfaEnabled): ?>
                                                <form method="post" action="/settings?page=security">
                                                    <input type="hidden" name="settings_action" value="enable_mfa">
                                                    <div class="mb-3">
                                                        <label class="form-label" for="mfa_code">Authenticator Code</label>
                                                        <input type="text" class="form-control" id="mfa_code" name="otp" placeholder="Enter 6-digit code" inputmode="numeric" autocomplete="one-time-code" required>
                                                    </div>
                                                    <button type="submit" class="btn btn-primary">Enable MFA</button>
                                                </form>
                                                <form method="post" action="/settings?page=security" class="mt-2">
                                                    <input type="hidden" name="settings_action" value="generate_mfa_secret">
                                                    <button type="submit" class="btn btn-outline-secondary">Reset Secret</button>
                                                </form>
                                            <?php else: ?>
                                                <form method="post" action="/settings?page=security">
                                                    <input type="hidden" name="settings_action" value="disable_mfa">
                                                    <button type="submit" class="btn btn-outline-danger">Disable MFA</button>
                                                </form>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <h5 class="mb-0">Password Policy</h5>
                                    </div>
                                    <div class="card-body">
                                        <p class="text-muted mb-2">Password change policy: every 6 months.</p>
                                        <p class="text-muted">Last changed: <strong><?= htmlspecialchars($lastPasswordChangedLabel, ENT_QUOTES, 'UTF-8') ?></strong></p>
                                        <hr>
                                        <form method="post" action="/settings?page=security">
                                            <input type="hidden" name="settings_action" value="update_password">
                                            <div class="mb-3">
                                                <label class="form-label" for="current_password">Current Password</label>
                                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label" for="new_password">New Password</label>
                                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label" for="confirm_password">Confirm New Password</label>
                                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                            </div>
                                            <button type="submit" class="btn btn-primary">Update Password</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php elseif ($currentPage === 'global'): ?>
                        <!-- Global Settings Content -->
                        <form method="post" action="/settings?page=global">
                            <input type="hidden" name="settings_action" value="update_global_settings">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card mb-3">
                                        <div class="card-header">
                                            <h5 class="mb-0">Default Currency</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label class="form-label" for="default_currency">Currency</label>
                                                <select class="form-select" id="default_currency" name="default_currency">
                                                    <?php foreach ($currencyOptions as $currencyCode => $currencyLabel): ?>
                                                        <option value="<?= htmlspecialchars($currencyCode, ENT_QUOTES, 'UTF-8') ?>" <?= $defaultCurrency === $currencyCode ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($currencyLabel, ENT_QUOTES, 'UTF-8') ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <small class="text-muted">Applied to amounts in Cases.</small>
                                            </div>
                                            <button type="submit" class="btn btn-primary">Save Settings</button>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card mb-3">
                                        <div class="card-header">
                                            <h5 class="mb-0">Include User Name on Chat</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label class="form-label" for="include_user_name_on_chat">Add name to outgoing messages</label>
                                                <select class="form-select" id="include_user_name_on_chat" name="include_user_name_on_chat">
                                                    <option value="no" <?= !$includeUserNameOnChat ? 'selected' : '' ?>>No</option>
                                                    <option value="yes" <?= $includeUserNameOnChat ? 'selected' : '' ?>>Yes</option>
                                                </select>
                                                <small class="text-muted">If enabled, your name is prefixed on messages sent in Groups.</small>
                                            </div>
                                            <button type="submit" class="btn btn-primary">Save Settings</button>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card mb-3">
                                        <div class="card-header">
                                            <h5 class="mb-0">File Handling for Category Assignment</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label class="form-label" for="file_handling_category_assignment">Assignment Mode</label>
                                                <select class="form-select" id="file_handling_category_assignment" name="file_handling_category_assignment">
                                                    <option value="1" <?= $fileHandlingCategoryAssignment === 1 ? 'selected' : '' ?>>Keywords Assign</option>
                                                    <option value="2" <?= $fileHandlingCategoryAssignment === 2 ? 'selected' : '' ?>>Choose Category</option>
                                                    <option value="3" <?= $fileHandlingCategoryAssignment === 3 ? 'selected' : '' ?>>AI Assign (Prompt Aware)</option>
                                                </select>
                                                <small class="text-muted">AI Assign might make mistake, post user handling may require.</small>
                                            </div>
                                            <button type="submit" class="btn btn-primary">Save Settings</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    <?php elseif ($currentPage === 'connects'): ?>
                        <div class="row">
                            <div class="col-md-8">
                                <div class="card mb-3">
                                    <div class="card-header">
                                            <h5 class="mb-0">WebbyCloud</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <span class="badge <?= $webbycloudConnected ? 'bg-success' : 'bg-secondary' ?>">
                                                <?= $webbycloudConnected ? 'Connected' : 'Not Connected' ?>
                                            </span>
                                            <?php if ($webbycloudConnected): ?>
                                                <div class="text-muted small mt-2">
                                                    Connected as <?= htmlspecialchars($webbycloudDisplayName !== '' ? $webbycloudDisplayName : $webbycloudEmail, ENT_QUOTES, 'UTF-8') ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="d-flex flex-wrap gap-2">
                                            <?php if ($webbycloudConnected): ?>
                                                <form method="post" action="/settings?page=connects">
                                                    <input type="hidden" name="settings_action" value="disconnect_webbycloud">
                                                    <button type="submit" class="btn btn-outline-danger">Disconnect</button>
                                                </form>
                                            <?php else: ?>
                                                <a href="/sso-connect" class="btn btn-primary">Connect WebbyCloud</a>
                                            <?php endif; ?>
                                        </div>
                                        <small class="text-muted d-block mt-3">Use the same email address as your portal account.</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Category Management Content -->
                        <div class="category-management">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <div>
                                    <h5 class="mb-0">Manage Categories</h5>
                                    <p class="text-muted mb-0">Create and organize hierarchical categories for messages and groups</p>
                                </div>
                                <?php if (($user['role'] ?? '') !== 'users'): ?>
                                    <button type="button" class="btn btn-primary" id="addCategoryBtn">
                                        <i class="fas fa-plus me-2"></i>Add Category
                                    </button>
                                <?php endif; ?>
                            </div>
                            
                            <div id="category-management-container" data-user-role="<?= htmlspecialchars($user['role'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                <!-- JavaScript will load content here -->
                                <div class="text-center py-5">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <p class="mt-2 text-muted">Loading categories...</p>
                                </div>
                            </div>
                        </div>
                        
                        <?php if (($user['role'] ?? '') !== 'users'): ?>
                            <!-- Category Modal (Create/Edit) -->
                            <div class="modal fade" id="categoryModal" tabindex="-1" aria-labelledby="categoryModalLabel">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="categoryModalLabel">Add Category</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <form id="categoryForm">
                                                <input type="hidden" id="categoryId" name="id" value="">
                                                
                                                <div class="mb-3">
                                                    <label for="categoryName" class="form-label">Category Name <span class="text-danger">*</span></label>
                                                    <input type="text" class="form-control" id="categoryName" name="name" required maxlength="255">
                                                    <div class="invalid-feedback">Please enter a category name.</div>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label for="categoryDescription" class="form-label">Description</label>
                                                    <textarea class="form-control" id="categoryDescription" name="description" rows="3" maxlength="1000"></textarea>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label for="categoryKeywords" class="form-label">Keywords</label>
                                                    <textarea class="form-control" id="categoryKeywords" name="keywords" rows="2" maxlength="500" placeholder="Enter keywords separated by commas"></textarea>
                                                    <small class="text-muted">Keywords for categorizing messages (comma-separated)</small>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label for="categoryPrompt" class="form-label">Prompt</label>
                                                    <textarea class="form-control" id="categoryPrompt" name="prompt" rows="3" maxlength="2000" placeholder="Enter AI prompt for this category"></textarea>
                                                    <small class="text-muted">AI prompt template for this category</small>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label for="categoryColor" class="form-label">Color</label>
                                                    <div class="input-group">
                                                        <input type="color" class="form-control form-control-color" id="categoryColor" name="color" value="#6c757d" title="Choose category color">
                                                        <input type="text" class="form-control" id="categoryColorText" value="#6c757d" maxlength="7" pattern="^#[0-9A-Fa-f]{6}$">
                                                    </div>
                                                    <small class="text-muted">Click the color box or enter HEX code (e.g., #6c757d)</small>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label for="categoryParent" class="form-label">Parent Category</label>
                                                    <select class="form-select" id="categoryParent" name="parent_id">
                                                        <option value="">None (Root Category)</option>
                                                        <!-- Parent categories will be loaded via JavaScript -->
                                                    </select>
                                                    <small class="text-muted">Select a parent category to create a sub-category</small>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label for="categorySortOrder" class="form-label">Sort Order</label>
                                                    <input type="number" class="form-control" id="categorySortOrder" name="sort_order" value="0" min="0">
                                                    <small class="text-muted">Lower numbers appear first</small>
                                                </div>
                                            </form>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="button" class="btn btn-primary" id="saveCategoryBtn">Save Category</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Include Category Management JavaScript -->
                        <script src="<?= htmlspecialchars(app_asset('js/category-management.js') . '?v=20260420', ENT_QUOTES, 'UTF-8') ?>"></script>
                        
                        <style>
                        .color-badge {
                            display: inline-block;
                            width: 16px;
                            height: 16px;
                            border-radius: 3px;
                            border: 1px solid #dee2e6;
                        }
                        
                        .category-item {
                            transition: background-color 0.2s;
                        }
                        
                        .category-item:hover {
                            background-color: #f8f9fa;
                        }
                        
                        .form-control-color {
                            height: 38px;
                            padding: 3px;
                        }
                        
                        .table tbody tr {
                            vertical-align: middle;
                        }
                        
                        .table tbody tr:hover {
                            background-color: rgba(0, 0, 0, 0.02);
                        }
                        
                        /* Sidebar active state */
                        .list-group-item.active {
                            background-color: #0d6efd;
                            border-color: #0d6efd;
                            color: white;
                        }
                        
                        .list-group-item.active .text-muted {
                            color: rgba(255, 255, 255, 0.8) !important;
                        }
                        
                        .list-group-item.active i {
                            color: white;
                        }
                        </style>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php
    
    // End dashboard layout
    app_render_dashboard_end();
    
    app_render_footer();
}

function app_page_cases(): void
{
    app_require_auth();

    $user = app_current_user();
    $effectiveUser = $user ? app_get_effective_user($user) : $user;
    $effectiveUserId = $effectiveUser['id'] ?? 0;
    $settings = [];
    if ($effectiveUser && !empty($effectiveUser['settings'])) {
        $decodedSettings = json_decode($effectiveUser['settings'], true);
        if (is_array($decodedSettings)) {
            $settings = $decodedSettings;
        }
    }
    $defaultCurrency = $settings['default_currency'] ?? 'USD';

    app_render_head('Cases');

    // Start dashboard layout
    app_render_dashboard_start($user);
    
    // Render flash messages
    app_render_flash();
    
    // Get WhatsApp groups from database (these will act as folders)
    $db = app_db();
    $groups = [];
    $sessions = [];
    
    try {
        // Get all groups with session info
        $stmt = $db->prepare("
            SELECT wg.*, ws.session_name, ws.id as session_id,
                   wgs.frequency as summary_frequency,
                   wgs.summary_schedule as summary_schedule,
                   wgs.prompt as summary_prompt,
                   wgs.include_non_assigned as summary_include_non_assigned,
                   wgs.latest_summary as summary_latest
            FROM whatsapp_groups wg
            LEFT JOIN whatsapp_sessions ws ON wg.session_id = ws.id
            LEFT JOIN whatsapp_group_summaries wgs
                ON wgs.session_id = wg.session_id
                AND wgs.group_id = wg.group_id
                AND wgs.user_id = ?
            WHERE ws.user_id = ?
            ORDER BY ws.session_name ASC, wg.name ASC
        ");
        $stmt->execute([$effectiveUserId, $effectiveUserId]);
        $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get sessions with account info (like the /groups page does)
        require_once __DIR__ . '/whatsapp/sessions.php';
        $sessions = app_whatsapp_get_user_sessions($effectiveUserId);
        
        // Filter to only sessions that have groups
        $sessionsWithGroups = [];
        foreach ($sessions as $session) {
            // Check if this session has any groups
            $hasGroups = false;
            foreach ($groups as $group) {
                if (isset($group['session_id']) && $group['session_id'] == $session['id']) {
                    $hasGroups = true;
                    break;
                }
            }
            
            if ($hasGroups) {
                $sessionsWithGroups[] = $session;
            }
        }
        $sessions = $sessionsWithGroups;
    } catch (Exception $e) {
        // Groups or sessions might not exist yet
    }

    $activeGroupCount = 0;
    $archivedGroupCount = 0;
    $sessionActiveCounts = [];
    $sessionArchivedCounts = [];
    foreach ($groups as $group) {
        if (!isset($group['session_id'])) {
            continue;
        }

        $sessionId = (int) $group['session_id'];
        if ($sessionId <= 0) {
            continue;
        }

        $isArchived = false;
        if (array_key_exists('status', $group) && $group['status'] !== null) {
            $isArchived = $group['status'] === 'archived';
        } elseif (array_key_exists('is_archived', $group)) {
            $isArchived = (bool) $group['is_archived'];
        }

        if ($isArchived) {
            $archivedGroupCount++;
            $sessionArchivedCounts[$sessionId] = ($sessionArchivedCounts[$sessionId] ?? 0) + 1;
        } else {
            $activeGroupCount++;
            $sessionActiveCounts[$sessionId] = ($sessionActiveCounts[$sessionId] ?? 0) + 1;
        }
    }
    
    // Cases content - File Browser Design with Sidebar
    ?>
    <div class="row">
        <!-- Left Sidebar for Session Filtering -->
        <div class="col-lg-3 col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-header bg-white">
                    <h6 class="mb-0">Filter by Session</h6>
                </div>
                <div class="card-body p-0">
                    <!-- Search Box -->
                    <div class="p-3 border-bottom">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white border-end-0">
                                <i class="fas fa-search text-muted"></i>
                            </span>
                            <input type="text" class="form-control border-start-0" id="folder-search" placeholder="Search folders...">
                            <button class="btn btn-outline-secondary" type="button" id="clear-search" style="display: none;">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="list-group list-group-flush session-filter">
                        <a href="#" class="list-group-item list-group-item-action active" data-session-id="all">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-layer-group me-2 text-primary"></i>
                                <span>All Sessions</span>
                                <span class="badge bg-primary rounded-pill ms-auto">
                                    <?php
                                    echo $activeGroupCount;
                                    ?>
                                </span>
                            </div>
                        </a>
                        <?php if (!empty($sessions)): ?>
                            <?php foreach ($sessions as $session): ?>
                                <?php
                                // Validate session data
                                if (!isset($session['id']) || !isset($session['session_name'])) {
                                    continue;
                                }
                                
                                // Ensure session ID is numeric
                                $sessionId = (int)$session['id'];
                                if ($sessionId <= 0) {
                                    continue;
                                }
                                
                                $sessionGroupCount = $sessionActiveCounts[$sessionId] ?? 0;
                                ?>
                                 <a href="#" class="list-group-item list-group-item-action" data-session-id="<?= $sessionId ?>">
                                    <div class="d-flex align-items-center">
                                        <i class="fab fa-whatsapp me-2 text-success"></i>
                                        <span class="text-truncate" title="<?= htmlspecialchars($session['session_name'], ENT_QUOTES, 'UTF-8') ?>">
                                            <?php if (isset($session['account_info']) && isset($session['account_info']['pushName'])): ?>
                                                <?= htmlspecialchars($session['account_info']['pushName'] ?? 'Unknown', ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($session['account_info']['id'] ?? 'Unknown', ENT_QUOTES, 'UTF-8') ?>)
                                            <?php else: ?>
                                                <?= htmlspecialchars($session['session_name'], ENT_QUOTES, 'UTF-8') ?>
                                            <?php endif; ?>
                                        </span>
                                        <?php if ($sessionGroupCount > 0): ?>
                                            <span class="badge bg-secondary rounded-pill ms-auto"><?= $sessionGroupCount ?></span>
                                        <?php endif; ?>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <div class="px-3 py-2 text-muted small text-uppercase border-top">Archived</div>
                        <a href="#" class="list-group-item list-group-item-action" data-session-id="archived">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-archive me-2 text-muted"></i>
                                <span>Archived Groups</span>
                                <span class="badge bg-light text-dark rounded-pill ms-auto">
                                    <?= $archivedGroupCount ?>
                                </span>
                            </div>
                        </a>
                    </div>
                </div>
                <div class="card-footer bg-white border-top-0 pt-0">
                    <div class="d-grid">
                        <a href="/whatsapp-connect" class="btn btn-outline-primary btn-sm">
                            <i class="fab fa-whatsapp me-1"></i> Add Session
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Main Content Area -->
        <div class="col-lg-9 col-md-8">
            <div class="card mb-4">
                <div class="card-header bg-white border-bottom-0 d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="mb-0">Cases</h4>
                        <p class="text-muted mb-0">File browser for synced WhatsApp groups (folders)</p>
                    </div>
                     <div class="d-flex align-items-center" id="cases-toolbar">
                         <div class="btn-group me-3">
                             <button type="button" class="btn btn-outline-secondary active" id="view-grid">
                                 <i class="fas fa-th-large"></i>
                             </button>
                             <button type="button" class="btn btn-outline-secondary" id="view-list">
                                 <i class="fas fa-list"></i>
                             </button>
                         </div>
                         <div class="dropdown">
                             <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="sort-dropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                 <i class="fas fa-sort-amount-down me-1"></i> Sort
                             </button>
                             <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="sort-dropdown">
                                  <li><a class="dropdown-item" href="#" data-sort="name-asc"><i class="fas fa-sort-alpha-down me-2"></i>Name (A-Z)</a></li>
                                  <li><a class="dropdown-item" href="#" data-sort="name-desc"><i class="fas fa-sort-alpha-up me-2"></i>Name (Z-A)</a></li>
                                 <li><hr class="dropdown-divider"></li>
                                 <li><a class="dropdown-item" href="#" data-sort="messages-desc"><i class="fas fa-comments me-2"></i>Most Messages</a></li>
                                 <li><a class="dropdown-item" href="#" data-sort="messages-asc"><i class="fas fa-comment me-2"></i>Fewest Messages</a></li>
                                 <li><hr class="dropdown-divider"></li>
                                 <li><a class="dropdown-item" href="#" data-sort="members-desc"><i class="fas fa-users me-2"></i>Most Members</a></li>
                                 <li><a class="dropdown-item" href="#" data-sort="members-asc"><i class="fas fa-user me-2"></i>Fewest Members</a></li>
                                 <li><hr class="dropdown-divider"></li>
                                  <li><a class="dropdown-item active" href="#" data-sort="latest"><i class="fas fa-clock me-2"></i>Latest Activity</a></li>
                                  <li><a class="dropdown-item" href="#" data-sort="oldest"><i class="fas fa-history me-2"></i>Oldest Activity</a></li>
                             </ul>
                         </div>
                     </div>
                </div>
                <div class="card-body p-0" style="height: 75vh; overflow-y: auto;">
                    <div class="container-fluid p-4">
                        <?php if (empty($groups)): ?>
                            <div class="text-center text-muted py-5">
                                <i class="fas fa-folder-open fa-3x mb-3"></i>
                                <h5>No groups found</h5>
                                <p>Sync WhatsApp groups to see them appear as folders here.</p>
                                <a href="/whatsapp-connect" class="btn btn-primary mt-2">
                                    <i class="fab fa-whatsapp me-2"></i>Connect WhatsApp
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="row" id="folders-grid">
                                <?php if (!empty($groups)): ?>
                                    <?php foreach ($groups as $group): ?>
                                        <?php
                                        // Validate group data
                                        if (!isset($group['name'], $group['session_id'], $group['session_name'], $group['group_id'])) {
                                            continue;
                                        }
                                        
                                        // Ensure session ID is numeric
                                        $sessionId = (int)$group['session_id'];
                                        if ($sessionId <= 0) {
                                            continue;
                                        }

                                        $isArchived = false;
                                        if (array_key_exists('status', $group) && $group['status'] !== null) {
                                            $isArchived = $group['status'] === 'archived';
                                        } elseif (array_key_exists('is_archived', $group)) {
                                            $isArchived = (bool) $group['is_archived'];
                                        }
                                        $groupChatId = $group['group_id'] ?? '';
                                        if (!is_string($groupChatId)) {
                                            $groupChatId = '';
                                        }

                                        $summaryFrequency = '';
                                        $summaryTime = '';
                                        $summaryWeekday = '';
                                        $summaryMonthDay = '';
                                        $summaryPrompt = '';
                                        $summaryIncludeNonAssigned = 0;
                                        $summaryLatest = '';
                                        $hasScheduleSummary = false;
                                        if (!empty($group['summary_frequency']) && !empty($group['summary_schedule'])) {
                                            $summaryFrequency = (string) $group['summary_frequency'];
                                            $summaryPrompt = (string) ($group['summary_prompt'] ?? '');
                                            $summaryIncludeNonAssigned = (int) ($group['summary_include_non_assigned'] ?? 0);
                                            $decodedSchedule = json_decode((string) $group['summary_schedule'], true);
                                            if (is_array($decodedSchedule)) {
                                                $summaryTime = (string) ($decodedSchedule['time'] ?? '');
                                                $summaryWeekday = (string) ($decodedSchedule['weekday'] ?? '');
                                                $summaryMonthDay = (string) ($decodedSchedule['month_day'] ?? '');
                                            }
                                            if ($summaryFrequency !== '' && $summaryTime !== '') {
                                                $hasScheduleSummary = true;
                                            }
                                        }
                                        if (!empty($group['summary_latest'])) {
                                            $summaryLatest = (string) $group['summary_latest'];
                                        }

                                        // Get message count safely
                                        $messageCount = 0;
                                        $lastActivity = 0;
                                        try {
                                            // Get message count
                                            $stmt = $db->prepare("SELECT COUNT(*) FROM group_messages WHERE group_id = ?");
                                            $stmt->execute([$group['group_id']]);
                                            $messageCount = (int)$stmt->fetchColumn();
                                            
                                            // Get last activity timestamp
                                            $stmt = $db->prepare("SELECT MAX(timestamp) FROM group_messages WHERE group_id = ?");
                                            $stmt->execute([$group['group_id']]);
                                            $lastActivity = (int)$stmt->fetchColumn();
                                        } catch (Exception $e) {
                                            $messageCount = 0;
                                            $lastActivity = 0;
                                        }
                                        ?>
                                        <div class="col-xl-4 col-lg-4 col-md-6 mb-4 folder-item" 
                                             data-session-id="<?= $sessionId ?>"
                                             data-name="<?= htmlspecialchars($group['name'], ENT_QUOTES, 'UTF-8') ?>"
                                             data-message-count="<?= $messageCount ?>"
                                             data-participant-count="<?= isset($group['participant_count']) ? (int)$group['participant_count'] : 0 ?>"
                                             data-last-activity="<?= $lastActivity ?>"
                                             data-status="<?= $isArchived ? 'archived' : 'active' ?>">
                                            <div class="card folder-card h-100 border">
                                                <div class="card-body d-flex flex-column">
                                                    <div class="d-flex align-items-start mb-3">
                                                        <div class="flex-grow-1">
                                                            <h6 class="mb-0 text-truncate" title="<?= htmlspecialchars($group['name'], ENT_QUOTES, 'UTF-8') ?>">
                                                                <?= htmlspecialchars($group['name'], ENT_QUOTES, 'UTF-8') ?>
                                                                <?php if ($summaryLatest !== ''): ?>
                                                                    <a class="ms-2 text-muted group-summary-latest" href="#"
                                                                       data-group-name="<?= htmlspecialchars($group['name'], ENT_QUOTES, 'UTF-8') ?>"
                                                                       data-summary-latest="<?= htmlspecialchars($summaryLatest, ENT_QUOTES, 'UTF-8') ?>"
                                                                       title="View latest summary">
                                                                        <i class="fas fa-info-circle"></i>
                                                                    </a>
                                                                <?php endif; ?>
                                                            </h6>
                                                            <?php if ($groupChatId !== ''): ?>
                                                                <small class="text-muted d-block" title="<?= htmlspecialchars($groupChatId, ENT_QUOTES, 'UTF-8') ?>">
                                                                    <?= htmlspecialchars($groupChatId, ENT_QUOTES, 'UTF-8') ?>
                                                                </small>
                                                            <?php endif; ?>
                                                            <small class="text-muted session-badge" data-session-id="<?= $sessionId ?>">
                                                                <?php
                                                                // Find the session info for this group
                                                                $sessionCaption = $group['session_name'];
                                                                foreach ($sessions as $session) {
                                                                    if ($session['id'] == $sessionId) {
                                                                        if (isset($session['account_info']) && isset($session['account_info']['pushName'])) {
                                                                            $sessionCaption = htmlspecialchars($session['account_info']['pushName'] ?? 'Unknown', ENT_QUOTES, 'UTF-8') . ' (' . htmlspecialchars($session['account_info']['id'] ?? 'Unknown', ENT_QUOTES, 'UTF-8') . ')';
                                                                        } else {
                                                                            $sessionCaption = htmlspecialchars($session['session_name'], ENT_QUOTES, 'UTF-8');
                                                                        }
                                                                        break;
                                                                    }
                                                                }
                                                                echo $sessionCaption;
                                                                ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="folder-stats mb-3">
                                                        <div class="row text-center">
                                                            <div class="col-6">
                                                                <div class="small text-muted">Participants</div>
                                                                <div class="h6 mb-0"><?= isset($group['participant_count']) ? (int)$group['participant_count'] : 0 ?></div>
                                                            </div>
                                                            <div class="col-6">
                                                                <div class="small text-muted">Messages</div>
                                                                <div class="h6 mb-0"><?= $messageCount ?></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="mt-auto">
                                                        <div class="btn-group w-100" role="group">
                                                            <button class="btn btn-outline-primary btn-sm open-folder" style="flex: 0 0 80%;"
                                                                    data-group-id="<?= htmlspecialchars($group['group_id'], ENT_QUOTES, 'UTF-8') ?>"
                                                                data-group-name="<?= htmlspecialchars($group['name'], ENT_QUOTES, 'UTF-8') ?>">
                                                                <i class="fas fa-folder-open me-1"></i> Open
                                                            </button>
                                                            <button class="btn btn-outline-secondary btn-sm dropdown-toggle dropdown-toggle-split" style="flex: 0 0 20%;" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                                <i class="fas fa-ellipsis-v" aria-hidden="true"></i>
                                                                <span class="visually-hidden">Toggle dropdown</span>
                                                            </button>
                                                            <ul class="dropdown-menu dropdown-menu-end">
                                                                <li>
                                                                    <a class="dropdown-item group-schedule-summary" href="#"
                                                                       data-group-id="<?= htmlspecialchars($group['group_id'], ENT_QUOTES, 'UTF-8') ?>"
                                                                       data-session-id="<?= $sessionId ?>"
                                                                       data-session-name="<?= htmlspecialchars($group['session_name'], ENT_QUOTES, 'UTF-8') ?>"
                                                                       data-group-name="<?= htmlspecialchars($group['name'], ENT_QUOTES, 'UTF-8') ?>"
                                                                       data-summary-frequency="<?= htmlspecialchars($summaryFrequency, ENT_QUOTES, 'UTF-8') ?>"
                                                                       data-summary-time="<?= htmlspecialchars($summaryTime, ENT_QUOTES, 'UTF-8') ?>"
                                                                       data-summary-weekday="<?= htmlspecialchars($summaryWeekday, ENT_QUOTES, 'UTF-8') ?>"
                                                                       data-summary-month-day="<?= htmlspecialchars($summaryMonthDay, ENT_QUOTES, 'UTF-8') ?>"
                                                                       data-summary-prompt="<?= htmlspecialchars($summaryPrompt, ENT_QUOTES, 'UTF-8') ?>"
                                                                       data-summary-include-non-assigned="<?= (int) $summaryIncludeNonAssigned ?>">
                                                                        <i class="fas fa-calendar-alt me-2"></i>
                                                                        <?= $hasScheduleSummary ? 'Edit Schedule Summary' : 'Set Schedule Summary' ?>
                                                                    </a>
                                                                </li>
                                                                <li><hr class="dropdown-divider"></li>
                                                                <li>
                                                                    <a class="dropdown-item group-archive-toggle" href="#"
                                                                       data-group-id="<?= htmlspecialchars($group['group_id'], ENT_QUOTES, 'UTF-8') ?>"
                                                                       data-session-id="<?= $sessionId ?>"
                                                                       data-action="<?= $isArchived ? 'unarchive' : 'archive' ?>">
                                                                        <i class="fas <?= $isArchived ? 'fa-box-open' : 'fa-archive' ?> me-2"></i>
                                                                        <?= $isArchived ? 'Unarchive' : 'Archive' ?>
                                                                    </a>
                                                                </li>
                                                            </ul>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="col-12">
                                        <div class="text-center text-muted py-5">
                                            <i class="fas fa-folder-open fa-3x mb-3"></i>
                                            <h5>No WhatsApp groups found</h5>
                                            <p>Connect WhatsApp and sync groups to see them here.</p>
                                            <a href="/whatsapp-connect" class="btn btn-primary mt-2">
                                                <i class="fab fa-whatsapp me-2"></i>Connect WhatsApp
                                            </a>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Folder Content View (hidden by default) -->
                            <div id="folder-content-view" class="d-none">
                                <div class="d-flex align-items-center mb-4">
                                    <button class="btn btn-outline-secondary me-3" id="back-to-folders">
                                        <i class="fas fa-arrow-left me-1"></i> Back
                                    </button>
                                    <div>
                                        <h4 class="mb-0" id="current-folder-name"></h4>
                                        <small class="text-muted" id="current-folder-info"></small>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-lg-4">
                                        <div class="card mb-4">
                                            <div class="card-header bg-white">
                                                <h6 class="mb-0">Category Tree</h6>
                                            </div>
                                            <div class="card-body p-0">
                                                <div id="category-tree" class="p-3">
                                                    <!-- Category tree will be loaded via AJAX -->
                                                    <div class="text-center text-muted py-4">
                                                        <div class="spinner-border spinner-border-sm me-2"></div>
                                                        Loading categories...
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-lg-8">
                                        <div class="card mb-4">
                                            <div class="card-header bg-white">
                                                <div class="d-flex align-items-center gap-3 flex-wrap">
                                                    <h6 class="mb-0 me-auto">Messages & Files</h6>
                                                    <button class="btn btn-sm btn-outline-primary" type="button" id="case-download-btn">
                                                        <i class="fas fa-download me-1"></i>Download Case
                                                    </button>
                                                    <div class="input-group input-group-sm" style="max-width: 260px;">
                                                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                                                        <input type="text" class="form-control" id="messages-files-search" placeholder="Search messages & files" aria-label="Search messages and files">
                                                    </div>
                                                    <div class="btn-group">
                                                        <button class="btn btn-sm btn-outline-secondary active" data-filter="all">All</button>
                                                        <button class="btn btn-sm btn-outline-secondary" data-filter="messages">Messages</button>
                                                        <button class="btn btn-sm btn-outline-secondary" data-filter="files">Files</button>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="card-body p-0">
                                                <div id="messages-files-list" class="p-3" style="max-height: 90%; overflow-y: auto;">
                                                    <!-- Messages and files will be loaded via AJAX -->
                                                    <div class="text-center text-muted py-4">
                                                        <div class="spinner-border spinner-border-sm me-2"></div>
                                                        Loading messages and files...
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <style>
    /* Cases Page - Folder Browser Styles */
    .folder-card {
        transition: all 0.2s ease;
        border: 1px solid #e9ecef;
        overflow: visible;
    }
    
    .folder-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        border-color: #007bff;
    }
    
    .folder-card .card-body {
        display: flex;
        flex-direction: column;
        overflow: visible;
    }

    .group-menu {
        line-height: 1;
    }

    .group-menu:hover {
        text-decoration: none;
    }

    .folder-card .dropdown-menu {
        z-index: 3001;
    }

    .folder-item {
        position: relative;
        z-index: 1;
    }

    .folder-item.dropdown-open {
        z-index: 3000;
    }
    
    .folder-icon {
        width: 48px;
        height: 48px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(255, 193, 7, 0.1);
        border-radius: 8px;
    }
    
    .folder-stats {
        border-top: 1px solid #e9ecef;
        border-bottom: 1px solid #e9ecef;
        padding: 12px 0;
        margin: 12px 0;
    }
    
    .folder-stats .col-6 {
        border-right: 1px solid #e9ecef;
    }
    
    .folder-stats .col-6:last-child {
        border-right: none;
    }
    
    /* Category Tree Styles */
    .category-tree {
        max-height: 300px;
        overflow-y: auto;
    }
    
    .category-item {
        cursor: pointer;
        transition: all 0.2s ease;
    }
    
    .category-item:hover {
        background-color: #f8f9fa;
    }
    
    .category-item.selected {
        background-color: #e7f1ff;
        border-color: #007bff !important;
    }
    
    .category-children {
        border-left: 2px solid #e9ecef;
        padding-left: 12px;
    }
    
    .category-item .badge {
        font-size: 0.7rem;
        padding: 0.25rem 0.5rem;
    }
    
    /* Messages & Files List */
    .message-file-list {
        max-height: 100%;
        overflow-y: auto;
    }
    
    .message-item, .file-item {
        transition: all 0.2s ease;
        width: 95%;
    }
    
    .message-item:hover, .file-item:hover {
        background-color: #f8f9fa;
        transform: translateX(2px);
    }
    
    .message-item .fa-comment {
        color: #007bff;
    }
    
    .message-item .fa-comment-dots {
        color: #28a745;
    }

    .message-data-highlight {
        font-weight: 700;
        font-size: 1.05rem;
        text-align: right;
    }

    .message-data-view {
        margin-top: 0.35rem;
    }

    .message-data-modal-content {
        white-space: pre-wrap;
        font-size: 0.95rem;
    }

    .message-data-hidden {
        display: none;
    }
    
    .file-item .fa-file-pdf {
        color: #dc3545;
    }
    
    .file-item .fa-file-word {
        color: #2b579a;
    }
    
    .file-item .fa-file-excel {
        color: #217346;
    }
    
    .file-item .fa-file-image {
        color: #28a745;
    }
    
    .file-item .fa-file-audio {
        color: #6f42c1;
    }
    
    .file-item .fa-file-video {
        color: #fd7e14;
    }

    .message-file-thumb {
        max-height: 120px;
        cursor: pointer;
    }
    
    /* Narrower folder cards */
    .folder-card {
        margin-left: 1%;
        margin-right: 1%;
        width: 98%;
    }
    
    /* Message item dropdown button */
    .message-item .dropdown-toggle,
    .file-item .dropdown-toggle {
        padding: 0.15rem 0.3rem;
        font-size: 0.75rem;
        border: none;
        background: transparent;
        color: #6c757d;
    }
    
    .message-item .dropdown-toggle:hover,
    .file-item .dropdown-toggle:hover {
        background-color: rgba(0, 0, 0, 0.05);
        color: #495057;
    }
    
    .message-item .dropdown-toggle:focus,
    .file-item .dropdown-toggle:focus {
        box-shadow: none;
    }
    
    .message-item .dropdown-toggle::after,
    .file-item .dropdown-toggle::after {
        display: none;
    }
    
    /* Scrollable grid container */
    .card-body[style*="height: 75vh"] {
        scrollbar-width: thin;
        scrollbar-color: #adb5bd #f8f9fa;
    }
    
    .card-body[style*="height: 75vh"]::-webkit-scrollbar {
        width: 8px;
    }
    
    .card-body[style*="height: 75vh"]::-webkit-scrollbar-track {
        background: #f8f9fa;
    }
    
    .card-body[style*="height: 75vh"]::-webkit-scrollbar-thumb {
        background-color: #adb5bd;
        border-radius: 4px;
    }
    
    /* List view for folders */
    .row-cols-1 .folder-item {
        margin-bottom: 1rem;
        width: 100%;
    }
    
    .row-cols-1 .folder-card {
        flex-direction: row !important;
        height: auto !important;
    }
    
    .row-cols-1 .folder-card .card-body {
        flex-direction: row !important;
        align-items: center;
        padding: 1rem !important;
    }
    
    .row-cols-1 .folder-card .flex-grow-1 {
        flex-grow: 1;
        min-width: 200px;
    }
    
    .row-cols-1 .folder-stats {
        border: none;
        padding: 0;
        margin: 0 2rem;
        flex-grow: 1;
    }
    
    .row-cols-1 .folder-stats .row {
        justify-content: space-around;
    }
    
    .row-cols-1 .folder-stats .col-6 {
        border: none;
        text-align: center;
    }
    
    .row-cols-1 .folder-card .mt-auto {
        margin-top: 0 !important;
        margin-left: auto;
    }
    
    /* Session Filter Sidebar Styles */
    .session-filter .list-group-item {
        border-left: 0;
        border-right: 0;
        border-radius: 0;
        padding: 0.75rem 1rem;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    
    .session-filter .list-group-item:first-child {
        border-top: 0;
    }
    
    .session-filter .list-group-item:last-child {
        border-bottom: 0;
    }
    
    .session-filter .list-group-item:hover {
        background-color: #f8f9fa;
    }
    
    .session-filter .list-group-item.active {
        background-color: #e7f1ff;
        border-color: #dee2e6;
        color: #0d6efd;
        font-weight: 500;
    }
    
    .session-filter .list-group-item.active .badge {
        background-color: #0d6efd !important;
    }
    
    .session-filter .list-group-item .text-truncate {
        max-width: 150px;
    }
    
    /* Session badge in folder cards */
    .session-badge {
        display: inline-block;
        padding: 0.2rem 0.5rem;
        background-color: #f8f9fa;
        border-radius: 4px;
        font-size: 0.75rem;
    }
    
    /* Search highlighting */
    mark.bg-warning {
        padding: 0.1rem 0.2rem;
        border-radius: 3px;
    }
    
    /* Empty state */
    .empty-state {
        min-height: 300px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    /* Search input styling */
    #folder-search {
        border-radius: 0.375rem 0 0 0.375rem;
    }
    
    #clear-search {
        border-radius: 0 0.375rem 0.375rem 0;
    }
    
    /* Responsive adjustments */
    @media (max-width: 768px) {
        .col-lg-3, .col-md-4 {
            margin-bottom: 1rem;
        }
        
        .session-filter .list-group-item .text-truncate {
            max-width: 100px;
        }
        
        .empty-state {
            min-height: 200px;
        }
    }
    
    @media (max-width: 576px) {
        .session-filter .list-group-item {
            padding: 0.5rem 0.75rem;
        }
        
        .session-filter .list-group-item .text-truncate {
            max-width: 80px;
        }
    }
    </style>

    <!-- Messages & Files Image Lightbox -->
    <div class="modal fade" id="messagesFilesImageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Image Preview</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="messagesFilesImagePreview" src="" class="img-fluid" alt="Image preview">
                    <div id="messagesFilesImageCaption" class="mt-2 small text-muted"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="downloadMessagesFilesImage()">Download</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Messages & Files PDF Modal -->
    <div class="modal fade" id="messagesFilesPdfModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">PDF Preview</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <iframe id="messagesFilesPdfFrame" src="" title="PDF Preview" style="width: 100%; height: 100%; border: 0;"></iframe>
                </div>
            </div>
        </div>
    </div>

    <!-- Messages & Files Data Modal -->
    <div class="modal fade" id="messagesFilesDataModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Message data</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <pre id="messagesFilesDataContent" class="message-data-modal-content mb-0"></pre>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Schedule Summary Modal -->
    <div class="modal fade" id="scheduleSummaryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="schedule-summary-form">
                    <div class="modal-header">
                        <h5 class="modal-title">Set Schedule Summary</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="small text-muted mb-3" id="schedule-summary-target"></div>
                        <input type="hidden" id="schedule-summary-group-id" name="group_id">
                        <input type="hidden" id="schedule-summary-session-id" name="session_id">

                        <div class="mb-3">
                            <label for="schedule-summary-frequency" class="form-label">Frequency of Summary</label>
                            <select class="form-select" id="schedule-summary-frequency" name="frequency" required>
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                                <option value="monthly">Monthly</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="schedule-summary-time" class="form-label">Summary Time</label>
                            <input type="time" class="form-control" id="schedule-summary-time" name="summary_time" required>
                        </div>

                        <div class="mb-3">
                            <label for="schedule-summary-include-non-assigned" class="form-label">Include Non-assigned</label>
                            <select class="form-select" id="schedule-summary-include-non-assigned" name="include_non_assigned" required>
                                <option value="0">No</option>
                                <option value="1">Yes</option>
                            </select>
                        </div>

                        <div class="mb-3 d-none" id="schedule-summary-weekly">
                            <label for="schedule-summary-weekday" class="form-label">Summary Day</label>
                            <select class="form-select" id="schedule-summary-weekday" name="summary_weekday">
                                <option value="">Select day</option>
                                <option value="monday">Monday</option>
                                <option value="tuesday">Tuesday</option>
                                <option value="wednesday">Wednesday</option>
                                <option value="thursday">Thursday</option>
                                <option value="friday">Friday</option>
                                <option value="saturday">Saturday</option>
                                <option value="sunday">Sunday</option>
                            </select>
                        </div>

                        <div class="mb-3 d-none" id="schedule-summary-monthly">
                            <label for="schedule-summary-month-day" class="form-label">Summary Day</label>
                            <select class="form-select" id="schedule-summary-month-day" name="summary_month_day">
                                <option value="">Select day</option>
                                <option value="1">1st of every month</option>
                                <option value="15">15th of every month</option>
                                <option value="end">End of every month</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="schedule-summary-prompt" class="form-label">Summary Prompt</label>
                            <textarea class="form-control" id="schedule-summary-prompt" name="prompt" rows="4" placeholder="Describe how the summary should be written" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-danger me-auto d-none" id="schedule-summary-remove">Remove Schedule</button>
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="schedule-summary-save">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Latest Summary Modal -->
    <div class="modal fade" id="latestSummaryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Latest Summary</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="small text-muted mb-3" id="latest-summary-target"></div>
                    <pre class="mb-0" id="latest-summary-content"></pre>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    const casesDefaultCurrency = <?= json_encode($defaultCurrency, JSON_UNESCAPED_SLASHES) ?>;
    const currentUserRole = <?= json_encode($user['role'] ?? '', JSON_UNESCAPED_SLASHES) ?>;
    const currentUserId = <?= json_encode((int) ($user['id'] ?? 0), JSON_UNESCAPED_SLASHES) ?>;
    const canDeleteMessages = ['admin', 'superadmin'].includes(currentUserRole);
    const canManageCategories = currentUserRole !== 'users';
    let currentCaseExport = null;
    // Global functions for category assignment
     function loadCategoriesForMessage(messageId, dropdownItem) {
         console.log('loadCategoriesForMessage called with messageId:', messageId, 'dropdownItem:', dropdownItem);
         
         // Check if escapeHtml function is available
         if (typeof escapeHtml !== 'function') {
             console.error('escapeHtml function is not available!');
             dropdownItem.textContent = 'Error: escapeHtml function not found';
             return;
         }
         
         fetch('/api/whatsapp/categories?all=true')
             .then(response => {
                 console.log('Categories API response status:', response.status);
                 if (!response.ok) {
                     throw new Error(`HTTP error! status: ${response.status}`);
                 }
                 return response.json();
             })
             .then(data => {
                 console.log('Categories API response data:', data);
                 if (data.success && data.data.categories) {
                     const categories = data.data.categories;
                     console.log('Loaded categories:', categories.length);
                     
                     // Use the same function to populate dropdown
                     if (typeof window.populateCategoriesInDropdown === 'function') {
                         window.populateCategoriesInDropdown(dropdownItem, messageId, categories);
                     } else {
                         console.error('populateCategoriesInDropdown function not available');
                         dropdownItem.textContent = 'Error: Function not available';
                     }
                 } else {
                     console.log('API returned error:', data.message);
                     dropdownItem.textContent = 'Failed to load categories: ' + (data.message || 'Unknown error');
                 }
             })
             .catch(error => {
                 console.error('Error loading categories:', error);
                 dropdownItem.textContent = 'Error loading categories: ' + error.message;
             });
     }
    
     function assignMessageToCategory(messageId, categoryId) {
         fetch(`/api/whatsapp/messages/${messageId}/category`, {
             method: 'POST',
             headers: {
                 'Content-Type': 'application/json',
             },
             body: JSON.stringify({
                 category_id: categoryId
             })
         })
         .then(response => response.json())
         .then(data => {
             if (data.success) {
                 // Show success message with SweetAlert
                 Swal.fire({
                     icon: 'success',
                     title: 'Success',
                     text: 'Message assigned to category successfully!',
                     timer: 2000,
                     showConfirmButton: false
                 });
                 
                 // Use setTimeout to trigger All button click after SweetAlert timer
                 setTimeout(() => {
                     console.log('SweetAlert timer finished, resetting to All view');
                     
                     // Check if we're in folder content view
                     const folderContentView = document.getElementById('folder-content-view');
                     if (!folderContentView || folderContentView.classList.contains('d-none')) {
                         console.log('Not in folder content view, skipping All button click');
                         return;
                     }
                     
                     // Get the current folder from the folder content view
                     const currentFolderName = document.getElementById('current-folder-name');
                     let groupId = null;
                     
                     if (currentFolderName && currentFolderName.textContent) {
                         // Find the open folder button by name
                         const openFolderBtn = document.querySelector('.open-folder[data-group-name="' + currentFolderName.textContent + '"]');
                         if (openFolderBtn) {
                             groupId = openFolderBtn.getAttribute('data-group-id');
                             console.log('Current folder ID:', groupId);
                         }
                     }
                     
                     // Check if loadFolderContent function exists (it's defined inside DOMContentLoaded)
                     if (typeof loadFolderContent === 'function') {
                         // Also clear any selected category
                         document.querySelectorAll('.category-item').forEach(item => {
                             item.classList.remove('selected');
                         });
                         
                         // Reset filter to "All" view by clicking the All button
                         const allFilterBtn = document.querySelector('[data-filter="all"]');
                         console.log('All button found:', allFilterBtn);
                         
                         if (allFilterBtn) {
                             console.log('Clicking All button...');
                             
                             // First, manually update active state
                             const filterBtns = document.querySelectorAll('[data-filter]');
                             filterBtns.forEach(btn => btn.classList.remove('active'));
                             allFilterBtn.classList.add('active');
                             
                             // Try to trigger click
                             allFilterBtn.click();
                             console.log('All button clicked');
                             
                             // Also directly call loadFolderContent as backup
                             if (groupId) {
                                 setTimeout(() => {
                                     console.log('Calling loadFolderContent as backup');
                                     loadFolderContent(groupId);
                                 }, 100);
                             }
                         } else {
                             console.log('All button not found, reloading folder content directly');
                             // If All button not found, just reload
                             if (groupId) {
                                 loadFolderContent(groupId);
                             }
                         }
                     } else {
                         console.error('loadFolderContent function not found!');
                     }
                 }, 2100); // Slightly longer than SweetAlert timer (2000ms)
             } else {
                 Swal.fire({
                     icon: 'error',
                     title: 'Error',
                     text: 'Failed to assign message: ' + (data.message || 'Unknown error')
                 });
             }
         })
         .catch(error => {
             console.error('Error assigning message to category:', error);
             Swal.fire({
                 icon: 'error',
                 title: 'Error',
                 text: 'Error assigning message to category'
             });
         });
     }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function formatAmount(value) {
        if (value === null || value === undefined) {
            return '';
        }
        const rawText = value.toString().trim();
        if (!rawText) {
            return '';
        }
        const numericValue = parseFloat(rawText.replace(/,/g, ''));
        if (!Number.isFinite(numericValue)) {
            return rawText;
        }
        if (numericValue === 0) {
            return '';
        }
        const formattedNumber = numericValue.toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
        const currencyCode = typeof casesDefaultCurrency === 'string' ? casesDefaultCurrency.trim() : '';
        if (!currencyCode) {
            return formattedNumber;
        }
        return `${currencyCode} ${formattedNumber}`;
    }

    function setCurrentCaseExport(groupId, sessionId, groupName) {
        if (!groupId || !sessionId) {
            currentCaseExport = null;
            const button = document.getElementById('case-download-btn');
            if (button) {
                button.disabled = true;
            }
            return;
        }
        currentCaseExport = {
            groupId: groupId,
            sessionId: sessionId,
            groupName: groupName || 'Case'
        };
        const button = document.getElementById('case-download-btn');
        if (button) {
            button.disabled = false;
        }
    }

    function startCaseExport() {
        if (!currentCaseExport || !currentCaseExport.groupId || !currentCaseExport.sessionId) {
            if (window.Swal) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Select a case',
                    text: 'Open a case first to download the export.'
                });
            } else {
                alert('Open a case first to download the export.');
            }
            return;
        }

        const button = document.getElementById('case-download-btn');
        if (button) {
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Preparing...';
        }

        fetch(`/api/cases/${encodeURIComponent(currentCaseExport.groupId)}/export`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                session_id: currentCaseExport.sessionId
            })
        })
            .then(response => response.json())
            .then(data => {
                if (data && data.success) {
                    if (window.Swal) {
                        Swal.fire({
                            icon: 'info',
                            title: 'Export started',
                            text: 'We are preparing your case export. You will get a notification here when it is ready.'
                        });
                    } else {
                        alert('Export started. You will get a notification when it is ready.');
                    }
                } else {
                    const message = (data && data.message) ? data.message : 'Failed to start export.';
                    if (window.Swal) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Export failed',
                            text: message
                        });
                    } else {
                        alert(message);
                    }
                }
            })
            .catch(error => {
                const message = error && error.message ? error.message : 'Failed to start export.';
                if (window.Swal) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Export failed',
                        text: message
                    });
                } else {
                    alert(message);
                }
            })
            .finally(() => {
                if (button) {
                    button.disabled = false;
                    button.innerHTML = '<i class="fas fa-download me-1"></i>Download Case';
                }
            });
    }

    function showCaseExportReady(data) {
        const groupName = data && data.group_name ? data.group_name : 'Case';
        const downloadUrl = data && data.download_url ? data.download_url : '';
        if (downloadUrl === '') {
            return;
        }
        const safeUrl = encodeURI(downloadUrl);

        if (window.Swal) {
            Swal.fire({
                icon: 'success',
                title: 'Export ready',
                html: `Your export for <strong>${escapeHtml(groupName)}</strong> is ready. <br><a href="${safeUrl}" class="btn btn-sm btn-primary mt-2">Download</a>`,
                showConfirmButton: false
            });
        } else {
            window.location.href = safeUrl;
        }
    }

    function showCaseExportFailed(data) {
        const groupName = data && data.group_name ? data.group_name : 'Case';
        const errorMessage = data && data.error_message ? data.error_message : 'Export failed.';
        if (window.Swal) {
            Swal.fire({
                icon: 'error',
                title: `Export failed: ${groupName}`,
                text: errorMessage
            });
        } else {
            alert(`Export failed for ${groupName}: ${errorMessage}`);
        }
    }

    function openMessagesFilesImageLightbox(trigger) {
        const imageUrl = trigger.getAttribute('data-image-url') || '';
        const caption = trigger.getAttribute('data-image-caption') || '';
        if (!imageUrl) {
            return;
        }

        const imageEl = document.getElementById('messagesFilesImagePreview');
        const captionEl = document.getElementById('messagesFilesImageCaption');
        if (!imageEl || !captionEl) {
            return;
        }

        imageEl.src = imageUrl;
        captionEl.textContent = caption;
        captionEl.style.display = caption ? 'block' : 'none';

        window.currentMessagesFilesImage = {
            url: imageUrl,
            caption: caption
        };

        const modal = new bootstrap.Modal(document.getElementById('messagesFilesImageModal'));
        modal.show();
    }

    function downloadMessagesFilesImage() {
        if (!window.currentMessagesFilesImage || !window.currentMessagesFilesImage.url) {
            return;
        }

        const link = document.createElement('a');
        link.href = window.currentMessagesFilesImage.url;
        link.download = 'image_' + Date.now() + '.jpg';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    function openMessagesFilesPdfModal(trigger) {
        const fileUrl = trigger.getAttribute('data-file-url') || '';
        if (!fileUrl) {
            return;
        }

        const isDesktop = window.innerWidth >= 992;
        if (!isDesktop) {
            window.open(fileUrl, '_blank', 'noopener');
            return;
        }

        const frame = document.getElementById('messagesFilesPdfFrame');
        if (!frame) {
            return;
        }

        frame.src = fileUrl;
        const modal = new bootstrap.Modal(document.getElementById('messagesFilesPdfModal'));
        modal.show();
    }

    function openMessagesFilesDataModal(trigger) {
        const encodedData = trigger.getAttribute('data-data-content') || '';
        if (!encodedData) {
            return;
        }

        let decodedData = encodedData;
        try {
            decodedData = decodeURIComponent(encodedData);
        } catch (error) {
            decodedData = encodedData;
        }

        const contentEl = document.getElementById('messagesFilesDataContent');
        if (!contentEl) {
            return;
        }

        contentEl.textContent = decodedData;
        const modal = new bootstrap.Modal(document.getElementById('messagesFilesDataModal'));
        modal.show();
    }

    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            const modal = bootstrap.Modal.getInstance(document.getElementById('messagesFilesImageModal'));
            if (modal) {
                modal.hide();
            }
        }
    });
    
    document.addEventListener('DOMContentLoaded', function() {
        // View toggle functionality
        const viewGridBtn = document.getElementById('view-grid');
        const viewListBtn = document.getElementById('view-list');
        const foldersGrid = document.getElementById('folders-grid');
        
        viewGridBtn.addEventListener('click', function() {
            viewGridBtn.classList.add('active');
            viewListBtn.classList.remove('active');
            foldersGrid.classList.remove('row-cols-1');
            foldersGrid.classList.add('row');
            
            // Restore grid column classes on folder items
            document.querySelectorAll('.folder-item').forEach(item => {
                item.classList.remove('col-12');
                item.classList.add('col-xl-4', 'col-lg-4', 'col-md-6');
            });
        });
        
        viewListBtn.addEventListener('click', function() {
            viewListBtn.classList.add('active');
            viewGridBtn.classList.remove('active');
            foldersGrid.classList.remove('row');
            foldersGrid.classList.add('row-cols-1');
            
            // Change folder items to full width for list view
            document.querySelectorAll('.folder-item').forEach(item => {
                item.classList.remove('col-xl-4', 'col-lg-4', 'col-md-6');
                item.classList.add('col-12');
            });
        });
        
         // Sorting functionality
         const sortDropdownItems = document.querySelectorAll('#sort-dropdown + .dropdown-menu .dropdown-item');
         let currentSort = 'latest';
        
        sortDropdownItems.forEach(item => {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Remove active class from all sort items
                sortDropdownItems.forEach(i => i.classList.remove('active'));
                
                // Add active class to clicked item
                this.classList.add('active');
                
                // Get sort type
                const sortType = this.getAttribute('data-sort');
                currentSort = sortType;
                
                // Update dropdown button text
                const sortDropdownBtn = document.getElementById('sort-dropdown');
                const iconClass = this.querySelector('i').className;
                const text = this.textContent.trim();
                sortDropdownBtn.innerHTML = `<i class="${iconClass}"></i> ${text}`;
                
                // Sort folders
                sortFolders(sortType);
            });
        });
        
        function updateEmptyState(visibleCount = null, searchTerm = null, sessionId = null) {
            const foldersGrid = document.getElementById('folders-grid');
            if (!foldersGrid) {
                return;
            }

            if (visibleCount === null) {
                visibleCount = Array.from(document.querySelectorAll('.folder-item'))
                    .filter(item => item.style.display !== 'none')
                    .length;
            }

            if (searchTerm === null) {
                const folderSearchInput = document.getElementById('folder-search');
                searchTerm = folderSearchInput ? folderSearchInput.value.toLowerCase().trim() : '';
            }

            if (sessionId === null) {
                const activeSessionItem = document.querySelector('.session-filter .list-group-item.active');
                sessionId = activeSessionItem ? activeSessionItem.getAttribute('data-session-id') : 'all';
            }

            const emptyState = document.querySelector('.empty-state');
            if (visibleCount === 0) {
                if (!emptyState) {
                    let emptyMessage = 'No groups found';
                    let emptyDescription = 'Select a different session or sync more groups.';

                    if (searchTerm.length > 0) {
                        emptyMessage = 'No matching groups found';
                        emptyDescription = 'Try a different search term or select a different session.';
                    } else if (sessionId === 'archived') {
                        emptyMessage = 'No archived groups';
                        emptyDescription = 'Archive a group to manage it here.';
                    } else if (sessionId !== 'all') {
                        emptyMessage = 'No groups in this session';
                        emptyDescription = 'Select a different session or sync more groups.';
                    }

                    const emptyStateHtml = `
                        <div class="col-12 empty-state">
                            <div class="text-center text-muted py-5">
                                <i class="fas fa-folder-open fa-3x mb-3"></i>
                                <h5>${emptyMessage}</h5>
                                <p>${emptyDescription}</p>
                            </div>
                        </div>
                    `;
                    foldersGrid.insertAdjacentHTML('beforeend', emptyStateHtml);
                }
            } else if (emptyState) {
                emptyState.remove();
            }
        }

        // Function to sort folders
         function sortFolders(sortType) {
             const foldersGrid = document.getElementById('folders-grid');
             // Get all folder items
             const folderItems = Array.from(document.querySelectorAll('.folder-item'));
             
             // Sort based on sort type
             folderItems.sort((a, b) => {
                 switch (sortType) {
                     case 'name-asc':
                         return a.getAttribute('data-name').localeCompare(b.getAttribute('data-name'));
                     case 'name-desc':
                         return b.getAttribute('data-name').localeCompare(a.getAttribute('data-name'));
                     case 'messages-desc':
                         return parseInt(b.getAttribute('data-message-count')) - parseInt(a.getAttribute('data-message-count'));
                     case 'messages-asc':
                         return parseInt(a.getAttribute('data-message-count')) - parseInt(b.getAttribute('data-message-count'));
                     case 'members-desc':
                         return parseInt(b.getAttribute('data-participant-count')) - parseInt(a.getAttribute('data-participant-count'));
                     case 'members-asc':
                         return parseInt(a.getAttribute('data-participant-count')) - parseInt(b.getAttribute('data-participant-count'));
                     case 'latest':
                         return parseInt(b.getAttribute('data-last-activity')) - parseInt(a.getAttribute('data-last-activity'));
                     case 'oldest':
                         return parseInt(a.getAttribute('data-last-activity')) - parseInt(b.getAttribute('data-last-activity'));
                     default:
                         return 0;
                 }
             });
             
             // Re-append sorted items while preserving their display state
             folderItems.forEach(item => {
                 const currentDisplay = item.style.display;
                 foldersGrid.appendChild(item);
                 // Restore the display state after moving
                 item.style.display = currentDisplay;
             });
             
             // Update empty state if needed
             updateEmptyState();
         }
        
        // Session filter functionality
        const sessionFilterItems = document.querySelectorAll('.session-filter .list-group-item');
        sessionFilterItems.forEach(item => {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Remove active class from all items
                sessionFilterItems.forEach(i => i.classList.remove('active'));
                
                // Add active class to clicked item
                this.classList.add('active');
                
                // Get selected session ID
                const sessionId = this.getAttribute('data-session-id');
                
                // Filter folders
                filterFoldersBySession(sessionId);
            });
        });
        
        // Search functionality
        const folderSearch = document.getElementById('folder-search');
        const clearSearchBtn = document.getElementById('clear-search');
        
        folderSearch.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase().trim();
            
            // Show/hide clear button
            if (searchTerm.length > 0) {
                clearSearchBtn.style.display = 'block';
            } else {
                clearSearchBtn.style.display = 'none';
            }
            
            // Get current session filter
            const activeSessionItem = document.querySelector('.session-filter .list-group-item.active');
            const activeSessionId = activeSessionItem ? activeSessionItem.getAttribute('data-session-id') : 'all';
            
            // Filter folders by search term
            filterFoldersBySearch(searchTerm, activeSessionId);
        });
        
        clearSearchBtn.addEventListener('click', function() {
            folderSearch.value = '';
            this.style.display = 'none';
            
            // Get current session filter
            const activeSessionItem = document.querySelector('.session-filter .list-group-item.active');
            const activeSessionId = activeSessionItem ? activeSessionItem.getAttribute('data-session-id') : 'all';
            
            // Reset filters
            filterFoldersBySearch('', activeSessionId);
        });
        
        // Folder navigation
        const openFolderBtns = document.querySelectorAll('.open-folder');
        const folderGridView = document.getElementById('folders-grid');
        const folderContentView = document.getElementById('folder-content-view');
        const backToFoldersBtn = document.getElementById('back-to-folders');
        const currentFolderName = document.getElementById('current-folder-name');
        const currentFolderInfo = document.getElementById('current-folder-info');
        const casesToolbar = document.getElementById('cases-toolbar');
        
        openFolderBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const groupId = this.getAttribute('data-group-id');
                const groupName = this.getAttribute('data-group-name');
                const folderItem = this.closest('.folder-item');
                const sessionId = folderItem ? folderItem.getAttribute('data-session-id') : null;
                
                // Update UI
                currentFolderName.textContent = groupName;
                currentFolderInfo.textContent = `Group ID: ${groupId}`;

                setCurrentCaseExport(groupId, sessionId, groupName);
                
                // Switch views
                folderGridView.classList.add('d-none');
                folderContentView.classList.remove('d-none');
                if (casesToolbar) {
                    casesToolbar.classList.add('d-none');
                }
                
                // Load folder content via AJAX
                loadFolderContent(groupId);
            });
        });
        
        backToFoldersBtn.addEventListener('click', function() {
            folderContentView.classList.add('d-none');
            folderGridView.classList.remove('d-none');
            setCurrentCaseExport(null, null, null);
            if (casesToolbar) {
                casesToolbar.classList.remove('d-none');
            }
        });

        const caseDownloadBtn = document.getElementById('case-download-btn');
        if (caseDownloadBtn) {
            caseDownloadBtn.disabled = true;
            caseDownloadBtn.addEventListener('click', startCaseExport);
        }
        
        function applyMessagesFilesSearch() {
            const searchInput = document.getElementById('messages-files-search');
            const list = document.getElementById('messages-files-list');
            if (!searchInput || !list) {
                return;
            }

            const query = searchInput.value.toLowerCase().trim();
            const items = list.querySelectorAll('.message-item, .file-item');
            if (items.length === 0) {
                return;
            }

            let matchCount = 0;
            items.forEach(item => {
                const itemText = (item.textContent || '').toLowerCase();
                const isMatch = query === '' || itemText.includes(query);
                item.classList.toggle('d-none', !isMatch);
                if (isMatch) {
                    matchCount++;
                }
            });

            let emptyState = document.getElementById('messages-files-search-empty');
            if (!emptyState) {
                emptyState = document.createElement('div');
                emptyState.id = 'messages-files-search-empty';
                emptyState.className = 'text-center text-muted py-4 d-none';
                emptyState.innerHTML = '<i class="fas fa-search fa-2x mb-2"></i><p class="mb-0">No matches found</p>';
                list.appendChild(emptyState);
            }

            const shouldShowEmpty = query !== '' && matchCount === 0;
            emptyState.classList.toggle('d-none', !shouldShowEmpty);
        }

        // Filter buttons for messages/files
        const filterBtns = document.querySelectorAll('[data-filter]');
        filterBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                filterBtns.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                const filter = this.getAttribute('data-filter');
                // Get current group ID from the open folder
                const currentFolderName = document.getElementById('current-folder-name');
                if (currentFolderName.textContent && currentFolderName.textContent !== '') {
                    // Find the current group ID
                    const openFolderBtn = document.querySelector('.open-folder[data-group-name="' + currentFolderName.textContent + '"]');
                    if (openFolderBtn) {
                        const groupId = openFolderBtn.getAttribute('data-group-id');
                        // Simply reload the folder content - the filter will be applied when rendering
                        loadFolderContent(groupId);
                    }
                }
            });
        });

        const messagesFilesSearch = document.getElementById('messages-files-search');
        if (messagesFilesSearch) {
            messagesFilesSearch.addEventListener('input', function() {
                applyMessagesFilesSearch();
            });
        }
        
        // Function to filter folders by session
        function filterFoldersBySession(sessionId) {
            // Get current search term
            const searchTerm = folderSearch.value.toLowerCase().trim();
            
            // Apply combined filters
            filterFoldersBySearch(searchTerm, sessionId);
            
            // Reapply current sort after filtering
            if (typeof currentSort !== 'undefined') {
                sortFolders(currentSort);
            }
        }
        
        // Function to filter folders by search term and session
        function filterFoldersBySearch(searchTerm, sessionId) {
            const allFolders = document.querySelectorAll('.folder-item');
            let visibleCount = 0;

            allFolders.forEach(folder => {
                const folderSessionId = folder.getAttribute('data-session-id');
                const folderStatus = folder.getAttribute('data-status');
                const isArchived = folderStatus === 'archived';
                const folderName = folder.querySelector('h6').textContent.toLowerCase();
                const sessionName = folder.querySelector('.session-badge').textContent.toLowerCase();

                let sessionMatch = false;
                if (sessionId === 'archived') {
                    sessionMatch = isArchived;
                } else if (sessionId === 'all') {
                    sessionMatch = !isArchived;
                } else {
                    sessionMatch = !isArchived && folderSessionId === sessionId;
                }

                const searchMatch = searchTerm === '' ||
                    folderName.includes(searchTerm) ||
                    sessionName.includes(searchTerm);

                if (sessionMatch && searchMatch) {
                    folder.style.display = 'block';
                    visibleCount++;

                    if (searchTerm.length > 0) {
                        const folderNameElement = folder.querySelector('h6');
                        const originalName = folderNameElement.getAttribute('data-original-name') || folderNameElement.textContent;
                        folderNameElement.setAttribute('data-original-name', originalName);

                        const highlightedName = originalName.replace(
                            new RegExp(`(${searchTerm})`, 'gi'),
                            '<mark class="bg-warning px-1 rounded">$1</mark>'
                        );
                        folderNameElement.innerHTML = highlightedName;
                    }
                } else {
                    folder.style.display = 'none';

                    if (searchTerm.length > 0) {
                        const folderNameElement = folder.querySelector('h6');
                        const originalName = folderNameElement.getAttribute('data-original-name');
                        if (originalName) {
                            folderNameElement.textContent = originalName;
                        }
                    }
                }
            });
            
            // Update folder count in active filter badge
            const activeFilterItem = document.querySelector('.session-filter .list-group-item.active');
            if (activeFilterItem) {
                const badge = activeFilterItem.querySelector('.badge');
                if (badge) {
                    badge.textContent = visibleCount;
                }
            }
            
            updateEmptyState(visibleCount, searchTerm, sessionId);
        }

        function updateSessionBadges() {
            const folderItems = document.querySelectorAll('.folder-item');
            const activeCounts = {};
            let activeTotal = 0;
            let archivedTotal = 0;

            folderItems.forEach(folder => {
                const sessionId = folder.getAttribute('data-session-id');
                const status = folder.getAttribute('data-status');
                if (status === 'archived') {
                    archivedTotal++;
                    return;
                }
                activeTotal++;
                activeCounts[sessionId] = (activeCounts[sessionId] || 0) + 1;
            });

            document.querySelectorAll('.session-filter .list-group-item').forEach(item => {
                const sessionId = item.getAttribute('data-session-id');
                if (!sessionId) {
                    return;
                }

                let count = 0;
                if (sessionId === 'all') {
                    count = activeTotal;
                } else if (sessionId === 'archived') {
                    count = archivedTotal;
                } else {
                    count = activeCounts[sessionId] || 0;
                }

                let badge = item.querySelector('.badge');
                if (count > 0 || sessionId === 'archived' || sessionId === 'all') {
                    if (!badge) {
                        badge = document.createElement('span');
                        if (sessionId === 'archived') {
                            badge.className = 'badge bg-light text-dark rounded-pill ms-auto';
                        } else if (sessionId === 'all') {
                            badge.className = 'badge bg-primary rounded-pill ms-auto';
                        } else {
                            badge.className = 'badge bg-secondary rounded-pill ms-auto';
                        }
                        item.querySelector('.d-flex').appendChild(badge);
                    }
                    badge.textContent = count;
                } else if (badge) {
                    badge.remove();
                }
            });
        }

        const archiveToggleLinks = document.querySelectorAll('.group-archive-toggle');
        archiveToggleLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();

                const action = this.getAttribute('data-action');
                const status = action === 'unarchive' ? 'active' : 'archived';
                const groupId = this.getAttribute('data-group-id');
                const sessionId = this.getAttribute('data-session-id');
                const folderItem = this.closest('.folder-item');

                this.classList.add('disabled');

                fetch(`/api/whatsapp/groups/${encodeURIComponent(groupId)}/archive`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        session_id: sessionId,
                        status: status
                    })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (!data.success) {
                            throw new Error(data.message || 'Failed to update group status');
                        }

                        const isArchived = status === 'archived';
                        if (folderItem) {
                            folderItem.setAttribute('data-status', isArchived ? 'archived' : 'active');
                        }

                        this.setAttribute('data-action', isArchived ? 'unarchive' : 'archive');
                        this.innerHTML = `<i class="fas ${isArchived ? 'fa-box-open' : 'fa-archive'} me-2"></i>${isArchived ? 'Unarchive' : 'Archive'}`;

                        updateSessionBadges();

                        const activeSessionItem = document.querySelector('.session-filter .list-group-item.active');
                        const activeSessionId = activeSessionItem ? activeSessionItem.getAttribute('data-session-id') : 'all';
                        filterFoldersBySession(activeSessionId);
                    })
                    .catch(error => {
                        alert(error.message);
                    })
                    .finally(() => {
                        this.classList.remove('disabled');
                    });
            });
        });

        document.querySelectorAll('.folder-item .dropdown-toggle').forEach(toggle => {
            const folderItem = toggle.closest('.folder-item');
            if (!folderItem) {
                return;
            }

            toggle.addEventListener('show.bs.dropdown', () => {
                folderItem.classList.add('dropdown-open');
            });

            toggle.addEventListener('hidden.bs.dropdown', () => {
                folderItem.classList.remove('dropdown-open');
            });
        });

        const scheduleSummaryLinks = document.querySelectorAll('.group-schedule-summary');
        const scheduleSummaryModalEl = document.getElementById('scheduleSummaryModal');
        const scheduleSummaryForm = document.getElementById('schedule-summary-form');
        const scheduleSummaryTarget = document.getElementById('schedule-summary-target');
        const scheduleSummaryGroupId = document.getElementById('schedule-summary-group-id');
        const scheduleSummarySessionId = document.getElementById('schedule-summary-session-id');
        const scheduleSummaryFrequency = document.getElementById('schedule-summary-frequency');
        const scheduleSummaryTime = document.getElementById('schedule-summary-time');
        const scheduleSummaryWeekly = document.getElementById('schedule-summary-weekly');
        const scheduleSummaryWeekday = document.getElementById('schedule-summary-weekday');
        const scheduleSummaryMonthly = document.getElementById('schedule-summary-monthly');
        const scheduleSummaryMonthDay = document.getElementById('schedule-summary-month-day');
        const scheduleSummaryPrompt = document.getElementById('schedule-summary-prompt');
        const scheduleSummaryIncludeNonAssigned = document.getElementById('schedule-summary-include-non-assigned');
        const scheduleSummarySaveButton = document.getElementById('schedule-summary-save');
        const scheduleSummaryRemoveButton = document.getElementById('schedule-summary-remove');
        const scheduleSummaryModal = scheduleSummaryModalEl ? new bootstrap.Modal(scheduleSummaryModalEl) : null;
        let activeScheduleSummaryLink = null;

        const latestSummaryLinks = document.querySelectorAll('.group-summary-latest');
        const latestSummaryModalEl = document.getElementById('latestSummaryModal');
        const latestSummaryTarget = document.getElementById('latest-summary-target');
        const latestSummaryContent = document.getElementById('latest-summary-content');
        const latestSummaryModal = latestSummaryModalEl ? new bootstrap.Modal(latestSummaryModalEl) : null;

        const updateScheduleSummaryFields = () => {
            if (!scheduleSummaryFrequency) {
                return;
            }

            const frequency = scheduleSummaryFrequency.value;
            if (scheduleSummaryWeekly) {
                scheduleSummaryWeekly.classList.toggle('d-none', frequency !== 'weekly');
            }
            if (scheduleSummaryMonthly) {
                scheduleSummaryMonthly.classList.toggle('d-none', frequency !== 'monthly');
            }

            if (frequency !== 'weekly' && scheduleSummaryWeekday) {
                scheduleSummaryWeekday.value = '';
            }

            if (frequency !== 'monthly' && scheduleSummaryMonthDay) {
                scheduleSummaryMonthDay.value = '';
            }
        };

        scheduleSummaryLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();

                activeScheduleSummaryLink = this;

                const groupId = this.getAttribute('data-group-id') || '';
                const sessionId = this.getAttribute('data-session-id') || '';
                const sessionName = this.getAttribute('data-session-name') || '';
                const groupName = this.getAttribute('data-group-name') || '';
                const existingFrequency = this.getAttribute('data-summary-frequency') || '';
                const existingTime = this.getAttribute('data-summary-time') || '';
                const existingWeekday = this.getAttribute('data-summary-weekday') || '';
                const existingMonthDay = this.getAttribute('data-summary-month-day') || '';
                const existingPrompt = this.getAttribute('data-summary-prompt') || '';
                const existingIncludeNonAssigned = this.getAttribute('data-summary-include-non-assigned') || '0';
                const hasExistingSchedule = existingFrequency !== '' || existingTime !== '' || existingWeekday !== '' || existingMonthDay !== '' || existingPrompt !== '';

                if (scheduleSummaryGroupId) {
                    scheduleSummaryGroupId.value = groupId;
                }
                if (scheduleSummarySessionId) {
                    scheduleSummarySessionId.value = sessionId;
                }
                if (scheduleSummaryFrequency) {
                    scheduleSummaryFrequency.value = existingFrequency !== '' ? existingFrequency : 'daily';
                }
                if (scheduleSummaryTime) {
                    scheduleSummaryTime.value = hasExistingSchedule ? existingTime : '';
                }
                if (scheduleSummaryWeekday) {
                    scheduleSummaryWeekday.value = hasExistingSchedule ? existingWeekday : '';
                }
                if (scheduleSummaryMonthDay) {
                    scheduleSummaryMonthDay.value = hasExistingSchedule ? existingMonthDay : '';
                }
                if (scheduleSummaryPrompt) {
                    scheduleSummaryPrompt.value = hasExistingSchedule ? existingPrompt : '';
                }
                if (scheduleSummaryIncludeNonAssigned) {
                    scheduleSummaryIncludeNonAssigned.value = hasExistingSchedule ? existingIncludeNonAssigned : '0';
                }
                if (scheduleSummaryRemoveButton) {
                    scheduleSummaryRemoveButton.classList.toggle('d-none', !hasExistingSchedule);
                }
                if (scheduleSummaryTarget) {
                    const labelParts = [];
                    if (groupName) {
                        labelParts.push(groupName);
                    }
                    if (sessionName) {
                        labelParts.push(sessionName);
                    }
                    scheduleSummaryTarget.textContent = labelParts.join(' - ');
                }

                if (scheduleSummaryModal) {
                    updateScheduleSummaryFields();
                    scheduleSummaryModal.show();
                }
            });
        });

        latestSummaryLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();

                const groupName = this.getAttribute('data-group-name') || '';
                const latestSummary = this.getAttribute('data-summary-latest') || '';

                if (latestSummaryTarget) {
                    latestSummaryTarget.textContent = groupName;
                }
                if (latestSummaryContent) {
                    latestSummaryContent.textContent = latestSummary || 'No summary available.';
                }

                if (latestSummaryModal) {
                    latestSummaryModal.show();
                }
            });
        });

        if (scheduleSummaryFrequency) {
            scheduleSummaryFrequency.addEventListener('change', updateScheduleSummaryFields);
        }

        if (scheduleSummaryForm) {
            scheduleSummaryForm.addEventListener('submit', function(e) {
                e.preventDefault();

                const groupId = scheduleSummaryGroupId ? scheduleSummaryGroupId.value : '';
                const sessionId = scheduleSummarySessionId ? scheduleSummarySessionId.value : '';
                const frequency = scheduleSummaryFrequency ? scheduleSummaryFrequency.value : '';
                const summaryTime = scheduleSummaryTime ? scheduleSummaryTime.value : '';
                const prompt = scheduleSummaryPrompt ? scheduleSummaryPrompt.value : '';
                const summaryWeekday = scheduleSummaryWeekday ? scheduleSummaryWeekday.value : '';
                const summaryMonthDay = scheduleSummaryMonthDay ? scheduleSummaryMonthDay.value : '';
                const includeNonAssigned = scheduleSummaryIncludeNonAssigned ? scheduleSummaryIncludeNonAssigned.value : '0';

                if (!groupId || !sessionId) {
                    if (window.Swal) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Missing group or session information.'
                        });
                    } else {
                        alert('Missing group or session information.');
                    }
                    return;
                }

                if (frequency === 'weekly' && !summaryWeekday) {
                    if (window.Swal) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Please select a summary day.'
                        });
                    } else {
                        alert('Please select a summary day.');
                    }
                    return;
                }

                if (!summaryTime) {
                    if (window.Swal) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Please select a summary time.'
                        });
                    } else {
                        alert('Please select a summary time.');
                    }
                    return;
                }

                if (frequency === 'monthly' && !summaryMonthDay) {
                    if (window.Swal) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Please select a summary day.'
                        });
                    } else {
                        alert('Please select a summary day.');
                    }
                    return;
                }

                if (scheduleSummarySaveButton) {
                    scheduleSummarySaveButton.disabled = true;
                    scheduleSummarySaveButton.textContent = 'Saving...';
                }

                fetch(`/api/whatsapp/groups/${encodeURIComponent(groupId)}/schedule-summary`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        session_id: sessionId,
                        frequency: frequency,
                        summary_time: summaryTime,
                        summary_weekday: summaryWeekday,
                        summary_month_day: summaryMonthDay,
                        prompt: prompt,
                        include_non_assigned: includeNonAssigned
                    })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (!data.success) {
                            throw new Error(data.message || 'Failed to save schedule summary');
                        }

                        if (window.Swal) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Saved',
                                text: 'Schedule summary saved successfully.',
                                timer: 2000,
                                showConfirmButton: false
                            });
                        } else {
                            alert('Schedule summary saved successfully.');
                        }

                        if (activeScheduleSummaryLink) {
                            activeScheduleSummaryLink.setAttribute('data-summary-frequency', frequency);
                            activeScheduleSummaryLink.setAttribute('data-summary-time', summaryTime);
                            activeScheduleSummaryLink.setAttribute('data-summary-weekday', summaryWeekday);
                            activeScheduleSummaryLink.setAttribute('data-summary-month-day', summaryMonthDay);
                            activeScheduleSummaryLink.setAttribute('data-summary-prompt', prompt);
                            activeScheduleSummaryLink.setAttribute('data-summary-include-non-assigned', includeNonAssigned);
                            activeScheduleSummaryLink.innerHTML = '<i class="fas fa-calendar-alt me-2"></i>Edit Schedule Summary';
                        }

                        if (scheduleSummaryRemoveButton) {
                            scheduleSummaryRemoveButton.classList.remove('d-none');
                        }

                        if (scheduleSummaryModal) {
                            scheduleSummaryModal.hide();
                        }
                    })
                    .catch(error => {
                        if (window.Swal) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: error.message
                            });
                        } else {
                            alert(error.message);
                        }
                    })
                    .finally(() => {
                        if (scheduleSummarySaveButton) {
                            scheduleSummarySaveButton.disabled = false;
                            scheduleSummarySaveButton.textContent = 'Save';
                        }
                    });
            });
        }

        if (scheduleSummaryRemoveButton) {
            scheduleSummaryRemoveButton.addEventListener('click', function() {
                const groupId = scheduleSummaryGroupId ? scheduleSummaryGroupId.value : '';
                const sessionId = scheduleSummarySessionId ? scheduleSummarySessionId.value : '';

                if (!groupId || !sessionId) {
                    if (window.Swal) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Missing group or session information.'
                        });
                    } else {
                        alert('Missing group or session information.');
                    }
                    return;
                }

                const confirmRemoval = () => {
                    this.disabled = true;
                    this.textContent = 'Removing...';

                    fetch(`/api/whatsapp/groups/${encodeURIComponent(groupId)}/schedule-summary/delete`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            session_id: sessionId
                        })
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (!data.success) {
                                throw new Error(data.message || 'Failed to remove schedule summary');
                            }

                            if (activeScheduleSummaryLink) {
                                activeScheduleSummaryLink.setAttribute('data-summary-frequency', '');
                                activeScheduleSummaryLink.setAttribute('data-summary-time', '');
                                activeScheduleSummaryLink.setAttribute('data-summary-weekday', '');
                                activeScheduleSummaryLink.setAttribute('data-summary-month-day', '');
                                activeScheduleSummaryLink.setAttribute('data-summary-prompt', '');
                                activeScheduleSummaryLink.setAttribute('data-summary-include-non-assigned', '0');
                                activeScheduleSummaryLink.innerHTML = '<i class="fas fa-calendar-alt me-2"></i>Set Schedule Summary';
                            }

                            if (scheduleSummaryFrequency) {
                                scheduleSummaryFrequency.value = 'daily';
                            }
                            if (scheduleSummaryTime) {
                                scheduleSummaryTime.value = '';
                            }
                            if (scheduleSummaryWeekday) {
                                scheduleSummaryWeekday.value = '';
                            }
                            if (scheduleSummaryMonthDay) {
                                scheduleSummaryMonthDay.value = '';
                            }
                            if (scheduleSummaryPrompt) {
                                scheduleSummaryPrompt.value = '';
                            }
                            if (scheduleSummaryIncludeNonAssigned) {
                                scheduleSummaryIncludeNonAssigned.value = '0';
                            }
                            updateScheduleSummaryFields();

                            if (scheduleSummaryRemoveButton) {
                                scheduleSummaryRemoveButton.classList.add('d-none');
                            }

                            if (window.Swal) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Removed',
                                    text: 'Schedule summary removed successfully.',
                                    timer: 2000,
                                    showConfirmButton: false
                                });
                            } else {
                                alert('Schedule summary removed successfully.');
                            }

                            if (scheduleSummaryModal) {
                                scheduleSummaryModal.hide();
                            }
                        })
                        .catch(error => {
                            if (window.Swal) {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: error.message
                                });
                            } else {
                                alert(error.message);
                            }
                        })
                        .finally(() => {
                            this.disabled = false;
                            this.textContent = 'Remove Schedule';
                        });
                };

                if (window.Swal) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Remove schedule?',
                        text: 'This will delete the scheduled summary settings for this group.',
                        showCancelButton: true,
                        confirmButtonText: 'Remove',
                        confirmButtonColor: '#dc3545'
                    }).then(result => {
                        if (result.isConfirmed) {
                            confirmRemoval();
                        }
                    });
                } else if (confirm('Remove the scheduled summary settings for this group?')) {
                    confirmRemoval();
                }
            });
        }

        updateSessionBadges();
        filterFoldersBySession('all');

        if (typeof RealtimeClient !== 'undefined' && currentUserId) {
            const realtime = new RealtimeClient(currentUserId);
            realtime.on('case_export_ready', showCaseExportReady);
            realtime.on('case_export_failed', showCaseExportFailed);
            realtime.startPolling();
        }
        
        // Function to load folder content via AJAX (global scope)
        window.loadFolderContent = function(groupId) {
            console.log('Loading content for group:', groupId);
            
            // Show loading state
            const categoryTree = document.getElementById('category-tree');
            const messagesList = document.getElementById('messages-files-list');
            
            categoryTree.innerHTML = `
                <div class="text-center text-muted py-4">
                    <div class="spinner-border spinner-border-sm me-2"></div>
                    Loading categories...
                </div>
            `;
            
            messagesList.innerHTML = `
                <div class="text-center text-muted py-4">
                    <div class="spinner-border spinner-border-sm me-2"></div>
                    Loading messages and files...
                </div>
            `;

            // Get session ID from the folder element
            // Escape special CSS selector characters in groupId
            const escapedGroupId = groupId.replace(/[!"#$%&'()*+,.\/:;<=>?@[\\\]^`{|}~]/g, '\\$&');
            console.log('Looking for folder with groupId:', groupId, 'escaped:', escapedGroupId);
            const folderElement = document.querySelector(`.open-folder[data-group-id="${escapedGroupId}"]`);
            console.log('Found button:', folderElement);
            const sessionId = folderElement ? folderElement.closest('.folder-item').getAttribute('data-session-id') : null;
            console.log('Session ID found:', sessionId);
            const groupName = folderElement ? folderElement.getAttribute('data-group-name') : (currentFolderName ? currentFolderName.textContent : '');
            setCurrentCaseExport(groupId, sessionId, groupName);

            if (!sessionId) {
                categoryTree.innerHTML = `
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-exclamation-triangle text-danger fa-2x mb-2"></i>
                        <p>Unable to determine session for this group</p>
                    </div>
                `;
                messagesList.innerHTML = `
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-exclamation-triangle text-danger fa-2x mb-2"></i>
                        <p>Unable to determine session for this group</p>
                    </div>
                `;
                return;
            }
            
            // Load category tree via API with group_id parameter to get group-specific counts
            fetch(`/api/whatsapp/categories/tree${groupId ? '?group_id=' + encodeURIComponent(groupId) + '&session_id=' + encodeURIComponent(sessionId) : ''}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        return response.text().then(text => {
                            throw new Error(`Expected JSON but got: ${text.substring(0, 100)}`);
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success && data.data.categories) {
                        renderCategoryTree(data.data.categories);
                    } else {
                        categoryTree.innerHTML = `
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-exclamation-triangle text-warning fa-2x mb-2"></i>
                                <p>Failed to load categories: ${data.message || 'Unknown error'}</p>
                                <button class="btn btn-sm btn-outline-primary" onclick="loadFolderContent('${groupId}')">
                                    <i class="fas fa-redo me-1"></i> Retry
                                </button>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error loading categories:', error);
                    categoryTree.innerHTML = `
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-exclamation-triangle text-danger fa-2x mb-2"></i>
                            <p>Error loading categories: ${error.message}</p>
                            <button class="btn btn-sm btn-outline-primary" onclick="loadFolderContent('${groupId}')">
                                <i class="fas fa-redo me-1"></i> Retry
                            </button>
                        </div>
                    `;
                });
            
            // Load messages and files for this group via API
            fetch(`/api/whatsapp/groups/${encodeURIComponent(groupId)}/messages?session_id=${encodeURIComponent(sessionId)}&limit=50`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        return response.text().then(text => {
                            throw new Error(`Expected JSON but got: ${text.substring(0, 100)}`);
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success && data.data.messages) {
                        renderMessagesAndFiles(data.data.messages);
                    } else {
                        messagesList.innerHTML = `
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-exclamation-triangle text-warning fa-2x mb-2"></i>
                                <p>Failed to load messages: ${data.message || 'Unknown error'}</p>
                                <button class="btn btn-sm btn-outline-primary" onclick="loadFolderContent('${groupId}')">
                                    <i class="fas fa-redo me-1"></i> Retry
                                </button>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error loading messages:', error);
                    messagesList.innerHTML = `
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-exclamation-triangle text-danger fa-2x mb-2"></i>
                            <p>Error loading messages: ${error.message}</p>
                            <button class="btn btn-sm btn-outline-primary" onclick="loadFolderContent('${groupId}')">
                                <i class="fas fa-redo me-1"></i> Retry
                            </button>
                        </div>
                    `;
                });
            
             function renderCategoryTree(categories) {
                 // Store categories globally for use in message dropdowns
                 window.currentCategories = categories;
                 
                if (!categories || categories.length === 0) {
                    const addCategoryLink = canManageCategories
                        ? `<a href="/settings?page=category" class="btn btn-sm btn-outline-primary" id="add-category-btn">
                                <i class="fas fa-plus me-1"></i> Add Category
                            </a>`
                        : '';
                    categoryTree.innerHTML = `
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-tags fa-2x mb-2"></i>
                            <p>No categories yet</p>
                            ${addCategoryLink}
                        </div>
                    `;
                     
                     // Update all message dropdowns with empty state
                     updateAllMessageDropdowns([]);
                     return;
                 }
                 
                 let html = '<div class="category-tree">';
                 html += renderCategoryTreeItems(categories);
                 html += '</div>';
                if (canManageCategories) {
                    html += `
                        <div class="mt-3">
                            <a href="/settings?page=category" class="btn btn-sm btn-outline-primary w-100" id="add-category-btn">
                                <i class="fas fa-plus me-1"></i> Add Category
                            </a>
                        </div>
                    `;
                }
                 
                 categoryTree.innerHTML = html;
                 
                 // Update all message dropdowns with the loaded categories
                 updateAllMessageDropdowns(categories);
                
                // Add click handlers for category items
                document.querySelectorAll('.category-clickable').forEach(clickable => {
                    clickable.addEventListener('click', function() {
                        const categoryItem = this.closest('.category-item');
                        const categoryId = categoryItem.getAttribute('data-category-id');
                        loadCategoryContent(groupId, categoryId);
                    });
                });
            }
            
            function renderCategoryTreeItems(items, level = 0) {
                let html = '';
                
                items.forEach(item => {
                    const hasChildren = item.subcategories && item.subcategories.length > 0;
                    const marginLeft = level * 20;
                    const totalItems = (item.message_count || 0) + (item.group_count || 0);
                    
                     html += `<div class="category-item mb-2" data-category-id="${item.id}">`;
                     html += `<div class="category-clickable d-flex align-items-center justify-content-between p-2 border rounded" style="margin-left: ${marginLeft}px;">`;
                     html += '<div class="d-flex align-items-center">';
                     
                     if (hasChildren) {
                         html += '<i class="fas fa-folder text-warning me-2"></i>';
                     } else {
                         html += `<i class="fas fa-tag me-2" style="color: ${item.color || '#6c757d'}"></i>`;
                     }
                     
                     html += `<span>${escapeHtml(item.name)}</span>`;
                     html += '</div>';
                     
                     if (totalItems > 0) {
                         html += `<span class="badge bg-light text-dark">${totalItems} items</span>`;
                     }
                     
                     html += '</div>';
                    
                    if (hasChildren) {
                        html += '<div class="category-children">';
                        html += renderCategoryTreeItems(item.subcategories, level + 1);
                        html += '</div>';
                    }
                    
                    html += '</div>';
                });
                
                return html;
            }
            
            function loadCategoryContent(groupId, categoryId) {
                // Highlight selected category
                document.querySelectorAll('.category-item').forEach(item => {
                    item.classList.remove('selected');
                });
                const selectedCategory = document.querySelector(`.category-item[data-category-id="${categoryId}"]`);
                if (selectedCategory) {
                    selectedCategory.classList.add('selected');
                }
                
                // Get session ID from the folder element
                // Escape special CSS selector characters in groupId
                const escapedGroupId = groupId.replace(/[!"#$%&'()*+,.\/:;<=>?@[\\\]^`{|}~]/g, '\\$&');
                console.log('Category filter: Looking for folder with groupId:', groupId, 'escaped:', escapedGroupId);
                const folderElement = document.querySelector(`.open-folder[data-group-id="${escapedGroupId}"]`);
                console.log('Category filter: Found button:', folderElement);
                const sessionId = folderElement ? folderElement.closest('.folder-item').getAttribute('data-session-id') : null;
                console.log('Category filter: Session ID found:', sessionId);
                
                if (!sessionId) {
                    messagesList.innerHTML = `
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-exclamation-triangle text-danger fa-2x mb-2"></i>
                            <p>Unable to determine session for this group</p>
                        </div>
                    `;
                    return;
                }
                
                // Load messages for this category
                fetch(`/api/whatsapp/groups/${encodeURIComponent(groupId)}/messages?session_id=${encodeURIComponent(sessionId)}&category_id=${categoryId}&limit=50`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.data.messages) {
                            renderMessagesAndFiles(data.data.messages);
                        } else {
                            messagesList.innerHTML = `
                                <div class="text-center text-muted py-4">
                                    <i class="fas fa-exclamation-triangle text-warning fa-2x mb-2"></i>
                                    <p>Failed to load category messages</p>
                                    <button class="btn btn-sm btn-outline-primary" onclick="loadCategoryContent('${groupId}', '${categoryId}')">
                                        <i class="fas fa-redo me-1"></i> Retry
                                    </button>
                                </div>
                            `;
                        }
                    })
                    .catch(error => {
                        console.error('Error loading category messages:', error);
                        messagesList.innerHTML = `
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-exclamation-triangle text-danger fa-2x mb-2"></i>
                                <p>Error loading category messages</p>
                                <button class="btn btn-sm btn-outline-primary" onclick="loadCategoryContent('${groupId}', '${categoryId}')">
                                    <i class="fas fa-redo me-1"></i> Retry
                                </button>
                            </div>
                        `;
                    });
            }
            
            function deleteMessageItem(messageId) {
                if (!messageId) {
                    return;
                }

                Swal.fire({
                    title: 'Delete message?',
                    text: 'This will permanently delete the message from WhatsApp and your database.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Delete',
                    cancelButtonText: 'Cancel',
                    reverseButtons: true,
                    showLoaderOnConfirm: true,
                    preConfirm: () => {
                        return fetch(`/api/whatsapp/messages/${messageId}`, {
                            method: 'DELETE'
                        })
                        .then(response => response.json().then(data => ({ response, data })))
                        .then(({ response, data }) => {
                            if (!response.ok || !data.success) {
                                throw new Error(data.message || 'Delete failed');
                            }
                            return data;
                        })
                        .catch(error => {
                            Swal.showValidationMessage(`Request failed: ${error.message || error}`);
                        });
                    },
                    allowOutsideClick: () => !Swal.isLoading()
                }).then((result) => {
                    if (!result.isConfirmed) {
                        return;
                    }

                    const messageEl = document.querySelector(`.message-item[data-message-id="${messageId}"], .file-item[data-message-id="${messageId}"]`);
                    if (messageEl) {
                        messageEl.remove();
                    }

                    const messagesList = document.getElementById('messages-files-list');
                    const remainingItems = messagesList ? messagesList.querySelectorAll('.message-item, .file-item') : [];
                    if (messagesList && remainingItems.length === 0) {
                        messagesList.innerHTML = `
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-comments fa-2x mb-2"></i>
                                <p>No messages or files found</p>
                            </div>
                        `;
                    } else if (typeof applyMessagesFilesSearch === 'function') {
                        applyMessagesFilesSearch();
                    }

                    Swal.fire({
                        icon: 'success',
                        title: 'Deleted',
                        text: 'Message deleted successfully!',
                        timer: 1500,
                        showConfirmButton: false
                    });
                });
            }

            function renderMessagesAndFiles(messages) {
                if (!messages || messages.length === 0) {
                    messagesList.innerHTML = `
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-comments fa-2x mb-2"></i>
                            <p>No messages or files found</p>
                        </div>
                    `;
                    return;
                }

                messages.sort((a, b) => {
                    const timeA = a.timestamp ? parseInt(a.timestamp, 10) : 0;
                    const timeB = b.timestamp ? parseInt(b.timestamp, 10) : 0;
                    return timeB - timeA;
                });
                
                let html = '<div class="message-file-list">';
                
                // Get current filter
                const filter = document.querySelector('[data-filter].active')?.getAttribute('data-filter') || 'all';
                
                const isTextMessage = (item) => !item.message_type || item.message_type === 'chat';

                // Apply filter while preserving timestamp order
                if (filter === 'all') {
                    messages.forEach(item => {
                        html += isTextMessage(item) ? renderMessageItem(item) : renderFileItem(item);
                    });
                } else if (filter === 'messages') {
                    messages.filter(isTextMessage).forEach(message => {
                        html += renderMessageItem(message);
                    });
                } else if (filter === 'files') {
                    messages.filter(item => !isTextMessage(item)).forEach(file => {
                        html += renderFileItem(file);
                    });
                }
                
                 html += '</div>';
                 messagesList.innerHTML = html;

                 applyMessagesFilesSearch();
                 
                 // Update message dropdowns with current categories if available
                 if (typeof window.currentCategories !== 'undefined') {
                     updateAllMessageDropdowns(window.currentCategories);
                 }
                 
                  // Add event listeners for dropdown items
                  document.querySelectorAll('.category-loading-item').forEach(item => {
                     item.addEventListener('click', function(e) {
                         e.preventDefault();
                         e.stopPropagation();
                         
                         const messageId = this.getAttribute('data-message-id');
                         console.log('Category link clicked for message:', messageId);
                         
                         // Use pre-loaded categories if available
                         if (typeof window.currentCategories !== 'undefined') {
                             populateCategoriesInDropdown(this, messageId, window.currentCategories);
                         } else {
                             // Fallback to API call
                             loadCategoriesForMessage(messageId, this);
                         }
                     });
                 });
                 
                  document.querySelectorAll('.no-category-item').forEach(item => {
                      item.addEventListener('click', function(e) {
                          e.preventDefault();
                          e.stopPropagation();
                          
                          const messageId = this.getAttribute('data-message-id');
                          assignMessageToCategory(messageId, null);
                      });
                  });

                  document.querySelectorAll('.message-delete-item').forEach(item => {
                      item.addEventListener('click', function(e) {
                          e.preventDefault();
                          e.stopPropagation();

                          const messageId = this.getAttribute('data-message-id');
                          deleteMessageItem(messageId);
                      });
                  });
               }

             
             // Function to update all message dropdowns with categories
             function updateAllMessageDropdowns(categories) {
                 document.querySelectorAll('.category-loading-item').forEach(dropdownItem => {
                     const messageId = dropdownItem.getAttribute('data-message-id');
                     populateCategoriesInDropdown(dropdownItem, messageId, categories);
                 });
             }
             
             // Function to populate categories in a dropdown
             function populateCategoriesInDropdown(dropdownItem, messageId, categories) {
                 const dropdownMenu = dropdownItem.closest('.dropdown-menu');
                 const loadingItem = dropdownItem.closest('li');
                 
                 if (!loadingItem) return;
                 
                 loadingItem.innerHTML = '';
                 
                 if (!categories || categories.length === 0) {
                     const noCatItem = document.createElement('li');
                     noCatItem.innerHTML = '<a class="dropdown-item disabled" href="#">No categories found</a>';
                     loadingItem.appendChild(noCatItem);
                     return;
                 }
                 
                 // Flatten the category tree for the dropdown
                 const flattenedCategories = flattenCategoryTree(categories);
                 
                 flattenedCategories.forEach(category => {
                     const catItem = document.createElement('li');
                     const categoryName = escapeHtml(category.name);
                     const categoryDescription = escapeHtml(category.description || '');
                     catItem.innerHTML = `
                         <a class="dropdown-item category-assign-item" href="#" data-message-id="${messageId}" data-category-id="${category.id}">
                             <span class="badge me-2" style="background-color: ${category.color || '#6c757d'}; color: white">${categoryName}</span>
                             ${categoryDescription}
                         </a>
                     `;
                     loadingItem.appendChild(catItem);
                 });
                 
                 // Add event listeners for category assignment
                 loadingItem.querySelectorAll('.category-assign-item').forEach(item => {
                     item.addEventListener('click', function(e) {
                         e.preventDefault();
                         e.stopPropagation();
                         
                         const messageId = this.getAttribute('data-message-id');
                         const categoryId = this.getAttribute('data-category-id');
                         assignMessageToCategory(messageId, categoryId);
                     });
                 });
             }
             
             // Make function available globally for loadCategoriesForMessage
             window.populateCategoriesInDropdown = populateCategoriesInDropdown;
             
             // Function to flatten category tree (recursive)
             function flattenCategoryTree(categories, result = [], level = 0) {
                 categories.forEach(category => {
                     // Add current category with indentation based on level
                     const categoryWithLevel = {
                         ...category,
                         name: '  '.repeat(level) + (level > 0 ? '↳ ' : '') + category.name
                     };
                     result.push(categoryWithLevel);
                     
                     // Recursively add subcategories
                     if (category.subcategories && category.subcategories.length > 0) {
                         flattenCategoryTree(category.subcategories, result, level + 1);
                     }
                 });
                 return result;
             }
             
             // Make function available globally
             window.flattenCategoryTree = flattenCategoryTree;
             
             function renderMessageItem(message) {
                // Convert timestamp from milliseconds to Date object
                const timestamp = message.timestamp ? new Date(parseInt(message.timestamp)) : new Date();
                const timeStr = timestamp.toLocaleDateString() + ' ' + timestamp.toLocaleTimeString();
                
                let html = '<div class="message-item mb-3 p-3 border rounded" data-message-id="' + message.id + '">';
                html += '<div class="d-flex align-items-start">';
                html += '<div class="flex-shrink-0">';
                
                // Message type icon
                let iconClass = 'fas fa-comment text-primary';
                if (message.is_from_me) {
                    iconClass = 'fas fa-comment-dots text-success';
                }
                
                html += `<i class="${iconClass} fa-lg"></i>`;
                html += '</div>';
                html += '<div class="flex-grow-1 ms-3">';
                html += '<div class="d-flex justify-content-between align-items-start">';
                html += '<div>';
                html += `<h6 class="mb-1">${escapeHtml(message.sender_name || message.sender_number || 'Unknown')}</h6>`;
                html += `<small class="text-muted">${timeStr}</small>`;
                html += '</div>';
                
                 // Three dots dropdown menu for category assignment
                 html += '<div class="dropdown">';
                 html += '<button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">';
                 html += '<i class="fas fa-ellipsis-v"></i>';
                 html += '</button>';
                 html += '<ul class="dropdown-menu dropdown-menu-end">';
                 if (canDeleteMessages) {
                     html += '<li><a class="dropdown-item text-danger message-delete-item" href="#" data-message-id="' + message.id + '"><i class="fas fa-trash-alt me-2"></i>Delete message</a></li>';
                     html += '<li><hr class="dropdown-divider"></li>';
                 }
                 html += '<li><h6 class="dropdown-header">Assign to Category</h6></li>';
                 html += '<li><a class="dropdown-item no-category-item" href="#" data-message-id="' + message.id + '">No Category</a></li>';
                 html += '<li><hr class="dropdown-divider"></li>';
                 html += '<li><a class="dropdown-item category-loading-item" href="#" data-message-id="' + message.id + '">Loading categories...</a></li>';
                 html += '</ul>';
                 html += '</div>';
                
                html += '</div>';
                
                // Message content
                let content = escapeHtml(message.content || 'No content');
                if (content.length > 200) {
                    content = content.substring(0, 200) + '...';
                }
                html += `<p class="mb-1">${content}</p>`;

                const amountTextRaw = formatAmount(message.amount);
                if (amountTextRaw.length > 0) {
                    html += `<div class="message-data-highlight">${escapeHtml(amountTextRaw)}</div>`;
                }

                const dataTextRaw = message.data !== null && message.data !== undefined ? message.data.toString().trim() : '';
                if (dataTextRaw.length > 0) {
                    const encodedData = encodeURIComponent(dataTextRaw);
                    html += `<button class="btn btn-sm btn-outline-secondary message-data-view" type="button" data-data-content="${encodedData}" onclick="openMessagesFilesDataModal(this)">`;
                    html += '<i class="fas fa-eye me-1"></i>View data';
                    html += '</button>';
                    html += `<div class="message-data-hidden">${escapeHtml(dataTextRaw)}</div>`;
                }
                
                // Category badge if available
                if (message.category_name) {
                    html += '<div class="d-flex align-items-center">';
                    html += `<span class="badge me-2" style="background-color: ${message.category_color || '#6c757d'}; color: white">`;
                    html += escapeHtml(message.category_name);
                    html += '</span>';
                    html += '</div>';
                }
                html += '</div>';
                html += '</div>';
                html += '</div>';
                
                return html;
            }
            
            function renderFileItem(file) {
                // Convert timestamp from milliseconds to Date object
                const timestamp = file.timestamp ? new Date(parseInt(file.timestamp)) : new Date();
                const timeStr = timestamp.toLocaleDateString() + ' ' + timestamp.toLocaleTimeString();
                const imageUrl = (file.message_type === 'image' || file.message_type === 'sticker')
                    ? (file.media_url ? escapeHtml(file.media_url) : (file.content ? `data:image/jpeg;base64,${escapeHtml(file.content)}` : ''))
                    : '';
                const audioUrl = (file.message_type === 'audio' && file.media_url)
                    ? escapeHtml(file.media_url)
                    : '';
                const audioTranscript = file.ai_describe ? escapeHtml(file.ai_describe) : '';
                const imageCaption = escapeHtml(file.media_caption || file.caption || '');
                
                let html = '<div class="file-item mb-3 p-3 border rounded" data-message-id="' + file.id + '">';
                html += '<div class="d-flex align-items-start">';
                html += '<div class="flex-shrink-0">';
                
                // File type icon
                let iconClass = 'fas fa-file';
                let iconColor = '#6c757d';
                const fileNameSource = (file.file_name || (file.media_url ? file.media_url.split('?')[0] : '') || file.media_caption || file.content || '').toString();
                const extensionMatch = fileNameSource.toLowerCase().match(/\.([a-z0-9]+)$/);
                const fileExt = extensionMatch ? extensionMatch[1] : '';
                
                switch (file.message_type) {
                    case 'image':
                        iconClass = 'fas fa-file-image';
                        iconColor = '#28a745';
                        break;
                    case 'video':
                        iconClass = 'fas fa-file-video';
                        iconColor = '#e83e8c';
                        break;
                    case 'audio':
                        iconClass = 'fas fa-file-audio';
                        iconColor = '#6f42c1';
                        break;
                    case 'document':
                        if (['pdf'].includes(fileExt)) {
                            iconClass = 'fas fa-file-pdf';
                            iconColor = '#dc3545';
                        } else if (['doc', 'docx'].includes(fileExt)) {
                            iconClass = 'fas fa-file-word';
                            iconColor = '#2b579a';
                        } else if (['xls', 'xlsx'].includes(fileExt)) {
                            iconClass = 'fas fa-file-excel';
                            iconColor = '#217346';
                        } else if (['txt'].includes(fileExt)) {
                            iconClass = 'fas fa-file-alt';
                            iconColor = '#6c757d';
                        } else {
                            iconClass = 'fas fa-file-alt';
                            iconColor = '#6c757d';
                        }
                        break;
                    case 'sticker':
                        iconClass = 'fas fa-sticky-note';
                        iconColor = '#20c997';
                        break;
                }
                
                html += `<i class="${iconClass} fa-lg" style="color: ${iconColor}"></i>`;
                html += '</div>';
                html += '<div class="flex-grow-1 ms-3">';
                html += '<div class="d-flex justify-content-between align-items-start">';
                
                // File name
                let fileName = file.message_type === 'audio' ? 'Audio' : 'File';
                if (file.file_name) {
                    fileName = escapeHtml(file.file_name);
                } else if (file.message_type === 'document' && file.media_url) {
                    const urlName = file.media_url.split('?')[0].split('/').pop();
                    if (urlName) {
                        fileName = escapeHtml(urlName);
                    }
                } else if (file.media_caption) {
                    fileName = escapeHtml(file.media_caption);
                } else if (file.message_type === 'sticker') {
                    fileName = 'Sticker';
                } else if (file.content) {
                    fileName = escapeHtml(file.content.substring(0, 50)) + '...';
                }
                if (file.media_type === 'application/pdf' && file.media_caption) {
                    fileName = escapeHtml(file.media_caption);
                }
                const fileCaption = file.message_type === 'document'
                    ? escapeHtml(file.media_caption || file.caption || '')
                    : '';
                
                const rawFileName = fileName;
                let displayFileName = rawFileName;
                if (rawFileName.length > 25) {
                    displayFileName = rawFileName.slice(-25);
                }

                html += '<div>';
                html += `<h6 class="mb-1" title="${rawFileName}">${displayFileName}</h6>`;
                if (fileCaption) {
                    html += `<div class="small text-muted">${fileCaption}</div>`;
                }
                html += `<small class="text-muted">${timeStr}</small>`;
                html += '</div>';
                
                html += '<div class="d-flex align-items-center">';
                if (imageUrl) {
                    html += `<button class="btn btn-sm btn-outline-secondary me-2" type="button" title="View image" data-image-url="${imageUrl}" data-image-caption="${imageCaption}" onclick="openMessagesFilesImageLightbox(this)">`;
                    html += '<i class="fas fa-eye"></i>';
                    html += '</button>';
                }
                
                // Three dots dropdown menu for category assignment
                html += '<div class="dropdown">';
                html += '<button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">';
                html += '<i class="fas fa-ellipsis-v"></i>';
                html += '</button>';
                html += '<ul class="dropdown-menu dropdown-menu-end">';
                if (canDeleteMessages) {
                    html += '<li><a class="dropdown-item text-danger message-delete-item" href="#" data-message-id="' + file.id + '"><i class="fas fa-trash-alt me-2"></i>Delete message</a></li>';
                    html += '<li><hr class="dropdown-divider"></li>';
                }
                html += '<li><h6 class="dropdown-header">Assign to Category</h6></li>';
                html += '<li><a class="dropdown-item no-category-item" href="#" data-message-id="' + file.id + '">No Category</a></li>';
                html += '<li><hr class="dropdown-divider"></li>';
                html += '<li><a class="dropdown-item category-loading-item" href="#" data-message-id="' + file.id + '">Loading categories...</a></li>';
                html += '</ul>';
                html += '</div>';
                html += '</div>';
                
                html += '</div>';
                
                // Sender info
                html += `<p class="mb-1 text-muted">From: ${escapeHtml(file.sender_name || file.sender_number || 'Unknown')}</p>`;

                const amountTextRaw = formatAmount(file.amount);
                if (amountTextRaw.length > 0) {
                    html += `<div class="message-data-highlight">${escapeHtml(amountTextRaw)}</div>`;
                }

                const dataTextRaw = file.data !== null && file.data !== undefined ? file.data.toString().trim() : '';
                if (dataTextRaw.length > 0) {
                    const encodedData = encodeURIComponent(dataTextRaw);
                    html += `<button class="btn btn-sm btn-outline-secondary message-data-view" type="button" data-data-content="${encodedData}" onclick="openMessagesFilesDataModal(this)">`;
                    html += '<i class="fas fa-eye me-1"></i>View data';
                    html += '</button>';
                    html += `<div class="message-data-hidden">${escapeHtml(dataTextRaw)}</div>`;
                }

                if (file.message_type === 'document' && file.media_url) {
                    const docUrl = escapeHtml(file.media_url);
                    const isPdf = fileExt === 'pdf';
                    html += '<div class="mt-2">';
                    if (isPdf) {
                        html += `<button class="btn btn-sm btn-outline-secondary" type="button" data-file-url="${docUrl}" onclick="openMessagesFilesPdfModal(this)">`;
                        html += '<i class="fas fa-file-pdf me-1"></i>View PDF';
                        html += '</button>';
                    } else {
                        html += `<a class="btn btn-sm btn-outline-secondary" href="${docUrl}" target="_blank" rel="noopener">`;
                        html += '<i class="fas fa-download me-1"></i>Open document';
                        html += '</a>';
                    }
                    html += '</div>';
                }

                if (file.message_type === 'audio') {
                    html += '<div class="mt-2">';
                    if (audioUrl) {
                        html += `<audio controls preload="metadata" style="width: 100%; max-width: 320px;">`;
                        html += `<source src="${audioUrl}">`;
                        html += 'Your browser does not support the audio element.';
                        html += '</audio>';
                    }
                    if (audioTranscript) {
                        html += `<div class="small text-muted mt-1">${audioTranscript}</div>`;
                    }
                    html += '</div>';
                }

                if (imageUrl) {
                    html += '<div class="mt-2">';
                    html += `<img src="${imageUrl}" class="img-fluid rounded message-file-thumb" alt="Image" data-image-url="${imageUrl}" data-image-caption="${imageCaption}" onclick="openMessagesFilesImageLightbox(this)">`;
                    html += '</div>';
                }
                
                // Category badge if available
                if (file.category_name) {
                    html += '<div class="d-flex align-items-center">';
                    html += `<span class="badge me-2" style="background-color: ${file.category_color || '#6c757d'}; color: white">`;
                    html += escapeHtml(file.category_name);
                    html += '</span>';
                    html += '</div>';
                }
                html += '</div>';
                html += '</div>';
                html += '</div>';
                
                return html;
            }
            
            function showAddCategoryModal(groupId) {
                Swal.fire({
                    title: 'Create New Category',
                    html: `
                        <input id="category-name" class="swal2-input" placeholder="Category name" required>
                        <input id="category-color" class="swal2-input" placeholder="Color (hex code, e.g., #007bff)" value="#6c757d">
                    `,
                    focusConfirm: false,
                    showCancelButton: true,
                    confirmButtonText: 'Create',
                    cancelButtonText: 'Cancel',
                    preConfirm: () => {
                        const name = document.getElementById('category-name').value.trim();
                        const color = document.getElementById('category-color').value.trim();
                        
                        if (!name) {
                            Swal.showValidationMessage('Category name is required');
                            return false;
                        }
                        
                        return { name, color: color || '#6c757d' };
                    }
                }).then((result) => {
                    if (result.isConfirmed && result.value) {
                        const { name, color } = result.value;
                        
                        // Create category via API
                        fetch('/api/whatsapp/categories', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                name: name,
                                color: color
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Success',
                                    text: 'Category created successfully!',
                                    timer: 2000,
                                    showConfirmButton: false
                                });
                                // Reload category tree
                                loadFolderContent(groupId);
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: 'Failed to create category: ' + (data.message || 'Unknown error')
                                });
                            }
                        })
                        .catch(error => {
                            console.error('Error creating category:', error);
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: 'Error creating category'
                            });
                        });
                    }
                });
             }
         }
         
         // Initialize sorting with "Latest Activity" as default
         const latestSortItem = document.querySelector('[data-sort="latest"]');
         if (latestSortItem) {
             // Update dropdown button text
             const sortDropdownBtn = document.getElementById('sort-dropdown');
             const iconClass = latestSortItem.querySelector('i').className;
             const text = latestSortItem.textContent.trim();
             sortDropdownBtn.innerHTML = `<i class="${iconClass}"></i> ${text}`;
             
             // Apply initial sort
             sortFolders('latest');
         }
     });
     </script>
    <?php
    
    // End dashboard layout
    app_render_dashboard_end();
    
    app_render_footer();
}

function app_format_audit_details(array $entry): string
{
    $context = $entry['context'] ?? [];
    $details = [];

    if (!empty($context['title'])) {
        $details[] = 'Title: ' . htmlspecialchars((string) $context['title'], ENT_QUOTES, 'UTF-8');
    }
    if (!empty($context['page_id'])) {
        $details[] = 'Page ID: ' . (int) $context['page_id'];
    }
    if (!empty($context['session_name'])) {
        $details[] = 'Session: ' . htmlspecialchars((string) $context['session_name'], ENT_QUOTES, 'UTF-8');
    }
    if (!empty($context['session_id'])) {
        $details[] = 'Session ID: ' . (int) $context['session_id'];
    }
    if (!empty($context['group_id'])) {
        $details[] = 'Group: ' . htmlspecialchars((string) $context['group_id'], ENT_QUOTES, 'UTF-8');
    }
    if (!empty($context['group_name'])) {
        $details[] = 'Group Name: ' . htmlspecialchars((string) $context['group_name'], ENT_QUOTES, 'UTF-8');
    }
    if (!empty($context['export_id'])) {
        $details[] = 'Export ID: ' . (int) $context['export_id'];
    }
    if (!empty($context['message_id'])) {
        $messageId = $context['message_id'];
        if (is_numeric($messageId)) {
            $details[] = 'Message ID: ' . (int) $messageId;
        } else {
            $details[] = 'Message ID: ' . htmlspecialchars((string) $messageId, ENT_QUOTES, 'UTF-8');
        }
    }
    if (!empty($context['zip_filename'])) {
        $details[] = 'File: ' . htmlspecialchars((string) $context['zip_filename'], ENT_QUOTES, 'UTF-8');
    }
    if (!empty($context['file_size'])) {
        $details[] = 'File Size: ' . number_format((float) $context['file_size']) . ' bytes';
    }
    if (!empty($context['error_message'])) {
        $details[] = 'Error: ' . htmlspecialchars((string) $context['error_message'], ENT_QUOTES, 'UTF-8');
    }
    if (!empty($context['category_id'])) {
        $details[] = 'Category ID: ' . (int) $context['category_id'];
    }
    if (!empty($context['category_name'])) {
        $details[] = 'Category: ' . htmlspecialchars((string) $context['category_name'], ENT_QUOTES, 'UTF-8');
    }
    if (!empty($context['changed_fields']) && is_array($context['changed_fields'])) {
        $fields = array_map('strval', $context['changed_fields']);
        $details[] = 'Changed: ' . htmlspecialchars(implode(', ', $fields), ENT_QUOTES, 'UTF-8');
    }
    if (array_key_exists('has_media', $context)) {
        $details[] = 'Has Media: ' . (!empty($context['has_media']) ? 'Yes' : 'No');
    }
    if (!empty($context['message_preview'])) {
        $details[] = 'Message: ' . htmlspecialchars((string) $context['message_preview'], ENT_QUOTES, 'UTF-8');
    }
    if (!empty($context['target_user_id'])) {
        $details[] = 'User ID: ' . (int) $context['target_user_id'];
    }
    if (!empty($context['ip'])) {
        $details[] = 'IP: ' . htmlspecialchars((string) $context['ip'], ENT_QUOTES, 'UTF-8');
    }

    return $details ? implode(' | ', $details) : '-';
}

function app_page_audits(): void
{
    app_require_auth();
    $user = app_current_user();

    if (!in_array($user['role'] ?? '', ['admin', 'superadmin'], true)) {
        app_flash('error', 'Access denied');
        app_redirect('/welcome');
    }

    $selectedAction = trim((string) ($_GET['action'] ?? ''));
    $dateFrom = trim((string) ($_GET['from'] ?? ''));
    $dateTo = trim((string) ($_GET['to'] ?? ''));

    $dateRegex = '/^\d{4}-\d{2}-\d{2}$/';
    $fromTs = preg_match($dateRegex, $dateFrom) ? strtotime($dateFrom . ' 00:00:00') : null;
    $toTs = preg_match($dateRegex, $dateTo) ? strtotime($dateTo . ' 23:59:59') : null;

    $entries = app_get_audit_entries_for_user($user, 200);
    $entries = array_values(array_filter($entries, function (array $entry) use ($selectedAction, $fromTs, $toTs): bool {
        if ($selectedAction !== '' && ($entry['action'] ?? '') !== $selectedAction) {
            return false;
        }

        if ($fromTs !== null || $toTs !== null) {
            $timestamp = strtotime((string) ($entry['timestamp'] ?? ''));
            if (!$timestamp) {
                return false;
            }
            if ($fromTs !== null && $timestamp < $fromTs) {
                return false;
            }
            if ($toTs !== null && $timestamp > $toTs) {
                return false;
            }
        }

        return true;
    }));
    $actionLabels = app_audit_action_labels();

    app_render_head('Master Audit');
    app_render_dashboard_start($user);
    app_render_flash();
    ?>
    <div class="container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <div>
                <h2 class="mb-1">Master Audit</h2>
                <div class="text-muted small">
                    <?php if (($user['role'] ?? '') === 'admin'): ?>
                        Showing your activity and your child users.
                    <?php else: ?>
                        Showing all audit activity.
                    <?php endif; ?>
                </div>
            </div>
            <div class="d-flex gap-2 mt-2 mt-md-0">
                <button type="button" class="btn btn-outline-secondary" id="audit-export-csv">
                    <i class="fas fa-file-csv me-1"></i> CSV
                </button>
                <button type="button" class="btn btn-outline-success" id="audit-export-xlsx">
                    <i class="fas fa-file-excel me-1"></i> Excel
                </button>
                <button type="button" class="btn btn-outline-danger" id="audit-export-pdf">
                    <i class="fas fa-file-pdf me-1"></i> PDF
                </button>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0">Filters</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-12 col-md-4">
                        <label class="form-label">Action</label>
                        <select name="action" class="form-select">
                            <option value="">All actions</option>
                            <?php foreach ($actionLabels as $actionKey => $actionLabel): ?>
                                <option value="<?= htmlspecialchars($actionKey, ENT_QUOTES, 'UTF-8') ?>" <?= $selectedAction === $actionKey ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($actionLabel, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">From</label>
                        <input type="date" name="from" class="form-control" value="<?= htmlspecialchars($dateFrom, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">To</label>
                        <input type="date" name="to" class="form-control" value="<?= htmlspecialchars($dateTo, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-12 col-md-2 d-grid gap-2">
                        <button type="submit" class="btn btn-primary">Apply</button>
                        <a class="btn btn-outline-secondary" href="/audits">Clear</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Recent Activity</h5>
                <span class="badge bg-secondary">Showing <?= count($entries) ?> entries</span>
            </div>
            <div class="card-body">
                <?php if (empty($entries)): ?>
                    <div class="text-muted">No audit activity recorded yet.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle" id="audits-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Action</th>
                                    <th>Actor</th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($entries as $entry): ?>
                                    <?php
                                    $context = $entry['context'] ?? [];
                                    $actionKey = $entry['action'] ?? '';
                                    $actionLabel = $actionLabels[$actionKey] ?? ucwords(str_replace('_', ' ', (string) $actionKey));
                                    $actorName = trim((string) ($context['actor_name'] ?? ''));
                                    $actorEmail = trim((string) ($context['actor_email'] ?? ''));
                                    $actorId = (int) ($context['actor_id'] ?? 0);
                                    $actorRole = trim((string) ($context['actor_role'] ?? ''));
                                    $actorText = $actorName !== '' ? $actorName : ($actorEmail !== '' ? $actorEmail : ($actorId ? 'User #' . $actorId : 'Unknown'));
                                    $timestamp = (string) ($entry['timestamp'] ?? '');
                                    $dateText = $timestamp !== '' ? date('M d, Y H:i', strtotime($timestamp)) : '';
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($dateText, ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <span class="badge bg-primary">
                                                <?= htmlspecialchars($actionLabel, ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div><?= htmlspecialchars($actorText, ENT_QUOTES, 'UTF-8') ?></div>
                                            <?php if ($actorRole !== ''): ?>
                                                <div class="text-muted small"><?= htmlspecialchars(ucfirst($actorRole), ENT_QUOTES, 'UTF-8') ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-muted small">
                                            <?= app_format_audit_details($entry) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php

    ?>

    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jspdf-autotable@3.5.31/dist/jspdf.plugin.autotable.min.js"></script>
    <script>
        (function() {
            const table = document.getElementById('audits-table');
            const csvButton = document.getElementById('audit-export-csv');
            const xlsxButton = document.getElementById('audit-export-xlsx');
            const pdfButton = document.getElementById('audit-export-pdf');

            function getTableData() {
                if (!table) {
                    return { headers: [], rows: [] };
                }
                const headers = Array.from(table.querySelectorAll('thead th')).map(th => th.textContent.trim());
                const rows = Array.from(table.querySelectorAll('tbody tr')).map(row => {
                    return Array.from(row.querySelectorAll('td')).map(cell => cell.textContent.trim());
                });
                return { headers, rows };
            }

            function downloadFile(blob, filename) {
                const url = window.URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = filename;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                window.URL.revokeObjectURL(url);
            }

            function buildCsvContent(data) {
                const escapeValue = value => {
                    if (value.includes('"') || value.includes(',') || value.includes('\n')) {
                        return '"' + value.replace(/"/g, '""') + '"';
                    }
                    return value;
                };
                const lines = [data.headers.map(escapeValue).join(',')];
                data.rows.forEach(row => {
                    lines.push(row.map(escapeValue).join(','));
                });
                return lines.join('\n');
            }

            function showNoData() {
                if (window.Swal) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'No data to export',
                        text: 'There are no rows to export.'
                    });
                } else {
                    alert('No data to export.');
                }
            }

            function exportCsv() {
                const data = getTableData();
                if (!data.rows.length) {
                    showNoData();
                    return;
                }
                const csvContent = buildCsvContent(data);
                downloadFile(new Blob([csvContent], { type: 'text/csv;charset=utf-8;' }), 'audits.csv');
            }

            function exportXlsx() {
                const data = getTableData();
                if (!data.rows.length || !window.XLSX) {
                    showNoData();
                    return;
                }
                const worksheet = window.XLSX.utils.aoa_to_sheet([data.headers, ...data.rows]);
                const workbook = window.XLSX.utils.book_new();
                window.XLSX.utils.book_append_sheet(workbook, worksheet, 'Audits');
                window.XLSX.writeFile(workbook, 'audits.xlsx');
            }

            function exportPdf() {
                const data = getTableData();
                if (!data.rows.length || !window.jspdf || !window.jspdf.jsPDF) {
                    showNoData();
                    return;
                }
                const doc = new window.jspdf.jsPDF({ orientation: 'landscape', unit: 'pt', format: 'a4' });
                doc.setFontSize(12);
                doc.text('Master Audit', 40, 40);
                if (doc.autoTable) {
                    doc.autoTable({
                        startY: 60,
                        head: [data.headers],
                        body: data.rows,
                        styles: { fontSize: 8, cellPadding: 3 }
                    });
                }
                doc.save('audits.pdf');
            }

            if (csvButton) {
                csvButton.addEventListener('click', exportCsv);
            }
            if (xlsxButton) {
                xlsxButton.addEventListener('click', exportXlsx);
            }
            if (pdfButton) {
                pdfButton.addEventListener('click', exportPdf);
            }
        })();
    </script>
    <?php

    app_render_dashboard_end();
    app_render_footer();
}

function app_pages_max_limit(): int
{
    return 3;
}

function app_pages_list_for_user(int $userId): array
{
    if ($userId <= 0) {
        return [];
    }

    $stmt = app_db()->prepare('SELECT id, user_id, token, title, is_public, created_at, updated_at FROM user_pages WHERE user_id = ? ORDER BY created_at DESC');
    $stmt->execute([$userId]);

    return $stmt->fetchAll() ?: [];
}

function app_pages_count_for_user(int $userId): int
{
    if ($userId <= 0) {
        return 0;
    }

    $stmt = app_db()->prepare('SELECT COUNT(*) FROM user_pages WHERE user_id = ?');
    $stmt->execute([$userId]);

    return (int) $stmt->fetchColumn();
}

function app_pages_find_by_token(string $token): ?array
{
    $stmt = app_db()->prepare('SELECT id, user_id, token, title, is_public, created_at, updated_at FROM user_pages WHERE token = ? LIMIT 1');
    $stmt->execute([$token]);
    $page = $stmt->fetch();

    return $page === false ? null : $page;
}

function app_pages_find_for_user(int $userId, int $pageId): ?array
{
    if ($userId <= 0 || $pageId <= 0) {
        return null;
    }

    $stmt = app_db()->prepare('SELECT id, user_id, token, title, is_public, created_at, updated_at FROM user_pages WHERE id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$pageId, $userId]);
    $page = $stmt->fetch();

    return $page === false ? null : $page;
}

function app_pages_generate_token(): string
{
    $maxAttempts = 5;
    for ($i = 0; $i < $maxAttempts; $i++) {
        $raw = bin2hex(random_bytes(8));
        $token = substr($raw, 0, 12);
        if (app_pages_find_by_token($token) === null) {
            return $token;
        }
    }

    return bin2hex(random_bytes(10));
}

function app_pages_create(int $userId, string $token, ?string $title, int $isPublic): array
{
    $stmt = app_db()->prepare('INSERT INTO user_pages (user_id, token, title, is_public) VALUES (?, ?, ?, ?)');
    $stmt->execute([$userId, $token, $title, $isPublic]);

    $pageId = (int) app_db()->lastInsertId();

    return app_pages_find_for_user($userId, $pageId) ?? [];
}

function app_pages_update(int $userId, int $pageId, ?string $title, int $isPublic): ?array
{
    $stmt = app_db()->prepare('UPDATE user_pages SET title = ?, is_public = ? WHERE id = ? AND user_id = ?');
    $stmt->execute([$title, $isPublic, $pageId, $userId]);

    return app_pages_find_for_user($userId, $pageId);
}

function app_pages_delete(int $userId, int $pageId): bool
{
    $stmt = app_db()->prepare('DELETE FROM user_pages WHERE id = ? AND user_id = ?');
    $stmt->execute([$pageId, $userId]);

    return $stmt->rowCount() > 0;
}

function app_page_pages(): void
{
    $user = app_current_user();
    $isAuthenticated = $user !== null;
    $effectiveUser = $isAuthenticated ? app_get_effective_user($user) : null;
    $effectiveUserId = (int) ($effectiveUser['id'] ?? 0);
    $tokenInput = trim((string) ($_GET['token'] ?? ''));
    $requestedFile = null;
    if ($tokenInput !== '' && str_contains($tokenInput, '/')) {
        $parts = explode('/', $tokenInput, 2);
        $tokenInput = trim((string) ($parts[0] ?? ''));
        $requestedFile = trim((string) ($parts[1] ?? ''));
    }
    $token = $tokenInput;
    $tokenIsValid = $token !== '' && preg_match('/^[A-Za-z0-9_-]+$/', $token) === 1;
    $pageRecord = $tokenIsValid ? app_pages_find_by_token($token) : null;
    $tokenExists = $pageRecord !== null;
    $isOwner = $tokenExists && $effectiveUserId > 0 && (int) $pageRecord['user_id'] === $effectiveUserId;
    $isPublic = $tokenExists && (int) $pageRecord['is_public'] === 1;
    $canViewPage = $tokenExists && ($isPublic || $isOwner);
    $tokenForbidden = $tokenIsValid && $tokenExists && !$canViewPage;

    $baseDir = dirname(__DIR__) . '/pages';
    $resolvedBaseDir = realpath($baseDir) ?: $baseDir;
    $pageDir = $canViewPage ? $baseDir . '/' . $token : null;
    $resolvedPageDir = null;

    if ($pageDir && is_dir($pageDir)) {
        $candidate = realpath($pageDir);
        if ($candidate !== false && str_starts_with($candidate, rtrim($resolvedBaseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
            $resolvedPageDir = $candidate;
        }
    }

    if ($requestedFile !== null) {
        $allowedFiles = [
            'app.html' => 'text/html; charset=UTF-8',
            'app.css' => 'text/css; charset=UTF-8',
            'app.js' => 'application/javascript; charset=UTF-8',
            'reports.csv' => 'text/csv; charset=UTF-8',
            'report.csv' => 'text/csv; charset=UTF-8',
        ];
        if (!$canViewPage || $requestedFile === '' || !array_key_exists($requestedFile, $allowedFiles)) {
            http_response_code(404);
            return;
        }

        if ($resolvedPageDir === null) {
            http_response_code(404);
            return;
        }

        $filePath = $resolvedPageDir . DIRECTORY_SEPARATOR . $requestedFile;
        if (!is_file($filePath)) {
            http_response_code(404);
            return;
        }

        header('Content-Type: ' . $allowedFiles[$requestedFile]);
        readfile($filePath);
        return;
    }

    $htmlPath = $resolvedPageDir ? $resolvedPageDir . '/app.html' : null;
    $cssPath = $resolvedPageDir ? $resolvedPageDir . '/app.css' : null;
    $jsPath = $resolvedPageDir ? $resolvedPageDir . '/app.js' : null;

    $htmlExists = $htmlPath ? is_file($htmlPath) : false;
    $cssExists = $cssPath ? is_file($cssPath) : false;
    $jsExists = $jsPath ? is_file($jsPath) : false;

    $htmlContent = $htmlExists ? (string) file_get_contents($htmlPath) : '';
    $cssContent = $cssExists ? (string) file_get_contents($cssPath) : '';
    $jsContent = $jsExists ? (string) file_get_contents($jsPath) : '';

    $logDir = dirname(__DIR__) . '/logs';
    $statusFile = $tokenIsValid ? $logDir . '/page_generate_' . $token . '.status' : null;
    $generationStatus = '';
    if ($statusFile && is_file($statusFile)) {
        $generationStatus = trim((string) file_get_contents($statusFile));
    }
    $isGenerating = $generationStatus === 'running';
    $isGenerationFailed = $generationStatus === 'failed';

    $iframeSrcdoc = null;
    if ($htmlExists) {
        $iframeSrcdoc = "<!DOCTYPE html>\n" .
            "<html lang=\"en\">\n" .
            "<head>\n" .
            "  <meta charset=\"utf-8\">\n" .
            "  <meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n" .
            "  <base href=\"/pages/" . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . "/\">\n" .
            "  <link rel=\"stylesheet\" href=\"/assets/pages/bootstrap.min.css\">\n" .
            "  <link rel=\"stylesheet\" href=\"/assets/pages/fontawesome.min.css\">\n" .
            "  <link rel=\"stylesheet\" href=\"/assets/pages/sweetalert2.min.css\">\n" .
            "  <style>\n" . $cssContent . "\n  </style>\n" .
            "</head>\n" .
            "<body>\n" .
            $htmlContent . "\n" .
            "  <script src=\"/assets/pages/jquery.min.js\"></script>\n" .
            "  <script src=\"/assets/pages/bootstrap.bundle.min.js\"></script>\n" .
            "  <script src=\"/assets/pages/sweetalert2.all.min.js\"></script>\n" .
            "  <script>\n" . $jsContent . "\n  </script>\n" .
            "</body>\n" .
            "</html>";
    }

    $pagesList = $isAuthenticated ? app_pages_list_for_user($effectiveUserId) : [];
    $pageCount = $isAuthenticated ? count($pagesList) : 0;
    $pageLimit = app_pages_max_limit();
    $baseUrl = rtrim((string) app_env('APP_PUBLIC_URL', ''), '/');
    if ($baseUrl === '') {
        $baseUrl = rtrim((string) app_env('APP_BASE_URL', ''), '/');
    }
    if ($baseUrl === '') {
        $baseUrl = rtrim((string) app_env('APP_URL', ''), '/');
    }
    $isLocalBase = $baseUrl !== '' && preg_match('/^(https?:\/\/)?(localhost|127\.0\.0\.1)(:\d+)?/i', $baseUrl) === 1;
    if ($baseUrl === '' || $isLocalBase) {
        $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '');
    }

    app_render_head('Pages');
    if ($isAuthenticated) {
        app_render_dashboard_start($user);
        app_render_flash();
    }
    ?>
    <?php if ($isAuthenticated && !($token !== '' && $tokenIsValid && $canViewPage)): ?>
        <div class="row">
            <div class="col-12">
                <div class="card mb-4" id="pages-management" data-limit="<?= $pageLimit ?>" data-base-url="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-0">Pages Sandbox (Beta)</h4>
                            <p class="text-muted mb-0">manage and preview AI Generated pages from reports</p>
                        </div>
                        <div class="text-end">
                            <div class="text-muted small"><?= $pageCount ?> / <?= $pageLimit ?> pages used</div>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if ($token !== '' && !$tokenIsValid): ?>
                            <div class="alert alert-danger mt-3 mb-0">Token contains invalid characters. Use letters, numbers, hyphen, and underscore only.</div>
                        <?php elseif ($tokenIsValid && $tokenExists === false): ?>
                            <div class="alert alert-warning mt-3 mb-0">Token not found in database. Create it first to manage access.</div>
                        <?php elseif ($tokenForbidden): ?>
                            <div class="alert alert-danger mt-3 mb-0">You do not have access to this page.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Your Pages</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($pagesList)): ?>
                            <div class="p-4 text-muted">No pages yet. Click “Create Page” to add one.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Title</th>
                                            <th>Token</th>
                                            <th>Public</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pagesList as $page): ?>
                                            <tr data-page-id="<?= (int) $page['id'] ?>">
                                                <td><?= htmlspecialchars((string) ($page['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?: 'Untitled' ?></td>
                                                <td>
                                                    <div class="d-flex flex-column">
                                                        <span class="fw-semibold"><?= htmlspecialchars($page['token'], ENT_QUOTES, 'UTF-8') ?></span>
                                                        <div class="mt-1">
                                                            <a class="btn btn-outline-primary btn-sm" href="/pages?token=<?= urlencode($page['token']) ?>">View Page</a>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input page-public-toggle" type="checkbox" role="switch" id="page-public-<?= (int) $page['id'] ?>" data-page-id="<?= (int) $page['id'] ?>" <?= ((int) $page['is_public'] === 1) ? 'checked' : '' ?>>
                                                        <label class="form-check-label" for="page-public-<?= (int) $page['id'] ?>"><?= ((int) $page['is_public'] === 1) ? 'Public' : 'Private' ?></label>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="d-flex gap-2">
                                                        <button class="btn btn-sm btn-outline-secondary page-edit-btn" type="button"
                                                            data-page-id="<?= (int) $page['id'] ?>"
                                                            data-page-token="<?= htmlspecialchars($page['token'], ENT_QUOTES, 'UTF-8') ?>"
                                                            data-page-title="<?= htmlspecialchars((string) ($page['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                            data-page-public="<?= (int) $page['is_public'] ?>">
                                                            Edit
                                                        </button>
                                                        <?php if ((int) $page['is_public'] === 1): ?>
                                                            <button class="btn btn-sm btn-outline-primary page-share-btn" type="button"
                                                                data-page-token="<?= htmlspecialchars($page['token'], ENT_QUOTES, 'UTF-8') ?>"
                                                                data-share-url="<?= htmlspecialchars($baseUrl . '/pages/' . $page['token'], ENT_QUOTES, 'UTF-8') ?>"
                                                                onclick="appPagesShare(this)">
                                                                Share
                                                            </button>
                                                        <?php endif; ?>
                                                        <?php if (($user['role'] ?? '') !== 'users'): ?>
                                                            <button class="btn btn-sm btn-outline-danger page-delete-btn" type="button" data-page-id="<?= (int) $page['id'] ?>" data-page-token="<?= htmlspecialchars($page['token'], ENT_QUOTES, 'UTF-8') ?>">
                                                                Delete
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <?php if (!$isAuthenticated && $token !== '' && (!$tokenIsValid || !$canViewPage)): ?>
        <div class="container py-4">
            <div class="alert alert-warning mb-0">Page not available.</div>
        </div>
    <?php endif; ?>

    <?php
    $wrapGuestContainer = !$isAuthenticated && $token !== '' && $tokenIsValid && $canViewPage;
    if ($wrapGuestContainer):
        ?>
        <div class="container pb-4">
        <?php
    endif;
    ?>
    <?php if ($isAuthenticated && $token !== '' && $tokenIsValid && $canViewPage && !($htmlExists && $cssExists && $jsExists)): ?>
        <?php if ($isAuthenticated): ?>
            <div class="row">
                <div class="col-12">
                    <div class="mb-3">
                        <a class="btn btn-outline-secondary btn-sm" href="/pages">Back to Pages</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <div class="row">
            <div class="col-12">
                <div class="card mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">File Checks</h5>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-4">
                            <video class="rounded" autoplay muted loop playsinline style="width: 30%; max-width: 320px; height: auto;">
                                <source src="/assets/videos/building.mp4" type="video/mp4">
                            </video>
                            <div class="text-muted small mt-2">Your page is still being built. Check back soon.</div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="fas <?= $htmlExists ? 'fa-circle-check text-success' : 'fa-circle-xmark text-danger' ?>"></i>
                                    <span>app.html</span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="fas <?= $cssExists ? 'fa-circle-check text-success' : 'fa-circle-xmark text-danger' ?>"></i>
                                    <span>app.css</span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="fas <?= $jsExists ? 'fa-circle-check text-success' : 'fa-circle-xmark text-danger' ?>"></i>
                                    <span>app.js</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <script>
            (function () {
                var token = "<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>";
                if (!token) {
                    return;
                }

                window.setInterval(function () {
                    window.location.reload();
                }, 10000);
            })();
        </script>
    <?php endif; ?>

    <?php if ($token !== '' && $tokenIsValid && $canViewPage): ?>
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-white d-flex align-items-center justify-content-between">
                        <h5 class="mb-0">Preview</h5>
                        <div class="d-flex align-items-center gap-2">
                            <?php if ($isOwner && ($isGenerationFailed || $isGenerating || !($htmlExists && $cssExists && $jsExists))): ?>
                                <button
                                    class="btn btn-outline-warning btn-sm"
                                    id="page-regenerate-btn"
                                    data-page-id="<?= (int) ($pageRecord['id'] ?? 0) ?>"
                                    <?= $isGenerating ? 'disabled' : '' ?>
                                >
                                    <?= $isGenerating ? 'Generating...' : 'Regenerate' ?>
                                </button>
                            <?php endif; ?>
                            <a class="btn btn-outline-secondary btn-sm" href="/pages">Back to Pages</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if ($iframeSrcdoc): ?>
                            <iframe
                                title="Page Preview"
                                srcdoc="<?= htmlspecialchars($iframeSrcdoc, ENT_QUOTES, 'UTF-8') ?>"
                                style="width: 100%; height: 70vh; border: 1px solid #e3e6f0; border-radius: 6px; background: #fff;"
                            ></iframe>
                        <?php else: ?>
                            <div class="alert alert-info mb-0">Add <code>app.html</code> to the token folder to render the preview.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <?php if ($wrapGuestContainer): ?>
        </div>
    <?php endif; ?>
    <?php if ($isAuthenticated): ?>
        <div class="modal fade" id="pageModal" tabindex="-1" aria-labelledby="pageModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="pageModalLabel">Create Page</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form id="pageForm">
                        <div class="modal-body">
                            <input type="hidden" name="id" id="page-id" />
                            <input type="hidden" name="token" id="page-token" />
                            <div class="mb-3">
                                <label class="form-label">Title</label>
                                <input type="text" class="form-control" name="title" id="page-title" placeholder="Page title" required>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="page-public" name="is_public">
                                <label class="form-check-label" for="page-public">Public (anyone with token can view)</label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary" id="page-save-btn">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <?php
    if ($isAuthenticated) {
        app_render_dashboard_end();
    }
    app_render_scripts();
    if ($isAuthenticated) {
        ?>
        <script src="/js/pages-management.js?bump_version=1"></script>
        <script>
            function appPagesShare(button) {
                if (!button) {
                    return;
                }
                var shareUrl = button.getAttribute('data-share-url') || '';
                if (!shareUrl) {
                    return;
                }

                var showSuccess = function (message) {
                    if (window.Swal) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success',
                            text: message,
                            timer: 1200,
                            showConfirmButton: false
                        });
                        return;
                    }
                    alert(message);
                };

                var showError = function (message) {
                    if (window.Swal) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: message
                        });
                        return;
                    }
                    alert(message);
                };

                var fallback = function () {
                    var temp = document.createElement('textarea');
                    temp.value = shareUrl;
                    document.body.appendChild(temp);
                    temp.select();
                    var copied = false;
                    try {
                        copied = document.execCommand('copy');
                    } catch (error) {
                        copied = false;
                    }
                    document.body.removeChild(temp);

                    if (copied) {
                        showSuccess('Link copied');
                        return;
                    }

                    if (window.Swal) {
                        Swal.fire({
                            icon: 'info',
                            title: 'Copy link',
                            input: 'text',
                            inputValue: shareUrl,
                            confirmButtonText: 'Close'
                        });
                    } else {
                        prompt('Copy link:', shareUrl);
                    }
                };

                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(shareUrl)
                        .then(function () {
                            showSuccess('Link copied');
                        })
                        .catch(function () {
                            fallback();
                        });
                    return;
                }

                fallback();
            }

            document.addEventListener('DOMContentLoaded', function () {
                var regenBtn = document.getElementById('page-regenerate-btn');
                if (!regenBtn) {
                    return;
                }

                var showMessage = function (type, title, message) {
                    if (window.Swal) {
                        Swal.fire({
                            icon: type,
                            title: title,
                            text: message
                        });
                        return;
                    }
                    alert(message);
                };

                regenBtn.addEventListener('click', function () {
                    if (regenBtn.disabled) {
                        return;
                    }

                    var pageId = regenBtn.getAttribute('data-page-id');
                    if (!pageId) {
                        return;
                    }

                    regenBtn.disabled = true;
                    regenBtn.textContent = 'Generating...';

                    fetch('/api/pages/' + pageId + '/generate', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        credentials: 'same-origin'
                    })
                        .then(function (response) {
                            return response.json()
                                .catch(function () {
                                    return null;
                                })
                                .then(function (data) {
                                    return { response: response, data: data };
                                });
                        })
                        .then(function (result) {
                            if (!result.response.ok || !result.data || result.data.success !== true) {
                                var message = result.data && result.data.message
                                    ? result.data.message
                                    : 'Failed to start generation.';
                                showMessage('error', 'Error', message);
                                regenBtn.disabled = false;
                                regenBtn.textContent = 'Regenerate';
                                return;
                            }

                            showMessage('success', 'Generation started', 'The page is regenerating now.');
                            window.location.reload();
                        })
                        .catch(function () {
                            showMessage('error', 'Error', 'Unable to start generation.');
                            regenBtn.disabled = false;
                            regenBtn.textContent = 'Regenerate';
                        });
                });
            });
        </script>
        <?php
    }
    ?>
</body>
</html>
    <?php
}

function app_page_logout(): void
{
    app_logout_user();
    session_start();
    app_flash('success', 'You have been logged out.');

    app_redirect('/login');
}

function app_page_expired(): void
{
    app_require_auth();

    $user = app_current_user();
    if ($user !== null && ($user['role'] ?? '') === 'users') {
        app_redirect('/restricted');
    }

    app_render_head('Account Expired');
    app_render_dashboard_start($user);

    ?>
    <div class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-xl-8 col-lg-9">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-5 text-center">
                        <div class="mb-4">
                            <i class="fas fa-hourglass-end fa-3x text-danger"></i>
                        </div>
                        <h3 class="mb-3">Account Expired</h3>
                        <p class="text-muted mb-4">Your admin account has expired. Please contact your agent for renewal.</p>
                        <?php
                        $agentContacts = trim((string) ($user['agent_contacts'] ?? ''));
                        if ($agentContacts === '') {
                            $agentContacts = "Brandon Chong (Developer)\nEmail: brandon@kkbuddy.com\nPhone: +6011 1879 6268";
                        }
                        ?>
                        <div class="text-start border rounded p-3 bg-light">
                            <div class="fw-semibold mb-2">Agent Contacts</div>
                            <div class="text-muted small">
                                <?= nl2br(htmlspecialchars($agentContacts, ENT_QUOTES, 'UTF-8')) ?>
                            </div>
                        </div>
                        <a href="/logout" class="btn btn-outline-secondary">Sign out</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php

    app_render_dashboard_end();
    app_render_footer();
}

function app_page_restricted(): void
{
    app_require_auth();

    $user = app_current_user();
    if ($user !== null && ($user['role'] ?? '') === 'admin' && app_is_admin_account_expired($user)) {
        app_redirect('/expired');
    }

    $parent = null;
    if ($user !== null && ($user['role'] ?? '') === 'users') {
        $parent = app_get_parent_user($user);
    }

    app_render_head('Restricted');
    app_render_dashboard_start($user);

    ?>
    <div class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-xl-8 col-lg-9">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-5 text-center">
                        <div class="mb-4">
                            <i class="fas fa-ban fa-3x text-warning"></i>
                        </div>
                        <h3 class="mb-3">Access Restricted</h3>
                        <p class="text-muted mb-4">
                            Your parent admin account is expired. Please contact your parent for renewal.
                            <?php if (!empty($parent['email'])): ?>
                                <br>
                                <span class="fw-semibold">Parent email:</span>
                                <a href="mailto:<?= htmlspecialchars($parent['email'], ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($parent['email'], ENT_QUOTES, 'UTF-8') ?>
                                </a>
                            <?php endif; ?>
                        </p>
                        <a href="/logout" class="btn btn-outline-secondary">Sign out</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php

    app_render_dashboard_end();
    app_render_footer();
}

function app_page_not_found(): void
{
    http_response_code(404);
    $reference = bin2hex(random_bytes(6));
    app_log('Route not found', 'INFO', [
        'reference' => $reference,
        'path' => $_SERVER['REQUEST_URI'] ?? '',
        'method' => $_SERVER['REQUEST_METHOD'] ?? ''
    ]);
    app_render_head('Not Found');

    $theme = app_theme();

    if ($theme === 'softing-v2.0') {
        app_render_not_found_softing($reference);
    } elseif ($theme === 'Anada-v2.0') {
        app_render_not_found_anada($reference);
    } else {
        app_render_not_found_sasoft($reference);
    }

    app_render_footer();
}

function app_page_server_error(?string $reference = null): void
{
    http_response_code(500);
    app_render_head('Server Error');

    $theme = app_theme();

    if ($theme === 'softing-v2.0') {
        app_render_server_error_softing($reference);
    } elseif ($theme === 'Anada-v2.0') {
        app_render_server_error_anada($reference);
    } else {
        app_render_server_error_sasoft($reference);
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
    <div class="banner-area responsive-top-pad circle-shape auto-height bg-shape bg-gray" style="background-image: url(<?= htmlspecialchars(app_theme_asset('assets/img/shape/1.png'), ENT_QUOTES, 'UTF-8') ?>);">
        <div class="container">
            <div class="content-box">
                <div class="row align-center">
                    <div class="col-lg-6 info">
                        <h2><strong>Business Management Portal</strong></h2>
                        <p>Run finance, inventory, sales, HR, and projects in one workspace. Keep every team aligned with real-time dashboards, approvals, and alerts.</p>
                        <ul>
                        </ul>
                        <div class="button">
                            <a class="btn circle btn-theme effect btn-md" href="/register">Get Started</a>
                            <a class="btn circle btn-theme border btn-md" href="/login">Login</a>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="thumb-innner">
                            <img src="<?= htmlspecialchars(app_asset('images/erpsoft-webbycms.png'), ENT_QUOTES, 'UTF-8') ?>" alt="ERPSoft-WebbyCMS">
                        </div>
                    </div>
                </div>
            </div>
        </div>
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
                    <div class="row"><div class="col-lg-12 col-md-12"><div class="form-group"><i class="fas fa-key"></i> <input class="form-control" name="otp" placeholder="MFA Code (if enabled)" type="text" inputmode="numeric" autocomplete="one-time-code"></div></div></div>
                    <div class="col-lg-12 col-md-12"><div class="row"><button type="submit">Login</button></div></div>
                </form>
                <div class="sign-up"><p>Don't have an account? <a href="/register">Sign up now</a></p></div>
                <div class="sign-up"><p>Sign in with <a href="/sso-connect">WebbyCloud</a></p></div>
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
                    <div class="row"><div class="col-lg-12 col-md-12"><div class="form-group"><i class="fas fa-ticket-alt"></i> <input class="form-control" name="invite_code" placeholder="Invite Code (optional)" type="text"></div></div></div>
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

function app_render_not_found_sasoft(?string $reference = null): void
{
    $refText = $reference ? '<p>Reference: <code>' . htmlspecialchars($reference, ENT_QUOTES, 'UTF-8') . '</code></p>' : '';
    ?>
    <div class="error-page-area default-padding"><div class="container"><div class="row align-center"><div class="col-lg-6"><div class="error-box"><h1>404</h1><h2>Sorry page was not found!</h2><?= $refText ?><a href="/" class="btn circle btn-theme effect btn-md">Back Home</a></div></div></div></div></div>
    <?php
}

function app_render_server_error_sasoft(?string $reference = null): void
{
    $refText = $reference ? '<p>Reference: <code>' . htmlspecialchars($reference, ENT_QUOTES, 'UTF-8') . '</code></p>' : '';
    ?>
    <div class="error-page-area default-padding"><div class="container"><div class="row align-center"><div class="col-lg-6"><div class="error-box"><h1>500</h1><h2>Something went wrong on our side.</h2><p>Please try again or contact support.</p><?= $refText ?><a href="/" class="btn circle btn-theme effect btn-md">Back Home</a></div></div></div></div></div>
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
    <div class="banner-area content-double shape-line bg-theme-small normal-text"><div class="box-table"><div class="box-cell"><div class="container"><div class="row align-center"><div class="col-lg-5 left-info"><div class="content"><h1>ERPSoft-WebbyCMS</h1><p>Business Management Portal to run finance, inventory, sales, HR, and projects in one place.</p><a class="btn circle btn-theme effect btn-md" href="/register">Get Started</a> <a class="btn circle btn-theme border btn-md" href="/login">Login</a></div></div><div class="col-lg-7 right-info"><img src="<?= htmlspecialchars(app_theme_asset('assets/img/app/app-4.png'), ENT_QUOTES, 'UTF-8') ?>" alt="ERP Dashboard"></div></div></div></div></div></div>
    <div id="features" class="features-area default-padding bottom-less">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 offset-lg-2">
                    <div class="site-heading text-center">
                        <h2 class="area-title">Daily operations, simplified</h2>
                        <div class="devider"></div>
                        <p>Short, focused tools for the work your teams do every day.</p>
                    </div>
                </div>
            </div>
            <div class="features-box text-center">
                <div class="row">
                    <div class="single-item col-lg-4 col-md-6"><div class="item"><div class="icon"><i class="flaticon-website"></i></div><h4>Unified Data</h4><p>All departments work from the same records and reports.</p></div></div>
                    <div class="single-item col-lg-4 col-md-6"><div class="item"><div class="icon"><i class="flaticon-report"></i></div><h4>Real-Time KPIs</h4><p>See cash flow, sales, and operations at a glance.</p></div></div>
                    <div class="single-item col-lg-4 col-md-6"><div class="item"><div class="icon"><i class="flaticon-resolution-1"></i></div><h4>Role Security</h4><p>Granular permissions with complete audit trails.</p></div></div>
                </div>
            </div>
        </div>
    </div>
    <div id="workflow" class="process-area default-padding-bottom">
        <div class="container">
            <div class="row align-center">
                <div class="col-lg-6 thumb"><img src="<?= htmlspecialchars(app_theme_asset('assets/img/illustration/7.png'), ENT_QUOTES, 'UTF-8') ?>" alt="Workflow"></div>
                <div class="col-lg-6 info ml-auto">
                    <h2 class="area-title">Three steps to go live</h2>
                    <ul>
                        <li><div class="icon"><i class="flaticon-presentation"></i><span>01</span></div><div class="info"><h4>Set roles</h4><p>Invite teams and define access in minutes.</p></div></li>
                        <li><div class="icon"><i class="flaticon-target"></i><span>02</span></div><div class="info"><h4>Capture work</h4><p>Log transactions, requests, and updates daily.</p></div></li>
                        <li><div class="icon"><i class="flaticon-report"></i><span>03</span></div><div class="info"><h4>Track results</h4><p>Use dashboards to keep leaders informed.</p></div></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <div id="modules" class="overview-area relative bg-gray default-padding-top">
        <div class="container">
            <div class="row align-center">
                <div class="col-lg-6 info">
                    <h2 class="area-title">Core modules</h2>
                    <p>Finance, inventory, sales, HR, and projects ready on day one.</p>
                    <ul>
                        <li>General ledger, invoicing, expenses</li>
                        <li>Stock, purchasing, vendors</li>
                        <li>Leads, orders, customer records</li>
                        <li>Payroll, leave, performance</li>
                    </ul>
                </div>
                <div class="col-lg-6 thumb"><img src="<?= htmlspecialchars(app_theme_asset('assets/img/dashboard/1.jpg'), ENT_QUOTES, 'UTF-8') ?>" alt="Modules"></div>
            </div>
        </div>
    </div>
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
                <div class="row"><div class="col-lg-12"><div class="form-group"><input class="form-control" name="otp" placeholder="MFA Code (if enabled)" type="text" inputmode="numeric" autocomplete="one-time-code"></div></div></div>
                <div class="row"><div class="col-lg-12"><button type="submit">Login</button></div></div>
            </form>
            <div class="sign-up"><p>Don't have an account? <a href="/register">Sign up now</a></p></div>
            <div class="sign-up"><p>Sign in with <a href="/sso-connect">WebbyCloud</a></p></div>
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
                <div class="row"><div class="col-lg-12"><div class="form-group"><input class="form-control" name="invite_code" placeholder="Invite Code (optional)" type="text"></div></div></div>
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

function app_render_not_found_softing(?string $reference = null): void
{
    $refText = $reference ? '<p>Reference: <code>' . htmlspecialchars($reference, ENT_QUOTES, 'UTF-8') . '</code></p>' : '';
    ?>
    <div class="login-area"><div class="container"><div class="row"><div class="col-lg-4 offset-lg-4"><div class="login-box"><div class="login"><div class="content"><h2>404 - Page Not Found</h2><p>The route does not exist.</p><?= $refText ?><a href="/" class="btn circle btn-theme effect btn-md">Back Home</a></div></div></div></div></div></div></div>
    <?php
}

function app_render_server_error_softing(?string $reference = null): void
{
    $refText = $reference ? '<p>Reference: <code>' . htmlspecialchars($reference, ENT_QUOTES, 'UTF-8') . '</code></p>' : '';
    ?>
    <div class="login-area"><div class="container"><div class="row"><div class="col-lg-4 offset-lg-4"><div class="login-box"><div class="login"><div class="content"><h2>500 - Server Error</h2><p>We hit a problem while processing your request.</p><?= $refText ?><a href="/" class="btn circle btn-theme effect btn-md">Back Home</a></div></div></div></div></div></div></div>
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
    <div class="banner-area text-combo top-pad-90 rectangular-shape bg-light-gradient"><div class="item"><div class="box-table"><div class="box-cell"><div class="container"><h1>ERPSoft-WebbyCMS</h1><p>Business Management Portal to unify finance, inventory, sales, HR, and projects.</p><a class="btn circle btn-theme effect btn-md" href="/register">Get Started</a> <a class="btn circle btn-theme border btn-md" href="/login">Login</a></div></div></div></div></div>
    <div id="features" class="features-area default-padding bottom-less">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 offset-lg-2">
                    <div class="site-heading text-center">
                        <h2 class="area-title">Built for operational clarity</h2>
                        <div class="devider"></div>
                        <p>Short, simple workflows that keep teams aligned.</p>
                    </div>
                </div>
            </div>
            <div class="features-box text-center">
                <div class="row">
                    <div class="single-item col-lg-4 col-md-6"><div class="item"><div class="icon"><i class="flaticon-website"></i></div><h4>Unified Records</h4><p>One place for every department's data.</p></div></div>
                    <div class="single-item col-lg-4 col-md-6"><div class="item"><div class="icon"><i class="flaticon-drag"></i></div><h4>Approval Flows</h4><p>Route requests with clear ownership.</p></div></div>
                    <div class="single-item col-lg-4 col-md-6"><div class="item"><div class="icon"><i class="flaticon-report"></i></div><h4>Live Dashboards</h4><p>See performance and cash flow instantly.</p></div></div>
                </div>
            </div>
        </div>
    </div>
    <div id="workflow" class="process-area default-padding-bottom">
        <div class="container">
            <div class="row align-center">
                <div class="col-lg-6 thumb"><img src="<?= htmlspecialchars(app_theme_asset('assets/img/illustration/7.png'), ENT_QUOTES, 'UTF-8') ?>" alt="Workflow"></div>
                <div class="col-lg-6 info ml-auto">
                    <h2 class="area-title">Go live in three steps</h2>
                    <ul>
                        <li><div class="icon"><i class="flaticon-presentation"></i><span>01</span></div><div class="info"><h4>Configure roles</h4><p>Assign permissions and departments fast.</p></div></li>
                        <li><div class="icon"><i class="flaticon-target"></i><span>02</span></div><div class="info"><h4>Run operations</h4><p>Capture orders, expenses, and updates daily.</p></div></li>
                        <li><div class="icon"><i class="flaticon-report"></i><span>03</span></div><div class="info"><h4>Review performance</h4><p>Use alerts and reports to make decisions.</p></div></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <div id="modules" class="overview-area relative bg-gray default-padding-top">
        <div class="container">
            <div class="row align-center">
                <div class="col-lg-6 info">
                    <h2 class="area-title">Core modules included</h2>
                    <p>Start with the essentials and expand as needed.</p>
                    <ul>
                        <li>Finance, invoices, expenses</li>
                        <li>Inventory, purchasing, vendors</li>
                        <li>Sales, customers, orders</li>
                        <li>HR, payroll, performance</li>
                    </ul>
                </div>
                <div class="col-lg-6 thumb"><img src="<?= htmlspecialchars(app_theme_asset('assets/img/app/app-4.png'), ENT_QUOTES, 'UTF-8') ?>" alt="Modules"></div>
            </div>
        </div>
    </div>
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
                <div class="row"><div class="col-lg-12 col-md-12"><div class="form-group"><i class="fas fa-key"></i> <input class="form-control" name="otp" placeholder="MFA Code (if enabled)" type="text" inputmode="numeric" autocomplete="one-time-code"></div></div></div>
                <div class="col-lg-12 col-md-12"><div class="row"><button type="submit">Login</button></div></div>
            </form>
            <div class="sign-up"><p>Don't have an account? <a href="/register">Sign up now</a></p></div>
            <div class="sign-up"><p>Sign in with <a href="/sso-connect">WebbyCloud</a></p></div>
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
                <div class="row"><div class="col-lg-12 col-md-12"><div class="form-group"><i class="fas fa-ticket-alt"></i> <input class="form-control" name="invite_code" placeholder="Invite Code (optional)" type="text"></div></div></div>
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

function app_render_not_found_anada(?string $reference = null): void
{
    $refText = $reference ? '<p>Reference: <code>' . htmlspecialchars($reference, ENT_QUOTES, 'UTF-8') . '</code></p>' : '';
    ?>
    <div class="login-area"><div class="container"><div class="row"><div class="col-lg-4 offset-lg-4"><div class="login-box"><div class="login"><div class="content"><h2>404 - Page Not Found</h2><p>The route does not exist.</p><?= $refText ?><a href="/" class="btn circle btn-theme effect btn-md">Back Home</a></div></div></div></div></div></div></div>
    <?php
}

function app_render_server_error_anada(?string $reference = null): void
{
    $refText = $reference ? '<p>Reference: <code>' . htmlspecialchars($reference, ENT_QUOTES, 'UTF-8') . '</code></p>' : '';
    ?>
    <div class="login-area"><div class="container"><div class="row"><div class="col-lg-4 offset-lg-4"><div class="login-box"><div class="login"><div class="content"><h2>500 - Server Error</h2><p>We hit a problem while processing your request.</p><?= $refText ?><a href="/" class="btn circle btn-theme effect btn-md">Back Home</a></div></div></div></div></div></div></div>
    <?php
}
