/**
 * Category Management JavaScript
 * Handles category CRUD operations with hierarchical display
 */

class CategoryManager {
    constructor() {
        this.categories = [];
        this.currentCategoryId = null;
        this.modal = null;
        this.init();
    }

    init() {
        // Initialize when DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.setup());
        } else {
            this.setup();
        }
    }

    setup() {
        // Only run on category management page
        if (!document.getElementById('category-management-container')) {
            return;
        }

        console.log('Category Manager initialized');
        
        // Load categories
        this.loadCategories();
        
        // Set up event listeners
        this.setupEventListeners();
        
        // Initialize modal if it exists
        const modalElement = document.getElementById('categoryModal');
        if (modalElement) {
            try {
                this.modal = new bootstrap.Modal(modalElement);
                console.log('Modal initialized successfully');
            } catch (error) {
                console.error('Failed to initialize modal:', error);
                // Don't fail completely, we'll try to initialize later if needed
            }
        } else {
            console.warn('Modal element not found during initialization');
        }
    }

    setupEventListeners() {
        // Add category button
        const addBtn = document.getElementById('addCategoryBtn');
        if (addBtn) {
            addBtn.addEventListener('click', () => this.openModal());
        }

        // Save category button
        const saveBtn = document.getElementById('saveCategoryBtn');
        if (saveBtn) {
            saveBtn.addEventListener('click', () => this.saveCategory());
        }

        // Color picker synchronization
        this.setupColorPicker();
    }

    setupColorPicker() {
        const colorInput = document.getElementById('categoryColor');
        const colorText = document.getElementById('categoryColorText');
        
        if (colorInput && colorText) {
            colorInput.addEventListener('input', function() {
                colorText.value = this.value;
            });
            
            colorText.addEventListener('input', function() {
                if (this.value.match(/^#[0-9A-Fa-f]{6}$/)) {
                    colorInput.value = this.value;
                }
            });
            
            colorText.addEventListener('change', function() {
                if (!this.value.match(/^#[0-9A-Fa-f]{6}$/)) {
                    this.value = '#6c757d';
                    colorInput.value = '#6c757d';
                }
            });
        }
    }

    async loadCategories() {
        const container = document.getElementById('category-management-container');
        if (!container) return;

        try {
            // Show loading state
            container.innerHTML = `
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">Loading categories...</p>
                </div>
            `;
            
            // Add timeout to prevent hanging
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 10000); // 10 second timeout
            
            const response = await fetch('/api/whatsapp/categories/tree', {
                signal: controller.signal
            });
            
            clearTimeout(timeoutId);
            const data = await response.json();
            
            if (data.success) {
                this.categories = data.data.categories || [];
                this.renderCategories();
            } else {
                this.showError('Failed to load categories: ' + (data.message || 'Unknown error'));
                // Show empty state on error
                container.innerHTML = this.getEmptyStateHTML();
            }
        } catch (error) {
            console.error('Error loading categories:', error);
            if (error.name === 'AbortError') {
                this.showError('Request timed out. Please check your connection and try again.');
            } else {
                this.showError('Failed to load categories. Please try again.');
            }
            // Show empty state on error
            const container = document.getElementById('category-management-container');
            if (container) {
                container.innerHTML = this.getEmptyStateHTML();
            }
        }
    }

    renderCategories() {
        const container = document.getElementById('category-management-container');
        if (!container) return;

        if (!this.categories || this.categories.length === 0) {
            container.innerHTML = this.getEmptyStateHTML();
            return;
        }

        // Flatten categories for table display
        const flattened = this.flattenCategoryTree(this.categories);
        
        container.innerHTML = this.getTableHTML(flattened);
        
        // Add event listeners to action buttons
        this.addTableEventListeners();
    }

    flattenCategoryTree(tree, level = 0, result = [], visited = new Set()) {
        // Safety check: prevent infinite recursion
        if (level > 20) {
            console.warn('Maximum recursion depth reached in category tree');
            return result;
        }
        
        tree.forEach(category => {
            // Check for circular references
            if (visited.has(category.id)) {
                console.warn(`Circular reference detected in category tree: category ${category.id} already visited`);
                return;
            }
            
            visited.add(category.id);
            
            // Add level for indentation
            const categoryCopy = { ...category };
            categoryCopy.level = level;
            result.push(categoryCopy);
            
            // Recursively flatten subcategories
            if (categoryCopy.subcategories && categoryCopy.subcategories.length > 0) {
                this.flattenCategoryTree(categoryCopy.subcategories, level + 1, result, visited);
            }
            
            visited.delete(category.id);
        });
        return result;
    }

    getParentChain(category, allCategories) {
        let chain = [];
        let current = category;
        
        // Build chain from current to root
        while (current && current.parent_id) {
            const parent = allCategories.find(c => c.id == current.parent_id);
            if (parent) {
                chain.unshift(parent.name);
                current = parent;
            } else {
                break;
            }
        }
        
        return chain.length > 0 ? chain.join(' → ') : '-';
    }

    getEmptyStateHTML() {
        return `
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-tags fa-3x text-muted mb-3"></i>
                    <h5>No Categories Yet</h5>
                    <p class="text-muted">Create your first category to organize messages and groups.</p>
                    <button class="btn btn-primary mt-2" id="addFirstCategoryBtn">
                        <i class="fas fa-plus me-2"></i>Create First Category
                    </button>
                </div>
            </div>
        `;
    }

    getTableHTML(categories) {
        let rowsHTML = '';
        
        categories.forEach(category => {
            // Calculate indentation
            const indentPx = (category.level || 0) * 20;
            
            // Get parent chain
            const parentChain = this.getParentChain(category, categories);
            
            // Calculate usage
            const totalUsage = (category.message_count || 0) + (category.group_count || 0);
            const usageBadge = totalUsage > 0 ? 
                `<span class="badge bg-info">${totalUsage}</span>` : 
                `<span class="badge bg-light text-muted">0</span>`;
            
            // Subcategory badge
            const subcategoryBadge = category.subcategory_count > 0 ?
                `<span class="badge bg-warning ms-1">${category.subcategory_count} sub</span>` : '';
            
            // Format trigger content
            const triggerContent = this.formatTriggerContent(category.keywords, category.prompt);
            
            rowsHTML += `
                <tr data-category-id="${category.id}">
                    <td>
                        <div style="margin-left: ${indentPx}px;">
                            <i class="fas ${category.subcategory_count > 0 ? 'fa-folder text-warning' : 'fa-tag'}" 
                               style="color: ${category.color || '#6c757d'}; margin-right: 8px;"></i>
                            ${this.escapeHtml(category.name)}
                            ${subcategoryBadge}
                        </div>
                    </td>
                    <td>${this.escapeHtml(category.description || '-')}</td>
                    <td>${triggerContent}</td>
                    <td>${parentChain}</td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-primary me-1 edit-btn" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger delete-btn" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
        });
        
        return `
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th width="20%">Name</th>
                            <th width="20%">Description</th>
                            <th width="25%">Trigger</th>
                            <th width="15%">Parent Category</th>
                            <th width="20%" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${rowsHTML}
                    </tbody>
                </table>
            </div>
        `;
    }

    addTableEventListeners() {
        // Edit buttons
        document.querySelectorAll('.edit-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const row = e.target.closest('tr');
                const categoryId = row.dataset.categoryId;
                this.editCategory(categoryId);
            });
        });

        // Delete buttons
        document.querySelectorAll('.delete-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const row = e.target.closest('tr');
                const categoryId = row.dataset.categoryId;
                this.deleteCategory(categoryId);
            });
        });

        // Add first category button
        const addFirstBtn = document.getElementById('addFirstCategoryBtn');
        if (addFirstBtn) {
            addFirstBtn.addEventListener('click', () => this.openModal());
        }
    }

    async openModal(categoryId = null) {
        try {
            this.currentCategoryId = categoryId;
            
            // Reset form
            this.resetForm();
            
            // Set modal title
            const modalTitle = document.getElementById('categoryModalLabel');
            if (modalTitle) {
                modalTitle.textContent = categoryId ? 'Edit Category' : 'Add Category';
            }
            
            // Set save button text
            const saveBtn = document.getElementById('saveCategoryBtn');
            if (saveBtn) {
                saveBtn.textContent = categoryId ? 'Update Category' : 'Save Category';
            }
            
            // Ensure categories are loaded
            if (this.categories.length === 0) {
                await this.loadCategories();
            }
            
            // If editing, load category data
            if (categoryId) {
                await this.loadCategoryForEdit(categoryId);
            } else {
                // For new category, populate parent dropdown
                await this.populateParentDropdown();
            }
            
            // Show modal
            if (this.modal) {
                this.modal.show();
            } else {
                console.error('Modal not initialized');
                // Try to initialize modal if it exists
                const modalElement = document.getElementById('categoryModal');
                if (modalElement) {
                    this.modal = new bootstrap.Modal(modalElement);
                    this.modal.show();
                } else {
                    this.showError('Modal element not found');
                }
            }
        } catch (error) {
            console.error('Error opening modal:', error);
            this.showError('Failed to open category modal. Please try again.');
        }
    }

    resetForm() {
        const form = document.getElementById('categoryForm');
        if (form) {
            form.reset();
            document.getElementById('categoryId').value = '';
            document.getElementById('categoryColor').value = '#6c757d';
            document.getElementById('categoryColorText').value = '#6c757d';
            document.getElementById('categorySortOrder').value = '0';
            document.getElementById('categoryKeywords').value = '';
            document.getElementById('categoryPrompt').value = '';
            
            // Clear validation
            document.getElementById('categoryName').classList.remove('is-invalid');
        }
    }

    async loadCategoryForEdit(categoryId) {
        try {
            // Add timeout to prevent hanging
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 10000); // 10 second timeout
            
            const response = await fetch(`/api/whatsapp/categories/${categoryId}?_=${Date.now()}`, {
                signal: controller.signal
            });
            
            clearTimeout(timeoutId);
            const data = await response.json();
            
            if (data.success && data.data.category) {
                const category = data.data.category;
                
                // Populate form
                document.getElementById('categoryId').value = category.id;
                document.getElementById('categoryName').value = category.name || '';
                document.getElementById('categoryDescription').value = category.description || '';
                document.getElementById('categoryKeywords').value = category.keywords || '';
                document.getElementById('categoryPrompt').value = category.prompt || '';
                document.getElementById('categoryColor').value = category.color || '#6c757d';
                document.getElementById('categoryColorText').value = category.color || '#6c757d';
                document.getElementById('categorySortOrder').value = category.sort_order || '0';
                
                // Populate parent dropdown, excluding current category
                await this.populateParentDropdown(categoryId);
                
                // Set parent value
                const parentSelect = document.getElementById('categoryParent');
                if (parentSelect && category.parent_id) {
                    parentSelect.value = category.parent_id;
                }
            } else {
                this.showError('Failed to load category: ' + (data.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error loading category:', error);
            if (error.name === 'AbortError') {
                this.showError('Request timed out. Please check your connection and try again.');
            } else {
                this.showError('Failed to load category. Please try again.');
            }
        }
    }

    async populateParentDropdown(excludeId = null) {
        const select = document.getElementById('categoryParent');
        if (!select) return;

        // Keep the "None" option
        select.innerHTML = '<option value="">None (Root Category)</option>';
        
        if (!this.categories || this.categories.length === 0) {
            return;
        }
        
        // Flatten with hierarchy display
        const flattened = this.flattenCategoryTree(this.categories);
        
        flattened.forEach(category => {
            // Don't include the category being edited or its descendants
            if (excludeId && (category.id == excludeId || this.isDescendant(category, excludeId, flattened))) {
                return;
            }
            
            const indent = '&nbsp;&nbsp;'.repeat(category.level || 0);
            const displayName = indent + this.escapeHtml(category.name);
            select.innerHTML += `<option value="${category.id}">${displayName}</option>`;
        });
    }

    isDescendant(category, parentId, allCategories) {
        let current = category;
        let depth = 0;
        const maxDepth = 50; // Safety limit
        
        while (current && current.parent_id && depth < maxDepth) {
            if (current.parent_id == parentId) {
                return true;
            }
            current = allCategories.find(c => c.id == current.parent_id);
            depth++;
        }
        
        if (depth >= maxDepth) {
            console.warn('Maximum depth reached while checking category hierarchy');
        }
        
        return false;
    }

    async saveCategory() {
        const form = document.getElementById('categoryForm');
        const categoryId = document.getElementById('categoryId').value;
        const nameInput = document.getElementById('categoryName');
        
        // Validate
        if (!nameInput.value.trim()) {
            nameInput.classList.add('is-invalid');
            nameInput.focus();
            return;
        }
        
        nameInput.classList.remove('is-invalid');
        
        // Prepare data
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());
        
        // Convert empty parent_id to null
        if (data.parent_id === '') {
            data.parent_id = null;
        }
        
        // Convert sort_order to integer
        data.sort_order = parseInt(data.sort_order) || 0;
        
        // Determine method and URL
        const method = categoryId ? 'PUT' : 'POST';
        const url = categoryId ? `/api/whatsapp/categories/${categoryId}` : '/api/whatsapp/categories';
        
        // Show loading state
        const saveBtn = document.getElementById('saveCategoryBtn');
        const originalText = saveBtn.textContent;
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...';
        
        try {
            const response = await fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Close modal
                if (this.modal) {
                    this.modal.hide();
                }
                
                // Show success message
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: categoryId ? 'Category updated successfully' : 'Category created successfully',
                    timer: 2000,
                    showConfirmButton: false
                });
                
                // Reload categories
                setTimeout(() => this.loadCategories(), 500);
            } else {
                this.showError('Failed to save category: ' + (result.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error saving category:', error);
            this.showError('Failed to save category. Please try again.');
        } finally {
            // Reset button state
            saveBtn.disabled = false;
            saveBtn.textContent = originalText;
        }
    }

    async deleteCategory(categoryId) {
        // First get category details
        try {
            const response = await fetch(`/api/whatsapp/categories/${categoryId}`);
            const data = await response.json();
            
            if (data.success && data.data.category) {
                const category = data.data.category;
                const hasSubcategories = category.subcategory_count > 0;
                const hasUsage = (category.message_count || 0) + (category.group_count || 0) > 0;
                
                let warningText = `Are you sure you want to delete the category "${this.escapeHtml(category.name)}"?`;
                
                if (hasSubcategories) {
                    warningText += `\n\n⚠️ This category has ${category.subcategory_count} subcategory(ies). Deleting it will also delete all subcategories.`;
                }
                
                if (hasUsage) {
                    const totalUsage = (category.message_count || 0) + (category.group_count || 0);
                    warningText += `\n\n⚠️ This category is assigned to ${totalUsage} item(s). They will be unassigned from this category.`;
                }
                
                const result = await Swal.fire({
                    title: 'Delete Category?',
                    text: warningText,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Yes, delete it!',
                    cancelButtonText: 'Cancel',
                    reverseButtons: true
                });
                
                if (result.isConfirmed) {
                    // Perform deletion
                    const deleteResponse = await fetch(`/api/whatsapp/categories/${categoryId}`, {
                        method: 'DELETE'
                    });
                    
                    const deleteResult = await deleteResponse.json();
                    
                    if (deleteResult.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Deleted!',
                            text: 'Category has been deleted.',
                            timer: 2000,
                            showConfirmButton: false
                        });
                        
                        // Reload categories
                        this.loadCategories();
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: deleteResult.message || 'Failed to delete category'
                        });
                    }
                }
            } else {
                this.showError('Failed to load category details for deletion.');
            }
        } catch (error) {
            console.error('Error deleting category:', error);
            this.showError('Failed to delete category. Please try again.');
        }
    }

    editCategory(categoryId) {
        this.openModal(categoryId);
    }

    showError(message) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: message,
            timer: 3000,
            showConfirmButton: false
        });
    }

    formatTriggerContent(keywords, prompt) {
        let content = '';
        
        if (keywords && keywords.trim()) {
            content += `<div class="mb-1"><strong>Keywords:</strong> ${this.escapeHtml(keywords)}</div>`;
        }
        
        if (prompt && prompt.trim()) {
            content += `<div><strong>Prompt:</strong> ${this.escapeHtml(prompt)}</div>`;
        }
        
        if (!content) {
            return '-';
        }
        
        return content;
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Initialize category manager when page loads
window.categoryManager = new CategoryManager();