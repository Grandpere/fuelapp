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

        if (!this.hasConfigTarget || !this.hasCanvasTarget) {
            this.syncButtons();

            return;
        }

        this.config = this.readConfig();
        this.storageKey = this.config.key ? `analytics.chartView.${this.config.key}` : null;
        this.currentView = this.readInitialView();
        this.syncButtons();
        this.renderChart();
    }

    disconnect() {
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
            window.localStorage?.setItem(this.storageKey, nextView);
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

        const savedView = window.localStorage?.getItem(this.storageKey);

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

            return;
        }

        this.destroyChart();

        const context = this.canvasTarget.getContext('2d');
        if (!context) {
            return;
        }

        this.chart = new Chart(context, this.buildChartConfig());
    }

    destroyChart() {
        if (this.chart) {
            this.chart.destroy();
            this.chart = null;
        }
    }

    buildChartConfig() {
        if (null === this.config) {
            throw new Error('Analytics chart config is missing.');
        }

        const isLine = this.currentView === 'line';

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
                            color: '#d6e5ef',
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
                            color: 'rgba(214, 229, 239, 0.78)',
                            maxRotation: 0,
                            autoSkip: false,
                        },
                        grid: {
                            display: false,
                        },
                        border: {
                            color: 'rgba(147, 178, 198, 0.3)',
                        },
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            color: 'rgba(214, 229, 239, 0.78)',
                            callback: (value) => this.formatTick(value),
                        },
                        title: {
                            display: true,
                            text: this.config.yAxisLabel,
                            color: '#eef8ff',
                            font: {
                                size: 11,
                                weight: '600',
                            },
                        },
                        grid: {
                            color: 'rgba(147, 178, 198, 0.14)',
                            drawBorder: false,
                        },
                        border: {
                            color: 'rgba(147, 178, 198, 0.3)',
                        },
                    },
                },
            },
        };
    }

    buildDataset(dataset, isLine) {
        const subtlePoints = dataset.subtlePoints === true;

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
            pointBackgroundColor: isLine ? '#08141c' : dataset.barColor,
            pointBorderColor: dataset.borderColor,
            pointBorderWidth: isLine ? 1.2 : 0,
            pointHitRadius: 10,
            barThickness: 18,
            maxBarThickness: 22,
            borderRadius: isLine ? 0 : 999,
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
}
