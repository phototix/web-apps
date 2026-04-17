document.addEventListener('DOMContentLoaded', () => {
    const management = document.getElementById('pages-management');
    if (!management) {
        return;
    }

    const modalEl = document.getElementById('pageModal');
    const form = document.getElementById('pageForm');
    const createBtn = document.getElementById('createPageBtn');
    const modalLabel = document.getElementById('pageModalLabel');
    const idField = document.getElementById('page-id');
    const tokenField = document.getElementById('page-token');
    const titleField = document.getElementById('page-title');
    const publicField = document.getElementById('page-public');

    if (!modalEl || !form || !createBtn || !tokenField || !titleField || !publicField || !modalLabel) {
        return;
    }

    const modal = window.bootstrap ? new bootstrap.Modal(modalEl) : null;

    const apiRequest = async (url, method, payload) => {
        const response = await fetch(url, {
            method,
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'same-origin',
            body: payload ? JSON.stringify(payload) : undefined
        });

        let data = null;
        try {
            data = await response.json();
        } catch (error) {
            data = null;
        }

        const success = response.ok && data && data.success === true;
        return { success, data };
    };

    const openCreateModal = () => {
        idField.value = '';
        tokenField.value = '';
        titleField.value = '';
        publicField.checked = false;
        modalLabel.textContent = 'Create Page';
        modal?.show();
    };

    const openEditModal = (button) => {
        idField.value = button.dataset.pageId || '';
        tokenField.value = button.dataset.pageToken || '';
        titleField.value = button.dataset.pageTitle || '';
        publicField.checked = button.dataset.pagePublic === '1';
        modalLabel.textContent = 'Edit Page';
        modal?.show();
    };

    const showError = (message) => {
        if (window.Swal) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: message
            });
        } else {
            alert(message);
        }
    };

    const showSuccess = (message) => {
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

    createBtn.addEventListener('click', openCreateModal);

    document.querySelectorAll('.page-edit-btn').forEach((button) => {
        button.addEventListener('click', () => openEditModal(button));
    });

    document.querySelectorAll('.page-public-toggle').forEach((toggle) => {
        toggle.addEventListener('change', async (event) => {
            const target = event.currentTarget;
            const pageId = target.dataset.pageId;
            if (!pageId) {
                return;
            }

            const { success, data } = await apiRequest(`/api/pages/${pageId}`, 'PUT', {
                is_public: target.checked
            });

            if (!success) {
                target.checked = !target.checked;
                showError((data && data.message) ? data.message : 'Failed to update page');
                return;
            }

            window.location.reload();
        });
    });

    document.querySelectorAll('.page-delete-btn').forEach((button) => {
        button.addEventListener('click', () => {
            const pageId = button.dataset.pageId;
            const token = button.dataset.pageToken || 'this page';

            if (!pageId) {
                return;
            }

            const confirmDelete = () => apiRequest(`/api/pages/${pageId}`, 'DELETE')
                .then(({ success, data }) => {
                    if (!success) {
                        showError((data && data.message) ? data.message : 'Failed to delete page');
                        return;
                    }
                    showSuccess('Page deleted');
                    window.location.reload();
                });

            if (window.Swal) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Delete Page',
                    text: `Delete ${token}? This will remove tracking and delete files on disk.`,
                    showCancelButton: true,
                    confirmButtonText: 'Delete',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        confirmDelete();
                    }
                });
            } else if (confirm('Delete this page?')) {
                confirmDelete();
            }
        });
    });

    document.querySelectorAll('.page-share-btn').forEach((button) => {
        button.addEventListener('click', async () => {
            const token = button.dataset.pageToken;
            if (!token) {
                return;
            }

            const baseUrlRaw = management.dataset.baseUrl || window.location.origin || '';
            const baseUrl = baseUrlRaw.replace(/\/$/, '');
            const shareUrl = `${baseUrl}/pages/${token}`;

            const copyFallback = () => {
                const temp = document.createElement('textarea');
                temp.value = shareUrl;
                document.body.appendChild(temp);
                temp.select();
                let copied = false;
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
                try {
                    await navigator.clipboard.writeText(shareUrl);
                    showSuccess('Link copied');
                } catch (error) {
                    copyFallback();
                }
            } else {
                copyFallback();
            }
        });
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const pageId = idField.value.trim();
        const payload = {
            token: tokenField.value.trim(),
            title: titleField.value.trim(),
            is_public: publicField.checked
        };
        if (!payload.title) {
            showError('Title is required.');
            return;
        }

        let url = '/api/pages';
        let method = 'POST';

        if (pageId) {
            url = `/api/pages/${pageId}`;
            method = 'PUT';
            delete payload.token;
        }

        const { success, data } = await apiRequest(url, method, payload);
        if (!success) {
            showError((data && data.message) ? data.message : 'Failed to save page');
            return;
        }

        modal?.hide();
        showSuccess('Page saved');
        window.location.reload();
    });
});
