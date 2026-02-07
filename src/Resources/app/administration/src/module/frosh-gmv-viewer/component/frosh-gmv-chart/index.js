import template from './frosh-gmv-chart.html.twig';
import './frosh-gmv-chart.scss';

const { Component } = Shopware;

Component.register('frosh-gmv-chart', {
    template,

    props: {
        gmvData: {
            type: Object,
            required: true
        }
    },

    data() {
        return {
            chartOptions: {
                chart: {
                    stacked: true
                },
                xaxis: {
                    tooltip: false,
                    labels: {
                        formatter: (value) => {
                            return value;
                        },
                        rotate: -45,
                        hideOverlappingLabels: false
                    }
                },
                yaxis: {
                    min: 0,
                    tickAmount: 5,
                    labels: {
                        formatter: (value) => {
                            return Shopware.Utils.format.currency(
                                Number.parseFloat(value),
                                Shopware.Context.app.systemCurrencyISOCode,
                                2
                            );
                        }
                    }
                },
                tooltip: {
                    shared: true,
                    intersect: false,
                    totalLable: this.$tc('frosh-gmv-viewer.chart.total'),
                    custom: function({ series, seriesIndex, dataPointIndex, w }) {
                        let total = 0;
                        let tooltipContent = '';

                        w.config.series.forEach((serie, idx) => {
                            const point = serie.data[dataPointIndex];

                            if (point.y === 0) {
                                return;
                            }

                            total += point.y;

                            tooltipContent += `
                                <div class="apexcharts-tooltip-series-group">
                                    <div class="frosh-gmv-chart-tooltip-group-label">
                                        <span class="apexcharts-tooltip-marker" style="background-color: ${w.globals.colors[idx]};"></span>
                                        ${serie.name}:
                                    </div>
                                    <strong>${Shopware.Utils.format.currency(point.original, serie.name, 2)}</strong>
                                </div>
                            `;
                        });

                        if (total === 0) {
                            return '';
                        }

                        tooltipContent += `
                            <div class="apexcharts-tooltip-series-group frosh-gmv-chart-tooltip-group-summary">
                                <div class="frosh-gmv-chart-tooltip-group-label">${w.config.tooltip.totalLable}:</div>
                                <strong>${Shopware.Utils.format.currency(total, Shopware.Context.app.systemCurrencyISOCode, 2)}</strong>
                            </div>
                        `;

                        return `<div class="apexcharts-tooltip-series">${tooltipContent}</div>`;
                    }
                },
                dataLabels: {
                    enabled: false
                }
            },
            chartSeries: []
        };
    },

    watch: {
        gmvData: {
            deep: true,
            handler() {
                this.updateChartData();
            }
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.updateChartData();
        },

        updateChartData() {
            if (!this.gmvData || !this.gmvData.month) return;

            const now = new Date();
            const monthLabels = [];
            const currencyData = {};

            for (let i = 11; i >= 0; i--) {
                const date = new Date(now.getFullYear(), now.getMonth() - i, 1);
                const key = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
                const label = `${this.$tc(`frosh-gmv-viewer.chart.months.${date.toLocaleString('en', { month: 'long' }).toLowerCase()}`)} ${date.getFullYear()}`;
                monthLabels.push(label);

                for (const currency in this.gmvData.month) {
                    if (!currencyData[currency]) {
                        currencyData[currency] = [];
                    }

                    const entry = this.gmvData.month[currency][key] || {
                        converted_total: 0,
                        turnover_total: 0,
                        currency_iso_code: currency
                    };

                    currencyData[currency].push({
                        x: label,
                        y: parseFloat(entry.converted_total), // for bar height
                        original: parseFloat(entry.turnover_total),
                    });
                }
            }

            this.chartSeries = Object.keys(currencyData).map(currency => ({
                name: currency,
                data: currencyData[currency]
            }));

            this.chartOptions.xaxis.categories = monthLabels;
        }
    }
});
