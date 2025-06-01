// Apple-Style Enhanced Interactions

// Tab functionality with smooth transitions
function showTab(tabName) {
    // Remove active states
    document.querySelectorAll('.tab').forEach(t => {
        t.classList.remove('active');
        t.style.transform = 'scale(1)';
    });
    document.querySelectorAll('.tab-content').forEach(c => {
        c.classList.remove('active');
        c.style.opacity = '0';
    });
    
    // Add active states with animation
    const activeTab = document.querySelector(`[onclick="showTab('${tabName}')"]`);
    const activeContent = document.getElementById(tabName);
    
    if (activeTab && activeContent) {
        activeTab.classList.add('active');
        
        // Apple-style spring animation
        activeTab.style.transform = 'scale(1.02)';
        setTimeout(() => {
            activeTab.style.transform = 'scale(1)';
        }, 100);
        
        // Fade in content
        setTimeout(() => {
            activeContent.classList.add('active');
            activeContent.style.opacity = '1';
        }, 50);
    }
}

// Enhanced Modal functionality with Apple-style animations
function showModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'block';
        // Force reflow
        modal.offsetHeight;
        modal.style.animation = 'fadeIn 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94)';
        
        const modalContent = modal.querySelector('.modal-content');
        if (modalContent) {
            modalContent.style.animation = 'slideUp 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94)';
        }
        
        // Focus management for accessibility
        const firstFocusable = modal.querySelector('input, button, select, textarea, [tabindex]:not([tabindex="-1"])');
        if (firstFocusable) {
            setTimeout(() => firstFocusable.focus(), 100);
        }
    }
}

function hideModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        const modalContent = modal.querySelector('.modal-content');
        
        // Apple-style fade out
        if (modalContent) {
            modalContent.style.animation = 'fadeOut 0.2s ease-in forwards';
        }
        modal.style.animation = 'fadeOut 0.2s ease-in forwards';
        
        setTimeout(() => {
            modal.style.display = 'none';
        }, 200);
    }
}

// Apple-style button interactions
function addButtonEffects() {
    document.querySelectorAll('.btn').forEach(button => {
        button.addEventListener('mousedown', function() {
            this.style.transform = 'scale(0.95)';
        });
        
        button.addEventListener('mouseup', function() {
            this.style.transform = 'scale(1)';
        });
        
        button.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1)';
        });
    });
}

// Enhanced file upload with Apple-style feedback
function enhanceFileUpload() {
    const fileInput = document.getElementById('file');
    const fileUpload = document.querySelector('.file-upload');
    
    if (fileInput && fileUpload) {
        // Click to upload
        fileUpload.addEventListener('click', () => fileInput.click());
        
        // Drag and drop enhancement
        fileUpload.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.style.borderColor = '#007aff';
            this.style.background = 'rgba(0, 122, 255, 0.1)';
            this.style.transform = 'scale(1.02)';
        });
        
        fileUpload.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.style.borderColor = '#d2d2d7';
            this.style.background = 'rgba(255, 255, 255, 0.5)';
            this.style.transform = 'scale(1)';
        });
        
        fileUpload.addEventListener('drop', function(e) {
            e.preventDefault();
            this.style.borderColor = '#d2d2d7';
            this.style.background = 'rgba(255, 255, 255, 0.5)';
            this.style.transform = 'scale(1)';
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                updateFileUploadDisplay(files[0]);
            }
        });
        
        // File selection feedback
        fileInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                updateFileUploadDisplay(this.files[0]);
            }
        });
    }
}

function updateFileUploadDisplay(file) {
    const fileUpload = document.querySelector('.file-upload');
    if (fileUpload) {
        // Apple-style success animation
        fileUpload.style.borderColor = '#30d158';
        fileUpload.style.background = 'rgba(48, 209, 88, 0.1)';
        fileUpload.innerHTML = `
            <p style="color: #30d158; font-weight: 600;">✓ File Selected</p>
            <p style="color: #1d1d1f; margin: 8px 0;"><strong>${file.name}</strong></p>
            <small style="color: #86868b;">${formatFileSize(file.size)} • ${file.type || 'Unknown type'}</small>
        `;
        
        // Subtle success animation
        fileUpload.style.transform = 'scale(1.02)';
        setTimeout(() => {
            fileUpload.style.transform = 'scale(1)';
        }, 200);
    }
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// Apple-style form validation
function enhanceFormValidation() {
    document.querySelectorAll('input, select, textarea').forEach(field => {
        field.addEventListener('blur', function() {
            validateField(this);
        });
        
        field.addEventListener('input', function() {
            // Clear validation styling on input
            this.style.borderColor = '#d2d2d7';
            this.style.boxShadow = 'none';
        });
    });
}

function validateField(field) {
    const isValid = field.checkValidity();
    
    if (!isValid) {
        field.style.borderColor = '#ff3b30';
        field.style.boxShadow = '0 0 0 4px rgba(255, 59, 48, 0.1)';
    } else {
        field.style.borderColor = '#30d158';
        field.style.boxShadow = '0 0 0 4px rgba(48, 209, 88, 0.1)';
        
        // Remove success styling after 2 seconds
        setTimeout(() => {
            field.style.borderColor = '#d2d2d7';
            field.style.boxShadow = 'none';
        }, 2000);
    }
}

// Apple-style loading states
function showLoadingState(button) {
    const originalText = button.textContent;
    button.disabled = true;
    button.style.opacity = '0.6';
    button.innerHTML = `
        <span style="display: inline-block; width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3); border-top-color: white; border-radius: 50%; animation: spin 1s linear infinite; margin-right: 8px;"></span>
        Loading...
    `;
    
    return originalText;
}

function hideLoadingState(button, originalText) {
    button.disabled = false;
    button.style.opacity = '1';
    button.textContent = originalText;
}

// Keyboard accessibility enhancements
function enhanceKeyboardNavigation() {
    // Tab focus styling
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Tab') {
            document.body.classList.add('keyboard-navigating');
        }
    });
    
    document.addEventListener('mousedown', function() {
        document.body.classList.remove('keyboard-navigating');
    });
    
    // Modal keyboard controls
    document.addEventListener('keydown', function(e) {
        const visibleModal = document.querySelector('.modal[style*="display: block"]');
        if (visibleModal && e.key === 'Escape') {
            const modalId = visibleModal.id;
            hideModal(modalId);
        }
    });
}

// Smooth scroll for anchor links
function enhanceSmoothScrolling() {
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
}

// Auto-refresh with visual feedback
function setupAutoRefresh() {
    if (window.location.search.includes('action=dashboard')) {
        let countdown = 30;
        const refreshIndicator = document.createElement('div');
        refreshIndicator.style.cssText = `
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 12px 16px;
            border-radius: 20px;
            font-size: 14px;
            z-index: 1000;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        `;
        
        document.body.appendChild(refreshIndicator);
        
        const updateCountdown = () => {
            refreshIndicator.textContent = `Auto-refresh in ${countdown}s`;
            countdown--;
            
            if (countdown < 0) {
                refreshIndicator.textContent = 'Refreshing...';
                setTimeout(() => window.location.reload(), 500);
            }
        };
        
        updateCountdown();
        setInterval(updateCountdown, 1000);
    }
}

// Initialize Apple-style enhancements
document.addEventListener('DOMContentLoaded', function() {
    addButtonEffects();
    enhanceFileUpload();
    enhanceFormValidation();
    enhanceKeyboardNavigation();
    enhanceSmoothScrolling();
    setupAutoRefresh();
    
    // Add Apple-style focus indicators
    const style = document.createElement('style');
    style.textContent = `
        .keyboard-navigating *:focus {
            outline: 2px solid #007aff;
            outline-offset: 2px;
            border-radius: 4px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @keyframes fadeOut {
            from { opacity: 1; transform: scale(1); }
            to { opacity: 0; transform: scale(0.95); }
        }
    `;
    document.head.appendChild(style);
});

// Apple-style notification system
function showNotification(message, type = 'info', duration = 3000) {
    const notification = document.createElement('div');
    const colors = {
        success: { bg: 'rgba(48, 209, 88, 0.9)', text: 'white' },
        error: { bg: 'rgba(255, 59, 48, 0.9)', text: 'white' },
        info: { bg: 'rgba(0, 122, 255, 0.9)', text: 'white' }
    };
    
    notification.style.cssText = `
        position: fixed;
        top: 80px;
        right: 24px;
        background: ${colors[type].bg};
        color: ${colors[type].text};
        padding: 16px 20px;
        border-radius: 12px;
        font-size: 15px;
        font-weight: 500;
        z-index: 1001;
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        animation: slideInRight 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        max-width: 300px;
    `;
    
    notification.textContent = message;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.3s ease-in forwards';
        setTimeout(() => notification.remove(), 300);
    }, duration);
}

// Add slide animations for notifications
const notificationStyles = document.createElement('style');
notificationStyles.textContent = `
    @keyframes slideInRight {
        from { 
            opacity: 0;
            transform: translateX(100px);
        }
        to { 
            opacity: 1;
            transform: translateX(0);
        }
    }
    
    @keyframes slideOutRight {
        from { 
            opacity: 1;
            transform: translateX(0);
        }
        to { 
            opacity: 0;
            transform: translateX(100px);
        }
    }
`;
document.head.appendChild(notificationStyles);

// Enhanced job status functions
function showApprovalModal(jobId, printMethod) {
    document.getElementById('approve_job_id').value = jobId;
    document.getElementById('approve_print_method').value = printMethod;
    showModal('approvalModal');
}

function showRejectionModal(jobId) {
    document.getElementById('reject_job_id').value = jobId;
    showModal('rejectionModal');
}

function updateJobStatus(jobId, newStatus) {
    if (confirm('Update job status to: ' + newStatus + '?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '?action=status_update';
        form.style.display = 'none';
        
        const jobIdInput = document.createElement('input');
        jobIdInput.name = 'job_id';
        jobIdInput.value = jobId;
        
        const statusInput = document.createElement('input');
        statusInput.name = 'status';
        statusInput.value = newStatus;
        
        form.appendChild(jobIdInput);
        form.appendChild(statusInput);
        document.body.appendChild(form);
        form.submit();
    }
}

// Enhanced color update function with Apple-style transitions
function updateColors() {
    const methodSelect = document.getElementById('print_method');
    const colorSelect = document.getElementById('color');
    const method = methodSelect.value;
    const methods = typeof PRINT_METHODS !== 'undefined' ? PRINT_METHODS : {};
    
    // Clear current options with fade effect
    colorSelect.style.opacity = '0.5';
    colorSelect.disabled = true;
    
    setTimeout(() => {
        colorSelect.innerHTML = '<option value="">Choose a color</option>';
        
        if (method && methods[method]) {
            // Apple-style option building
            methods[method].colors.forEach(color => {
                const option = document.createElement('option');
                option.value = color;
                option.textContent = color;
                colorSelect.appendChild(option);
            });
            
            colorSelect.disabled = false;
            colorSelect.style.opacity = '1';
            
            // Update help text dynamically
            const helpText = document.getElementById('color_help');
            if (helpText) {
                helpText.textContent = `${methods[method].colors.length} colors available for ${methods[method].name}`;
            }
        } else {
            colorSelect.innerHTML = '<option value="">Select print method first</option>';
            colorSelect.style.opacity = '0.5';
        }
    }, 150);
} 