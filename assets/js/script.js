// function togglePassword(fieldId) {
//     const passwordField = document.getElementById(fieldId);
//     const toggleIcon = passwordField.nextElementSibling;
    
//     if (passwordField.type === "password") {
//         passwordField.type = "text";
//         toggleIcon.classList.replace('fa-eye', 'fa-eye-slash');
//     } else {
//         passwordField.type = "password";
//         toggleIcon.classList.replace('fa-eye-slash', 'fa-eye');
//     }
// }

function toggleRole(element) {
    document.querySelectorAll('.role-option').forEach(option => {
        option.classList.remove('selected');
    });
    element.classList.add('selected');
}

function toggleWorkerRoles() {
    const mainRole = document.getElementById('mainRole');
    const workerSection = document.getElementById('workerRolesSection');
    
    if (mainRole.value === 'worker') {
        workerSection.classList.remove('d-none');
    } else {
        workerSection.classList.add('d-none');
    }
}

// document.querySelectorAll('form').forEach(form => {
//     form.addEventListener('submit', function(e) {
//         e.preventDefault();
        
//         if(form.id === 'loginForm') {
//             alert('Login functionality would go here');
//         } else {
//             alert('Signup functionality would go here');
//         }
//     });
// });

// document.querySelectorAll('.form-control, .form-select').forEach(input => {
//     input.addEventListener('input', () => {
//         if(input.checkValidity()) {
//             input.classList.remove('is-invalid');
//             input.classList.add('is-valid');
//         } else {
//             input.classList.remove('is-valid');
//             input.classList.add('is-invalid');
//         }
//     });
// });
// Function to toggle password visibility
function togglePassword(inputId) {
    var input = document.getElementById(inputId);
    var icon = input.nextElementSibling;

    if (input.type === "password") {
        input.type = "text";
        icon.classList.remove("fa-eye");
        icon.classList.add("fa-eye-slash");
    } else {
        input.type = "password";
        icon.classList.remove("fa-eye-slash");
        icon.classList.add("fa-eye");
    }
}

// Function to validate password
function validatePassword(password) {
    const minLength = 8;
    const hasUpperCase = /[A-Z]/.test(password);
    const hasLowerCase = /[a-z]/.test(password);
    const hasNumbers = /\d/.test(password);
    const hasSpecialChar = /[!@#$%^&*(),.?":{}|<>]/.test(password);
    
    return {
        isValid: password.length >= minLength && hasUpperCase && hasLowerCase && hasNumbers && hasSpecialChar,
        requirements: {
            length: password.length >= minLength,
            uppercase: hasUpperCase,
            lowercase: hasLowerCase,
            number: hasNumbers,
            special: hasSpecialChar
        }
    };
}

// Function to validate email
function validateEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

// Function to toggle worker roles section
function toggleWorkerRoles() {
    const mainRole = document.getElementById('mainRole');
    const workerSection = document.getElementById('workerRolesSection');
    
    if (mainRole.value === 'worker') {
        workerSection.classList.remove('d-none');
    } else {
        workerSection.classList.add('d-none');
    }
}

// Function to update password validation display
function updatePasswordValidation(inputId, validation) {
    const errorsDiv = document.getElementById(inputId + 'Errors');
    const requirements = validation.requirements;
    
    // Update individual requirement displays
    document.getElementById(inputId + 'Length').style.color = requirements.length ? 'green' : 'red';
    document.getElementById(inputId + 'UpperCase').style.color = requirements.uppercase ? 'green' : 'red';
    document.getElementById(inputId + 'LowerCase').style.color = requirements.lowercase ? 'green' : 'red';
    document.getElementById(inputId + 'Number').style.color = requirements.number ? 'green' : 'red';
    document.getElementById(inputId + 'Special').style.color = requirements.special ? 'green' : 'red';
    
    // Show/hide the errors div
    errorsDiv.style.display = validation.isValid ? 'none' : 'block';
    
    // Update input field styling
    const input = document.getElementById(inputId);
    if (validation.isValid) {
        input.classList.remove('is-invalid');
        input.classList.add('is-valid');
    } else {
        input.classList.remove('is-valid');
        input.classList.add('is-invalid');
    }
}

// Login form validation
document.getElementById('loginForm').addEventListener('submit', function(event) {
    const email = this.querySelector('input[name="email"]').value;
    const password = this.querySelector('input[name="password"]').value;
    let isValid = true;
    let errors = [];

    // Email validation
    if (!validateEmail(email)) {
        errors.push('Please enter a valid email address');
        isValid = false;
    }

    // Password validation
    const passwordValidation = validatePassword(password);
    if (!passwordValidation.isValid) {
        errors.push('Please fix the password requirements');
        isValid = false;
    }

    if (!isValid) {
        event.preventDefault();
        const errorDiv = document.createElement('div');
        errorDiv.className = 'alert alert-danger';
        errorDiv.innerHTML = '<ul class="mb-0">' + errors.map(error => `<li>${error}</li>`).join('') + '</ul>';
        this.insertBefore(errorDiv, this.firstChild);
    }
});

// Signup form validation
document.getElementById('signupForm').addEventListener('submit', function(event) {
    const name = this.querySelector('input[name="name"]').value;
    const email = this.querySelector('input[name="email"]').value;
    const password = this.querySelector('input[name="password"]').value;
    const confirmPassword = this.querySelector('input[name="confirm_password"]').value;
    const role = this.querySelector('select[name="role"]').value;
    const school = this.querySelector('select[name="school"]').value;
    let isValid = true;
    let errors = [];

    // Name validation
    if (name.length < 2) {
        errors.push('Name must be at least 2 characters long');
        isValid = false;
    }

    // Email validation
    if (!validateEmail(email)) {
        errors.push('Please enter a valid email address');
        isValid = false;
    }

    // Password validation
    const passwordValidation = validatePassword(password);
    if (!passwordValidation.isValid) {
        errors.push('Please fix the password requirements');
        isValid = false;
    }

    // Confirm password validation
    if (password !== confirmPassword) {
        document.getElementById('confirmPasswordError').style.display = 'block';
        document.getElementById('confirmPassword').classList.add('is-invalid');
        errors.push('Passwords do not match');
        isValid = false;
    } else {
        document.getElementById('confirmPasswordError').style.display = 'none';
        document.getElementById('confirmPassword').classList.remove('is-invalid');
    }

    // Role validation
    if (!role) {
        errors.push('Please select a role');
        isValid = false;
    }

    // School validation
    if (!school) {
        errors.push('Please select a school');
        isValid = false;
    }

    // Worker position validation
    if (role === 'worker') {
        const workerPosition = this.querySelector('select[name="worker_position"]').value;
        if (!workerPosition) {
            errors.push('Please select a worker position');
            isValid = false;
        }
    }

    if (!isValid) {
        event.preventDefault();
        const errorDiv = document.createElement('div');
        errorDiv.className = 'alert alert-danger';
        errorDiv.innerHTML = '<ul class="mb-0">' + errors.map(error => `<li>${error}</li>`).join('') + '</ul>';
        this.insertBefore(errorDiv, this.firstChild);
    }
});

// Real-time password validation
document.getElementById('loginPassword').addEventListener('input', function() {
    updatePasswordValidation('loginPassword', validatePassword(this.value));
});

document.getElementById('signupPassword').addEventListener('input', function() {
    updatePasswordValidation('signupPassword', validatePassword(this.value));
});

document.getElementById('confirmPassword').addEventListener('input', function() {
    const password = document.getElementById('signupPassword').value;
    const confirmPasswordError = document.getElementById('confirmPasswordError');
    
    if (this.value !== password) {
        confirmPasswordError.style.display = 'block';
        this.classList.add('is-invalid');
    } else {
        confirmPasswordError.style.display = 'none';
        this.classList.remove('is-invalid');
    }
});

// Custom JavaScript for Canteen Automation System

// Document Ready Function
$(document).ready(function() {
    // Initialize tooltips
    $('[data-toggle="tooltip"]').tooltip();
    
    // Initialize popovers
    $('[data-toggle="popover"]').popover();
    
    // Handle sidebar toggle
    $('[data-widget="pushmenu"]').on('click', function(e) {
        e.preventDefault();
        $('body').toggleClass('sidebar-collapse');
    });
    
    // Handle form submissions with loading state
    $('form').on('submit', function() {
        $(this).find('button[type="submit"]').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');
    });
    
    // Handle table row click
    $('.table tbody tr').on('click', function() {
        if ($(this).data('href')) {
            window.location.href = $(this).data('href');
        }
    });
    
    // Handle delete confirmations
    $('.delete-confirm').on('click', function(e) {
        e.preventDefault();
        const url = $(this).attr('href');
        if (confirm('Are you sure you want to delete this item?')) {
            window.location.href = url;
        }
    });
    
    // Handle file input change
    $('.custom-file-input').on('change', function() {
        const fileName = $(this).val().split('\\').pop();
        $(this).next('.custom-file-label').html(fileName);
    });
    
    // Handle dynamic form fields
    $('.add-form-field').on('click', function() {
        const template = $(this).data('template');
        const container = $(this).data('container');
        $(container).append(template);
    });
    
    // Handle remove form field
    $(document).on('click', '.remove-form-field', function() {
        $(this).closest('.form-field-group').remove();
    });
    
    // Handle date range picker
    if ($.fn.daterangepicker) {
        $('.daterange-picker').daterangepicker({
            locale: {
                format: 'MM/DD/YYYY'
            }
        });
    }
    
    // Handle select2 initialization
    if ($.fn.select2) {
        $('.select2').select2({
            theme: 'bootstrap4'
        });
    }
    
    // Handle chart resizing
    $(window).on('resize', function() {
        if (window.charts) {
            window.charts.forEach(function(chart) {
                if (chart) {
                    chart.resize();
                }
            });
        }
    });
});

// Global Functions
function showLoading() {
    $('body').append('<div class="spinner-overlay"><div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div></div>');
}

function hideLoading() {
    $('.spinner-overlay').remove();
}

function showAlert(message, type = 'success') {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    `;
    $('.content').prepend(alertHtml);
    
    // Auto dismiss after 5 seconds
    setTimeout(function() {
        $('.alert').alert('close');
    }, 5000);
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
    }).format(amount);
}

function formatDate(date) {
    return new Intl.DateTimeFormat('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    }).format(new Date(date));
}

// Chart Helper Functions
function createLineChart(canvasId, labels, datasets, options = {}) {
    const ctx = document.getElementById(canvasId).getContext('2d');
    const defaultOptions = {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    };
    
    const chart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: datasets
        },
        options: { ...defaultOptions, ...options }
    });
    
    if (!window.charts) window.charts = [];
    window.charts.push(chart);
    
    return chart;
}

function createDoughnutChart(canvasId, labels, data, options = {}) {
    const ctx = document.getElementById(canvasId).getContext('2d');
    const defaultOptions = {
        responsive: true,
        maintainAspectRatio: false
    };
    
    const chart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: [
                    '#007bff',
                    '#28a745',
                    '#ffc107',
                    '#dc3545',
                    '#17a2b8',
                    '#6c757d',
                    '#343a40'
                ]
            }]
        },
        options: { ...defaultOptions, ...options }
    });
    
    if (!window.charts) window.charts = [];
    window.charts.push(chart);
    
    return chart;
}

// AJAX Helper Functions
function ajaxRequest(url, method = 'GET', data = null, successCallback = null, errorCallback = null) {
    showLoading();
    
    $.ajax({
        url: url,
        method: method,
        data: data,
        success: function(response) {
            hideLoading();
            if (successCallback) successCallback(response);
        },
        error: function(xhr, status, error) {
            hideLoading();
            showAlert('An error occurred: ' + error, 'danger');
            if (errorCallback) errorCallback(xhr, status, error);
        }
    });
}

// Form Validation Helper
function validateForm(formId) {
    const form = $(`#${formId}`);
    let isValid = true;
    
    form.find('[required]').each(function() {
        if (!$(this).val()) {
            isValid = false;
            $(this).addClass('is-invalid');
        } else {
            $(this).removeClass('is-invalid');
        }
    });
    
    return isValid;
}

// Export Helper Functions
function exportToCSV(data, filename) {
    const csvContent = "data:text/csv;charset=utf-8," + data.map(row => Object.values(row).join(",")).join("\n");
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", filename);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Print Helper Function
function printElement(elementId) {
    const printContents = document.getElementById(elementId).innerHTML;
    const originalContents = document.body.innerHTML;
    
    document.body.innerHTML = printContents;
    window.print();
    document.body.innerHTML = originalContents;
    
    // Reinitialize any necessary scripts
    $(document).ready();
}
