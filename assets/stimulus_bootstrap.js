import { startStimulusApp } from '@symfony/stimulus-bundle';
import AnalyticsChartController from './controllers/analytics_chart_controller.js';
import RowLinkController from './controllers/row_link_controller.js';

const app = startStimulusApp();
app.register('analytics-chart', AnalyticsChartController);
app.register('row-link', RowLinkController);
