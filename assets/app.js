// assets/app.js
import 'bootstrap/dist/css/bootstrap.min.css';
import './styles/custom-bootstrap.scss';
import * as bootstrap from 'bootstrap';

// Rendre Bootstrap disponible globalement
window.bootstrap = bootstrap;

// Importer votre script
import './navbar-toggle.js';

console.log('Bootstrap chargé!');