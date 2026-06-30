import '@hotwired/turbo';
import { startStimulusApp } from '@symfony/stimulus-bundle';
import SidebarController from './controllers/sidebar_controller.js';

const app = startStimulusApp();
app.register('sidebar', SidebarController);

import './popper-unabled.js';