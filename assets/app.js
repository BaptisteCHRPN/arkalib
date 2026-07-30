import '@hotwired/turbo';
import { startStimulusApp } from '@symfony/stimulus-bundle';
import SidebarController from './controllers/sidebar_controller.js';

const app = startStimulusApp();
app.register('sidebar', SidebarController);

import './popper-unabled.js';

document.addEventListener('turbo:submit-start', (event) => {
    if (event.target.matches('[data-preserve-scroll]')) {
        sessionStorage.setItem('scrollY', window.scrollY);
    }
});

document.addEventListener('turbo:load', () => {
    const y = sessionStorage.getItem('scrollY');
    if (y !== null) {
        window.scrollTo(0, parseInt(y, 10));
        sessionStorage.removeItem('scrollY');
    }
});
