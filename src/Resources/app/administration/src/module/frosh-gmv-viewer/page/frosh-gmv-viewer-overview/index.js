import template from './frosh-gmv-viewer-overview.html.twig';
import './frosh-gmv-viewer-overview.scss';

const { Component } = Shopware;

Component.register('frosh-gmv-viewer-overview', {
    template,

    inject: ['repositoryFactory'],

    data() {
        return {
            isLoading: false,
            gmvData: {
                thisMonth: 0,
                lastMonth: 0,
                thisYear: 0,
                lastYear: 0,
                months: {
                    january: 0,
                    february: 0,
                    march: 0,
                    april: 0,
                    may: 0,
                    june: 0,
                    july: 0,
                    august: 0,
                    september: 0,
                    october: 0,
                    november: 0,
                    december: 0
                }
            },
            last12MonthsData: [],
            rawGmvData: null
        };
    },

    computed: {
        orderRepository() {
            return this.repositoryFactory.create('order');
        },

        currencyFilter() {
            return Shopware.Filter.getByName('currency');
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.fetchGMVData();
        },

        fetchGMVData() {
            this.isLoading = true;

            // Get the HTTP client from the Shopware Application container
            const httpClient = Shopware.Application.getContainer('init').httpClient;

            // Fetch data from the API
            httpClient.get(
                '/_action/frosh-gmv/gmv/list',
                {
                    headers: this.getApiHeaders()
                }
            ).then((response) => {
                this.rawGmvData = response.data;
                this.processGmvData();
                this.isLoading = false;
            }).catch((error) => {
                console.error('Error fetching GMV data:', error);
                this.isLoading = false;
            });
        },

        getApiHeaders() {
            return {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                Authorization: `Bearer ${Shopware.Service('loginService').getToken()}`
            };
        },

        processGmvData() {
            if (!this.rawGmvData || !this.rawGmvData.month) return;

            const standardCurrency = this.rawGmvData.defaultCurrency;
            const currentYear = new Date().getFullYear().toString();
            const lastYearStr = (parseInt(currentYear) - 1).toString();
            const thisMonthKey = new Date().toISOString().slice(0, 7);
            const lastMonthDate = new Date();
            lastMonthDate.setMonth(lastMonthDate.getMonth() - 1);
            const lastMonthKey = lastMonthDate.toISOString().slice(0, 7);

            const monthlyData = this.rawGmvData.month;

            // Hilfsfunktion für eine einzelne Periode (Monat/Jahr)
            const computePeriod = (periodKey, isYear = false) => {
                let total = 0;
                const detail = [];

                for (const [currency, entries] of Object.entries(monthlyData)) {
                    for (const [monthKey, data] of Object.entries(entries)) {
                        const year = monthKey.substring(0, 4);
                        const matches =
                            (isYear && year === periodKey) ||
                            (!isYear && monthKey === periodKey);

                        if (matches) {
                            const amount = parseFloat(data.turnover_total || 0);
                            const factor = parseFloat(data.currency_factor || 1);
                            total += amount / factor;

                            if (currency !== standardCurrency && amount > 0) {
                                const existing = detail.find(d => d.currency === currency);
                                if (existing) {
                                    existing.amount += amount;
                                } else {
                                    detail.push({ currency, amount });
                                }
                            }
                        }
                    }
                }

                return {
                    total,
                    detail
                };
            };

            // Daten zusammensetzen
            this.gmvData = {
                defaultCurrencyIsoCode: this.rawGmvData.defaultCurrency,
                thisMonth: computePeriod(thisMonthKey),
                lastMonth: computePeriod(lastMonthKey),
                thisYear: computePeriod(currentYear, true),
                lastYear: computePeriod(lastYearStr, true)
            };
        }
    }
});
