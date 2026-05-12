import '@hotwired/turbo';
import './navbar-toggle.js';
import './popper-unabled.js';

// Réinitialiser les dropdowns Bootstrap après navigation Turbo
document.addEventListener('turbo:load', function() {
    document.querySelectorAll('[data-bs-toggle="dropdown"]').forEach(function(dropdownToggle) {
        new window.bootstrap.Dropdown(dropdownToggle);
    });
});

document.addEventListener('turbo:frame-load', function() {
    document.querySelectorAll('[data-bs-toggle="dropdown"]').forEach(function(dropdownToggle) {
        new window.bootstrap.Dropdown(dropdownToggle);
    });
});