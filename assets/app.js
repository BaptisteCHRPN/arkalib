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