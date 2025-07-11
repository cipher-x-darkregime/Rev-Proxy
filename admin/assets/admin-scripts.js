// Sidebar collapse/expand functionality
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.getElementById('sidebarToggleBtn');
    const mainContent = document.querySelector('.main-content');
    
    if (toggleBtn && sidebar && mainContent) {
        toggleBtn.addEventListener('click', function() {
            sidebar.classList.toggle('sidebar-collapsed');
            mainContent.classList.toggle('sidebar-collapsed-main');
        });
    }
});

// Universal message popup function
function showMessage(message, type = 'danger') {
    const popup = document.createElement('div');
    popup.className = `alert alert-${type}`;
    popup.style.position = 'fixed';
    popup.style.top = '20px';
    popup.style.right = '-300px';
    popup.style.zIndex = '9999';
    popup.style.transition = 'right 0.3s ease-in-out';
    popup.style.padding = '15px 20px';
    popup.style.borderRadius = '8px 0 0 8px';
    popup.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.15)';
    popup.style.display = 'flex';
    popup.style.alignItems = 'center';
    popup.style.justifyContent = 'space-between';
    
    // Set background based on type
    if (type === 'success') {
        popup.style.background = 'linear-gradient(135deg, #d4edda, #c3e6cb)';
        popup.style.color = '#155724';
    } else if (type === 'info') {
        popup.style.background = 'linear-gradient(135deg, #d1ecf1, #bee5eb)';
        popup.style.color = '#0c5460';
    } else {
        popup.style.background = 'linear-gradient(135deg, #f8f9fa, #e9ecef)';
        popup.style.color = '#212529';
    }
    
    popup.style.fontWeight = '500';
    popup.style.fontSize = '1rem';
    popup.style.animation = 'fadeIn 0.3s ease-in-out';
    popup.innerHTML = `<span>${message}</span>`;
    document.body.appendChild(popup);
    
    setTimeout(() => {
        popup.style.right = '0';
    }, 10);
    
    setTimeout(() => {
        popup.style.right = '-300px';
        setTimeout(() => {
            popup.remove();
        }, 300);
    }, 3000);
}

// Add animation keyframes
const style = document.createElement('style');
style.textContent = `
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
`;
document.head.appendChild(style);

// AJAX form submission helper
function submitAjaxForm(formData, successCallback = null, errorCallback = null) {
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(text => {
        try {
            const data = JSON.parse(text);
            if (data.success) {
                showMessage(data.message, 'success');
                if (successCallback) successCallback(data);
            } else {
                showMessage(data.message, 'danger');
                if (errorCallback) errorCallback(data);
            }
        } catch (e) {
            showMessage('Server returned invalid response: ' + text.substring(0, 100), 'danger');
            if (errorCallback) errorCallback(e);
        }
    })
    .catch(error => {
        showMessage('Error submitting form: ' + error.message, 'danger');
        if (errorCallback) errorCallback(error);
    });
}

// Modal form reset helper
function resetModalForm(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.addEventListener('hidden.bs.modal', function() {
            const form = modal.querySelector('form');
            if (form) form.reset();
            // Remove any visible showMessage popup
            const popup = document.querySelector('.alert[style*="fixed"]');
            if (popup) popup.remove();
        });
    }
}

// Delete confirmation helper
function confirmDelete(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

// Toggle status helper
function toggleStatus(itemId, newStatus, action, successMessage = 'Status updated successfully') {
    const formData = new FormData();
    formData.append('ajax_action', action);
    formData.append('item_id', itemId);
    formData.append('new_status', newStatus);
    
    submitAjaxForm(formData, 
        (data) => {
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        }
    );
}

// Add item helper
function addItem(formId, action, successCallback = null) {
    const form = document.getElementById(formId);
    if (!form) return;
    
    const formData = new FormData(form);
    formData.append('ajax_action', action);
    
    submitAjaxForm(formData, 
        (data) => {
            const modal = bootstrap.Modal.getInstance(document.querySelector(`#${formId.replace('Form', 'Modal')}`));
            if (modal) modal.hide();
            if (successCallback) successCallback(data);
            else {
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            }
        }
    );
}

// Delete item helper
function deleteItem(itemId, itemName, action, successCallback = null) {
    confirmDelete(`Are you sure you want to delete "${itemName}"?`, () => {
        const formData = new FormData();
        formData.append('ajax_action', action);
        formData.append('item_id', itemId);
        
        submitAjaxForm(formData, 
            (data) => {
                if (successCallback) successCallback(data);
                else {
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                }
            }
        );
    });
} 