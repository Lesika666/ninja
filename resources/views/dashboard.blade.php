@extends('header')

@section('head')
	@parent

    @include('money_script')

    <script src="{{ asset('js/Chart.min.js') }}" type="text/javascript"></script>
    <script src="{{ asset('js/daterangepicker.min.js') }}" type="text/javascript"></script>
    <script src="{{ asset('js/chartjs-adapter-moment.min.js') }}" type="text/javascript"></script>

    <link href="{{ asset('css/daterangepicker.css') }}" rel="stylesheet" type="text/css"/>

@stop

@section('content')

<script type="text/javascript">
    @if (Auth::user()->hasPermission('admin'))

        function loadOverviewChart(overviewChart, totals) {
            var ctx = document.getElementById('overviewChart').getContext('2d');
            if (window.objOverviewChart) {
                window.objOverviewChart.config.data = overviewChart;
                window.objOverviewChart.update();
            } else {
                $('#overviewChart').fadeIn();
                objOverviewChart = new Chart(ctx, {
                    type: 'line',
                    data: overviewChart,
                    options: {
                        maintainAspectRatio: false,
                        scales: {
                            x: {
                                type: 'time',
                                time: {
                                    unit: 'month',
                                    round: 'month',
                                },
                                grid: {
                                    display: false,
                                },
                            },
                            y: {
                                grid: {
                                    display: true,
                                    drawBorder: true,
                                },
                            }
                        },
                        plugins: {
                            tooltip: {
                                mode: 'x',
                                callbacks: {
                                    title: function(item) {
                                        return moment(item[0].label).format("{{ $account->getMomentDateFormat() }}");
                                    },
                                    label: function(item) {
                                        if (item.datasetIndex == 0) {
                                            var label = " {!! trans('texts.payments') !!}: ";
                                        } else if (item.datasetIndex == 1) {
                                            var label = " {!! trans('texts.left_to_pay') !!}: ";
                                        } else if (item.datasetIndex == 2) {
                                            var label = " {!! trans('texts.expenses') !!}: ";
                                        }
                                        return label + formatMoney(item.parsed.y, chartCurrencyId, account.country_id);
                                    }
                                }
                            },
                            legend: {
                                position: 'bottom',
                            }
                        }
                    }
                });
                window.objOverviewChart = objOverviewChart;
            }
        }

        function loadInvoicesChart(invoicesChart, totals) {
            var ctx = document.getElementById('invoicesChart').getContext('2d');
            if (window.objInvoicesChart) {
                window.objInvoicesChart.config.data = invoicesChart;
                window.objInvoicesChart.update();
            } else {
                $('#invoicesChart').fadeIn();
                objInvoicesChart = new Chart(ctx, {
                    type: 'line',
                    data: invoicesChart,
                    options: {
                        maintainAspectRatio: false,
                        scales: {
                            x: {
                                type: 'time',
                                time: {
                                    unit: 'month',
                                    round: 'month',
                                },
                                grid: {
                                    display: false,
                                },
                            },
                            y: {
                                ticks: {
                                    display: false,
                                    // beginAtZero: true,
                                    // callback: function(label, index, labels) {
                                    //     return formatMoney(label, chartCurrencyId, account.country_id);
                                    // }
                                },
                                grid: {
                                    display: false,
                                    drawBorder: false,
                                },
                            }
                        },
                        plugins: {
                            tooltip: {
                                mode: 'x',
                                callbacks: {
                                    title: function(item) {
                                        return moment(item[0].label).format("{{ $account->getMomentDateFormat() }}");
                                    },
                                    label: function(item) {
                                        if (item.datasetIndex == 0) {
                                            var label = " {!! trans('texts.invoices') !!}: ";
                                        } else if (item.datasetIndex == 1) {
                                            var label = " {!! trans('texts.previous_year') !!}: ";
                                        }
                                        return label + formatMoney(item.parsed.y, chartCurrencyId, account.country_id);
                                    }
                                }
                            },
                            legend: {
                                position: 'bottom',
                            }
                        }
                    }
                });
                window.objInvoicesChart = objInvoicesChart;
            }

            // Update values
            $('#invoicesChartValue').text(formatMoney(arraySum(totals.current_year.invoiced), chartCurrencyId, account.country_id));
            if("previous_year" in totals) {
                $('#invoicesChartEvolution').html(htmlEvolution(calculatePercentageEvolution(arraySum(totals.previous_year.invoiced), arraySum(totals.current_year.invoiced))));
                $('#invoicesChartPreviousValue').text(formatMoney(arraySum(totals.previous_year.invoiced), chartCurrencyId, account.country_id));
            }
        }

        function loadCategoriesChart(categoriesChart) {
            var ctx = document.getElementById('categoriesChart').getContext('2d');
            if (window.objCategoriesChart) {
                window.objCategoriesChart.config.data = categoriesChart;
                window.objCategoriesChart.update();
            } else {
                $('#progress-div').hide();
                $('#categoriesChart').fadeIn();
                objCategoriesChart = new Chart(ctx, {
                    type: 'doughnut',
                    data: categoriesChart,
                    options: {
                        plugins: {
                            tooltip: {
                                callbacks: {
                                    title: function (context) {
                                        let percentage = calculatePercentageTotal(arraySum(context[0].dataset.data), context[0].dataset.data[context[0].dataIndex]);
                                        return context[0].label + ` (${percentage}%)`;
                                    },
                                    label: function (context) {
                                        return formatMoney(context.raw, chartCurrencyId, account.country_id);
                                    }
                                }
                            },
                            legend: {
                                display: false
                            }
                        },
                        responsive: true,
                    }
                });
                window.objCategoriesChart = objCategoriesChart;

                const table = $('#categoriesChartTable');

                $('#percent-toggle-btn').on('click', function() {
                    let btn = $(this);
                    let active = btn.hasClass('active');
                    active ? btn.removeClass('active') : btn.addClass('active');
                    table.find('tr').each(function() {
                        let tr = $(this);
                        if(tr.data('percentage') <= 2) {
                            let key = tr.data('key');
                            let visible = objCategoriesChart.getDataVisibility(key);
                            visible ? tr.addClass('strikeout') : tr.removeClass('strikeout');
                            objCategoriesChart.toggleDataVisibility(key);
                        }
                    });
                    updateAmount();
                    objCategoriesChart.update();
                });

                table.on('click', 'tr', function() {
                    let tr = $(this);
                    let key = tr.data('key');
                    let visible = objCategoriesChart.getDataVisibility(key);
                    visible ? tr.addClass('strikeout') : tr.removeClass('strikeout');
                    updateAmount();
                    objCategoriesChart.toggleDataVisibility(key);
                    objCategoriesChart.update();
                });
            }
            generateCategoriesChartTable(categoriesChart, objCategoriesChart);
        }

        function generateCategoriesChartTable(categoriesChart, objCategoriesChart) {
            const table = $('#categoriesChartTable');
            table.empty();

            $('#percent-toggle-btn').hasClass('active') ? '' : $('#percent-toggle-btn').removeClass('active');

            const dataset = categoriesChart.datasets[0];
            const amounts = dataset.data;
            amounts.forEach((value, key) => {
                let color = dataset.backgroundColor[key];
                let percentage = calculatePercentageTotal(arraySum(dataset.data), value);
                let nbClients = categoriesChart.clients[key].length;

                let previousValue = "previous_year" in categoriesChart ? categoriesChart.previous_year[categoriesChart.labels[key]] : 0;
                let evolution = calculatePercentageEvolution(previousValue, value);
                let res = previousValue < 0 || value < 0 ? '' : evolution === Infinity || isNaN(evolution) ? "New" : evolution;

                table.append(`
                    <tr data-key="${key}" data-percentage="${percentage}" data-value="${value}">
                        <td><div style="height: 12px;width: 12px;background-color: ${color}; border-radius: 3px"></div></td>
                        <td>${percentage}%</td>
                        <td>${categoriesChart.labels[key]}</td>
                        <td>${formatMoney(value, chartCurrencyId, account.country_id)}</td>
                        <td>${nbClients}</td>
                        <td>${formatMoney(value / nbClients, chartCurrencyId, account.country_id)}</td>
                        <td>${htmlEvolution(res)}</td>
                    </tr>
                `);
            });

            updateAmount();
            resetDataVisibility(objCategoriesChart);
        }

        function resetDataVisibility(objCategoriesChart) {
            const table = $('#categoriesChartTable');
            table.find('tr').each(function() {
                key = $(this).data('key');
                if(!objCategoriesChart.getDataVisibility(key)) {
                    objCategoriesChart.toggleDataVisibility(key);
                };
            })
            objCategoriesChart.update();
        }

        function updateAmount() {
            const table = $('#categoriesChartTable');
            let total = 0;
            table.find('tr').each(function() {
                if (!$(this).hasClass('strikeout')) {
                    total += $(this).data('value');
                }
            });
            $('#categoriesChartValue').text(formatMoney(total, chartCurrencyId, account.country_id))
        }

        function loadYearlySummaryChart(yearlySummaryChart) {
            var ctx = document.getElementById('yearlySummaryChart').getContext('2d');
            if (window.objYearlySummaryChart) {
                window.objYearlySummaryChart.config.data = yearlySummaryChart;
                window.objYearlySummaryChart.update();
            } else {
                $('#yearlySummaryChart').fadeIn();
                objYearlySummaryChart = new Chart(ctx, {
                    type: 'bar',
                    data: yearlySummaryChart,
                    options: {
                        scales: {
                            x: {
                                grid: {
                                    display: false,
                                },
                            },
                            y: {
                                ticks: {
                                    display: false,
                                },
                                grid: {
                                    display: false,
                                    drawBorder: false,
                                },
                            }
                        },
                        plugins: {
                            tooltip: {
                                mode: 'x',
                                callbacks: {
                                    title: function (item) {
                                        return moment(item[0].label).format("{{ $account->getMomentDateFormat() }}");
                                    },
                                    label: function (item) {
                                        if (item.datasetIndex == 0) {
                                            var label = " {!! trans('texts.invoices') !!}: ";
                                        } else if (item.datasetIndex == 1) {
                                            var label = " {!! trans('texts.expenses') !!}: ";
                                        }
                                        return label + formatMoney(item.parsed.y, chartCurrencyId, account.country_id);
                                    }
                                }
                            },
                            legend: {
                                display: false
                            },
                        },
                        legend: {
                            display: false,
                        }
                    }
                });
                window.objYearlySummaryChart = objYearlySummaryChart;
            }
        }

        function loadPaymentsChart(paymentsChart, totals) {
            var ctx = document.getElementById('paymentsChart').getContext('2d');
            if (window.objPaymentsChart) {
                window.objPaymentsChart.config.data = paymentsChart;
                window.objPaymentsChart.update();
            } else {
                $('#progress-div').hide();
                $('#paymentsChart').fadeIn();
                objPaymentsChart = new Chart(ctx, {
                    type: 'doughnut',
                    data: paymentsChart,
                    options: {
                        plugins: {
                            tooltip: {
                                callbacks: {
                                    title: function (context) {
                                        let percentage = calculatePercentageTotal(arraySum(context[0].dataset.data), context[0].dataset.data[context[0].dataIndex]);
                                        return context[0].label + ` (${percentage}%)`;
                                    },
                                    label: function (context) {
                                        return formatMoney(context.raw, chartCurrencyId, account.country_id);
                                    }
                                }
                            },
                            legend: {
                                display: false
                            }
                        },
                        responsive: true,
                    }
                });
                window.objPaymentsChart = objPaymentsChart;
            }
            generatePaymentsChartTable(paymentsChart, objPaymentsChart, totals);
        }

        function generatePaymentsChartTable(paymentsChart, objPaymentsChart, totals) {
            const table = $('#paymentsChartTable');
            table.empty();

            table.append(`
                <tr class="bold">
                    <td></td>
                    <td></td>
                    <td></td>
                    <td>Factures</td>
                    <td>Montant</td>
                </tr>
            `);
            const dataset = paymentsChart.datasets[0];
            const amounts = dataset.data;
            amounts.forEach((value, key) => {
                let color = dataset.backgroundColor[key];
                let percentage = calculatePercentageTotal(arraySum(dataset.data), value);

                table.append(`
                    <tr data-key="${key}" data-percentage="${percentage}" data-value="${value}">
                        <td><div style="height: 12px;width: 12px;background-color: ${color}; border-radius: 3px"></div></td>
                        <td>${percentage}%</td>
                        <td>${paymentsChart.labels[key]}</td>
                        <td>${key == 0 ? '' : paymentsChart.counts[key]}</td>
                        <td>${formatMoney(value, chartCurrencyId, account.country_id)}</td>
                    </tr>
                `);
            });

            // Update values
            $('#paymentsValue').html(formatMoney(arraySum(totals.current_year.invoiced) - arraySum(totals.current_year.payments), chartCurrencyId, account.country_id));
        }

        function loadClientsChart(clientsChart) {
            var ctx = document.getElementById('clientsChart').getContext('2d');
            if (window.objClientsChart) {
                window.objClientsChart.config.data = clientsChart;
                window.objClientsChart.update();
            } else {
                $('#clientsChart').fadeIn();
                objClientsChart = new Chart(ctx, {
                    type: 'bar',
                    data: clientsChart,
                    options: {
                        scales: {
                            x: {
                                grid: {
                                    display: false,
                                },
                            },
                            y: {
                                ticks: {
                                    display: false,
                                },
                                grid: {
                                    display: false,
                                    drawBorder: false,
                                },
                                linePercentage: 0.5,
                            }
                        },
                        plugins: {
                            tooltip: {
                                mode: 'x',
                                callbacks: {
                                    title: function (item) {
                                        return moment(item[0].label).format("{{ $account->getMomentDateFormat() }}");
                                    },
                                    label: function (item) {
                                        if (item.datasetIndex == 0) {
                                            var label = " Evolution: ";
                                            var value = item.parsed.y;
                                        } else if (item.datasetIndex == 1) {
                                            var label = " Clients: ";
                                            var value = item.parsed.y * 8;
                                        }
                                        return label + value;
                                    }
                                }
                            },
                            legend: {
                                display: false
                            },
                        },
                        legend: {
                            display: false,
                        }
                    }
                });
                window.objClientsChart = objClientsChart;
            }
        }

        function loadRevenuesChart(revenuesChart) {
            var ctx = document.getElementById('revenuesChart').getContext('2d');
            if (window.objRevenuesChart) {
                window.objRevenuesChart.config.data = revenuesChart;
                window.objRevenuesChart.update();
            } else {
                $('#revenuesChart').fadeIn();
                objRevenuesChart = new Chart(ctx, {
                    type: 'line',
                    data: revenuesChart,
                    options: {
                        maintainAspectRatio: false,
                        scales: {
                            x: {
                                type: 'time',
                                time: {
                                    unit: 'month',
                                    round: 'month',
                                },
                                grid: {
                                    display: false,
                                },
                            },
                            y: {
                                grid: {
                                    display: true,
                                    drawBorder: true,
                                },
                            }
                        },
                        plugins: {
                            tooltip: {
                                mode: 'x',
                                callbacks: {
                                    title: function(item) {
                                        return moment(item[0].label).format("{{ $account->getMomentDateFormat() }}");
                                    },
                                    label: function(item) {
                                        let dataset = window.objRevenuesChart ? window.objRevenuesChart.config.data.datasets : revenuesChart.datasets;
                                        if (dataset.length < 4) {
                                            if (item.datasetIndex == 0) {
                                                var label = " {!! trans('texts.ca_recurring') !!}: ";
                                            } else if (item.datasetIndex == 1) {
                                                var label = " {!! trans('texts.ca_no_recurring') !!}: ";
                                            }
                                        } else {
                                            if (item.datasetIndex == 0) {
                                                var label = " {!! trans('texts.ca_recurring_N-1') !!}: ";
                                            } else if (item.datasetIndex == 1) {
                                                var label = " {!! trans('texts.ca_no_recurring_N-1') !!}: ";
                                            } else if (item.datasetIndex == 2) {
                                                var label = " {!! trans('texts.ca_recurring') !!}: ";
                                            } else if (item.datasetIndex == 3) {
                                                var label = " {!! trans('texts.ca_no_recurring') !!}: ";
                                            }
                                        }
                                        return label + formatMoney(item.parsed.y, chartCurrencyId, account.country_id);
                                    }
                                }
                            },
                            legend: {
                                position: 'bottom',
                            }
                        }
                    }
                });
                window.objRevenuesChart = objRevenuesChart;
            }
        }

        function loadRevenuesClChart(revenuesClChart) {
            var ctx = document.getElementById('revenuesClChart').getContext('2d');
            if (window.objRevenuesClChart) {
                window.objRevenuesClChart.config.data = revenuesClChart;
                window.objRevenuesClChart.update();
            } else {
                $('#revenuesClChart').fadeIn();
                objRevenuesClChart = new Chart(ctx, {
                    type: 'line',
                    data: revenuesClChart,
                    options: {
                        maintainAspectRatio: false,
                        scales: {
                            x: {
                                type: 'time',
                                time: {
                                    unit: 'month',
                                    round: 'month',
                                },
                                grid: {
                                    display: false,
                                },
                            },
                            y: {
                                grid: {
                                    display: true,
                                    drawBorder: true,
                                },
                            }
                        },
                        plugins: {
                            tooltip: {
                                mode: 'x',
                                callbacks: {
                                    title: function(item) {
                                        return moment(item[0].label).format("{{ $account->getMomentDateFormat() }}");
                                    },
                                    label: function(item) {
                                        let dataset = window.objRevenuesClChart ? window.objRevenuesClChart.config.data.datasets : revenuesClChart.datasets;
                                        if (dataset.length < 4) {
                                            if (item.datasetIndex == 0) {
                                                var label = " {!! trans('texts.ca_old_clients') !!}: ";
                                            } else if (item.datasetIndex == 1) {
                                                var label = " {!! trans('texts.ca_new_clients') !!}: ";
                                            }
                                        } else {
                                            if (item.datasetIndex == 0) {
                                                var label = " {!! trans('texts.ca_old_clients_N-1') !!}: ";
                                            } else if (item.datasetIndex == 1) {
                                                var label = " {!! trans('texts.ca_new_clients_N-1') !!}: ";
                                            } else if (item.datasetIndex == 2) {
                                                var label = " {!! trans('texts.ca_old_clients') !!}: ";
                                            } else if (item.datasetIndex == 3) {
                                                var label = " {!! trans('texts.ca_new_clients') !!}: ";
                                            }
                                        }
                                        return label + formatMoney(item.parsed.y, chartCurrencyId, account.country_id);
                                    }
                                }
                            },
                            legend: {
                                position: 'bottom',
                            }
                        }
                    }
                });
                window.objRevenuesClChart = objRevenuesClChart;
            }
        }

        function loadDataTable(totals) {
            let table = $('#dataCaTable tbody');
            table.empty();
            let previous_year = "previous_year" in totals;
            let ca_recurring_evolution,
                ca_no_recurring_evolution,
                ca_new_clients_evolution,
                ca_old_clients_evolution,
                expenses_evolution,
                payments_evolution,
                real_margin_evolution,
                potential_margin_evolution;

            if (previous_year) {
                for (let i = 0; i < totals.current_year.labels.length; i++) {
                    ca_recurring_evolution = htmlEvolution(calculatePercentageEvolution(totals.previous_year.recurringAmount[i], totals.current_year.recurringAmount[i]));
                    ca_no_recurring_evolution = htmlEvolution(calculatePercentageEvolution(totals.previous_year.noRecurringAmount[i], totals.current_year.noRecurringAmount[i]));
                    ca_new_clients_evolution = htmlEvolution(calculatePercentageEvolution(totals.previous_year.newClients[i], totals.current_year.newClients[i]));
                    ca_old_clients_evolution = htmlEvolution(calculatePercentageEvolution(totals.previous_year.oldClients[i], totals.current_year.oldClients[i]));
                    expenses_evolution = htmlEvolution(calculatePercentageEvolution(totals.previous_year.expenses[i], totals.current_year.expenses[i]));
                    payments_evolution = htmlEvolution(calculatePercentageEvolution(totals.previous_year.payments[i], totals.current_year.payments[i]));
                    real_margin_evolution = htmlEvolution(calculatePercentageEvolution(totals.previous_year.realMargin[i], totals.current_year.realMargin[i]));
                    potential_margin_evolution = htmlEvolution(calculatePercentageEvolution(totals.previous_year.potentialMargin[i], totals.current_year.potentialMargin[i]));

                    let date = new Date(totals.current_year.labels[i])
                    table.append(`
                        <tr class="${i % 2 == 0 ? 'odd-color' : ''}">
                            <td>${capitalizeFirstLetter(date.toLocaleString("fr-FR", { month: "long", year: "numeric" }))}</td>
                            <td>${formatMoney(totals.current_year.recurringAmount[i], chartCurrencyId, account.country_id)}<br> ${ca_recurring_evolution}</td>
                            <td>${formatMoney(totals.current_year.noRecurringAmount[i], chartCurrencyId, account.country_id)}<br> ${ca_no_recurring_evolution}</td>
                            <td>${formatMoney(totals.current_year.newClients[i], chartCurrencyId, account.country_id)}<br> ${ca_new_clients_evolution}</td>
                            <td>${formatMoney(totals.current_year.oldClients[i], chartCurrencyId, account.country_id)}<br> ${ca_old_clients_evolution}</td>
                            <td>${formatMoney(totals.current_year.expenses[i], chartCurrencyId, account.country_id)}<br> ${expenses_evolution}</td>
                            <td>${formatMoney(totals.current_year.payments[i], chartCurrencyId, account.country_id)}<br> ${payments_evolution}</td>
                            <td>${formatMoney(totals.current_year.realMargin[i], chartCurrencyId, account.country_id)}<br> ${real_margin_evolution}</td>
                            <td>${formatMoney(totals.current_year.potentialMargin[i], chartCurrencyId, account.country_id)}<br> ${potential_margin_evolution}</td>
                            <td>${totals.current_year.nbNewClients[i]}</td>
                            <td>${totals.current_year.nbActiveClients[i]}</td>
                        </tr>
                    `);
                    date = new Date(totals.previous_year.labels[i])
                    table.append(`
                        <tr class="${i % 2 == 0 ? 'odd-color' : ''}">
                            <td>${capitalizeFirstLetter(date.toLocaleString("fr-FR", { month: "long", year: "numeric" }))}</td>
                            <td>${formatMoney(totals.previous_year.recurringAmount[i], chartCurrencyId, account.country_id)}</td>
                            <td>${formatMoney(totals.previous_year.noRecurringAmount[i], chartCurrencyId, account.country_id)}</td>
                            <td>${formatMoney(totals.previous_year.newClients[i], chartCurrencyId, account.country_id)}</td>
                            <td>${formatMoney(totals.previous_year.oldClients[i], chartCurrencyId, account.country_id)}</td>
                            <td>${formatMoney(totals.previous_year.expenses[i], chartCurrencyId, account.country_id)}</td>
                            <td>${formatMoney(totals.previous_year.payments[i], chartCurrencyId, account.country_id)}</td>
                            <td>${formatMoney(totals.previous_year.realMargin[i], chartCurrencyId, account.country_id)}</td>
                            <td>${formatMoney(totals.previous_year.potentialMargin[i], chartCurrencyId, account.country_id)}</td>
                            <td>${totals.previous_year.nbNewClients[i]}</td>
                            <td>${totals.previous_year.nbActiveClients[i]}</td>
                        </tr>
                    `);
                }
            } else {
                for (let i = 0; i < totals.current_year.labels.length; i++) {
                    let date = new Date(totals.current_year.labels[i])
                    table.append(`
                        <tr class="${i % 2 == 0 ? 'odd-color' : ''}">
                            <td>${capitalizeFirstLetter(date.toLocaleString("fr-FR", { month: "long", year: "numeric" }))}</td>
                            <td>${formatMoney(totals.current_year.recurringAmount[i], chartCurrencyId, account.country_id)}</td>
                            <td>${formatMoney(totals.current_year.noRecurringAmount[i], chartCurrencyId, account.country_id)}</td>
                            <td>${formatMoney(totals.current_year.newClients[i], chartCurrencyId, account.country_id)}</td>
                            <td>${formatMoney(totals.current_year.oldClients[i], chartCurrencyId, account.country_id)}</td>
                            <td>${formatMoney(totals.current_year.expenses[i], chartCurrencyId, account.country_id)}</td>
                            <td>${formatMoney(totals.current_year.payments[i], chartCurrencyId, account.country_id)}</td>
                            <td>${formatMoney(totals.current_year.realMargin[i], chartCurrencyId, account.country_id)}</td>
                            <td>${formatMoney(totals.current_year.potentialMargin[i], chartCurrencyId, account.country_id)}</td>
                            <td>${totals.current_year.nbNewClients[i]}</td>
                            <td>${totals.current_year.nbActiveClients[i]}</td>
                        </tr>
                    `);
                }
            }

        }

        function capitalizeFirstLetter(string) {
            return string.charAt(0).toUpperCase() + string.slice(1);
        }

        function arraySum(array) {
            array = Object.values(array);
            return array.reduce((a, b) => a + b, 0);
        }

        function calculatePercentageTotal(total, value) {
            return Math.round(((value/total) * 100));
        }

        function calculatePercentageEvolution(firstValue, secondValue) {
            return Math.round(((secondValue - firstValue) / firstValue) * 100);
        }

        function htmlEvolution(result, percentage = true) {
            let color = '0,0,0';
            let icon = 'fa fa-equals';
            if (result == 'New') {
                color = '0,75,122';
                return `<i style="color: rgb(${color}); font-weight: bold;" class="fa fa-plus"></i>`;
            }
            if(result > 0) {
                color = '51,122,0';
                icon = 'fa fa-arrow-up';
            } else if (result < 0) {
                color = '122,0,0';
                icon = 'fa fa-arrow-down';
            }
            return `
                <i style="color: rgb(${color}); font-weight: bold;" class="${icon}"></i>
                <span style="color: rgb(${color}); font-weight: bold;">${result}${percentage ? '%' : ''}</span>
            `;
        }

        var account = {!! $account !!};
        var chartCurrencyId = {{ $account->getCurrencyId() }};
		var dateRanges = {!! $account->present()->dateRangeOptions !!};
		var chartStartDate;
        var chartEndDate;

        $(function() {
            // Initialize date range selector
			chartStartDate = moment().subtract(7, 'months');
	        chartEndDate = moment().subtract(1, 'months');;
			lastRange = false;

			if (isStorageSupported()) {
				lastRange = localStorage.getItem('last:dashboard_range');
				dateRange = dateRanges[lastRange];

				if (dateRange) {
					chartStartDate = dateRange[0];
					chartEndDate = dateRange[1];
				}

				@if (count($currencies) > 1)
					var currencyId = localStorage.getItem('last:dashboard_currency_id');
					if (currencyId) {
						chartCurrencyId = currencyId;
						$("#currency-btn-group [data-button=\"" + chartCurrencyId + "\"]").addClass("active").siblings().removeClass("active");
					}
				@endif

				var groupBy = localStorage.getItem('last:dashboard_group_by');
				if (groupBy) {
					chartGroupBy = groupBy;
					$("#group-btn-group [data-button=\"" + groupBy + "\"]").addClass("active").siblings().removeClass("active");
				}
			}

            function cb(start, end, label) {
                $('#reportrange span').html(start.format('{{ $account->getMomentDateFormat() }}') + ' - ' + end.format('{{ $account->getMomentDateFormat() }}'));
                chartStartDate = start;
                chartEndDate = end;
				$('.range-label-div').show();
				if (label) {
					$('.range-label-div').text(label);
				}
                loadData();

				if (isStorageSupported() && label && label != "{{ trans('texts.custom_range') }}") {
					localStorage.setItem('last:dashboard_range', label);
				}
            }

            $('#reportrange').daterangepicker({
                locale: {
					format: "{{ $account->getMomentDateFormat() }}",
					customRangeLabel: "{{ trans('texts.custom_range') }}",
					applyLabel: "{{ trans('texts.apply') }}",
					cancelLabel: "{{ trans('texts.cancel') }}",
                },
				startDate: chartStartDate,
                endDate: chartEndDate,
                linkedCalendars: false,
                ranges: dateRanges,
            }, cb);

            cb(chartStartDate, chartEndDate, lastRange);

            $("#currency-btn-group > .btn").click(function(){
                $(this).addClass("active").siblings().removeClass("active");
                chartCurrencyId = currencyMap[$(this).text()].id;
                loadData();
				if (isStorageSupported()) {
					localStorage.setItem('last:dashboard_currency_id', $(this).attr('data-button'));
				}
            });

            $("#group-btn-group > .btn").click(function(){
                $(this).addClass("active").siblings().removeClass("active");
                chartGroupBy = $(this).attr('data-button');
                loadData();
				if (isStorageSupported()) {
					localStorage.setItem('last:dashboard_group_by', chartGroupBy);
				}
            });

            function loadData() {
                var url = '{{ url('/dashboard_chart_data') }}/' + chartStartDate.format('YYYY-MM-DD') + '/' + chartEndDate.format('YYYY-MM-DD');
                $.get(url, function(response) {
                    response = JSON.parse(response);
                    totals = response.totals;
                    console.log(response);
                    loadOverviewChart(response.overviewChart, totals);
                    loadInvoicesChart(response.invoicesChart, totals);
                    loadCategoriesChart(response.categoriesChart);
                    loadYearlySummaryChart(response.yearlySummaryChart);
                    loadPaymentsChart(response.paymentsChart, totals);
                    loadClientsChart(response.clientsChart);
                    loadRevenuesChart(response.revenuesChart);
                    loadRevenuesClChart(response.revenuesClChart);
                    loadDataTable(totals);

                    // Update values
                    $('#margeValue').text(
                        formatMoney(arraySum(totals.current_year.realMargin), chartCurrencyId, account.country_id) +
                        " / " +
                        formatMoney(arraySum(totals.current_year.potentialMargin), chartCurrencyId, account.country_id)
                    );

                    $('#revenuesRecurringValue').text(
                        formatMoney(arraySum(totals.current_year.recurringAmount), chartCurrencyId, account.country_id)
                    );
                    $('#revenuesNoRecurringValue').text(
                        formatMoney(arraySum(totals.current_year.noRecurringAmount), chartCurrencyId, account.country_id)
                    );

                    $('#revenuesNewClValue').text(
                        formatMoney(arraySum(totals.current_year.newClients), chartCurrencyId, account.country_id)
                    );
                    $('#revenuesOldClValue').text(
                        formatMoney(arraySum(totals.current_year.oldClients), chartCurrencyId, account.country_id)
                    );

                    if("previous_year" in totals) {
                        $('#margeEvolution').html(htmlEvolution(calculatePercentageEvolution(arraySum(totals.previous_year.realMargin), arraySum(totals.current_year.realMargin))));
                        $('#margePreviousValue').text(formatMoney(arraySum(totals.previous_year.realMargin), chartCurrencyId, account.country_id));

                        $('#revenuesRecurringEvolution').html(htmlEvolution(calculatePercentageEvolution(arraySum(totals.previous_year.recurringAmount), arraySum(totals.current_year.recurringAmount))));
                        $('#revenuesRecurringPreviousValue').text(formatMoney(arraySum(totals.previous_year.recurringAmount), chartCurrencyId, account.country_id));

                        $('#revenuesNoRecurringEvolution').html(htmlEvolution(calculatePercentageEvolution(arraySum(totals.previous_year.noRecurringAmount), arraySum(totals.current_year.noRecurringAmount))));
                        $('#revenuesNoRecurringPreviousValue').text(formatMoney(arraySum(totals.previous_year.noRecurringAmount), chartCurrencyId, account.country_id));

                        $('#revenuesNewClEvolution').html(htmlEvolution(calculatePercentageEvolution(arraySum(totals.previous_year.newClients), arraySum(totals.current_year.newClients))));
                        $('#revenuesNewClPreviousValue').text(formatMoney(arraySum(totals.previous_year.newClients), chartCurrencyId, account.country_id));

                        $('#revenuesOldClEvolution').html(htmlEvolution(calculatePercentageEvolution(arraySum(totals.previous_year.oldClients), arraySum(totals.current_year.oldClients))));
                        $('#revenuesOldClPreviousValue').text(formatMoney(arraySum(totals.previous_year.oldClients), chartCurrencyId, account.country_id));
                    }

                    // Remove every remaining data if range is superior to a year
                    if (!("previous_year" in totals)) {
                        $('.evolution').empty();
                        $('.previous-value').empty();
                    }
                })
            }
        });
    @else
        $(function() {
            $('.currency').show();
        })
    @endif

</script>

<div class="row">
    <div class="col-md-2">
        <ol class="breadcrumb"><li class='active'>{{ trans('texts.dashboard') }}</li></ol>
    </div>
    @if (count($tasks))
        <div class="col-md-2" style="padding-top:6px">
            @foreach ($tasks as $task)
                {!! Button::primary($task->present()->titledName)->small()->asLinkTo($task->present()->url) !!}
            @endforeach
        </div>
        <div class="col-md-8">
    @else
        <div class="col-md-10">
    @endif
        @if (Auth::user()->hasPermission('admin'))
        <div class="pull-right">
            @if (count($currencies) > 1)
            <div id="currency-btn-group" class="btn-group" role="group" style="border: 1px solid #ccc;">
              @foreach ($currencies as $key => $val)
                <button type="button" class="btn btn-normal {{ array_values($currencies)[0] == $val ? 'active' : '' }}"
                    data-button="{{ $key }}" style="font-weight:normal !important;background-color:white">{{ $val }}</button>
              @endforeach
            </div>
            @endif
            <div id="reportrange" class="pull-right" style="background: #fff; cursor: pointer; padding: 9px 14px; border: 1px solid #ccc; margin-top: 0px; margin-left:18px">
                <i class="glyphicon glyphicon-calendar fa fa-calendar"></i>&nbsp;
                <span></span> <b class="caret"></b>
            </div>
        </div>
        @endif
    </div>
</div>

@if ($account->company->hasEarnedPromo())
	@include('partials/discount_promo')
@elseif ($showBlueVinePromo)
    @include('partials/bluevine_promo')
@endif

@if ($showWhiteLabelExpired)
	@include('partials/white_label_expired')
@endif

@if (Auth::user()->hasPermission('admin'))

<div class="charts-row">
    {{-- OVERVIEW CHART --}}
    <div id="overviewChartCard" class="chart-card w-100">
        <div class="header d-flex flex-row">
            <div class="box-info d-inline-flex flex-row">
                <div class="d-flex flex-column">
                    <div class="d-flex flex-row justify-sb title-row">
                        <div class="title">Marge</div>
                        <div id="margeEvolution" class="evolution"></div>
                    </div>
                    <div class="d-flex flex-column values-row">
                        <div id="margeValue" class="value"></div>
                        <div id="margePreviousValue" class="previous-value"></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-content">
            <div>
                <canvas id="overviewChart" style="display:none"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="charts-row">
    {{-- INVOICES CHART --}}
    <div id="invoicesChartCard" class="chart-card w-38">
        <div class="header">
            <div class="lign">
                <h1>Facturations</h1>
                <div id="invoicesChartEvolution" class="evolution"></div>
            </div>
            <div class="lign">
                <h2 id="invoicesChartValue" style="color: rgb(51,122,183);"></h2>
                <h2 id="invoicesChartPreviousValue" class="previous-value" style="color: rgb(200,200,200);"></h2>
            </div>
        </div>
        <div class="card-content">
            <div style="width: 100%">
                <canvas id="invoicesChart" height="146px" style="display:none"></canvas>
            </div>
        </div>
    </div>

    {{-- CATEGORIES CHART --}}
    <div id="categoriesChartCard" class="chart-card doughnut-chart w-60">
        <div class="header">
            <div class="lign">
                <h1>Catégories</h1>
            </div>
            <div class="lign">
                <h2 id="categoriesChartValue" style="color: rgb(51,122,183);"></h2>
            </div>
        </div>
        <div class="card-content">
            <div>
                <canvas id="categoriesChart" width="100%" height="100%" style="display: none;"></canvas>
                <input type="button" value="Inclure 2%" id="percent-toggle-btn" class="active">
            </div>
            <div class="scrollable">
                <table id="categoriesChartTable"></table>
            </div>
        </div>
    </div>
</div>

<div class="charts-row">
    {{-- PAYMENTS CHART --}}
    <div id="paymentsChartCard" class="chart-card doughnut-chart w-60">
        <div class="header">
            <div class="lign">
                <h1>Reste à payer</h1>
            </div>
            <div class="lign">
                <h2 id="paymentsValue" style="color: rgb(51,122,183);"></h2>
            </div>
        </div>
        <div class="card-content">
            <div>
                <canvas id="paymentsChart" width="100%" height="100%" style="display: none;"></canvas>
            </div>
            <div class="scrollable">
                <table id="paymentsChartTable"></table>
            </div>
        </div>
    </div>

    {{-- YEARLY SUMMARY CHART --}}
    <div id="yearlySummaryChartCard" class="chart-card w-38">
        <div class="header">
            <div class="lign">
                <h1>Bilan annuel</h1>
            </div>
        </div>
        <div class="card-content">
            <div style="width: 100%">
                <canvas id="yearlySummaryChart" height="146px" style="display:none"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="charts-row">
    {{-- CLIENTS CHART --}}
    <div id="clientsChartCard" class="chart-card w-48">
        <div class="header">
            <div class="lign">
                <h1>Clients actifs</h1>
            </div>
        </div>
        <div class="card-content">
            <div style="width: 100%">
                <canvas id="clientsChart" height="146px" style="display:none"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="charts-row">
    {{-- DATA CA TABLE --}}
    <div id="dataCaTableContainer" class="chart-card w-100">
        <div class="header">
            <div class="lign">
                <h1>Table de données</h1>
            </div>
        </div>
        <div class="card-content">
            <table id="dataCaTable" class="table flex-table">
                <thead class="table__head">
                    <tr>
                        <th>Mois</th>
                        <th>CA Recurrent</th>
                        <th>CA Non recurrent</th>
                        <th>CA Nouveaux Clients</th>
                        <th>CA Anciens Clients</th>
                        <th>Depenses</th>
                        <th>Encaissement</th>
                        <th>Marge reel</th>
                        <th>Marge potentiel</th>
                        <th>Nb Nouveaux clients</th>
                        <th>Nb Clients actifs</th>
{{--                        <th></th>--}}
{{--                        <th>CA recu</th>--}}
{{--                        <th>CA non recu</th>--}}
{{--                        <th>CA nvx cl</th>--}}
{{--                        <th>CA anc cl</th>--}}
{{--                        <th>Dep</th>--}}
{{--                        <th>Encs</th>--}}
{{--                        <th>Mrg reelle</th>--}}
{{--                        <th>Mrg pot</th>--}}
{{--                        <th>Nb nvx cl</th>--}}
{{--                        <th>Nb cl actif</th>--}}
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<div class="charts-row">
    {{-- REVENUES CHART --}}
    <div id="revenuesChartCard" class="chart-card w-100">
        <div class="header d-flex flex-row">
            <div class="box-info d-inline-flex flex-row">
                <div class="d-flex flex-column">
                    <div class="d-flex flex-row justify-sb title-row">
                        <div class="title">CA Récurrent</div>
                        <div id="revenuesRecurringEvolution" class="evolution"></div>
                    </div>
                    <div class="d-flex flex-column values-row">
                        <div id="revenuesRecurringValue" class="value"></div>
                        <div id="revenuesRecurringPreviousValue" class="previous-value"></div>
                    </div>
                </div>
            </div>
            <div class="box-info d-inline-flex flex-row">
                <div class="d-flex flex-column">
                    <div class="d-flex flex-row justify-sb title-row">
                        <div class="title">CA Non-récurrent</div>
                        <div id="revenuesNoRecurringEvolution" class="evolution"></div>
                    </div>
                    <div class="d-flex flex-column values-row">
                        <div id="revenuesNoRecurringValue" class="value"></div>
                        <div id="revenuesNoRecurringPreviousValue" class="previous-value"></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-content">
            <div>
                <canvas id="revenuesChart" style="height: 300px; display:none"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="charts-row">
    {{-- REVENUES CL CHART --}}
    <div id="revenuesClChartCard" class="chart-card w-100">
        <div class="header d-flex flex-row">
            <div class="box-info d-inline-flex flex-row">
                <div class="d-flex flex-column">
                    <div class="d-flex flex-row justify-sb title-row">
                        <div class="title">CA Nouveaux clients</div>
                        <div id="revenuesNewClEvolution" class="evolution"></div>
                    </div>
                    <div class="d-flex flex-column values-row">
                        <div id="revenuesNewClValue" class="value"></div>
                        <div id="revenuesNewClPreviousValue" class="previous-value"></div>
                    </div>
                </div>
            </div>
            <div class="box-info d-inline-flex flex-row">
                <div class="d-flex flex-column">
                    <div class="d-flex flex-row justify-sb title-row">
                        <div class="title">CA Anciens clients</div>
                        <div id="revenuesOldClEvolution" class="evolution"></div>
                    </div>
                    <div class="d-flex flex-column values-row">
                        <div id="revenuesOldClValue" class="value"></div>
                        <div id="revenuesOldClPreviousValue" class="previous-value"></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-content">
            <div>
                <canvas id="revenuesClChart" style="height: 300px; display:none"></canvas>
            </div>
        </div>
    </div>
</div>

@endif

<div class="row">
{{--    <div class="col-md-6">--}}
{{--        <div class="panel panel-default dashboard" style="height:320px">--}}
{{--            <div class="panel-heading">--}}
{{--                <h3 class="panel-title in-bold-white">--}}
{{--                    <i class="glyphicon glyphicon-exclamation-sign"></i> {{ trans('texts.activity') }}--}}
{{--                    @if (Auth::user()->hasPermission('admin') && $invoicesSent)--}}
{{--                        <div class="pull-right" style="font-size:14px;padding-top:4px">--}}
{{--							@if ($invoicesSent == 1)--}}
{{--								{{ trans('texts.invoice_sent', ['count' => $invoicesSent]) }}--}}
{{--							@else--}}
{{--								{{ trans('texts.invoices_sent', ['count' => $invoicesSent]) }}--}}
{{--							@endif--}}
{{--                        </div>--}}
{{--                    @endif--}}
{{--                </h3>--}}
{{--            </div>--}}
{{--            <ul class="panel-body list-group" style="height:276px;overflow-y:auto;">--}}
{{--                @foreach ($activities as $activity)--}}
{{--                <li class="list-group-item">--}}
{{--                    <span style="color:#888;font-style:italic">{{ Utils::timestampToDateString(strtotime($activity->created_at)) }}:</span>--}}
{{--                    {!! $activity->getMessage() !!}--}}
{{--                </li>--}}
{{--                @endforeach--}}
{{--            </ul>--}}
{{--        </div>--}}
{{--    </div>--}}

    <div class="col-md-6">
        <div class="panel panel-default dashboard" style="height:320px;">
            <div class="panel-heading" style="margin:0; background-color: #f5f5f5 !important;">
                <h3 class="panel-title" style="color: black !important">
					@if (Auth::user()->hasPermission('admin'))
	                    @if (count($averageInvoice))
	                        <div class="pull-right" style="font-size:14px;padding-top:4px;font-weight:bold">
	                            @foreach ($averageInvoice as $item)
	                                <span class="currency currency_{{ $item->currency_id ?: $account->getCurrencyId() }}" style="display:none">
	                                    {{ trans('texts.average_invoice') }}
	                                    {{ Utils::formatMoney($item->invoice_avg, $item->currency_id) }} |
	                                </span>
	                            @endforeach
	                            <span class="average-div" style="color:#337ab7"/>
	                        </div>
						@endif
                    @endif
                    <i class="glyphicon glyphicon-ok-sign"></i> {{ trans('texts.recent_payments') }}
                </h3>
            </div>
            <div class="panel-body" style="height:274px;overflow-y:auto;">
                <table class="table table-striped">
                    <thead>
                        <th>{{ trans('texts.invoice_number_short') }}</th>
                        <th>{{ trans('texts.client') }}</th>
                        <th>{{ trans('texts.payment_date') }}</th>
                        <th>{{ trans('texts.amount') }}</th>
                    </thead>
                    <tbody>
                        @foreach ($payments as $payment)
                        <tr>
                            <td>{!! \App\Models\Invoice::calcLink($payment) !!}</td>
                            @can('view', [ENTITY_CLIENT, $payment])
                                <td>{!! link_to('/clients/'.$payment->client_public_id, trim($payment->client_name) ?: (trim($payment->first_name . ' ' . $payment->last_name) ?: $payment->email)) !!}</td>
                            @else
                                <td>{{ trim($payment->client_name) ?: (trim($payment->first_name . ' ' . $payment->last_name) ?: $payment->email) }}</td>
                            @endcan
                            <td>{{ Utils::fromSqlDate($payment->payment_date) }}</td>
                            <td>{{ Utils::formatMoney($payment->amount, $payment->currency_id ?: ($account->currency_id ?: DEFAULT_CURRENCY)) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="panel panel-default dashboard" style="height:320px;">
            <div class="panel-heading" style="margin:0; background-color: #f5f5f5 !important;">
                <h3 class="panel-title" style="color: black !important">
                    <div class="pull-right" style="font-size:14px;padding-top:4px">
                        Total : {{ Utils::formatMoney($upcomingTotal, $account->currency_id) }}
                    </div>
                    <i class="glyphicon glyphicon-time"></i> {{ trans('texts.upcoming_recurring_invoices') }}
                </h3>
            </div>
            <div class="panel-body" style="height:274px;overflow-y:auto;">
                <table class="table table-striped">
                    <thead>
                    <th>{{ trans('texts.recurring_invoice') }}</th>
                    <th>{{ trans('texts.name') }}</th>
                    <th>{{ trans('texts.next_sent') }}</th>
                    <th>{{ trans('texts.amount') }}</th>
                    </thead>
                    <tbody>
                    @foreach ($upcoming as $invoice)
                        @if ($invoice->invoice_type_id == INVOICE_TYPE_STANDARD)
                            <tr>
                                <td>{!! link_to("recurring_invoices/{$invoice->invoice_public_id}", $invoice->invoice_public_id) !!}</td>
                                <td>{!! link_to("clients/{$invoice->client_public_id}", $invoice->client_name) !!}</td>
                                <td>{{ Utils::fromSqlDate($invoice->getNextSendDate()->format('Y-m-d')) }}</td>
                                <td>{{ Utils::formatMoney($invoice->amount, $invoice->currency_id ?: ($account->currency_id ?: DEFAULT_CURRENCY)) }}</td>
                            </tr>
                        @endif
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{--<div class="row">--}}

{{--    <div class="col-md-6">--}}
{{--        <div class="panel panel-default dashboard" style="height:320px">--}}
{{--            <div class="panel-heading" style="background-color:#777 !important">--}}
{{--                <h3 class="panel-title in-bold-white">--}}
{{--                    <i class="glyphicon glyphicon-time"></i> {{ trans('texts.invoices_past_due') }}--}}
{{--                </h3>--}}
{{--            </div>--}}
{{--            <div class="panel-body" style="height:274px;overflow-y:auto;">--}}
{{--                <table class="table table-striped">--}}
{{--                    <thead>--}}
{{--                        <th>{{ trans('texts.invoice_number_short') }}</th>--}}
{{--                        <th>{{ trans('texts.client') }}</th>--}}
{{--                        <th>{{ trans('texts.due_date') }}</th>--}}
{{--                        <th>{{ trans('texts.balance_due') }}</th>--}}
{{--                    </thead>--}}
{{--                    <tbody>--}}
{{--                        @foreach ($pastDue as $invoice)--}}
{{--                            @if ($invoice->invoice_type_id == INVOICE_TYPE_STANDARD)--}}
{{--                                <tr>--}}
{{--                                    <td>{!! \App\Models\Invoice::calcLink($invoice) !!}</td>--}}
{{--                                    @can('view', [ENTITY_CLIENT, $invoice])--}}
{{--                                        <td>{!! link_to('/clients/'.$invoice->client_public_id, trim($invoice->client_name) ?: (trim($invoice->first_name . ' ' . $invoice->last_name) ?: $invoice->email)) !!}</td>--}}
{{--                                    @else--}}
{{--                                        <td>{{ trim($invoice->client_name) ?: (trim($invoice->first_name . ' ' . $invoice->last_name) ?: $invoice->email) }}</td>--}}
{{--                                    @endcan--}}
{{--                                    <td>{{ Utils::fromSqlDate($invoice->due_date) }}</td>--}}
{{--                                    <td>{{ Utils::formatMoney($invoice->balance, $invoice->currency_id ?: ($account->currency_id ?: DEFAULT_CURRENCY)) }}</td>--}}
{{--                                </tr>--}}
{{--                            @endif--}}
{{--                        @endforeach--}}
{{--                    </tbody>--}}
{{--                </table>--}}
{{--            </div>--}}
{{--        </div>--}}
{{--    </div>--}}
{{--</div>--}}

{{--@if ($hasQuotes)--}}
{{--    <div class="row">--}}
{{--        <div class="col-md-6">--}}
{{--            <div class="panel panel-default dashboard" style="height:320px;">--}}
{{--                <div class="panel-heading" style="margin:0; background-color: #f5f5f5 !important;">--}}
{{--                    <h3 class="panel-title" style="color: black !important">--}}
{{--                        <i class="glyphicon glyphicon-time"></i> {{ trans('texts.upcoming_quotes') }}--}}
{{--                    </h3>--}}
{{--                </div>--}}
{{--                <div class="panel-body" style="height:274px;overflow-y:auto;">--}}
{{--                    <table class="table table-striped">--}}
{{--                        <thead>--}}
{{--                            <th>{{ trans('texts.quote_number_short') }}</th>--}}
{{--                            <th>{{ trans('texts.client') }}</th>--}}
{{--                            <th>{{ trans('texts.valid_until') }}</th>--}}
{{--                            <th>{{ trans('texts.amount') }}</th>--}}
{{--                        </thead>--}}
{{--                        <tbody>--}}
{{--                            @foreach ($upcoming as $invoice)--}}
{{--                                @if ($invoice->invoice_type_id == INVOICE_TYPE_QUOTE)--}}
{{--                                    <tr>--}}
{{--                                        <td>{!! \App\Models\Invoice::calcLink($invoice) !!}</td>--}}
{{--                                        <td>{!! link_to('/clients/'.$invoice->client_public_id, trim($invoice->client_name) ?: (trim($invoice->first_name . ' ' . $invoice->last_name) ?: $invoice->email)) !!}</td>--}}
{{--                                        <td>{{ Utils::fromSqlDate($invoice->due_date) }}</td>--}}
{{--                                        <td>{{ Utils::formatMoney($invoice->balance, $invoice->currency_id ?: ($account->currency_id ?: DEFAULT_CURRENCY)) }}</td>--}}
{{--                                    </tr>--}}
{{--                                @endif--}}
{{--                            @endforeach--}}
{{--                        </tbody>--}}
{{--                    </table>--}}
{{--                </div>--}}
{{--            </div>--}}
{{--        </div>--}}
{{--        <div class="col-md-6">--}}
{{--            <div class="panel panel-default dashboard" style="height:320px">--}}
{{--                <div class="panel-heading" style="background-color:#777 !important">--}}
{{--                    <h3 class="panel-title in-bold-white">--}}
{{--                        <i class="glyphicon glyphicon-time"></i> {{ trans('texts.expired_quotes') }}--}}
{{--                    </h3>--}}
{{--                </div>--}}
{{--                <div class="panel-body" style="height:274px;overflow-y:auto;">--}}
{{--                    <table class="table table-striped">--}}
{{--                        <thead>--}}
{{--                            <th>{{ trans('texts.quote_number_short') }}</th>--}}
{{--                            <th>{{ trans('texts.client') }}</th>--}}
{{--                            <th>{{ trans('texts.valid_until') }}</th>--}}
{{--                            <th>{{ trans('texts.amount') }}</th>--}}
{{--                        </thead>--}}
{{--                        <tbody>--}}
{{--                            @foreach ($pastDue as $invoice)--}}
{{--                                @if ($invoice->invoice_type_id == INVOICE_TYPE_QUOTE)--}}
{{--                                    <tr>--}}
{{--                                        <td>{!! \App\Models\Invoice::calcLink($invoice) !!}</td>--}}
{{--                                        <td>{!! link_to('/clients/'.$invoice->client_public_id, trim($invoice->client_name) ?: (trim($invoice->first_name . ' ' . $invoice->last_name) ?: $invoice->email)) !!}</td>--}}
{{--                                        <td>{{ Utils::fromSqlDate($invoice->due_date) }}</td>--}}
{{--                                        <td>{{ Utils::formatMoney($invoice->balance, $invoice->currency_id ?: ($account->currency_id ?: DEFAULT_CURRENCY)) }}</td>--}}
{{--                                    </tr>--}}
{{--                                @endif--}}
{{--                            @endforeach--}}
{{--                        </tbody>--}}
{{--                    </table>--}}
{{--                </div>--}}
{{--            </div>--}}
{{--        </div>--}}
{{--    </div>--}}
{{--@endif--}}

@stop
