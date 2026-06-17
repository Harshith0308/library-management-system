// Main JavaScript file for Library Management System

document.addEventListener('DOMContentLoaded', function() {
    // Initialize any components that need JavaScript functionality
    initializeDataTables();
    initializeFormValidation();
    initializeAlertDismissal();
    initializeModalFunctionality();
});

// Initialize DataTables for better table functionality if available
function initializeDataTables() {
    // Check if DataTables is available
    if (typeof $.fn.DataTable !== 'undefined') {
        $('.data-table').DataTable({
            responsive: true,
            pageLength: 10,
            lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]]
        });
    }
}

// Form validation
function initializeFormValidation() {
    const forms = document.querySelectorAll('.needs-validation');
    
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            form.classList.add('was-validated');
        }, false);
    });
}

// Alert dismissal functionality
function initializeAlertDismissal() {
    const alerts = document.querySelectorAll('.alert');
    
    alerts.forEach(alert => {
        // Add close button if it doesn't exist
        if (!alert.querySelector('.close-btn')) {
            const closeBtn = document.createElement('span');
            closeBtn.classList.add('close-btn');
            closeBtn.innerHTML = '&times;';
            closeBtn.style.float = 'right';
            closeBtn.style.cursor = 'pointer';
            closeBtn.style.fontWeight = 'bold';
            
            closeBtn.addEventListener('click', () => {
                alert.style.display = 'none';
            });
            
            alert.insertBefore(closeBtn, alert.firstChild);
        }
        
        // Auto-dismiss success alerts after 5 seconds
        if (alert.classList.contains('alert-success')) {
            setTimeout(() => {
                alert.style.display = 'none';
            }, 5000);
        }
    });
}

// Modal functionality
function initializeModalFunctionality() {
    // Open modal
    const modalTriggers = document.querySelectorAll('[data-toggle="modal"]');
    
    modalTriggers.forEach(trigger => {
        trigger.addEventListener('click', () => {
            const targetModal = document.querySelector(trigger.dataset.target);
            if (targetModal) {
                targetModal.style.display = 'block';
            }
        });
    });
    
    // Close modal when clicking the close button
    const closeButtons = document.querySelectorAll('.modal .close');
    
    closeButtons.forEach(button => {
        button.addEventListener('click', () => {
            const modal = button.closest('.modal');
            if (modal) {
                modal.style.display = 'none';
            }
        });
    });
    
    // Close modal when clicking outside the modal content
    window.addEventListener('click', event => {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
        }
    });
}

// Function to confirm deletion
function confirmDelete(message = 'Are you sure you want to delete this item?') {
    return confirm(message);
}

// Function to preview image before upload
function previewImage(input, previewElement) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            document.getElementById(previewElement).src = e.target.result;
            document.getElementById(previewElement).style.display = 'block';
        }
        
        reader.readAsDataURL(input.files[0]);
    }
}