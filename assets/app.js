// assets/app.js
import 'bootstrap/dist/css/bootstrap.min.css';
import './styles/custom-bootstrap.scss';
import * as bootstrap from 'bootstrap';
import '@symfony/ux-turbo';

// Rendre Bootstrap disponible globalement
window.bootstrap = bootstrap;

// Importer votre script
import './navbar-toggle.js';
import './popper-unabled.js';

console.log('Bootstrap chargé!');

// Réinitialiser les dropdowns après chaque chargement Turbo
document.addEventListener('turbo:load', function() {
    // Réinitialiser tous les dropdowns
    document.querySelectorAll('[data-bs-toggle="dropdown"]').forEach(function(dropdownToggle) {
        new bootstrap.Dropdown(dropdownToggle);
    });
});

document.addEventListener('turbo:frame-load', function() {
    // Réinitialiser tous les dropdowns
    document.querySelectorAll('[data-bs-toggle="dropdown"]').forEach(function(dropdownToggle) {
        new bootstrap.Dropdown(dropdownToggle);
    });
});