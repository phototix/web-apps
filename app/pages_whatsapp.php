<?php

declare(strict_types=1);

// WhatsApp Integration Pages

function app_page_whatsapp_connect(): void {
    app_require_auth();
    $user = app_current_user();
    
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
         document.getElementById('createSessionBtn')?.addEventListener('click', function() {
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
     });
     </script>
    <?php
    
    app_render_dashboard_end();
    app_render_footer();
}

function app_page_groups(): void {
    app_require_auth();
    $user = app_current_user();
    
    app_render_head('WhatsApp Groups');
    app_render_dashboard_start($user);
    
    // Get user's WhatsApp sessions and groups
    $sessions = app_whatsapp_get_user_sessions($user['id']);
    $groups = app_whatsapp_get_user_groups($user['id']);
    
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
                         <?php if (app_can_create_group($user)): ?>
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
                                             <div class="mb-1"><?= htmlspecialchars($message['content']) ?></div>
                                             <?php if ($message['media_url']): ?>
                                                 <div class="mt-1">
                                                     <img src="<?= htmlspecialchars($message['media_url']) ?>" 
                                                          class="img-fluid rounded" 
                                                          style="max-height: 150px;">
                                                     <?php if ($message['media_caption']): ?>
                                                         <div class="small mt-1"><?= htmlspecialchars($message['media_caption']) ?></div>
                                                     <?php endif; ?>
                                                 </div>
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
    <script src="/js/realtime.js"></script>
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
        
         const selectedGroupId = document.querySelector('#messageForm')?.dataset.groupId;
         
         // Debug: Log group selection
         console.log('Group selected:', {
             groupId: selectedGroupId,
             groupName: document.querySelector('h6.fw-bold')?.textContent,
             hasMessagesContainer: !!document.getElementById('messagesList'),
             messageCount: document.querySelectorAll('#messagesList .message').length
         });
         
         if (selectedGroupId) {
             // Initialize real-time client
             const realtime = new RealtimeClient(<?= $user['id'] ?>);
             
              // Handle new messages
              realtime.on('new_message', function(data) {
                  console.log('New message received:', data);
                  if (data.group_id == selectedGroupId) {
                      addMessageToChat(data);
                      scrollToBottom();
                      updateUnreadCount(data.group_id);
                  }
              });
              
              // Handle message sent confirmation
              realtime.on('message_sent', function(data) {
                  console.log('Message sent confirmation:', data);
                  if (data.group_id == selectedGroupId) {
                      // Message was successfully sent to server
                      // We'll wait for the actual message to appear via new_message event
                      // But we can release grey-out state if it's still active
                      setMessageSendingState(false);
                  }
              });
             
             // Start polling
             realtime.startPolling();
            
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
             async function loadMessages() {
                 try {
                     console.log('Loading messages for group:', selectedGroupId);
                     const response = await fetch(`/api/whatsapp/groups/${selectedGroupId}/messages?limit=50`);
                     
                     if (!response.ok) {
                         console.error('API error:', response.status, response.statusText);
                         throw new Error(`API error: ${response.status}`);
                     }
                     
                     const result = await response.json();
                     
                     console.log('Messages API response:', result);
                     
                     if (result.success && result.messages && result.messages.length > 0) {
                         const messagesList = document.getElementById('messagesList');
                         if (messagesList) {
                             // Clear existing messages (from server-side render)
                             messagesList.innerHTML = '';
                             
                             // Add messages in chronological order
                             result.messages.forEach(message => {
                                 addMessageToChat(message);
                             });
                             
                             console.log(`Loaded ${result.messages.length} messages`);
                         }
                     } else {
                         console.log('No messages found or API error:', result.message || 'No messages');
                         
                         // Show informative message
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
                 } catch (error) {
                     console.error('Error loading messages:', error);
                 }
             }
             
             // Load messages on page load
             loadMessages();
             
             // Auto-scroll to bottom
             function scrollToBottom() {
                 const container = document.getElementById('messagesContainer');
                 if (container) {
                     container.scrollTop = container.scrollHeight;
                 }
             }
             
             // Initial scroll
             setTimeout(scrollToBottom, 100);
        }
        
        // Create group functionality
        document.getElementById('createGroupBtn')?.addEventListener('click', async function() {
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
        
          function addMessageToChat(data) {
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
                      const tempContent = tempMsg.querySelector('.mb-1')?.textContent;
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
              
              messageDiv.innerHTML = `
                  <div class="d-flex ${data.is_from_me ? 'justify-content-end' : ''} align-items-end">
                      ${!data.is_from_me ? `
                          <div class="me-2">
                              <div class="avatar-xs bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; font-size: 12px;">
                                  ${(data.sender_name?.charAt(0) || data.sender_number?.charAt(0) || '?').toUpperCase()}
                              </div>
                          </div>
                      ` : ''}
                      <div class="${data.is_from_me ? 'bg-primary text-white message-bubble sent' : 'bg-light message-bubble received'} px-3 py-2" style="max-width: 65%;">
                          ${!data.is_from_me ? `
                              <div class="small fw-bold mb-1">${escapeHtml(data.sender_name || data.sender_number)}</div>
                          ` : ''}
                          <div class="mb-1">${escapeHtml(data.content || '')}</div>
                          ${data.media_url ? `
                              <div class="mt-1">
                                  <img src="${escapeHtml(data.media_url)}" class="img-fluid rounded" style="max-height: 150px;">
                                  ${data.media_caption ? `
                                      <div class="small mt-1">${escapeHtml(data.media_caption)}</div>
                                  ` : ''}
                              </div>
                          ` : ''}
                          <div class="small text-end ${data.is_from_me ? 'text-white-50' : 'text-muted'}">
                              ${timeStr}
                          </div>
                      </div>
                  </div>
              `;
              
              messagesList.appendChild(messageDiv);
              scrollToBottom();
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
            
            messageDiv.innerHTML = `
                <div class="d-flex ${data.is_from_me ? 'justify-content-end' : ''} align-items-end">
                    <div class="${data.is_from_me ? 'bg-primary text-white message-bubble sent' : 'bg-light message-bubble received'} px-3 py-2" style="max-width: 65%; ${data.is_temp ? 'opacity: 0.8;' : ''}">
                        <div class="mb-1">${escapeHtml(data.content || '')}</div>
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
            scrollToBottom();
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
                <h5 class="mb-0">Generate Invitation</h5>
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
                                    </td>
                                    <td>
                                        <?php 
                                        $sessionCount = app_whatsapp_count_user_sessions($u['id']);
                                        $maxSessions = app_tier_limits()[$u['tier']]['max_sessions'] ?? 1;
                                        ?>
                                        <span class="<?= $sessionCount >= $maxSessions ? 'text-danger' : 'text-success' ?>">
                                            <?= $sessionCount ?> / <?= $maxSessions ?>
                                        </span>
                                    </td>
                                    <td><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                                    <td>
                                         <?php if ($user['role'] === 'superadmin' && $u['role'] !== 'superadmin'): ?>
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

// Helper functions for user management
function app_get_all_users(): array {
    $pdo = app_db();
    $stmt = $pdo->prepare("
        SELECT id, name, email, role, tier, created_at 
        FROM users 
        ORDER BY created_at DESC
    ");
    $stmt->execute();
    return $stmt->fetchAll();
}

function app_update_user_tier(int $userId, string $tier): bool {
    $pdo = app_db();
    $stmt = $pdo->prepare("
        UPDATE users 
        SET tier = :tier, 
            max_sessions = CASE 
                WHEN :tier = 'basic' THEN 1
                WHEN :tier = 'business' THEN 3
                WHEN :tier = 'enterprise' THEN 5
                ELSE 1
            END
        WHERE id = :id
    ");
    return $stmt->execute(['tier' => $tier, 'id' => $userId]);
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