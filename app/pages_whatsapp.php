<?php

declare(strict_types=1);

// WhatsApp Integration Pages

function app_page_whatsapp_connect(): void {
    app_require_auth();
    $user = app_current_user();
    $effectiveUser = $user ? app_get_effective_user($user) : $user;
    $effectiveUserId = $effectiveUser['id'] ?? 0;
    
    app_render_head('WhatsApp Connect');
    app_render_dashboard_start($user);
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'create_session') {
            try {
                $result = app_whatsapp_create_session($user['id']);
                app_flash('success', 'WhatsApp session created. Scan the QR code to connect.');
            } catch (Exception $e) {
                app_flash('error', $e->getMessage());
            }
        } elseif ($action === 'delete_session') {
            $sessionId = (int) ($_POST['session_id'] ?? 0);
            if ($sessionId) {
                try {
                    app_whatsapp_delete_session($sessionId);
                    app_flash('success', 'Session deleted');
                } catch (Exception $e) {
                    app_flash('error', $e->getMessage());
                }
            }
        }
        
        app_redirect('/whatsapp-connect');
    }
    
    // Get user's sessions
    $sessions = app_whatsapp_get_user_sessions($user['id']);
    $canCreate = app_can_create_session($user);
    $maxSessions = app_get_session_limit($user);
    
    ?>
    <div class="container-fluid">
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-white">
                        <h4 class="mb-0">WhatsApp Connect</h4>
                        <p class="text-muted mb-0">Manage your WhatsApp sessions</p>
                    </div>
                    <div class="card-body">
                        <?php app_render_flash(); ?>
                        
                        <!-- Session Creation Form -->
                        <?php if ($canCreate): ?>
                            <div class="mb-4">
                                 <h5>Create New Session</h5>
                                 <div class="row g-3">
                                     <div class="col-12">
                                         <div class="d-grid">
                                             <button type="button" class="btn btn-primary btn-lg" id="createSessionBtn">
                                                 <i class="fab fa-whatsapp me-2"></i> Create New WhatsApp Session
                                             </button>
                                         </div>
                                         <div class="mt-2 text-muted small text-center">
                                             <i class="fas fa-info-circle me-1"></i>
                                             A unique session name will be automatically generated
                                         </div>
                                     </div>
                                 </div>
                                <div class="mt-2 text-muted small">
                                    <i class="fas fa-info-circle me-1"></i>
                                    You can create up to <?= $maxSessions ?> session(s) with your <?= ucfirst($user['tier']) ?> tier.
                                    Currently using: <?= count($sessions) ?>/<?= $maxSessions ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                You have reached the maximum number of sessions (<?= $maxSessions ?>) for your <?= ucfirst($user['tier']) ?> tier.
                                Please delete an existing session or upgrade your tier to create more.
                            </div>
                        <?php endif; ?>
                        
                        <!-- Sessions List -->
                        <div class="mt-4">
                            <h5>Your Sessions</h5>
                            <?php if (empty($sessions)): ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    No WhatsApp sessions yet. Create your first session above.
                                </div>
                            <?php else: ?>
                                <div class="row">
                                    <?php foreach ($sessions as $session): ?>
                                        <div class="col-md-6 col-lg-4 mb-3">
                                            <div class="card h-100">
                                                <div class="card-body">
                                                     <div class="d-flex justify-content-between align-items-start mb-2">
                                                        <h6 class="card-title mb-0">
                                                            <?php if ($session['status'] === 'active' && isset($session['account_info'])): ?>
                                                                <?= htmlspecialchars($session['account_info']['pushName'] ?? 'Unknown') ?>
                                                            <?php else: ?>
                                                                <?= htmlspecialchars($session['session_name']) ?>
                                                            <?php endif; ?>
                                                        </h6>
                                                        <span class="badge bg-<?= $session['status'] === 'active' ? 'success' : ($session['status'] === 'pending' ? 'warning' : 'secondary') ?>">
                                                            <?= ucfirst($session['status']) ?>
                                                        </span>
                                                    </div>
                                                    <p class="card-text small text-muted">
                                                        Created: <?= date('M d, Y', strtotime($session['created_at'])) ?>
                                                    </p>
                                                    
                                                    <?php if ($session['status'] === 'active' && isset($session['account_info'])): ?>
                                                        <div class="mt-2 p-2 bg-light rounded">
                                                            <div class="small fw-bold">Connected Account:</div>
                                                            <div class="small">
                                                                <i class="fas fa-user me-1"></i>
                                                                <?= htmlspecialchars($session['account_info']['pushName'] ?? 'Unknown') ?>
                                                            </div>
                                                            <div class="small text-muted">
                                                                <i class="fas fa-id-card me-1"></i>
                                                                <?= htmlspecialchars($session['account_info']['id'] ?? 'Unknown') ?>
                                                            </div>
                                                            <?php if (isset($session['account_info']['platform'])): ?>
                                                                <div class="small text-muted">
                                                                    <i class="fas fa-mobile-alt me-1"></i>
                                                                    <?= ucfirst(htmlspecialchars($session['account_info']['platform'])) ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php elseif ($session['status'] === 'active'): ?>
                                                        <div class="mt-2 p-2 bg-light rounded">
                                                            <div class="small text-muted">
                                                                <i class="fas fa-check-circle me-1 text-success"></i>
                                                                Connected to WhatsApp
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($session['status'] === 'pending' || $session['status'] === 'authenticating'): ?>
                                                        <div class="text-center my-3">
                                                            <div id="qr-container-<?= $session['id'] ?>" class="mb-2">
                                                                <!-- QR code will be loaded here -->
                                                            </div>
                                                             <button class="btn btn-sm btn-outline-primary refresh-qr" 
                                                                     data-session-id="<?= $session['id'] ?>"
                                                                     data-session-name="<?= htmlspecialchars($session['session_name']) ?>">
                                                                 <i class="fas fa-sync-alt me-1"></i> Refresh QR
                                                             </button>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                     <div class="d-flex justify-content-between mt-3">
                                                         <button type="button" class="btn btn-sm btn-danger delete-session-btn" 
                                                                 data-session-id="<?= $session['id'] ?>"
                                                                 data-session-name="<?= htmlspecialchars($session['session_name']) ?>">
                                                             <i class="fas fa-trash me-1"></i> Delete
                                                         </button>
                                                        <?php if ($session['status'] === 'active'): ?>
                                                            <a href="/groups?session=<?= urlencode($session['session_name']) ?>" class="btn btn-sm btn-success">
                                                                <i class="fas fa-users me-1"></i> View Groups
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- QR Code Loading Script -->
    <script>
    function loadQRCode(button) {
        const sessionId = button.dataset.sessionId;
        const qrContainer = document.getElementById(`qr-container-${sessionId}`);
        
        if (!qrContainer) return;
        
        // Show loading state
        const originalHTML = qrContainer.innerHTML;
        qrContainer.innerHTML = '<div class="text-center"><div class="spinner-border spinner-border-sm text-primary" role="status"><span class="visually-hidden">Loading...</span></div><p class="text-muted mt-2">Loading QR code...</p></div>';
        
        // Fetch QR code from API
        fetch(`/api/whatsapp/sessions/${sessionId}/qr`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success && data.data.qr_code) {
                    qrContainer.innerHTML = `<img src="${data.data.qr_code}" alt="WhatsApp QR Code" class="img-fluid" style="max-width: 200px;">`;
                } else {
                    qrContainer.innerHTML = '<div class="alert alert-warning">QR code not available. Session may be connecting or already connected.</div>';
                }
            })
            .catch(error => {
                console.error('Error loading QR code:', error);
                qrContainer.innerHTML = '<div class="alert alert-danger">Failed to load QR code. Please try again.</div>';
            });
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        // Load QR codes for pending sessions
        document.querySelectorAll('.refresh-qr').forEach(button => {
            loadQRCode(button);
            
            button.addEventListener('click', function() {
                loadQRCode(this);
            });
        });
        
        // Auto-refresh QR codes every 30 seconds for pending sessions
        setInterval(() => {
            document.querySelectorAll('.refresh-qr').forEach(button => {
                loadQRCode(button);
            });
        }, 30000);
         
         // Handle session deletion with SweetAlert
         document.querySelectorAll('.delete-session-btn').forEach(button => {
             button.addEventListener('click', function() {
                 const sessionId = this.dataset.sessionId;
                 const sessionName = this.dataset.sessionName;
                 
                 Swal.fire({
                     title: 'Delete Session?',
                     html: `Are you sure you want to delete session <strong>${sessionName}</strong>?<br><br>
                            <small class="text-muted">This will disconnect the WhatsApp account from this session.</small>`,
                     icon: 'warning',
                     showCancelButton: true,
                     confirmButtonColor: '#d33',
                     cancelButtonColor: '#3085d6',
                     confirmButtonText: 'Yes, delete it!',
                     cancelButtonText: 'Cancel',
                     reverseButtons: true,
                     showLoaderOnConfirm: true,
                     preConfirm: () => {
                         return fetch('/whatsapp-connect', {
                             method: 'POST',
                             headers: {
                                 'Content-Type': 'application/x-www-form-urlencoded',
                             },
                             body: `action=delete_session&session_id=${sessionId}`,
                             redirect: 'manual' // Don't automatically follow redirects
                         })
                         .then(response => {
                             // Check if it's a redirect (302, 303, 307, 308)
                             if (response.status >= 300 && response.status < 400) {
                                 // Get the redirect URL from the Location header
                                 const redirectUrl = response.headers.get('Location') || '/whatsapp-connect';
                                 window.location.href = redirectUrl;
                                 return new Promise(() => {}); // Never resolve to keep Swal open
                             } else if (response.ok) {
                                 // If not a redirect but successful, reload the page
                                 window.location.reload();
                                 return new Promise(() => {});
                             } else {
                                 // Handle error
                                 return response.text().then(text => {
                                     throw new Error(`Delete failed: ${response.status} ${response.statusText}`);
                                 });
                             }
                         })
                         .catch(error => {
                             Swal.showValidationMessage(`Request failed: ${error}`);
                         });
                     },
                     allowOutsideClick: () => !Swal.isLoading()
                 }).then((result) => {
                     if (result.isConfirmed) {
                         // Page will reload from the redirect
                     }
                 });
             });
         });
         
         // Handle session creation with SweetAlert
         const createSessionBtn = document.getElementById('createSessionBtn');
         if (createSessionBtn) {
             createSessionBtn.addEventListener('click', function() {
             const button = this;
             const originalText = button.innerHTML;
             
             Swal.fire({
                 title: 'Create New Session?',
                 html: 'This will create a new WhatsApp session.<br><br>' +
                       '<small class="text-muted">A unique session name will be automatically generated.</small>',
                 icon: 'question',
                 showCancelButton: true,
                 confirmButtonColor: '#3085d6',
                 cancelButtonColor: '#d33',
                 confirmButtonText: 'Yes, create it!',
                 cancelButtonText: 'Cancel',
                 reverseButtons: true,
                 showLoaderOnConfirm: true,
                 preConfirm: () => {
                     return fetch('/whatsapp-connect', {
                         method: 'POST',
                         headers: {
                             'Content-Type': 'application/x-www-form-urlencoded',
                         },
                         body: 'action=create_session',
                         redirect: 'manual' // Don't automatically follow redirects
                     })
                     .then(response => {
                         // Check if it's a redirect (302, 303, 307, 308)
                         if (response.status >= 300 && response.status < 400) {
                             // Get the redirect URL from the Location header
                             const redirectUrl = response.headers.get('Location') || '/whatsapp-connect';
                             window.location.href = redirectUrl;
                             return new Promise(() => {}); // Never resolve to keep Swal open
                         } else if (response.ok) {
                             // If not a redirect but successful, reload the page
                             window.location.reload();
                             return new Promise(() => {});
                         } else {
                             // Handle error
                             return response.text().then(text => {
                                 throw new Error(`Create session failed: ${response.status} ${response.statusText}`);
                             });
                         }
                     })
                     .catch(error => {
                         Swal.showValidationMessage(`Request failed: ${error}`);
                     });
                 },
                 allowOutsideClick: () => !Swal.isLoading()
             }).then((result) => {
                 if (result.isConfirmed) {
                     // Page will reload from the redirect
                 } else {
                     // Reset button if cancelled
                     button.innerHTML = originalText;
                 }
             });
             });
         }
     });
     </script>
    <?php
    
    app_render_dashboard_end();
    app_render_footer();
}

function app_page_groups(): void {
    app_require_auth();
    $user = app_current_user();
    $effectiveUser = $user ? app_get_effective_user($user) : $user;
    $effectiveUserId = $effectiveUser['id'] ?? 0;
    
    app_render_head('WhatsApp Groups');
    app_render_dashboard_start($user);
    
    // Get user's WhatsApp sessions and groups
    $sessions = app_whatsapp_get_user_sessions($effectiveUserId);
    $groups = app_whatsapp_get_user_groups($effectiveUserId);
    
    // Determine selected session and group from query params
    $selectedSessionName = $_GET['session'] ?? '';
    $selectedGroupId = (int) ($_GET['group'] ?? 0);
    
    // Find session by name
    $selectedSession = null;
    $selectedSessionId = 0;
    if ($selectedSessionName) {
        foreach ($sessions as $session) {
            if ($session['session_name'] === $selectedSessionName) {
                $selectedSession = $session;
                $selectedSessionId = $session['id'];
                break;
            }
        }
    }
    
    // If session is selected, filter groups by that session
    $filteredGroups = $groups;
    if ($selectedSessionId > 0) {
        $filteredGroups = array_filter($groups, function($group) use ($selectedSessionId) {
            return $group['session_id'] == $selectedSessionId;
        });
    }
    
    // Get selected group (with security check - must belong to user)
    $selectedGroup = null;
    $messages = [];
    if ($selectedGroupId > 0) {
        foreach ($groups as $group) {
            if ($group['id'] == $selectedGroupId) {
                $selectedGroup = $group;
                $messages = app_whatsapp_get_group_messages($selectedGroup['session_id'], $selectedGroup['group_id'], 50);
                break;
            }
        }
    }
    
    ?>
     <div class="container-fluid">
         <style>
             .message-bubble {
                 border-radius: 18px;
                 line-height: 1.4;
             }
             .message-bubble.sent {
                 border-bottom-right-radius: 4px;
             }
             .message-bubble.received {
                 border-bottom-left-radius: 4px;
             }
             .avatar-xs {
                 width: 28px;
                 height: 28px;
                 font-size: 12px;
             }
              #messagesContainer {
                  scroll-behavior: smooth;
                  min-height: 0; /* Important for flexbox scrolling */
                  flex: 1 1 auto;
                  height: 0; /* Force flexbox to calculate height properly */
                  position: relative; /* Ensure proper scrolling context */
              }
              #messagesList {
                  min-height: 100%; /* Ensure it takes at least full height */
              }
             #messagesContainer::-webkit-scrollbar {
                 width: 6px;
             }
             #messagesContainer::-webkit-scrollbar-track {
                 background: #f1f1f1;
             }
              #messagesContainer::-webkit-scrollbar-thumb {
                  background: #c1c1c1;
                  border-radius: 3px;
              }
              .input-group.disabled {
                  opacity: 0.6;
                  pointer-events: none;
              }
              .input-group.disabled .form-control,
              .input-group.disabled .btn {
                  background-color: #f8f9fa;
                  border-color: #dee2e6;
              }
              .temp-message .message-bubble {
                  opacity: 0.8;
              }
              .temp-message .fa-clock {
                  font-size: 0.8em;
              }
         </style>
         <div class="row">
             <!-- Left Panel: Group List -->
             <div class="col-md-4 col-lg-3 border-end" style="height: calc(100vh - 120px); overflow-y: auto;">
                 <div class="d-flex justify-content-between align-items-center mb-2">
                     <h6 class="mb-0 fw-bold">Groups</h6>
                     <div>
                         <?php if ($selectedSession && $selectedSession['status'] === 'active'): ?>
                              <button class="btn btn-sm btn-outline-secondary btn-sm me-1" id="syncGroupsBtn" 
                                      data-session-id="<?= $selectedSessionId ?>"
                                      data-session-name="<?= htmlspecialchars($selectedSession['session_name'] ?? '') ?>"
                                      title="Sync groups from WhatsApp">
                                  <i class="fas fa-sync-alt"></i>
                              </button>
                         <?php endif; ?>
                          <?php if (app_can_create_group($effectiveUser)): ?>
                             <button class="btn btn-sm btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createGroupModal" title="Create new group">
                                 <i class="fas fa-plus"></i>
                             </button>
                         <?php endif; ?>
                     </div>
                 </div>
                
                <!-- Session Selector -->
                <div class="mb-3">
                    <select class="form-select form-select-sm" id="sessionFilter">
                        <option value="all" <?= !$selectedSessionName ? 'selected' : '' ?>>All Sessions</option>
                        <?php foreach ($sessions as $session): ?>
                            <option value="<?= htmlspecialchars($session['session_name']) ?>" <?= $selectedSessionName === $session['session_name'] ? 'selected' : '' ?>>
                                <?php if ($session['status'] === 'active' && isset($session['account_info'])): ?>
                                    <?= htmlspecialchars($session['account_info']['pushName'] ?? 'Unknown') ?> (<?= htmlspecialchars($session['account_info']['id'] ?? 'Unknown') ?>)
                                <?php else: ?>
                                    <?= htmlspecialchars($session['session_name']) ?> 
                                <?php endif; ?>
                                <span class="badge bg-<?= $session['status'] === 'active' ? 'success' : 'warning' ?>">
                                    <?= $session['status'] ?>
                                </span>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Group List -->
                <div class="list-group" id="groupList">
                    <?php if (empty($filteredGroups)): ?>
                         <div class="list-group-item text-center text-muted py-3">
                             <i class="fas fa-users fa-lg mb-2"></i>
                             <div class="small fw-bold mb-1">No groups found</div>
                             <small>
                                 <?php if ($selectedSessionId > 0 && $selectedSession && $selectedSession['status'] === 'active'): ?>
                                     No WhatsApp groups found for this session.
                                     <div class="mt-1">
                                          <button class="btn btn-sm btn-outline-primary btn-sm" id="syncGroupsInline" 
                                                  data-session-id="<?= $selectedSessionId ?>"
                                                  data-session-name="<?= htmlspecialchars($selectedSession['session_name'] ?? '') ?>">
                                              <i class="fas fa-sync-alt me-1"></i> Sync Groups
                                          </button>
                                     </div>
                                 <?php elseif ($selectedSessionId > 0): ?>
                                     No WhatsApp groups found for this session.
                                 <?php else: ?>
                                     Select a session to view or sync groups.
                                 <?php endif; ?>
                             </small>
                         </div>
                    <?php else: ?>
                         <?php foreach ($filteredGroups as $group): ?>
                             <a href="/groups?session=<?= urlencode($group['whatsapp_session_name'] ?? '') ?>&group=<?= $group['id'] ?>" 
                                class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2
                                       <?= $selectedGroupId === $group['id'] ? 'active' : '' ?>"
                                data-group-id="<?= $group['id'] ?>"
                                data-session-id="<?= $group['session_id'] ?>"
                                data-session-name="<?= htmlspecialchars($group['whatsapp_session_name'] ?? '') ?>">
                                 <div class="d-flex align-items-center">
                                     <div class="me-2">
                                         <i class="fas fa-users"></i>
                                     </div>
                                     <div class="text-truncate" style="max-width: 180px;">
                                         <div class="fw-bold small text-truncate"><?= htmlspecialchars($group['name']) ?></div>
                                         <small class="text-muted">
                                             <?= $group['participant_count'] ?> members
                                             <?php if ($group['unread_count'] > 0): ?>
                                                 <span class="badge bg-danger ms-1"><?= $group['unread_count'] ?></span>
                                             <?php endif; ?>
                                         </small>
                                     </div>
                                 </div>
                                 <small class="text-muted text-truncate" style="max-width: 80px;">
                                     <?= $group['last_message_preview'] ? substr($group['last_message_preview'], 0, 15) . '...' : '' ?>
                                 </small>
                             </a>
                         <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Right Panel: Chat Area -->
            <div class="col-md-8 col-lg-9" style="height: calc(100vh - 120px); display: flex; flex-direction: column;">
                <?php if ($selectedGroup): ?>
                     <!-- Chat Header -->
                     <div class="border-bottom py-2 px-3 bg-light">
                         <div class="d-flex justify-content-between align-items-center">
                             <div>
                                 <h6 class="mb-0 fw-bold"><?= htmlspecialchars($selectedGroup['name']) ?></h6>
                                 <small class="text-muted"><?= $selectedGroup['participant_count'] ?> members</small>
                             </div>
                             <div>
                                 <button class="btn btn-sm btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#groupInfoModal" title="Group info">
                                     <i class="fas fa-info-circle"></i>
                                 </button>
                             </div>
                         </div>
                     </div>
                    
                     <!-- Messages Area -->
                     <div class="flex-grow-1 p-3" style="overflow-y: auto;" id="messagesContainer">
                         <div id="messagesList">
                             <?php foreach (array_reverse($messages) as $message): ?>
                                 <div class="message mb-2 <?= $message['is_from_me'] ? 'text-end' : '' ?>">
                                     <div class="d-flex <?= $message['is_from_me'] ? 'justify-content-end' : '' ?> align-items-end">
                                         <?php if (!$message['is_from_me']): ?>
                                             <div class="me-2">
                                                 <div class="avatar-xs bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; font-size: 12px;">
                                                     <?= substr($message['sender_name'] ?? $message['sender_number'], 0, 1) ?>
                                                 </div>
                                             </div>
                                         <?php endif; ?>
                                         <div class="<?= $message['is_from_me'] ? 'bg-primary text-white message-bubble sent' : 'bg-light message-bubble received' ?> px-3 py-2" style="max-width: 65%;">
                                             <?php if (!$message['is_from_me']): ?>
                                                 <div class="small fw-bold mb-1"><?= htmlspecialchars($message['sender_name'] ?? $message['sender_number']) ?></div>
                                             <?php endif; ?>
                                              <?php if ($message['message_type'] === 'sticker'): ?>
                                                  <?php
                                                      $stickerUrl = $message['media_url'] ?: (!empty($message['content']) ? 'data:image/jpeg;base64,' . $message['content'] : '');
                                                  ?>
                                                  <?php if (!empty($stickerUrl)): ?>
                                                       <div class="mt-1">
                                                           <img src="<?= htmlspecialchars($stickerUrl) ?>" 
                                                                class="img-fluid rounded message-image" 
                                                                style="max-height: 120px; cursor: pointer;"
                                                                data-image-url="<?= htmlspecialchars($stickerUrl) ?>"
                                                                alt="Sticker"
                                                                onclick="enlargeImage(this)">
                                                           <div class="small mt-1 text-muted">Sticker</div>
                                                       </div>
                                                  <?php endif; ?>
                                              <?php elseif ($message['message_type'] === 'image'): ?>
                                                  <?php
                                                      $imageUrl = $message['media_url'] ?: (!empty($message['content']) ? 'data:image/jpeg;base64,' . $message['content'] : '');
                                                  ?>
                                                  <?php if (!empty($imageUrl)): ?>
                                                       <div class="mt-1">
                                                           <img src="<?= htmlspecialchars($imageUrl) ?>" 
                                                                class="img-fluid rounded message-image" 
                                                                style="max-height: 150px; cursor: pointer;"
                                                                data-image-url="<?= htmlspecialchars($imageUrl) ?>"
                                                                alt="Image"
                                                                onclick="enlargeImage(this)">
                                                           <?php if (!empty($message['media_caption']) || !empty($message['caption'])): ?>
                                                               <div class="small mt-1"><?= htmlspecialchars($message['media_caption'] ?? $message['caption'] ?? '') ?></div>
                                                           <?php endif; ?>
                                                       </div>
                                                  <?php endif; ?>
                                               <?php elseif ($message['message_type'] === 'audio'): ?>
                                                   <?php
                                                       $audioUrl = $message['media_url'] ?? '';
                                                       $audioTranscript = $message['ai_describe'] ?? '';
                                                   ?>
                                                   <div class="mt-1">
                                                       <?php if (!empty($audioUrl)): ?>
                                                           <audio controls preload="metadata" style="width: 100%; max-width: 320px;">
                                                               <source src="<?= htmlspecialchars($audioUrl) ?>">
                                                               Your browser does not support the audio element.
                                                           </audio>
                                                       <?php endif; ?>
                                                       <?php if (!empty($audioTranscript)): ?>
                                                           <div class="small mt-1 <?= $message['is_from_me'] ? 'text-white-50' : 'text-muted' ?>">
                                                               <strong>Transcribed:</strong><br>
                                                               <?= htmlspecialchars($audioTranscript) ?>
                                                           </div>
                                                       <?php endif; ?>
                                                   </div>
                                               <?php elseif ($message['message_type'] === 'document' && !empty($message['media_url'])): ?>
                                                  <?php
                                                      $docPath = parse_url($message['media_url'], PHP_URL_PATH);
                                                      $docExt = strtolower(pathinfo($docPath ?? '', PATHINFO_EXTENSION));
                                                      $isPdf = $docExt === 'pdf';
                                                      $docCaption = $message['media_caption'] ?? $message['caption'] ?? '';
                                                  ?>
                                                   <div class="mt-1">
                                                       <?php if ($isPdf): ?>
                                                           <button class="btn btn-sm btn-outline-secondary pdf-preview-trigger" type="button" data-file-url="<?= htmlspecialchars($message['media_url']) ?>">
                                                               <i class="fas fa-file-pdf me-1"></i>View PDF
                                                           </button>
                                                       <?php else: ?>
                                                           <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars($message['media_url']) ?>" target="_blank" rel="noopener">
                                                               <i class="fas fa-download me-1"></i>Open document
                                                           </a>
                                                       <?php endif; ?>
                                                       <div class="small mt-1">&nbsp;</div>
                                                   </div>
                                               <?php elseif ($message['media_url']): ?>
                                                   <div class="mt-1">
                                                       <img src="<?= htmlspecialchars($message['media_url']) ?>" 
                                                            class="img-fluid rounded" 
                                                            style="max-height: 150px;">
                                                       <?php if ($message['media_caption']): ?>
                                                           <div class="small mt-1"><?= htmlspecialchars($message['media_caption']) ?></div>
                                                       <?php endif; ?>
                                                   </div>
                                              <?php else: ?>
                                                  <div class="mb-1"><?= htmlspecialchars($message['content']) ?></div>
                                              <?php endif; ?>
                                             <div class="small text-end <?= $message['is_from_me'] ? 'text-white-50' : 'text-muted' ?>">
                                                 <?= date('H:i', $message['timestamp'] / 1000) ?>
                                             </div>
                                         </div>
                                     </div>
                                 </div>
                             <?php endforeach; ?>
                         </div>
                     </div>
                    
                     <!-- Message Input -->
                     <div class="border-top p-2">
                         <form id="messageForm" data-group-id="<?= $selectedGroupId ?>">
                             <div class="input-group input-group-sm">
                                 <input type="text" class="form-control" id="messageInput" 
                                        placeholder="Type a message..." autocomplete="off">
                                 <button class="btn btn-outline-secondary btn-sm" type="button" id="attachMedia" title="Attach media">
                                     <i class="fas fa-paperclip"></i>
                                 </button>
                                 <button class="btn btn-primary btn-sm" type="submit" title="Send message">
                                     <i class="fas fa-paper-plane"></i>
                                 </button>
                             </div>
                             <div id="mediaPreview" class="mt-1 d-none">
                                 <div class="d-flex align-items-center">
                                     <img id="previewImage" src="" class="img-thumbnail me-1" style="max-height: 40px;">
                                     <button type="button" class="btn btn-sm btn-danger btn-sm" id="removeMedia">
                                         <i class="fas fa-times"></i>
                                     </button>
                                 </div>
                             </div>
                         </form>
                     </div>
                <?php else: ?>
                     <!-- Empty State -->
                     <div class="d-flex flex-column align-items-center justify-content-center h-100 text-muted p-4">
                         <i class="fas fa-comments fa-3x mb-3 opacity-50"></i>
                         <h5 class="fw-light mb-2">Select a group to start chatting</h5>
                         <p class="small">Choose a group from the list on the left</p>
                     </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Create Group Modal -->
    <div class="modal fade" id="createGroupModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Group</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="createGroupForm">
                        <div class="mb-3">
                            <label class="form-label">Session</label>
                            <select class="form-select" name="session_id" required>
                                <option value="">Select a session</option>
                                <?php foreach ($sessions as $session): ?>
                                    <?php if ($session['status'] === 'active'): ?>
                                        <option value="<?= $session['id'] ?>">
                                            <?php if (isset($session['account_info'])): ?>
                                                <?= htmlspecialchars($session['account_info']['pushName'] ?? 'Unknown') ?> (<?= htmlspecialchars($session['account_info']['id'] ?? 'Unknown') ?>)
                                            <?php else: ?>
                                                <?= htmlspecialchars($session['session_name']) ?>
                                            <?php endif; ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Group Name</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Participants (optional)</label>
                            <textarea class="form-control" name="participants" rows="3" 
                                      placeholder="Enter phone numbers separated by commas"></textarea>
                            <div class="form-text">Include country code (e.g., +1234567890)</div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="createGroupBtn">Create Group</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Real-time Script -->
    <script src="<?= htmlspecialchars(app_asset('js/realtime.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Session filter functionality
        const sessionFilter = document.getElementById('sessionFilter');
        if (sessionFilter) {
            // Set selected session in filter
            const urlParams = new URLSearchParams(window.location.search);
            const selectedSessionName = urlParams.get('session');
            if (selectedSessionName) {
                sessionFilter.value = selectedSessionName;
            }
            
            // Handle filter change
            sessionFilter.addEventListener('change', function() {
                const sessionName = this.value;
                const url = new URL(window.location);
                
                if (sessionName === 'all') {
                    url.searchParams.delete('session');
                    // Also remove group if filtering by session
                    url.searchParams.delete('group');
                } else {
                    url.searchParams.set('session', sessionName);
                    // Keep group if it belongs to this session
                    const currentGroupId = url.searchParams.get('group');
                    if (currentGroupId) {
                        const groupElement = document.querySelector(`[data-group-id="${currentGroupId}"]`);
                        if (groupElement && groupElement.dataset.sessionName !== sessionName) {
                            // Group doesn't belong to selected session, remove it
                            url.searchParams.delete('group');
                        }
                    }
                }
                
                window.location.href = url.toString();
            });
        }
        
        // Sync groups functionality
        function setupSyncButton(button) {
            if (!button) return;
            
            button.addEventListener('click', async function() {
                const sessionId = this.dataset.sessionId;
                const sessionName = this.dataset.sessionName;
                const originalText = this.innerHTML;
                const originalTitle = this.title || '';
                const isInline = this.id === 'syncGroupsInline';
                
                // Show loading state
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Syncing...';
                if (!isInline) this.disabled = true;
                this.title = 'Syncing groups from WhatsApp...';
                
                try {
                    const response = await fetch(`/api/whatsapp/sessions/${sessionName}/sync`, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'}
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        // Show success message
                        this.innerHTML = '<i class="fas fa-check"></i> Synced!';
                        if (!isInline) {
                            this.classList.remove('btn-outline-secondary');
                            this.classList.add('btn-success');
                        } else {
                            this.classList.remove('btn-outline-primary');
                            this.classList.add('btn-success');
                        }
                        
                        // Reload page after 1 second to show updated groups
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        // Show error
                        this.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Failed';
                        if (!isInline) {
                            this.classList.remove('btn-outline-secondary');
                            this.classList.add('btn-danger');
                        } else {
                            this.classList.remove('btn-outline-primary');
                            this.classList.add('btn-danger');
                        }
                         Swal.fire({
                             icon: 'error',
                             title: 'Sync Failed',
                             text: 'Failed to sync groups: ' + (result.message || 'Unknown error'),
                             confirmButtonColor: '#3085d6',
                         });
                        
                        // Reset button after 3 seconds
                        setTimeout(() => {
                            this.innerHTML = originalText;
                            if (!isInline) {
                                this.classList.remove('btn-danger');
                                this.classList.add('btn-outline-secondary');
                                this.disabled = false;
                            } else {
                                this.classList.remove('btn-danger');
                                this.classList.add('btn-outline-primary');
                            }
                            this.title = originalTitle;
                        }, 3000);
                    }
                } catch (error) {
                    // Show error
                    this.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Error';
                    if (!isInline) {
                        this.classList.remove('btn-outline-secondary');
                        this.classList.add('btn-danger');
                    } else {
                        this.classList.remove('btn-outline-primary');
                        this.classList.add('btn-danger');
                    }
                     Swal.fire({
                         icon: 'error',
                         title: 'Sync Error',
                         text: 'Error syncing groups: ' + error.message,
                         confirmButtonColor: '#3085d6',
                     });
                    
                    // Reset button after 3 seconds
                    setTimeout(() => {
                        this.innerHTML = originalText;
                        if (!isInline) {
                            this.classList.remove('btn-danger');
                            this.classList.add('btn-outline-secondary');
                            this.disabled = false;
                        } else {
                            this.classList.remove('btn-danger');
                            this.classList.add('btn-outline-primary');
                        }
                        this.title = originalTitle;
                    }, 3000);
                }
            });
        }
        
        // Setup sync buttons
        setupSyncButton(document.getElementById('syncGroupsBtn'));
        setupSyncButton(document.getElementById('syncGroupsInline'));
        
         const messageFormElement = document.querySelector('#messageForm');
         const selectedGroupId = messageFormElement ? messageFormElement.dataset.groupId : null;
         const groupNameElement = document.querySelector('h6.fw-bold');
         
         // Debug: Log group selection
         console.log('Group selected:', {
             groupId: selectedGroupId,
             groupName: groupNameElement ? groupNameElement.textContent : '',
             hasMessagesContainer: !!document.getElementById('messagesList'),
             messageCount: document.querySelectorAll('#messagesList .message').length
         });
         
          if (selectedGroupId) {
              // Initialize real-time client only if RealtimeClient is available
              if (typeof RealtimeClient !== 'undefined') {
                  const realtime = new RealtimeClient(<?= $user['id'] ?>);
                  
                   // Handle new messages
                    realtime.on('new_message', function(data) {
                        console.log('New message received:', data);
                        if (data.group_id == selectedGroupId) {
                            addMessageToChat(data); // Will scroll by default (shouldScroll = true)
                            updateUnreadCount(data.group_id);
                        }
                    });
                    
                    // Handle message sent confirmation
                    realtime.on('message_sent', function(data) {
                        console.log('Message sent confirmation:', data);
                        if (data.group_id == selectedGroupId) {
                            // Update UI to show message was sent
                            const tempMsg = document.querySelector(`#message-${data.id}`);
                            if (tempMsg) {
                                tempMsg.classList.remove('temp-message');
                                const clockIcon = tempMsg.querySelector('.fa-clock');
                                if (clockIcon) {
                                    clockIcon.remove();
                                }
                            }
                            // Release grey-out state
                            setMessageSendingState(false);
                        }
                    });
                    
                    realtime.startPolling();
               } else {
                console.warn('RealtimeClient not available. Real-time updates disabled.');
              }
              
              // Message form submission
            document.getElementById('messageForm').addEventListener('submit', async function(e) {
                e.preventDefault();
                const input = document.getElementById('messageInput');
                const message = input.value.trim();
                
                if (message) {
                    // Append message immediately to chat bubble
                    const tempMessageId = 'temp_' + Date.now();
                    appendSentMessageToChat({
                        id: tempMessageId,
                        content: message,
                        is_from_me: true,
                        sender_name: 'You',
                        timestamp: Date.now(),
                        is_temp: true
                    });
                    
                    // Clear input
                    input.value = '';
                    
                    // Grey out input, attachment, and send button
                    setMessageSendingState(true);
                    
                    try {
                        const success = await sendMessage(selectedGroupId, message, tempMessageId);
                        if (!success) {
                            // If send failed, remove the temporary message and release grey-out state
                            removeTempMessage(tempMessageId);
                            setMessageSendingState(false);
                        }
                    } catch (error) {
                        console.error('Failed to send message:', error);
                        removeTempMessage(tempMessageId);
                        setMessageSendingState(false);
                    }
                }
            });
            
             // Load existing messages
              let isLoadingMore = false;
              let hasMoreMessages = true;
              let oldestMessageTimestamp = null;
              
              async function loadMessages(loadMore = false) {
                  if (isLoadingMore) return;
                  
                  try {
                      isLoadingMore = true;
                      console.log('Loading messages for group:', selectedGroupId, loadMore ? '(loading more)' : '');
                      
                      let url = `/api/whatsapp/groups/${selectedGroupId}/messages?limit=50`;
                      if (loadMore && oldestMessageTimestamp) {
                          url += `&before=${oldestMessageTimestamp}`;
                      }
                      
                      const response = await fetch(url);
                      
                      if (!response.ok) {
                          console.error('API error:', response.status, response.statusText);
                          throw new Error(`API error: ${response.status}`);
                      }
                      
                      const result = await response.json();
                      
                      console.log('Messages API response:', result);
                      
                      if (result.success && result.messages && result.messages.length > 0) {
                          const messagesList = document.getElementById('messagesList');
                          if (messagesList) {
                              if (!loadMore) {
                                  // Clear existing messages (from server-side render) when loading fresh
                                  messagesList.innerHTML = '';
                                  hasMoreMessages = true;
                                  oldestMessageTimestamp = null;
                              }
                              
                              // Add messages in chronological order WITHOUT scrolling for each one
                              result.messages.forEach(message => {
                                  addMessageToChat(message, false); // false = don't scroll for each message
                              });
                              
                              // Update oldest message timestamp for pagination
                              if (result.messages.length > 0) {
                                  const oldestMessage = result.messages.reduce((oldest, current) => 
                                      current.timestamp < oldest.timestamp ? current : oldest
                                  );
                                  oldestMessageTimestamp = oldestMessage.timestamp;
                              }
                              
                              // Update hasMoreMessages flag
                              hasMoreMessages = result.has_more === true;
                              
                              if (!loadMore) {
                                  // Scroll to bottom after ALL messages are loaded (only for initial load)
                                   setTimeout(forceScrollToBottom, 50);
                              }
                              
                              console.log(`Loaded ${result.messages.length} messages`, loadMore ? '(more)' : '');
                              
                              // Show/hide load more button
                              updateLoadMoreButton();
                          }
                      } else {
                          console.log('No messages found or API error:', result.message || 'No messages');
                          
                          if (!loadMore) {
                              // Show informative message only for initial load
                              const messagesList = document.getElementById('messagesList');
                              if (messagesList && messagesList.children.length === 0) {
                                  messagesList.innerHTML = `
                                      <div class="text-center text-muted py-4">
                                          <i class="fas fa-comment-slash fa-2x mb-3"></i>
                                          <div class="fw-bold mb-2">No messages yet</div>
                                          <small class="d-block mb-3">Messages will appear here when received via WhatsApp webhooks</small>
                                          <div class="small text-muted">
                                              <div class="mb-1"><i class="fas fa-info-circle me-1"></i> Send a message to start the conversation</div>
                                              <div><i class="fas fa-bell me-1"></i> New messages will appear automatically</div>
                                          </div>
                                      </div>
                                  `;
                              }
                          }
                          
                          hasMoreMessages = false;
                          updateLoadMoreButton();
                      }
                  } catch (error) {
                      console.error('Error loading messages:', error);
                  } finally {
                      isLoadingMore = false;
                  }
              }
              
              function updateLoadMoreButton() {
                  let loadMoreBtn = document.getElementById('loadMoreBtn');
                  
                  if (!loadMoreBtn) {
                      // Create load more button if it doesn't exist
                      const messagesList = document.getElementById('messagesList');
                      if (messagesList) {
                          loadMoreBtn = document.createElement('div');
                          loadMoreBtn.id = 'loadMoreBtn';
                          loadMoreBtn.className = 'text-center py-3';
                          loadMoreBtn.innerHTML = `
                              <button class="btn btn-outline-secondary btn-sm" onclick="loadMoreMessages()">
                                  <i class="fas fa-history me-1"></i> Load older messages
                              </button>
                          `;
                          messagesList.insertBefore(loadMoreBtn, messagesList.firstChild);
                      }
                  }
                  
                  if (loadMoreBtn) {
                      loadMoreBtn.style.display = hasMoreMessages ? 'block' : 'none';
                  }
              }
              
              // Global function for button click
              window.loadMoreMessages = function() {
                  if (hasMoreMessages && !isLoadingMore) {
                      loadMessages(true);
                  }
              };
              
              // Auto-load older messages when scrolling to top
              function setupAutoLoadOnScroll() {
                  const messagesContainer = document.getElementById('messagesContainer');
                  if (!messagesContainer) return;
                  
                  let isAutoLoading = false;
                  
                  messagesContainer.addEventListener('scroll', function() {
                      // If scrolled near top (within 100px) and has more messages
                      if (this.scrollTop < 100 && hasMoreMessages && !isLoadingMore && !isAutoLoading) {
                          isAutoLoading = true;
                          
                          // Store current scroll height before loading
                          const scrollHeightBefore = this.scrollHeight;
                          const scrollTopBefore = this.scrollTop;
                          
                          loadMessages(true).then(() => {
                              // After loading, adjust scroll position to maintain user's view
                              setTimeout(() => {
                                  const scrollHeightAfter = this.scrollHeight;
                                  const heightDifference = scrollHeightAfter - scrollHeightBefore;
                                  this.scrollTop = scrollTopBefore + heightDifference;
                                  isAutoLoading = false;
                              }, 100);
                          });
                      }
                  });
              }
              
              // Initialize scroll listener after DOM is ready
              if (document.readyState === 'loading') {
                  document.addEventListener('DOMContentLoaded', setupAutoLoadOnScroll);
              } else {
                  setTimeout(setupAutoLoadOnScroll, 100);
              }
             
             // Load messages on page load
             loadMessages();
             

              
               // Simple reliable scroll to bottom
               function forceScrollToBottom() {
                   const container = document.getElementById('messagesContainer');
                   if (!container) {
                       console.error('messagesContainer not found');
                       return;
                   }
                   
                   // Debug: Log container dimensions
                   console.log('Container dimensions:', {
                       offsetHeight: container.offsetHeight,
                       clientHeight: container.clientHeight,
                       scrollHeight: container.scrollHeight,
                       scrollTop: container.scrollTop,
                       styleHeight: container.style.height,
                       computedStyle: window.getComputedStyle(container).height,
                       parentHeight: container.parentElement ? container.parentElement.offsetHeight : null,
                       isScrollable: container.scrollHeight > container.clientHeight
                   });
                   
                   // If container has no height, try to calculate it
                   if (container.clientHeight === 0) {
                       console.warn('Container has 0 clientHeight, attempting to fix...');
                       // Force a height calculation
                       container.style.height = 'auto';
                       container.style.minHeight = '100px';
                       
                       // Check parent constraints
                       const parent = container.parentElement;
                       if (parent) {
                           console.log('Parent dimensions:', {
                               parentOffsetHeight: parent.offsetHeight,
                               parentClientHeight: parent.clientHeight,
                               parentScrollHeight: parent.scrollHeight,
                               parentComputedHeight: window.getComputedStyle(parent).height
                           });
                       }
                   }
                   
                   // Use requestAnimationFrame for reliable timing
                   requestAnimationFrame(() => {
                       // Remove smooth scrolling temporarily
                       const originalStyle = container.style.scrollBehavior;
                       container.style.scrollBehavior = 'auto';
                       
                       // Scroll to bottom
                       container.scrollTop = container.scrollHeight;
                       
                       // Restore original style
                       requestAnimationFrame(() => {
                           container.style.scrollBehavior = originalStyle;
                       });
                       
                       console.log('Force scrolled to bottom:', {
                           scrollTop: container.scrollTop,
                           scrollHeight: container.scrollHeight,
                           clientHeight: container.clientHeight,
                           isAtBottom: Math.abs(container.scrollHeight - container.clientHeight - container.scrollTop) < 5
                       });
                       
                       // If still not at bottom, try alternative approach
                       if (Math.abs(container.scrollHeight - container.clientHeight - container.scrollTop) > 5) {
                           console.warn('Not at bottom, trying alternative scroll method...');
                           // Alternative: scroll to maximum possible
                           container.scrollTop = 9999999;
                           
                           setTimeout(() => {
                               console.log('Alternative scroll result:', {
                                   scrollTop: container.scrollTop,
                                   maxScroll: container.scrollHeight - container.clientHeight
                               });
                           }, 100);
                       }
                   });
               }
               
               // Initialize scrolling with multiple attempts
               function initScrolling() {
                   console.log('Initializing scrolling...');
                   
                   // Try multiple times with increasing delays
                   [0, 100, 300, 500, 1000, 2000].forEach(delay => {
                       setTimeout(() => {
                           console.log(`Scroll attempt ${delay}ms`);
                           forceScrollToBottom();
                       }, delay);
                   });
                   
                   // Also scroll when images load
                   document.querySelectorAll('#messagesList img').forEach(img => {
                       if (!img.complete) {
                           img.addEventListener('load', () => {
                               console.log('Image loaded, scrolling to bottom');
                               forceScrollToBottom();
                           });
                       }
                   });
               }
               
               // Start scrolling
               if (document.readyState === 'loading') {
                   document.addEventListener('DOMContentLoaded', initScrolling);
               } else {
                   setTimeout(initScrolling, 100);
               }
        }
        
        // Create group functionality
        const createGroupBtn = document.getElementById('createGroupBtn');
        if (createGroupBtn) {
            createGroupBtn.addEventListener('click', async function() {
            const form = document.getElementById('createGroupForm');
            const formData = new FormData(form);
            const data = {
                session_id: parseInt(formData.get('session_id')),
                name: formData.get('name'),
                participants: formData.get('participants') ? formData.get('participants').split(',').map(p => p.trim()).filter(p => p) : []
            };
            
            try {
                const response = await fetch('/api/whatsapp/groups', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    location.reload();
                } else {
                     Swal.fire({
                         icon: 'error',
                         title: 'Create Group Failed',
                         text: 'Failed to create group: ' + result.message,
                         confirmButtonColor: '#3085d6',
                     });
                }
            } catch (error) {
                 Swal.fire({
                     icon: 'error',
                     title: 'Create Group Error',
                     text: 'Error creating group',
                     confirmButtonColor: '#3085d6',
                 });
            }
            });
        }
        
        // Helper functions
        async function sendMessage(groupId, message, tempMessageId = null) {
            try {
                const response = await fetch('/api/whatsapp/messages', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ group_id: groupId, message: message })
                });
                
                const result = await response.json();
                
                // Set a safety timeout to release grey-out state after 10 seconds
                // in case real-time update doesn't come through
                if (safetyTimeoutId) {
                    clearTimeout(safetyTimeoutId);
                }
                safetyTimeoutId = setTimeout(() => {
                    // If timeout expires and we still have a temp message, remove it
                    if (tempMessageId) {
                        removeTempMessage(tempMessageId);
                    }
                    setMessageSendingState(false);
                    safetyTimeoutId = null;
                }, 10000);
                
                return result.success;
            } catch (error) {
                console.error('Failed to send message:', error);
                return false;
            }
        }
        
          function addMessageToChat(data, shouldScroll = true) {
              const messagesList = document.getElementById('messagesList');
              if (!messagesList) return;
              
              // Check if this might be a duplicate of a temp message we already showed
              // We'll look for temp messages with similar content and timestamp
              const tempMessages = document.querySelectorAll('.temp-message');
              let replacedTempMessage = false;
              
              if (data.is_from_me || data.sender_name === 'You') {
                  // This is a message from the current user
                  // Look for a temp message with similar content (sent within last 30 seconds)
                  const now = Date.now();
                  const messageTime = data.timestamp || now;
                  
                  for (const tempMsg of tempMessages) {
                       const tempContentElement = tempMsg.querySelector('.mb-1');
                       const tempContent = tempContentElement ? tempContentElement.textContent : '';
                      if (tempContent === data.content) {
                          // Found matching temp message, replace it with the real one
                          tempMsg.remove();
                          replacedTempMessage = true;
                          break;
                      }
                  }
                  
                  // Release grey-out state since message arrived from server
                  setMessageSendingState(false);
              }
              
              const messageDiv = document.createElement('div');
              messageDiv.className = `message mb-2 ${data.is_from_me ? 'text-end' : ''}`;
              
              const timestamp = new Date(data.timestamp);
              const timeStr = timestamp.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
              const imageUrl = data.media_url
                  ? escapeHtml(data.media_url)
                  : (data.content ? `data:image/jpeg;base64,${escapeHtml(data.content)}` : '');
              const documentExt = (data.media_url || '').split('?')[0].split('.').pop().toLowerCase();
              const isPdf = documentExt === 'pdf';
              const stickerUrl = (data.message_type === 'sticker') ? imageUrl : '';
              const documentHtml = (data.message_type === 'document' && data.media_url)
                  ? '<div class="mt-1">' +
                      (isPdf
                          ? '<button class="btn btn-sm btn-outline-secondary" type="button" data-file-url="' + escapeHtml(data.media_url) + '" onclick="openWhatsappPdfModal(this)"><i class="fas fa-file-pdf me-1"></i>View PDF</button>'
                          : '<a class="btn btn-sm btn-outline-secondary" href="' + escapeHtml(data.media_url) + '" target="_blank" rel="noopener"><i class="fas fa-download me-1"></i>Open document</a>'
                      ) +
                      '<div class="small mt-1">&nbsp;</div>' +
                    '</div>'
                  : '';
              const imageCaption = data.media_caption || data.caption || '';
              let messageContentHtml = '';
              if (data.message_type === 'sticker' && stickerUrl) {
                  messageContentHtml = '<div class="mt-1">' +
                      '<img src="' + stickerUrl + '" class="img-fluid rounded message-image" style="max-height: 120px; cursor: pointer;" data-image-url="' + stickerUrl + '" alt="Sticker" onclick="enlargeImage(this)">' +
                      '<div class="small mt-1 text-muted">Sticker</div>' +
                      '</div>';
              } else if (data.message_type === 'image' && imageUrl) {
                  messageContentHtml = '<div class="mt-1">' +
                      '<img src="' + imageUrl + '" class="img-fluid rounded message-image" style="max-height: 150px; cursor: pointer;" data-image-url="' + imageUrl + '" alt="Image" onclick="enlargeImage(this)">' +
                      (imageCaption ? '<div class="small mt-1">' + escapeHtml(imageCaption) + '</div>' : '') +
                      '</div>';
              } else if (documentHtml) {
                  messageContentHtml = documentHtml;
              } else if (data.media_url) {
                  messageContentHtml = '<div class="mt-1">' +
                      '<img src="' + escapeHtml(data.media_url) + '" class="img-fluid rounded" style="max-height: 150px;">' +
                      (data.media_caption ? '<div class="small mt-1">' + escapeHtml(data.media_caption) + '</div>' : '') +
                      '</div>';
              } else {
                  messageContentHtml = '<div class="mb-1">' + escapeHtml(data.content || '') + '</div>';
              }
              
              messageDiv.innerHTML = `
                  <div class="d-flex ${data.is_from_me ? 'justify-content-end' : ''} align-items-end">
                      ${!data.is_from_me ? `
                          <div class="me-2">
                              <div class="avatar-xs bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; font-size: 12px;">
                                   ${((data.sender_name ? data.sender_name.charAt(0) : (data.sender_number ? data.sender_number.charAt(0) : '?')) || '?').toUpperCase()}
                              </div>
                          </div>
                      ` : ''}
                      <div class="${data.is_from_me ? 'bg-primary text-white message-bubble sent' : 'bg-light message-bubble received'} px-3 py-2" style="max-width: 65%;">
                          ${!data.is_from_me ? `
                              <div class="small fw-bold mb-1">${escapeHtml(data.sender_name || data.sender_number)}</div>
                          ` : ''}
                           ${messageContentHtml}
                          <div class="small text-end ${data.is_from_me ? 'text-white-50' : 'text-muted'}">
                              ${timeStr}
                          </div>
                      </div>
                  </div>
              `;
              
               messagesList.appendChild(messageDiv);
               if (shouldScroll) {
                   forceScrollToBottom();
               }
          }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Append sent message immediately to chat
        function appendSentMessageToChat(data) {
            const messagesList = document.getElementById('messagesList');
            if (!messagesList) return;
            
             const messageDiv = document.createElement('div');
             messageDiv.id = `message-${data.id}`;
             messageDiv.className = `message mb-2 ${data.is_from_me ? 'text-end' : ''} ${data.is_temp ? 'temp-message' : ''}`;
             
             const timestamp = new Date(data.timestamp);
             const timeStr = timestamp.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
             const imageUrl = data.media_url
                 ? escapeHtml(data.media_url)
                 : (data.content ? `data:image/jpeg;base64,${escapeHtml(data.content)}` : '');
             const documentExt = (data.media_url || '').split('?')[0].split('.').pop().toLowerCase();
             const isPdf = documentExt === 'pdf';
             const stickerUrl = (data.message_type === 'sticker') ? imageUrl : '';
             const documentHtml = (data.message_type === 'document' && data.media_url)
                 ? '<div class="mt-1">' +
                     (isPdf
                         ? '<button class="btn btn-sm btn-outline-secondary pdf-preview-trigger" type="button" data-file-url="' + escapeHtml(data.media_url) + '"><i class="fas fa-file-pdf me-1"></i>View PDF</button>'
                         : '<a class="btn btn-sm btn-outline-secondary" href="' + escapeHtml(data.media_url) + '" target="_blank" rel="noopener"><i class="fas fa-download me-1"></i>Open document</a>'
                     ) +
                     '<div class="small mt-1">&nbsp;</div>' +
                   '</div>'
                 : '';
            
             const imageCaption = data.media_caption || data.caption || '';
             let messageContentHtml = '';
             if (data.message_type === 'sticker' && stickerUrl) {
                 messageContentHtml = '<div class="mt-1">' +
                     '<img src="' + stickerUrl + '" class="img-fluid rounded message-image" style="max-height: 120px; cursor: pointer; ' + (data.is_temp ? 'opacity: 0.8;' : '') + '" data-image-url="' + stickerUrl + '" alt="Sticker" onclick="enlargeImage(this)">' +
                     '<div class="small mt-1 text-muted">Sticker</div>' +
                     '</div>';
             } else if (data.message_type === 'image' && imageUrl) {
                 messageContentHtml = '<div class="mt-1">' +
                     '<img src="' + imageUrl + '" class="img-fluid rounded message-image" style="max-height: 150px; cursor: pointer; ' + (data.is_temp ? 'opacity: 0.8;' : '') + '" data-image-url="' + imageUrl + '" alt="Image" onclick="enlargeImage(this)">' +
                     (imageCaption ? '<div class="small mt-1">' + escapeHtml(imageCaption) + '</div>' : '') +
                     '</div>';
             } else if (documentHtml) {
                 messageContentHtml = documentHtml;
             } else {
                 messageContentHtml = '<div class="mb-1">' + escapeHtml(data.content || '') + '</div>';
             }

             messageDiv.innerHTML = `
                 <div class="d-flex ${data.is_from_me ? 'justify-content-end' : ''} align-items-end">
                     <div class="${data.is_from_me ? 'bg-primary text-white message-bubble sent' : 'bg-light message-bubble received'} px-3 py-2" style="max-width: 65%; ${data.is_temp ? 'opacity: 0.8;' : ''}">
                         ${messageContentHtml}
                         ${data.is_temp ? `
                             <div class="small text-end text-white-50">
                                 <i class="fas fa-clock me-1"></i>${timeStr}
                             </div>
                         ` : `
                             <div class="small text-end ${data.is_from_me ? 'text-white-50' : 'text-muted'}">
                                 ${timeStr}
                             </div>
                         `}
                     </div>
                 </div>
             `;
            
             messagesList.appendChild(messageDiv);
             forceScrollToBottom();
        }
        
        // Remove temporary message
        function removeTempMessage(messageId) {
            const messageElement = document.getElementById(`message-${messageId}`);
            if (messageElement) {
                messageElement.remove();
            }
        }
        
        // Grey-out state management
        let safetyTimeoutId = null;
        
        function setMessageSendingState(isSending) {
            const messageInput = document.getElementById('messageInput');
            const attachButton = document.getElementById('attachMedia');
            const sendButton = document.querySelector('#messageForm button[type="submit"]');
            const inputGroup = document.querySelector('#messageForm .input-group');
            
            if (messageInput) {
                messageInput.disabled = isSending;
                messageInput.style.opacity = isSending ? '0.5' : '1';
                messageInput.style.cursor = isSending ? 'not-allowed' : 'text';
                messageInput.style.backgroundColor = isSending ? '#f8f9fa' : '';
            }
            
            if (attachButton) {
                attachButton.disabled = isSending;
                attachButton.style.opacity = isSending ? '0.5' : '1';
                attachButton.style.cursor = isSending ? 'not-allowed' : 'pointer';
                attachButton.style.backgroundColor = isSending ? '#f8f9fa' : '';
            }
            
            if (sendButton) {
                sendButton.disabled = isSending;
                sendButton.style.opacity = isSending ? '0.5' : '1';
                sendButton.style.cursor = isSending ? 'not-allowed' : 'pointer';
                sendButton.style.backgroundColor = isSending ? '#f8f9fa' : '';
                
                // Update button text/icon to show loading state
                if (isSending) {
                    const originalHTML = sendButton.innerHTML;
                    sendButton.setAttribute('data-original-html', originalHTML);
                    sendButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                } else {
                    const originalHTML = sendButton.getAttribute('data-original-html');
                    if (originalHTML) {
                        sendButton.innerHTML = originalHTML;
                    }
                }
            }
            
            // Add/remove disabled class to input group
            if (inputGroup) {
                if (isSending) {
                    inputGroup.classList.add('disabled');
                } else {
                    inputGroup.classList.remove('disabled');
                }
            }
            
            // Clear any existing safety timeout when releasing state
            if (!isSending && safetyTimeoutId) {
                clearTimeout(safetyTimeoutId);
                safetyTimeoutId = null;
            }
        }
        
        function updateUnreadCount(groupId) {
            const groupLink = document.querySelector(`[data-group-id="${groupId}"]`);
            if (groupLink) {
                const badge = groupLink.querySelector('.badge');
                if (badge) {
                    const current = parseInt(badge.textContent) || 0;
                    badge.textContent = current + 1;
                }
            }
    }
});
</script>

<!-- Simple guaranteed scroll to bottom script -->
<script>
(function() {
    console.log('Starting guaranteed scroll to bottom...');
    
    function scrollToBottom() {
        const container = document.getElementById('messagesContainer');
        if (!container) {
            console.error('messagesContainer not found');
            return false;
        }
        
        // Check if container has content
        const messagesList = document.getElementById('messagesList');
        if (!messagesList || messagesList.children.length === 0) {
            console.log('No messages found yet');
            return false;
        }
        
        console.log('Scrolling container:', {
            scrollHeight: container.scrollHeight,
            clientHeight: container.clientHeight,
            scrollTop: container.scrollTop,
            hasContent: messagesList.children.length > 0,
            containerExists: !!container,
            containerStyle: window.getComputedStyle(container).overflowY
        });
        
        // Simple direct scroll
        container.scrollTop = container.scrollHeight;
        return true;
    }
    
    // Wait for container to exist
    function waitForContainer(callback, maxWait = 5000) {
        const startTime = Date.now();
        
        function check() {
            const container = document.getElementById('messagesContainer');
            const messagesList = document.getElementById('messagesList');
            
            if (container && messagesList) {
                console.log('Container found after', Date.now() - startTime, 'ms');
                callback();
            } else if (Date.now() - startTime < maxWait) {
                setTimeout(check, 100);
            } else {
                console.error('Container not found after', maxWait, 'ms');
            }
        }
        
        check();
    }
    
    // Try multiple times
    let attempts = 0;
    const maxAttempts = 10;
    
    function tryScroll() {
        attempts++;
        console.log(`Scroll attempt ${attempts}/${maxAttempts}`);
        
        if (scrollToBottom()) {
            console.log('Scroll successful on attempt', attempts);
            
            // Check if we're at bottom
            const container = document.getElementById('messagesContainer');
            const isAtBottom = Math.abs(container.scrollHeight - container.clientHeight - container.scrollTop) < 1;
            
            if (!isAtBottom && attempts < maxAttempts) {
                // Try again after a delay
                setTimeout(tryScroll, 100);
            }
        } else if (attempts < maxAttempts) {
            setTimeout(tryScroll, 200);
        }
    }
    
    // Wait for container then start scrolling
    waitForContainer(() => {
        // Start scrolling
        setTimeout(tryScroll, 100);
        setTimeout(tryScroll, 500);
        setTimeout(tryScroll, 1000);
        setTimeout(tryScroll, 2000);
    });
    
    // Also scroll when images load
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('#messagesList img').forEach(img => {
            if (!img.complete) {
                img.addEventListener('load', () => {
                    console.log('Image loaded, scrolling to bottom');
                    scrollToBottom();
                });
            }
        });
    });
    
    // Scroll on window load
    window.addEventListener('load', () => {
        console.log('Window loaded, scrolling to bottom');
        setTimeout(tryScroll, 100);
    });
})();
</script>
    <?php
    
    app_render_image_modal();
    app_render_dashboard_end();
    app_render_footer();
}

function app_page_reports(): void {
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

    app_render_head('Reports');
    app_render_dashboard_start($user);
    app_render_flash();

    $categoryIds = [];
    $categoryInput = $_GET['category_ids'] ?? [];
    if (is_array($categoryInput)) {
        foreach ($categoryInput as $categoryId) {
            $categoryId = (int) $categoryId;
            if ($categoryId > 0) {
                $categoryIds[] = $categoryId;
            }
        }
    } else {
        $categoryId = (int) $categoryInput;
        if ($categoryId > 0) {
            $categoryIds[] = $categoryId;
        }
    }
    $categoryIds = array_values(array_unique($categoryIds));

    $filters = [
        'session_id' => (int) ($_GET['session_id'] ?? 0),
        'group_id' => (int) ($_GET['group_id'] ?? 0),
        'category_ids' => $categoryIds,
        'message_type' => trim((string) ($_GET['message_type'] ?? '')),
        'sender' => trim((string) ($_GET['sender'] ?? '')),
        'date_from' => trim((string) ($_GET['date_from'] ?? '')),
        'date_to' => trim((string) ($_GET['date_to'] ?? ''))
    ];

    $sessions = app_whatsapp_get_user_sessions($effectiveUserId);
    $groups = app_whatsapp_get_user_groups($effectiveUserId);
    $categories = app_whatsapp_get_user_categories($effectiveUserId, null, true);

    $groupsBySession = [];
    foreach ($groups as $group) {
        $sessionId = (int) ($group['session_id'] ?? 0);
        if ($sessionId > 0) {
            $groupsBySession[$sessionId][] = $group;
        }
    }

    $messages = [];
    try {
        $db = app_db();
        $query = "
            SELECT gm.id, gm.session_id, gm.group_id, gm.message_id, gm.sender_number, gm.sender_name,
                   gm.message_type, gm.content, gm.data, gm.media_caption, gm.caption, gm.amount, gm.timestamp,
                   wg.name as group_name, ws.session_name,
                   c.name as category_name, c.color as category_color
            FROM group_messages gm
            JOIN whatsapp_groups wg ON gm.session_id = wg.session_id AND gm.group_id = wg.group_id
            JOIN whatsapp_sessions ws ON gm.session_id = ws.id
            LEFT JOIN categories c ON gm.category_id = c.id AND c.user_id = :category_user_id
            WHERE ws.user_id = :user_id AND gm.category_id IS NOT NULL
        ";

        $params = ['user_id' => $effectiveUserId, 'category_user_id' => $effectiveUserId];

        if ($filters['session_id'] > 0) {
            $query .= " AND gm.session_id = :session_id";
            $params['session_id'] = $filters['session_id'];
        }

        if ($filters['group_id'] > 0) {
            $query .= " AND wg.id = :group_id";
            $params['group_id'] = $filters['group_id'];
        }

        if (!empty($filters['category_ids'])) {
            $placeholders = [];
            foreach ($filters['category_ids'] as $index => $categoryId) {
                $placeholder = ':category_id_' . $index;
                $placeholders[] = $placeholder;
                $params['category_id_' . $index] = $categoryId;
            }
            $query .= " AND gm.category_id IN (" . implode(', ', $placeholders) . ")";
        }

        if ($filters['message_type'] !== '') {
            $query .= " AND gm.message_type = :message_type";
            $params['message_type'] = $filters['message_type'];
        }

        if ($filters['sender'] !== '') {
            $query .= " AND (gm.sender_name LIKE :sender OR gm.sender_number LIKE :sender)";
            $params['sender'] = '%' . $filters['sender'] . '%';
        }

        if ($filters['date_from'] !== '') {
            $startTimestamp = strtotime($filters['date_from'] . ' 00:00:00');
            if ($startTimestamp !== false) {
                $query .= " AND gm.timestamp >= :start_timestamp";
                $params['start_timestamp'] = $startTimestamp * 1000;
            }
        }

        if ($filters['date_to'] !== '') {
            $endTimestamp = strtotime($filters['date_to'] . ' 23:59:59');
            if ($endTimestamp !== false) {
                $query .= " AND gm.timestamp <= :end_timestamp";
                $params['end_timestamp'] = $endTimestamp * 1000;
            }
        }

        $query .= " ORDER BY gm.timestamp DESC";

        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $messages = [];
    }

    $messageTypes = [
        'chat' => 'Chat',
        'image' => 'Image',
        'video' => 'Video',
        'audio' => 'Audio',
        'document' => 'Document',
        'sticker' => 'Sticker',
        'location' => 'Location',
        'contact' => 'Contact',
        'poll' => 'Poll',
        'other' => 'Other'
    ];

    ?>
    <div class="container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <div>
                <h2 class="mb-1">Reports</h2>
                <div class="text-muted small">Messages grouped by case and session with assigned categories</div>
            </div>
            <div class="d-flex gap-2 mt-2 mt-md-0">
                <button type="button" class="btn btn-outline-secondary" id="export-csv">
                    <i class="fas fa-file-csv me-1"></i> CSV
                </button>
                <button type="button" class="btn btn-outline-success" id="export-xlsx">
                    <i class="fas fa-file-excel me-1"></i> Excel
                </button>
                <button type="button" class="btn btn-outline-danger" id="export-pdf">
                    <i class="fas fa-file-pdf me-1"></i> PDF
                </button>
                <button type="button" class="btn btn-outline-primary" id="generate-page">
                    <i class="fas fa-magic me-1"></i> Generate Page with data
                </button>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header bg-white">
                <h6 class="mb-0">Filters</h6>
            </div>
            <div class="card-body">
                <form method="get" class="row g-3">
                    <div class="col-lg-3 col-md-6">
                        <label class="form-label">Session</label>
                        <select class="form-select" name="session_id">
                            <option value="0">All Sessions</option>
                            <?php foreach ($sessions as $session): ?>
                                <?php $sessionId = (int) ($session['id'] ?? 0); ?>
                                <option value="<?= $sessionId ?>" <?= $filters['session_id'] === $sessionId ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($session['session_name'] ?? 'Unknown', ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <label class="form-label">Case (Group)</label>
                        <select class="form-select" name="group_id">
                            <option value="0">All Cases</option>
                            <?php foreach ($sessions as $session): ?>
                                <?php
                                $sessionId = (int) ($session['id'] ?? 0);
                                $sessionGroups = $groupsBySession[$sessionId] ?? [];
                                if (empty($sessionGroups)) {
                                    continue;
                                }
                                ?>
                                <optgroup label="<?= htmlspecialchars($session['session_name'] ?? 'Session', ENT_QUOTES, 'UTF-8') ?>">
                                    <?php foreach ($sessionGroups as $group): ?>
                                        <?php $groupId = (int) ($group['id'] ?? 0); ?>
                                        <option value="<?= $groupId ?>" <?= $filters['group_id'] === $groupId ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($group['name'] ?? 'Unknown', ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <label class="form-label">Category</label>
                        <div class="dropdown w-100" data-category-filter>
                            <button class="btn btn-outline-secondary dropdown-toggle w-100 text-start" type="button" data-bs-toggle="dropdown" aria-expanded="false" id="categoryFilterBtn">
                                <?= empty($filters['category_ids']) ? 'All Categories' : 'Selected Categories' ?>
                            </button>
                            <div class="dropdown-menu p-2 w-100" style="max-height: 260px; overflow-y: auto;">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="category-all" <?= empty($filters['category_ids']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="category-all">All Categories</label>
                                </div>
                                <hr class="my-2">
                                <?php foreach ($categories as $category): ?>
                                    <?php $categoryId = (int) ($category['id'] ?? 0); ?>
                                    <div class="form-check">
                                        <input class="form-check-input category-option" type="checkbox" name="category_ids[]" value="<?= $categoryId ?>" id="category-<?= $categoryId ?>" <?= in_array($categoryId, $filters['category_ids'], true) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="category-<?= $categoryId ?>">
                                            <?= htmlspecialchars($category['name'] ?? 'Category', ENT_QUOTES, 'UTF-8') ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <label class="form-label">Message Type</label>
                        <select class="form-select" name="message_type">
                            <option value="">All Types</option>
                            <?php foreach ($messageTypes as $typeKey => $typeLabel): ?>
                                <option value="<?= $typeKey ?>" <?= $filters['message_type'] === $typeKey ? 'selected' : '' ?>>
                                    <?= $typeLabel ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <label class="form-label">Sender</label>
                        <input type="text" class="form-control" name="sender" value="<?= htmlspecialchars($filters['sender'], ENT_QUOTES, 'UTF-8') ?>" placeholder="Name or number">
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <label class="form-label">From</label>
                        <input type="date" class="form-control" name="date_from" value="<?= htmlspecialchars($filters['date_from'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <label class="form-label">To</label>
                        <input type="date" class="form-control" name="date_to" value="<?= htmlspecialchars($filters['date_to'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-lg-3 col-md-6 d-flex align-items-end">
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Apply Filters</button>
                            <a href="/reports" class="btn btn-outline-secondary">Reset</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Results</h6>
                <div class="text-muted small"><?= count($messages) ?> messages</div>
            </div>
            <div class="card-body">
                <?php if (empty($messages)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No categorized messages match the selected filters.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle" id="reports-table">
                            <thead>
                                <tr>
                                    <th>Case</th>
                                    <th>Sender</th>
                                    <th>Data</th>
                                    <th>Amount</th>
                                    <th>Category</th>
                                    <th>Timestamp</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($messages as $message): ?>
                                    <?php
                                    $messageData = (string) ($message['data'] ?? '');

                                    $timestamp = (int) ($message['timestamp'] ?? 0);
                                    $displayTime = $timestamp > 0 ? date('M d, Y H:i', (int) floor($timestamp / 1000)) : 'Unknown';
                                    $senderLabel = $message['sender_name'] ?: $message['sender_number'];
                                    $amountValue = $message['amount'];
                                    if ($amountValue !== null && $amountValue !== '' && (float) $amountValue !== 0.0) {
                                        $amountValue = $defaultCurrency . ' ' . number_format((float) $amountValue, 2, '.', ',');
                                    } else {
                                        $amountValue = '- N/A -';
                                    }
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($message['group_name'] ?? 'Unknown', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($senderLabel ?: 'Unknown', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="text-truncate" style="max-width: 320px;" title="<?= htmlspecialchars($messageData, ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars($messageData, ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td><?= htmlspecialchars($amountValue, ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <span class="badge" style="background-color: <?= htmlspecialchars($message['category_color'] ?? '#6c757d', ENT_QUOTES, 'UTF-8') ?>;">
                                                <?= htmlspecialchars($message['category_name'] ?? 'Uncategorized', ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($displayTime, ENT_QUOTES, 'UTF-8') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="modal fade" id="generatePageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Generate Page with data</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <label for="reportPagePrompt" class="form-label">AI Prompt</label>
                    <textarea class="form-control" id="reportPagePrompt" rows="4">Generate an interactive website with this data.</textarea>
                    <div class="form-text">Your CSV data will be saved to the page folder for AI generation.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="submitGeneratePage">
                        <i class="fas fa-magic me-1"></i> Generate
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jspdf-autotable@3.5.31/dist/jspdf.plugin.autotable.min.js"></script>
    <script>
        (function() {
            const table = document.getElementById('reports-table');
            const csvButton = document.getElementById('export-csv');
            const xlsxButton = document.getElementById('export-xlsx');
            const pdfButton = document.getElementById('export-pdf');
            const generatePageButton = document.getElementById('generate-page');
            const generatePageModalEl = document.getElementById('generatePageModal');
            const generatePageSubmit = document.getElementById('submitGeneratePage');
            const promptField = document.getElementById('reportPagePrompt');

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

            function exportCsv() {
                const data = getTableData();
                if (!data.rows.length) {
                    if (window.Swal) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'No data to export',
                            text: 'There are no rows to export.'
                        });
                    } else {
                        alert('No data to export.');
                    }
                    return;
                }
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
                const csvContent = lines.join('\n');
                downloadFile(new Blob([csvContent], { type: 'text/csv;charset=utf-8;' }), 'reports.csv');
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

            function exportXlsx() {
                const data = getTableData();
                if (!data.rows.length || !window.XLSX) {
                    if (window.Swal) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'No data to export',
                            text: 'There are no rows to export.'
                        });
                    } else {
                        alert('No data to export.');
                    }
                    return;
                }
                const worksheet = window.XLSX.utils.aoa_to_sheet([data.headers, ...data.rows]);
                const workbook = window.XLSX.utils.book_new();
                window.XLSX.utils.book_append_sheet(workbook, worksheet, 'Reports');
                window.XLSX.writeFile(workbook, 'reports.xlsx');
            }

            function exportPdf() {
                const data = getTableData();
                if (!data.rows.length || !window.jspdf || !window.jspdf.jsPDF) {
                    if (window.Swal) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'No data to export',
                            text: 'There are no rows to export.'
                        });
                    } else {
                        alert('No data to export.');
                    }
                    return;
                }
                const doc = new window.jspdf.jsPDF({ orientation: 'landscape', unit: 'pt', format: 'a4' });
                doc.setFontSize(12);
                doc.text('Reports', 40, 40);
                if (doc.autoTable) {
                    doc.autoTable({
                        startY: 60,
                        head: [data.headers],
                        body: data.rows,
                        styles: { fontSize: 8, cellPadding: 3 }
                    });
                }
                doc.save('reports.pdf');
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

            const categoryFilter = document.querySelector('[data-category-filter]');
            if (categoryFilter) {
                const allToggle = categoryFilter.querySelector('#category-all');
                const options = Array.from(categoryFilter.querySelectorAll('.category-option'));
                const button = categoryFilter.querySelector('#categoryFilterBtn');

                const updateCategoryLabel = () => {
                    const selected = options.filter(option => option.checked);
                    if (!button) {
                        return;
                    }
                    if (selected.length === 0) {
                        button.textContent = 'All Categories';
                        if (allToggle) {
                            allToggle.checked = true;
                        }
                        return;
                    }
                    const label = selected.length === 1
                        ? selected[0].nextElementSibling?.textContent?.trim() || 'Selected Categories'
                        : `${selected.length} Categories Selected`;
                    button.textContent = label;
                    if (allToggle) {
                        allToggle.checked = false;
                    }
                };

                if (allToggle) {
                    allToggle.addEventListener('change', () => {
                        if (allToggle.checked) {
                            options.forEach(option => {
                                option.checked = false;
                            });
                        }
                        updateCategoryLabel();
                    });
                }

                options.forEach(option => {
                    option.addEventListener('change', () => {
                        if (option.checked && allToggle) {
                            allToggle.checked = false;
                        }
                        updateCategoryLabel();
                    });
                });

                updateCategoryLabel();
            }

            function formatTitleTimestamp() {
                const now = new Date();
                const pad = value => String(value).padStart(2, '0');
                return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())} ${pad(now.getHours())}:${pad(now.getMinutes())}`;
            }

            function getDefaultPrompt() {
                if (promptField && promptField.value.trim() !== '') {
                    return promptField.value.trim();
                }
                return 'Generate an interactive website with this data.';
            }

            async function generatePageWithData(promptOverride = '') {
                const data = getTableData();
                if (!data.rows.length) {
                    if (window.Swal) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'No data to export',
                            text: 'There are no rows to generate a page.'
                        });
                    } else {
                        alert('No data to export.');
                    }
                    return;
                }

                const prompt = promptOverride.trim() !== '' ? promptOverride.trim() : getDefaultPrompt();
                const csvContent = buildCsvContent(data);

                if (window.Swal) {
                    Swal.fire({
                        title: 'Creating page...',
                        allowOutsideClick: false,
                        didOpen: () => Swal.showLoading()
                    });
                }

                try {
                    const response = await fetch('/api/reports/pages', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            prompt,
                            csv: csvContent,
                            title: `Reports Data - ${formatTitleTimestamp()}`
                        })
                    });
                    const result = await response.json();
                    if (!response.ok || !result.success) {
                        const message = result && result.message ? result.message : 'Failed to create page.';
                        if (window.Swal) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Could not create page',
                                text: message
                            });
                        } else {
                            alert(message);
                        }
                        return;
                    }

                    if (generatePageModalEl && window.bootstrap) {
                        const modalInstance = window.bootstrap.Modal.getInstance(generatePageModalEl);
                        if (modalInstance) {
                            modalInstance.hide();
                        }
                    }

                    const token = result.data && result.data.token ? result.data.token : '';
                    const pageUrl = token ? `/pages?token=${encodeURIComponent(token)}` : '/pages';

                    if (token) {
                        window.open(pageUrl, '_blank');
                    }

                    if (window.Swal) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Page created',
                            html: token ? `Open your page: <a href="${pageUrl}" target="_blank" rel="noopener">${pageUrl}</a>` : 'Your page has been created.',
                            confirmButtonText: 'Done'
                        });
                    }
                } catch (error) {
                    if (window.Swal) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Request failed',
                            text: 'Unable to create page right now.'
                        });
                    } else {
                        alert('Unable to create page right now.');
                    }
                }
            }

            if (generatePageButton) {
                generatePageButton.addEventListener('click', () => {
                    if (window.Swal) {
                        Swal.fire({
                            title: 'Generate Page with data',
                            input: 'textarea',
                            inputValue: getDefaultPrompt(),
                            inputAttributes: {
                                'aria-label': 'AI prompt'
                            },
                            showCancelButton: true,
                            confirmButtonText: 'Generate'
                        }).then(result => {
                            if (result.isConfirmed) {
                                const promptValue = typeof result.value === 'string' ? result.value : '';
                                generatePageWithData(promptValue);
                            }
                        });
                        return;
                    }

                    if (generatePageModalEl && window.bootstrap) {
                        const modal = new window.bootstrap.Modal(generatePageModalEl);
                        modal.show();
                        return;
                    }

                    const fallbackPrompt = window.prompt('Enter AI prompt for this page', getDefaultPrompt());
                    if (fallbackPrompt !== null) {
                        generatePageWithData(fallbackPrompt);
                    }
                });
            }

            if (generatePageSubmit) {
                generatePageSubmit.addEventListener('click', () => {
                    generatePageWithData(getDefaultPrompt());
                });
            }
        })();
    </script>
    <?php

    app_render_dashboard_end();
    app_render_footer();
}

function app_page_admin_users(): void {
    app_require_auth();
    $user = app_current_user();
    
    if (!in_array($user['role'], ['superadmin', 'admin'])) {
        app_flash('error', 'Access denied');
        app_redirect('/welcome');
    }
    
    // Handle user actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        $targetUserId = (int) ($_POST['user_id'] ?? 0);
        
        switch ($action) {
            case 'update_tier':
                $tier = $_POST['tier'] ?? 'basic';
                app_update_user_tier($targetUserId, $tier);
                app_flash('success', 'User tier updated');
                break;
                
            case 'generate_invite':
                $tier = $_POST['tier'] ?? 'basic';
                $inviteCode = app_generate_invite_code($user['id'], $tier);
                app_flash('success', "Invite code generated: <code>{$inviteCode}</code>");
                break;
                
            case 'delete_user':
                if ($user['role'] === 'superadmin') {
                    app_delete_user($targetUserId);
                    app_flash('success', 'User deleted');
                }
                break;
        }
        
        app_redirect('/admin/users');
    }
    
    // Get all users
    $users = app_get_all_users();
    $invites = app_get_pending_invites();
    
    app_render_head('User Management');
    app_render_dashboard_start($user);
    
    ?>
    <div class="container-fluid">
        <h2 class="mb-4">User Management</h2>
        
        <!-- Invite Generation -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Generate Invitation for Users</h5>
            </div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <input type="hidden" name="action" value="generate_invite">
                    <div class="col-md-4">
                        <label class="form-label">Tier</label>
                        <select name="tier" class="form-select" required>
                            <option value="basic">Basic (1 session)</option>
                            <option value="business">Business (3 sessions)</option>
                            <option value="enterprise">Enterprise (5 sessions)</option>
                        </select>
                    </div>
                    <div class="col-md-8 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i> Generate Invite Code
                        </button>
                    </div>
                </form>
                <div class="mt-2">
                    <small class="text-muted">Note: Invite codes create accounts with 'users' role. These users will be nested under your account and won't have access to WhatsApp Connect.</small>
                </div>
                
                <?php if (!empty($invites)): ?>
                    <div class="mt-3">
                        <h6>Active Invites:</h6>
                        <div class="list-group">
                            <?php foreach ($invites as $invite): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <code><?= $invite['code'] ?></code>
                                        <span class="badge bg-<?= $invite['tier'] === 'enterprise' ? 'danger' : ($invite['tier'] === 'business' ? 'warning' : 'secondary') ?> ms-2">
                                            <?= ucfirst($invite['tier']) ?>
                                        </span>
                                    </div>
                                    <small class="text-muted">
                                        Expires: <?= date('M d, H:i', strtotime($invite['expires_at'])) ?>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- User List -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Users</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Tier</th>
                                <th>Sessions</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                                <tr>
                                    <td>#<?= $u['id'] ?></td>
                                    <td><?= htmlspecialchars($u['name']) ?></td>
                                    <td><?= htmlspecialchars($u['email']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $u['role'] === 'superadmin' ? 'danger' : ($u['role'] === 'admin' ? 'warning' : 'secondary') ?>">
                                            <?= ucfirst($u['role']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($u['role'] === 'users'): ?>
                                            <span class="text-muted">Inherited from <?= htmlspecialchars($u['parent_name'] ?? 'parent') ?></span>
                                            <?php if (!empty($u['parent_tier'])): ?>
                                                <span class="badge bg-secondary ms-1"><?= ucfirst($u['parent_tier']) ?></span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="update_tier">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <select name="tier" class="form-select form-select-sm" 
                                                        onchange="this.form.submit()" 
                                                        <?= $u['role'] === 'superadmin' ? 'disabled' : '' ?>>
                                                    <option value="basic" <?= $u['tier'] === 'basic' ? 'selected' : '' ?>>Basic</option>
                                                    <option value="business" <?= $u['tier'] === 'business' ? 'selected' : '' ?>>Business</option>
                                                    <option value="enterprise" <?= $u['tier'] === 'enterprise' ? 'selected' : '' ?>>Enterprise</option>
                                                </select>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($u['role'] === 'users'): ?>
                                            <?php
                                            $parentId = (int) ($u['parent_id'] ?? 0);
                                            $parentTier = $u['parent_tier'] ?? 'basic';
                                            $parentSessionCount = $parentId ? app_whatsapp_count_user_sessions($parentId) : 0;
                                            $parentMaxSessions = app_tier_limits()[$parentTier]['max_sessions'] ?? 1;
                                            ?>
                                            <span class="<?= $parentSessionCount >= $parentMaxSessions ? 'text-danger' : 'text-success' ?>">
                                                <?= $parentSessionCount ?> / <?= $parentMaxSessions ?>
                                            </span>
                                        <?php else: ?>
                                            <?php 
                                            $sessionCount = app_whatsapp_count_user_sessions($u['id']);
                                            $maxSessions = app_tier_limits()[$u['tier']]['max_sessions'] ?? 1;
                                            ?>
                                            <span class="<?= $sessionCount >= $maxSessions ? 'text-danger' : 'text-success' ?>">
                                                <?= $sessionCount ?> / <?= $maxSessions ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                                    <td>
                                         <?php if ($user['role'] === 'superadmin' && $u['role'] !== 'superadmin'): ?>
                                             <?php $magicToken = app_create_magic_login_token((int) $u['id']); ?>
                                             <a class="btn btn-sm btn-outline-primary me-1" 
                                                href="/admin/users/magic-login?token=<?= urlencode($magicToken) ?>"
                                                title="Login as user">
                                                 <i class="fas fa-lock"></i>
                                             </a>
                                             <button type="button" class="btn btn-sm btn-danger delete-user-btn" 
                                                     data-user-id="<?= $u['id'] ?>"
                                                     data-user-name="<?= htmlspecialchars($u['name']) ?>">
                                                 <i class="fas fa-trash"></i>
                                             </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Handle user deletion with SweetAlert
        document.querySelectorAll('.delete-user-btn').forEach(button => {
            button.addEventListener('click', function() {
                const userId = this.dataset.userId;
                const userName = this.dataset.userName;
                
                Swal.fire({
                    title: 'Delete User?',
                    html: `Are you sure you want to delete user <strong>${userName}</strong>?<br><br>
                           <small class="text-muted">This action cannot be undone.</small>`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Yes, delete!',
                    cancelButtonText: 'Cancel',
                    reverseButtons: true,
                    showLoaderOnConfirm: true,
                    preConfirm: () => {
                        return fetch('/admin/users', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `action=delete_user&user_id=${userId}`,
                            redirect: 'manual' // Don't automatically follow redirects
                        })
                        .then(response => {
                            // Check if it's a redirect (302, 303, 307, 308)
                            if (response.status >= 300 && response.status < 400) {
                                // Get the redirect URL from the Location header
                                const redirectUrl = response.headers.get('Location') || '/admin/users';
                                window.location.href = redirectUrl;
                                return new Promise(() => {}); // Never resolve to keep Swal open
                            } else if (response.ok) {
                                // If not a redirect but successful, reload the page
                                window.location.reload();
                                return new Promise(() => {});
                            } else {
                                // Handle error
                                return response.text().then(text => {
                                    throw new Error(`Delete failed: ${response.status} ${response.statusText}`);
                                });
                            }
                        })
                        .catch(error => {
                            Swal.showValidationMessage(`Request failed: ${error}`);
                        });
                    },
                    allowOutsideClick: () => !Swal.isLoading()
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Page will reload from the redirect
                    }
                });
            });
        });
    });
    </script>
    <?php
    
    app_render_dashboard_end();
    app_render_footer();
}

function app_page_admin_users_magic_login(): void
{
    app_require_auth();
    $user = app_current_user();

    if (($user['role'] ?? '') !== 'superadmin') {
        app_flash('error', 'Access denied');
        app_redirect('/admin/users');
    }

    $token = (string) ($_GET['token'] ?? '');
    if ($token === '') {
        app_flash('error', 'Magic link is invalid or expired');
        app_redirect('/admin/users');
    }

    $targetUserId = app_consume_magic_login_token($token);
    if ($targetUserId === null) {
        app_flash('error', 'Magic link is invalid or expired');
        app_redirect('/admin/users');
    }

    $targetUser = app_find_user_by_id(app_db(), $targetUserId);
    if ($targetUser === null) {
        app_flash('error', 'User not found');
        app_redirect('/admin/users');
    }

    if (($targetUser['role'] ?? '') === 'superadmin') {
        app_flash('error', 'Cannot login as superadmin');
        app_redirect('/admin/users');
    }

    app_login_user($targetUser);
    app_log_auth($targetUser['email'] ?? 'unknown', 'magic_login', true);
    app_flash('success', 'Logged in as ' . ($targetUser['name'] ?? 'user'));
    app_redirect('/welcome');
}

// Helper functions for user management
function app_get_all_users(): array {
    $pdo = app_db();
    $stmt = $pdo->prepare("
        SELECT u.id,
               u.name,
               u.email,
               u.role,
               u.tier,
               u.parent_id,
               u.created_at,
               p.name AS parent_name,
               p.role AS parent_role,
               p.tier AS parent_tier
        FROM users u
        LEFT JOIN users p ON u.parent_id = p.id
        ORDER BY u.created_at DESC
    ");
    $stmt->execute();
    return $stmt->fetchAll();
}

function app_update_user_tier(int $userId, string $tier): bool {
    $pdo = app_db();
    $tierLimits = app_tier_limits();
    $normalizedTier = array_key_exists($tier, $tierLimits) ? $tier : 'basic';
    $maxSessions = $tierLimits[$normalizedTier]['max_sessions'] ?? 1;

    $stmt = $pdo->prepare("
        UPDATE users
        SET tier = :tier,
            max_sessions = :max_sessions
        WHERE id = :id
    ");

    return $stmt->execute([
        'tier' => $normalizedTier,
        'max_sessions' => $maxSessions,
        'id' => $userId
    ]);
}

function app_generate_invite_code(int $adminId, string $tier = 'basic'): string {
    $code = bin2hex(random_bytes(8)); // 16 character code
    
    $pdo = app_db();
    $stmt = $pdo->prepare("
        INSERT INTO user_invites 
        (code, created_by, tier, expires_at) 
        VALUES (:code, :created_by, :tier, DATE_ADD(NOW(), INTERVAL 7 DAY))
    ");
    
    $stmt->execute([
        'code' => $code,
        'created_by' => $adminId,
        'tier' => $tier
    ]);
    
    return $code;
}

function app_get_pending_invites(): array {
    $pdo = app_db();
    $stmt = $pdo->prepare("
        SELECT ui.*, u.name as created_by_name 
        FROM user_invites ui
        LEFT JOIN users u ON ui.created_by = u.id
        WHERE ui.used_at IS NULL AND ui.expires_at > NOW()
        ORDER BY ui.created_at DESC
    ");
    $stmt->execute();
    return $stmt->fetchAll();
}

function app_delete_user(int $userId): bool {
    $pdo = app_db();
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
    return $stmt->execute(['id' => $userId]);
}

function app_page_admin_my_users(): void {
    app_require_auth();
    $user = app_current_user();
    
    if (!in_array($user['role'], ['admin', 'superadmin'])) {
        app_flash('error', 'Access denied');
        app_redirect('/welcome');
    }
    
    // Handle user actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'generate_invite':
                // Generate invite code for users role
                $inviteCode = app_generate_invite_code($user['id'], 'basic');
                app_flash('success', "Invite code generated: <code>{$inviteCode}</code>");
                break;
                
            case 'delete_user':
                $targetUserId = (int) ($_POST['user_id'] ?? 0);
                if ($targetUserId) {
                    // Only allow deleting users where current user is parent
                    $pdo = app_db();
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = :id AND parent_id = :parent_id");
                    $stmt->execute(['id' => $targetUserId, 'parent_id' => $user['id']]);
                    $targetUser = $stmt->fetch();
                    
                    if ($targetUser) {
                        app_delete_user($targetUserId);
                        app_flash('success', 'User deleted');
                    }
                }
                break;
        }
        
        app_redirect('/admin/my-users');
    }
    
    // Get users where current admin is parent
    $pdo = app_db();
    $stmt = $pdo->prepare("
        SELECT u.* 
        FROM users u
        WHERE u.parent_id = :parent_id AND u.role = 'users'
        ORDER BY u.created_at DESC
    ");
    $stmt->execute(['parent_id' => $user['id']]);
    $myUsers = $stmt->fetchAll();
    
    // Get pending invites created by current admin
    $stmt = $pdo->prepare("
        SELECT ui.* 
        FROM user_invites ui
        WHERE ui.created_by = :created_by AND ui.used_at IS NULL AND ui.expires_at > NOW()
        ORDER BY ui.created_at DESC
    ");
    $stmt->execute(['created_by' => $user['id']]);
    $myInvites = $stmt->fetchAll();
    
    app_render_head('My Users');
    app_render_dashboard_start($user);
    
    ?>
    <div class="container-fluid">
        <h2 class="mb-4">My Users</h2>
        
        <!-- Invite Generation -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Generate Invitation for Users</h5>
            </div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <input type="hidden" name="action" value="generate_invite">
                    <div class="col-md-12">
                        <p class="text-muted">Generate an invite code to create new users under your account. Users created with this code will have 'users' role and won't be able to access WhatsApp Connect.</p>
                    </div>
                    <div class="col-md-12 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i> Generate Invite Code
                        </button>
                    </div>
                </form>
                
                <?php if (!empty($myInvites)): ?>
                    <div class="mt-3">
                        <h6>Active Invites:</h6>
                        <div class="list-group">
                            <?php foreach ($myInvites as $invite): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <code><?= $invite['code'] ?></code>
                                        <span class="badge bg-secondary ms-2">
                                            Users Role
                                        </span>
                                    </div>
                                    <small class="text-muted">
                                        Expires: <?= date('M d, H:i', strtotime($invite['expires_at'])) ?>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- My Users List -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">My Users List</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($myUsers)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($myUsers as $u): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($u['name']) ?></td>
                                        <td><?= htmlspecialchars($u['email']) ?></td>
                                        <td><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                                        <td>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                                <input type="hidden" name="action" value="delete_user">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No users have been invited yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
    
    app_render_footer();
}

// Image enlargement modal and functions
function app_render_image_modal(): void {
    ?>
<!-- Image Enlargement Modal -->
<div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Image Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <img id="enlargedImage" src="" class="img-fluid" alt="Enlarged Image">
                <div id="imageCaption" class="mt-2 small text-muted"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="downloadImage()">Download</button>
            </div>
        </div>
    </div>
</div>

<!-- PDF Preview Modal -->
<div class="modal fade" id="whatsappPdfModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">PDF Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <iframe id="whatsappPdfFrame" src="" title="PDF Preview" style="width: 100%; height: 100%; border: 0;"></iframe>
            </div>
        </div>
    </div>
</div>

<script>
// Function to enlarge image when clicked
function enlargeImage(imgElement) {
    const imageUrl = imgElement.getAttribute('data-image-url') || imgElement.getAttribute('data-base64') || '';
    const captionSource = imgElement.nextElementSibling;
    const caption = captionSource ? captionSource.textContent : '';
    if (!imageUrl) {
        return;
    }
    
    // Set the enlarged image source
    document.getElementById('enlargedImage').src = imageUrl.startsWith('data:') ? imageUrl : imageUrl;
    
    // Set caption if available
    const captionElement = document.getElementById('imageCaption');
    captionElement.textContent = caption;
    captionElement.style.display = caption ? 'block' : 'none';
    
    // Store current image data for download
    window.currentEnlargedImage = {
        url: imageUrl,
        caption: caption
    };
    
    // Show the modal
    const modal = new bootstrap.Modal(document.getElementById('imageModal'));
    modal.show();
}

// Function to download the enlarged image
function downloadImage() {
    if (!window.currentEnlargedImage || !window.currentEnlargedImage.url) return;
    
    const link = document.createElement('a');
    link.href = window.currentEnlargedImage.url;
    link.download = 'image_' + Date.now() + '.jpg';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function openWhatsappPdfModal(trigger) {
    const fileUrl = trigger.getAttribute('data-file-url') || '';
    if (!fileUrl) {
        return;
    }

    const isDesktop = window.innerWidth >= 992;
    if (!isDesktop) {
        window.open(fileUrl, '_blank', 'noopener');
        return;
    }

    const frame = document.getElementById('whatsappPdfFrame');
    if (!frame) {
        return;
    }

    frame.src = fileUrl;
    const modal = new bootstrap.Modal(document.getElementById('whatsappPdfModal'));
    modal.show();
}

document.addEventListener('click', function(event) {
    const trigger = event.target.closest('.pdf-preview-trigger');
    if (!trigger) {
        return;
    }
    event.preventDefault();
    openWhatsappPdfModal(trigger);
});

// Close modal with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const modal = bootstrap.Modal.getInstance(document.getElementById('imageModal'));
        if (modal) {
            modal.hide();
        }
    }
});
</script>
    <?php
}
