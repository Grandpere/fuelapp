import { Controller } from '@hotwired/stimulus';
import {
    BarController,
    BarElement,
    CategoryScale,
    Chart,
    Filler,
    Legend,
    LineController,
    LineElement,
    LinearScale,
    PointElement,
    Tooltip,
} from 'chart.js';

Chart.register(
    CategoryScale,
    LinearScale,
    BarController,
    BarElement,
    LineController,
    LineElement,
    PointElement,
    Filler,
    Tooltip,
    Legend,
);

export default class extends Controller {
    static targets = ['button', 'canvas', 'config'];

    connect() {
        this.chart = null;
        this.config = null;
        this.storageKey = null;
        this.currentView = this.element.dataset.defaultView === 'line' ? 'line' : 'bars';
        this.handleThemeChanged = this.handleThemeChanged.bind(this);
        this.handlePageShow = this.handlePageShow.bind(this);
        this.handleVisibilityChange = this.handleVisibilityChange.bind(this);

        if (!this.hasConfigTarget || !this.hasCanvasTarget) {
            this.syncButtons();

            return;
        }

        try {
            this.config = this.readConfig();
        } catch (error) {
            this.element.dataset.chartRuntimeState = 'config-error';
            this.currentView = 'bars';
            this.syncButtons();

            return;
        }

        this.storageKey = this.config.key ? `analytics.chartView.${this.config.key}` : null;
        this.currentView = this.readInitialView();
        this.syncButtons();
        this.renderChart();
        window.addEventListener('fuelapp:theme-changed', this.handleThemeChanged);
        window.addEventListener('pageshow', this.handlePageShow);
        document.addEventListener('visibilitychange', this.handleVisibilityChange);
    }

    disconnect() {
        window.removeEventListener('fuelapp:theme-changed', this.handleThemeChanged);
        window.removeEventListener('pageshow', this.handlePageShow);
        document.removeEventListener('visibilitychange', this.handleVisibilityChange);
        this.destroyChart();
    }

    switch(event) {
        const button = event.currentTarget;
        if (!(button instanceof HTMLElement)) {
            return;
        }

        const nextView = button.dataset.view === 'line' ? 'line' : 'bars';
        if (nextView === this.currentView) {
            return;
        }

        this.currentView = nextView;
        if (this.storageKey) {
            this.writeStoredView(nextView);
        }

        this.syncButtons();
        this.renderChart();
    }

    readConfig() {
        if (!this.hasConfigTarget) {
            throw new Error('Missing analytics chart config target.');
        }

        const raw = this.configTarget.textContent?.trim() ?? '';
        if (raw === '') {
            throw new Error('Analytics chart config is empty.');
        }

        return JSON.parse(raw);
    }

    readInitialView() {
        const fallback = this.config.defaultView === 'line' ? 'line' : 'bars';
        if (!this.storageKey) {
            return fallback;
        }

        const savedView = this.readStoredView();

        if (savedView === 'line' || savedView === 'bars') {
            return savedView;
        }

        return fallback;
    }

    syncButtons() {
        this.buttonTargets.forEach((button) => {
            const isActive = button.dataset.view === this.currentView;
            button.classList.toggle('is-active', isActive);
            button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });

        this.element.dataset.chartView = this.currentView;
    }

    renderChart() {
        if (!this.hasCanvasTarget || this.currentView !== 'line') {
            this.destroyChart();
            this.element.dataset.chartRuntimeState = 'idle';

            return;
        }

        this.destroyChart();

        const context = this.canvasTarget.getContext('2d');
        if (!context) {
            this.element.dataset.chartRuntimeState = 'missing-canvas-context';

            return;
        }

        try {
            this.chart = new Chart(context, this.buildChartConfig());
            this.element.dataset.chartRuntimeState = 'ready';
        } catch (error) {
            this.fallbackToBars('chart-error');
        }
    }

    destroyChart() {
        if (this.chart) {
            this.chart.destroy();
            this.chart = null;
        }
    }

    handleThemeChanged() {
        if (this.currentView !== 'line') {
            return;
        }

        this.renderChart();
    }

    handlePageShow() {
        if (this.currentView !== 'line') {
            return;
        }

        this.renderChart();
    }

    handleVisibilityChange() {
        if (document.visibilityState !== 'visible' || this.currentView !== 'line') {
            return;
        }

        this.renderChart();
    }

    buildChartConfig() {
        if (null === this.config) {
            throw new Error('Analytics chart config is missing.');
        }

        const isLine = this.currentView === 'line';
        const theme = this.resolveThemePalette();

        return {
            type: isLine ? 'line' : 'bar',
            data: {
                labels: this.config.labels,
                datasets: this.config.datasets.map((dataset) => this.buildDataset(dataset, isLine)),
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        display: this.config.datasets.length > 1,
                        labels: {
                            color: theme.chartText,
                            usePointStyle: true,
                            boxWidth: 10,
                            boxHeight: 10,
                        },
                    },
                    tooltip: {
                        callbacks: {
                            label: (context) => {
                                const label = context.dataset.label ? `${context.dataset.label}: ` : '';

                                return `${label}${this.formatValue(context.parsed.y ?? 0)}`;
                            },
                        },
                    },
                },
                scales: {
                    x: {
                        ticks: {
                            color: theme.chartTextSoft,
                            maxRotation: 0,
                            autoSkip: false,
                            font: {
                                size: 12,
                                weight: '600',
                            },
                        },
                        grid: {
                            display: false,
                        },
                        border: {
                            color: theme.chartAxis,
                        },
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            color: theme.chartText,
                            font: {
                                size: 12,
                                weight: '600',
                            },
                            callback: (value) => this.formatTick(value),
                        },
                        title: {
                            display: true,
                            text: this.config.yAxisLabel,
                            color: theme.chartTextStrong,
                            font: {
                                size: 12,
                                weight: '700',
                            },
                        },
                        grid: {
                            color: theme.chartGridLine,
                            drawBorder: false,
                        },
                        border: {
                            color: theme.chartAxis,
                        },
                    },
                },
            },
        };
    }

    buildDataset(dataset, isLine) {
        const subtlePoints = dataset.subtlePoints === true;
        const theme = this.resolveThemePalette();

        return {
            label: dataset.label,
            data: dataset.data,
            borderColor: dataset.borderColor,
            backgroundColor: isLine ? dataset.fillColor : dataset.barColor,
            fill: isLine && dataset.fill !== false,
            tension: isLine ? 0.28 : 0,
            borderWidth: isLine ? 2 : 0,
            pointRadius: isLine ? (subtlePoints ? 2 : 2.8) : 0,
            pointHoverRadius: isLine ? (subtlePoints ? 3 : 4) : 0,
            pointBackgroundColor: isLine ? theme.chartPointFill : dataset.barColor,
            pointBorderColor: dataset.borderColor,
            pointBorderWidth: isLine ? 1.2 : 0,
            pointHitRadius: 10,
            barThickness: 18,
            maxBarThickness: 22,
            borderRadius: isLine ? 0 : 999,
        };
    }

    resolveThemePalette() {
        const styles = window.getComputedStyle(document.documentElement);

        return {
            chartText: styles.getPropertyValue('--chart-text').trim() || 'rgba(214, 229, 239, 0.78)',
            chartTextStrong: styles.getPropertyValue('--chart-text-strong').trim() || '#eef8ff',
            chartTextSoft: styles.getPropertyValue('--chart-text-soft').trim() || 'rgba(214, 229, 239, 0.78)',
            chartAxis: styles.getPropertyValue('--chart-axis').trim() || 'rgba(147, 178, 198, 0.3)',
            chartGridLine: styles.getPropertyValue('--chart-grid-line').trim() || 'rgba(147, 178, 198, 0.14)',
            chartPointFill: styles.getPropertyValue('--chart-point-fill').trim() || '#08141c',
        };
    }

    formatTick(value) {
        const numericValue = typeof value === 'number' ? value : Number(value);

        if (Number.isNaN(numericValue)) {
            return '';
        }

        return this.formatValue(numericValue);
    }

    formatValue(value) {
        switch (this.config.valueFormat) {
            case 'liters':
                return `${this.formatNumber(value, 2)} L`;
            case 'fuel_price':
                return `${this.formatNumber(value, 3)} EUR/L`;
            case 'currency':
            default:
                return `${this.formatNumber(value, 2)} EUR`;
        }
    }

    formatNumber(value, decimals) {
        return new Intl.NumberFormat('en-US', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals,
        }).format(value);
    }

    readStoredView() {
        try {
            return window.localStorage?.getItem(this.storageKey) ?? null;
        } catch (error) {
            return null;
        }
    }

    writeStoredView(nextView) {
        try {
            window.localStorage?.setItem(this.storageKey, nextView);
        } catch (error) {
        }
    }

    fallbackToBars(reason) {
        this.currentView = 'bars';
        this.destroyChart();
        this.element.dataset.chartRuntimeState = reason;
        this.syncButtons();
    }
}
