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
    
    // Get current page from query parameter
    $currentPage = $_GET['page'] ?? 'account';
    $validPages = ['account', 'category'];
    
    if (!in_array($currentPage, $validPages)) {
        $currentPage = 'account';
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
                        <a href="/settings?page=category" 
                           class="list-group-item list-group-item-action d-flex align-items-center <?= $currentPage === 'category' ? 'active' : '' ?>">
                            <i class="fas fa-tags me-3"></i>
                            <div>
                                <div class="fw-medium">Category Management</div>
                                <small class="text-muted">Organize messages and groups</small>
                            </div>
                        </a>
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
                        <?= $currentPage === 'account' ? 'Account Settings' : '' ?>
                    </h4>
                    <p class="text-muted mb-0">
                        <?= $currentPage === 'account' ? 'Manage your account information and preferences' : '' ?>
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
                                        <div class="mb-3">
                                            <label class="form-label">Name</label>
                                            <input type="text" class="form-control" value="<?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?>" readonly>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Email</label>
                                            <input type="email" class="form-control" value="<?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?>" readonly>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Role</label>
                                            <input type="text" class="form-control" value="<?= htmlspecialchars($user['role'], ENT_QUOTES, 'UTF-8') ?>" readonly>
                                        </div>
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
                    <?php else: ?>
                        <!-- Category Management Content -->
                        <div class="category-management">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <div>
                                    <h5 class="mb-0">Manage Categories</h5>
                                    <p class="text-muted mb-0">Create and organize hierarchical categories for messages and groups</p>
                                </div>
                                <button type="button" class="btn btn-primary" id="addCategoryBtn">
                                    <i class="fas fa-plus me-2"></i>Add Category
                                </button>
                            </div>
                            
                            <div id="category-management-container">
                                <!-- JavaScript will load content here -->
                                <div class="text-center py-5">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <p class="mt-2 text-muted">Loading categories...</p>
                                </div>
                            </div>
                        </div>
                        
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
                        
                        <!-- Include Category Management JavaScript -->
                        <script src="<?= htmlspecialchars(app_asset('js/category-management.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
                        
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
            SELECT wg.*, ws.session_name, ws.id as session_id
            FROM whatsapp_groups wg
            LEFT JOIN whatsapp_sessions ws ON wg.session_id = ws.id
            WHERE ws.user_id = ?
            ORDER BY ws.session_name ASC, wg.name ASC
        ");
        $stmt->execute([$user['id']]);
        $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get sessions with account info (like the /groups page does)
        require_once __DIR__ . '/whatsapp/sessions.php';
        $sessions = app_whatsapp_get_user_sessions($user['id']);
        
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
                                    // Count valid groups
                                    $validGroupCount = 0;
                                    if (!empty($groups)) {
                                        foreach ($groups as $group) {
                                            if (isset($group['name'], $group['session_id'], $group['session_name'], $group['group_id'])) {
                                                $sessionId = (int)$group['session_id'];
                                                if ($sessionId > 0) {
                                                    $validGroupCount++;
                                                }
                                            }
                                        }
                                    }
                                    echo $validGroupCount;
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
                                
                                // Count groups for this session
                                $sessionGroupCount = 0;
                                foreach ($groups as $group) {
                                    if (isset($group['session_id']) && $group['session_id'] == $sessionId) {
                                        $sessionGroupCount++;
                                    }
                                }
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
                     <div class="d-flex align-items-center">
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
                                             data-last-activity="<?= $lastActivity ?>">
                                            <div class="card folder-card h-100 border">
                                                <div class="card-body d-flex flex-column">
                                                    <div class="d-flex align-items-center mb-3">
                                                        <div class="flex-grow-1">
                                                            <h6 class="mb-0 text-truncate" title="<?= htmlspecialchars($group['name'], ENT_QUOTES, 'UTF-8') ?>">
                                                                <?= htmlspecialchars($group['name'], ENT_QUOTES, 'UTF-8') ?>
                                                            </h6>
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
                                                        <button class="btn btn-outline-primary btn-sm w-100 open-folder" 
                                                                data-group-id="<?= htmlspecialchars($group['group_id'], ENT_QUOTES, 'UTF-8') ?>"
                                                                data-group-name="<?= htmlspecialchars($group['name'], ENT_QUOTES, 'UTF-8') ?>">
                                                            <i class="fas fa-folder-open me-1"></i> Open
                                                        </button>
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
                                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                                <h6 class="mb-0">Messages & Files</h6>
                                                <div class="btn-group">
                                                    <button class="btn btn-sm btn-outline-secondary active" data-filter="all">All</button>
                                                    <button class="btn btn-sm btn-outline-secondary" data-filter="messages">Messages</button>
                                                    <button class="btn btn-sm btn-outline-secondary" data-filter="files">Files</button>
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
    }
    
    .folder-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        border-color: #007bff;
    }
    
    .folder-card .card-body {
        display: flex;
        flex-direction: column;
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
    
    <script>
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
        
        openFolderBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const groupId = this.getAttribute('data-group-id');
                const groupName = this.getAttribute('data-group-name');
                
                // Update UI
                currentFolderName.textContent = groupName;
                currentFolderInfo.textContent = `Group ID: ${groupId}`;
                
                // Switch views
                folderGridView.classList.add('d-none');
                folderContentView.classList.remove('d-none');
                
                // Load folder content via AJAX
                loadFolderContent(groupId);
            });
        });
        
        backToFoldersBtn.addEventListener('click', function() {
            folderContentView.classList.add('d-none');
            folderGridView.classList.remove('d-none');
        });
        
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
                const folderName = folder.querySelector('h6').textContent.toLowerCase();
                const sessionName = folder.querySelector('.session-badge').textContent.toLowerCase();
                
                // Check session filter
                const sessionMatch = sessionId === 'all' || folderSessionId === sessionId;
                
                // Check search filter
                const searchMatch = searchTerm === '' || 
                                   folderName.includes(searchTerm) || 
                                   sessionName.includes(searchTerm);
                
                // Show/hide folder based on both filters
                if (sessionMatch && searchMatch) {
                    folder.style.display = 'block';
                    visibleCount++;
                    
                    // Highlight search term in folder name
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
                    
                    // Restore original name if it was highlighted
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
            
            // Show/hide empty state
            const emptyState = document.querySelector('.empty-state');
            if (visibleCount === 0) {
                if (!emptyState) {
                    let emptyMessage = 'No groups found';
                    let emptyDescription = 'Select a different session or sync more groups.';
                    
                    if (searchTerm.length > 0) {
                        emptyMessage = 'No matching groups found';
                        emptyDescription = 'Try a different search term or select a different session.';
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
            
            // Load category tree via API
            fetch('/api/whatsapp/categories/tree')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data.categories) {
                        renderCategoryTree(data.data.categories);
                    } else {
                        categoryTree.innerHTML = `
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-exclamation-triangle text-warning fa-2x mb-2"></i>
                                <p>Failed to load categories</p>
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
                            <p>Error loading categories</p>
                            <button class="btn btn-sm btn-outline-primary" onclick="loadFolderContent('${groupId}')">
                                <i class="fas fa-redo me-1"></i> Retry
                            </button>
                        </div>
                    `;
                });
            
            // Get session ID from the folder element
            // Escape special CSS selector characters in groupId
            const escapedGroupId = groupId.replace(/[!"#$%&'()*+,.\/:;<=>?@[\\\]^`{|}~]/g, '\\$&');
            console.log('Looking for folder with groupId:', groupId, 'escaped:', escapedGroupId);
            const folderElement = document.querySelector(`.open-folder[data-group-id="${escapedGroupId}"]`);
            console.log('Found button:', folderElement);
            const sessionId = folderElement ? folderElement.closest('.folder-item').getAttribute('data-session-id') : null;
            console.log('Session ID found:', sessionId);
            
            if (!sessionId) {
                messagesList.innerHTML = `
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-exclamation-triangle text-danger fa-2x mb-2"></i>
                        <p>Unable to determine session for this group</p>
                    </div>
                `;
                return;
            }
            
            // Load messages and files for this group via API
            fetch(`/api/whatsapp/groups/${encodeURIComponent(groupId)}/messages?session_id=${encodeURIComponent(sessionId)}&limit=50`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data.messages) {
                        renderMessagesAndFiles(data.data.messages);
                    } else {
                        messagesList.innerHTML = `
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-exclamation-triangle text-warning fa-2x mb-2"></i>
                                <p>Failed to load messages</p>
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
                            <p>Error loading messages</p>
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
                     categoryTree.innerHTML = `
                         <div class="text-center text-muted py-4">
                             <i class="fas fa-tags fa-2x mb-2"></i>
                             <p>No categories yet</p>
                             <a href="/settings?page=category" class="btn btn-sm btn-outline-primary" id="add-category-btn">
                                 <i class="fas fa-plus me-1"></i> Add Category
                             </a>
                         </div>
                     `;
                     
                     // Update all message dropdowns with empty state
                     updateAllMessageDropdowns([]);
                     return;
                 }
                 
                 let html = '<div class="category-tree">';
                 html += renderCategoryTreeItems(categories);
                 html += '</div>';
                 html += `
                     <div class="mt-3">
                         <a href="/settings?page=category" class="btn btn-sm btn-outline-primary w-100" id="add-category-btn">
                             <i class="fas fa-plus me-1"></i> Add Category
                         </a>
                     </div>
                 `;
                 
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
                
                let html = '<div class="message-file-list">';
                
                // Get current filter
                const filter = document.querySelector('[data-filter].active')?.getAttribute('data-filter') || 'all';
                
                // Separate messages and files
                const textMessages = messages.filter(m => !m.message_type || m.message_type === 'text');
                const files = messages.filter(m => m.message_type && m.message_type !== 'text');
                
                // Apply filter
                if (filter === 'all' || filter === 'messages') {
                    textMessages.forEach(message => {
                        html += renderMessageItem(message);
                    });
                }
                
                if (filter === 'all' || filter === 'files') {
                    files.forEach(file => {
                        html += renderFileItem(file);
                    });
                }
                
                 html += '</div>';
                 messagesList.innerHTML = html;
                 
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
                
                let html = '<div class="file-item mb-3 p-3 border rounded" data-message-id="' + file.id + '">';
                html += '<div class="d-flex align-items-start">';
                html += '<div class="flex-shrink-0">';
                
                // File type icon
                let iconClass = 'fas fa-file';
                let iconColor = '#6c757d';
                
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
                        iconClass = 'fas fa-file-pdf';
                        iconColor = '#dc3545';
                        break;
                }
                
                html += `<i class="${iconClass} fa-lg" style="color: ${iconColor}"></i>`;
                html += '</div>';
                html += '<div class="flex-grow-1 ms-3">';
                html += '<div class="d-flex justify-content-between align-items-start">';
                
                // File name
                let fileName = 'File';
                if (file.media_caption) {
                    fileName = escapeHtml(file.media_caption);
                } else if (file.content) {
                    fileName = escapeHtml(file.content.substring(0, 50)) + '...';
                }
                
                html += '<div>';
                html += `<h6 class="mb-1">${fileName}</h6>`;
                html += `<small class="text-muted">${timeStr}</small>`;
                html += '</div>';
                
                 // Three dots dropdown menu for category assignment
                 html += '<div class="dropdown">';
                 html += '<button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">';
                 html += '<i class="fas fa-ellipsis-v"></i>';
                 html += '</button>';
                 html += '<ul class="dropdown-menu dropdown-menu-end">';
                 html += '<li><h6 class="dropdown-header">Assign to Category</h6></li>';
                 html += '<li><a class="dropdown-item no-category-item" href="#" data-message-id="' + file.id + '">No Category</a></li>';
                 html += '<li><hr class="dropdown-divider"></li>';
                 html += '<li><a class="dropdown-item category-loading-item" href="#" data-message-id="' + file.id + '">Loading categories...</a></li>';
                 html += '</ul>';
                 html += '</div>';
                
                html += '</div>';
                
                // Sender info
                html += `<p class="mb-1 text-muted">From: ${escapeHtml(file.sender_name || file.sender_number || 'Unknown')}</p>`;
                
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
