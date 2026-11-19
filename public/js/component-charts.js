$(document).ready(function() {
    // Canvas grafik hanya ada di halaman dasbor
    if (!barChartCanvas || !pieChartCanvas) return;

    const formatCountLabel = typeof window.formatCountLabel === 'function'
        ? window.formatCountLabel
        : value => String(Number.isFinite(Number(value)) ? Math.round(Number(value)) : 0);

    const compactAxisStep = maxValue => {
        const max = Math.max(0, Number(maxValue) || 0);
        if (max <= 10) return 1;

        const rough = max / 5;
        const power = 10 ** Math.floor(Math.log10(rough));
        const fraction = rough / power;

        if (fraction <= 1) return power;
        if (fraction <= 2) return 2 * power;
        if (fraction <= 5) return 5 * power;

        return 10 * power;
    };

    const chartCount = context => {
        if (typeof context.parsed === 'number') {
            return context.parsed;
        }

        return context.parsed?.y ?? 0;
    };

    const compactCountLabelsPlugin = {
        id: 'compactCountLabels',
        afterDatasetsDraw(chart) {
            const { ctx } = chart;
            const meta = chart.getDatasetMeta(0);
            const dataset = chart.data.datasets[0];
            if (!meta || !dataset) return;

            ctx.save();
            ctx.textAlign = 'center';
            ctx.font = '600 13px system-ui, sans-serif';

            meta.data.forEach((element, index) => {
                const value = dataset.data[index];
                const text = formatCountLabel(value);

                if (chart.config.type === 'bar') {
                    ctx.fillStyle = '#334155';
                    ctx.textBaseline = 'bottom';
                    ctx.fillText(text, element.x, element.y - 8);
                    return;
                }

                if (Number(value) <= 0) return;

                const center = typeof element.getCenterPoint === 'function'
                    ? element.getCenterPoint()
                    : element.tooltipPosition();

                ctx.fillStyle = '#fff';
                ctx.textBaseline = 'middle';
                ctx.fillText(text, center.x, center.y);
            });

            ctx.restore();
        }
    };

    const maxCount = Math.max(0, ...globalChartData.datasets[0].data.map(Number));
    const yStep = compactAxisStep(maxCount);
    const barWrap = barChartCanvas.parentElement;
    const pieWrap = pieChartCanvas.parentElement;

    const barDensity = width => {
        if (width < 420) return { radius: 10, thickness: 36, tick: 11, pad: 16 };
        if (width < 720) return { radius: 12, thickness: 56, tick: 12, pad: 20 };

        return { radius: 16, thickness: 84, tick: 13, pad: 24 };
    };

    const initialBar = barDensity(barWrap?.clientWidth || window.innerWidth);

    const barChart = new Chart(barChartCanvas, {
        type: 'bar',
        data: {
            ...globalChartData,
            datasets: [{
                ...globalChartData.datasets[0],
                borderRadius: initialBar.radius,
                borderSkipped: 0,
                maxBarThickness: initialBar.thickness,
                categoryPercentage: 0.68,
                barPercentage: 0.82
            }]
        },
        plugins: [compactCountLabelsPlugin],
        options: {
            responsive: true,
            maintainAspectRatio: false,
            layout: {
                padding: { top: initialBar.pad }
            },
            onResize(chart, size) {
                const next = barDensity(size.width);
                const dataset = chart.data.datasets[0];
                dataset.borderRadius = next.radius;
                dataset.maxBarThickness = next.thickness;
                chart.options.layout.padding.top = next.pad;
                chart.options.scales.x.ticks.font.size = next.tick;
                chart.options.scales.y.ticks.font.size = next.tick;
            },
            plugins: {
                legend: { display: 0 },
                tooltip: {
                    callbacks: {
                        label: context => `${context.label}: ${formatCountLabel(chartCount(context))}`
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: {
                        maxRotation: 0,
                        autoSkip: true,
                        font: { size: initialBar.tick }
                    }
                },
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0,
                        stepSize: yStep,
                        font: { size: initialBar.tick },
                        callback: value => Number.isInteger(Number(value)) ? formatCountLabel(value) : ''
                    },
                    afterBuildTicks(axis) {
                        const seen = new Set();
                        axis.ticks = axis.ticks.filter(tick => {
                            const n = Number(tick.value);
                            if (!Number.isInteger(n)) return false;

                            const label = formatCountLabel(n);
                            if (seen.has(label)) return false;

                            seen.add(label);
                            return true;
                        });
                    },
                    grid: {
                        display: true
                    }
                }
            }
        }
    });

    const pieCounts = globalChartData.datasets[0].data;
    const pieLegend = width => (width < 520 ? 'bottom' : 'top');
    const initialPieWidth = pieWrap?.clientWidth || window.innerWidth;

    new Chart(pieChartCanvas, {
        type: 'pie',
        data: {
            ...globalChartData,
            labels: globalChartData.labels.map((label, index) => `${label} (${formatCountLabel(pieCounts[index])})`),
            datasets: [{ ...globalChartData.datasets[0], borderColor: '#fff', borderWidth: 2 }]
        },
        plugins: [compactCountLabelsPlugin],
        options: {
            responsive: true,
            maintainAspectRatio: false,
            layout: {
                padding: { top: 4, bottom: 4 }
            },
            onResize(chart, size) {
                const position = pieLegend(size.width);
                const labels = chart.options.plugins.legend.labels;
                chart.options.plugins.legend.position = position;
                labels.padding = position === 'bottom' ? 10 : 16;
                labels.font.size = size.width < 420 ? 11 : 13;
            },
            plugins: {
                legend: {
                    position: pieLegend(initialPieWidth),
                    labels: {
                        usePointStyle: 1,
                        pointStyle: 'circle',
                        padding: initialPieWidth < 520 ? 10 : 16,
                        boxWidth: 8,
                        font: { size: initialPieWidth < 420 ? 11 : 13, weight: 500 }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: context => {
                            const value = chartCount(context);
                            const total = context.dataset.data.reduce((sum, item) => sum + Number(item), 0);
                            const percent = total ? Math.round((value / total) * 100) : 0;
                            const original = globalChartData.labels[context.dataIndex] || context.label;

                            return `${original}: ${formatCountLabel(value)} (${percent}%)`;
                        }
                    }
                }
            }
        }
    });
});
