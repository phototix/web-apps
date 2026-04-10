        function loadFolderContent(groupId) {
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
            
            // Load category tree
            fetch('/api/whatsapp/category-tree')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
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
            
            // Load messages and files for this group
            loadGroupMessages(groupId);
            
            function renderCategoryTree(categories) {
                if (!categories || categories.length === 0) {
                    categoryTree.innerHTML = `
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-tags fa-2x mb-2"></i>
                            <p>No categories yet</p>
                            <button class="btn btn-sm btn-outline-primary" id="add-category">
                                <i class="fas fa-plus me-1"></i> Add Category
                            </button>
                        </div>
                    `;
                    
                    document.getElementById('add-category').addEventListener('click', function() {
                        showAddCategoryModal();
                    });
                    return;
                }
                
                let html = '<div class="category-tree">';
                html += renderCategoryTreeItems(categories);
                html += '</div>';
                html += `
                    <div class="mt-3">
                        <button class="btn btn-sm btn-outline-primary w-100" id="add-category">
                            <i class="fas fa-plus me-1"></i> Add Category
                        </button>
                    </div>
                `;
                
                categoryTree.innerHTML = html;
                
                // Add click handlers for category items
                document.querySelectorAll('.category-item').forEach(item => {
                    item.addEventListener('click', function() {
                        const categoryId = this.getAttribute('data-category-id');
                        loadCategoryContent(groupId, categoryId);
                    });
                });
                
                document.getElementById('add-category').addEventListener('click', function() {
                    showAddCategoryModal();
                });
            }
            
            function renderCategoryTreeItems(items, level = 0) {
                let html = '';
                
                items.forEach(item => {
                    const hasChildren = item.subcategories && item.subcategories.length > 0;
                    const marginLeft = level * 20;
                    const totalItems = (item.message_count || 0) + (item.group_count || 0);
                    
                    html += `<div class="category-item mb-2" data-category-id="${item.id}">`;
                    html += `<div class="d-flex align-items-center justify-content-between p-2 border rounded" style="margin-left: ${marginLeft}px;">`;
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
            
            function loadGroupMessages(groupId, categoryId = null) {
                // Build API URL
                let url = `/api/whatsapp/group-messages?group_id=${encodeURIComponent(groupId)}&limit=50`;
                if (categoryId) {
                    url += `&category_id=${categoryId}`;
                }
                
                fetch(url)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            renderMessagesAndFiles(data.data.messages, groupId, categoryId);
                        } else {
                            messagesList.innerHTML = `
                                <div class="text-center text-muted py-4">
                                    <i class="fas fa-exclamation-triangle text-warning fa-2x mb-2"></i>
                                    <p>Failed to load messages</p>
                                    <button class="btn btn-sm btn-outline-primary" onclick="loadGroupMessages('${groupId}', ${categoryId ? `'${categoryId}'` : 'null'})">
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
                                <button class="btn btn-sm btn-outline-primary" onclick="loadGroupMessages('${groupId}', ${categoryId ? `'${categoryId}'` : 'null'})">
                                    <i class="fas fa-redo me-1"></i> Retry
                                </button>
                            </div>
                        `;
                    });
            }
            
            function loadCategoryContent(groupId, categoryId) {
                // Highlight selected category
                document.querySelectorAll('.category-item').forEach(item => {
                    item.classList.remove('selected');
                });
                document.querySelector(`.category-item[data-category-id="${categoryId}"]`).classList.add('selected');
                
                // Load messages for this category
                loadGroupMessages(groupId, categoryId);
            }
            
            function renderMessagesAndFiles(messages, groupId, categoryId) {
                if (!messages || messages.length === 0) {
                    const noItemsText = categoryId 
                        ? 'No messages or files in this category'
                        : 'No messages or files in this group yet';
                    
                    messagesList.innerHTML = `
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-comments fa-2x mb-2"></i>
                            <p>${noItemsText}</p>
                        </div>
                    `;
                    return;
                }
                
                let html = '<div class="message-file-list">';
                
                // Separate messages and files
                const textMessages = messages.filter(m => m.message_type === 'chat' || !m.message_type);
                const files = messages.filter(m => m.message_type && m.message_type !== 'chat');
                
                // Show all items by default
                const filter = document.querySelector('[data-filter].active')?.getAttribute('data-filter') || 'all';
                
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
            }
            
            function renderMessageItem(message) {
                // Convert timestamp from milliseconds to Date object
                const timestamp = new Date(parseInt(message.timestamp));
                const timeStr = timestamp.toLocaleDateString() + ' ' + timestamp.toLocaleTimeString();
                
                let html = '<div class="message-item mb-3 p-3 border rounded">';
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
                html += `<h6 class="mb-1">${escapeHtml(message.sender_name || message.sender_number)}</h6>`;
                html += `<small class="text-muted">${timeStr}</small>`;
                html += '</div>';
                
                // Message content
                let content = escapeHtml(message.content || '');
                if (content.length > 200) {
                    content = content.substring(0, 200) + '...';
                }
                html += `<p class="mb-1">${content}</p>`;
                
                // Category badge
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
                const timestamp = new Date(parseInt(file.timestamp));
                const timeStr = timestamp.toLocaleDateString() + ' ' + timestamp.toLocaleTimeString();
                
                let html = '<div class="file-item mb-3 p-3 border rounded">';
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
                
                html += `<h6 class="mb-1">${fileName}</h6>`;
                html += `<small class="text-muted">${timeStr}</small>`;
                html += '</div>';
                
                // Sender info
                html += `<p class="mb-1 text-muted">From: ${escapeHtml(file.sender_name || file.sender_number)}</p>`;
                
                // Category badge
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
            
            function showAddCategoryModal() {
                const categoryName = prompt('Enter category name:');
                if (!categoryName) return;
                
                const categoryColor = prompt('Enter category color (hex code, e.g., #007bff):', '#6c757d');
                if (!categoryColor) return;
                
                const parentCategory = confirm('Is this a subcategory? Click OK to select parent category, Cancel for root category.');
                let parentId = null;
                
                if (parentCategory) {
                    const parentName = prompt('Enter parent category name (must exist):');
                    // In a real app, you would look up the parent ID from existing categories
                    // For now, we'll just show a message
                    alert('Parent category selection would be implemented with a proper UI');
                    return;
                }
                
                // Create category via API
                fetch('/api/whatsapp/categories', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        name: categoryName,
                        color: categoryColor,
                        parent_id: parentId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Category created successfully!');
                        // Reload category tree
                        loadFolderContent(groupId);
                    } else {
                        alert('Failed to create category: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error creating category:', error);
                    alert('Error creating category');
                });
            }
            
            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }
        }