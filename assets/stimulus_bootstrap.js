import { startStimulusApp } from '@symfony/stimulus-bundle';
import RowLinkController from './controllers/row_link_controller.js';

const app = startStimulusApp();
app.register('row-link', RowLinkController);
