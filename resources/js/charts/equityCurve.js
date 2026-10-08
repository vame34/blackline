import { chartTimezoneOptions, formatChartTime } from '../timezone';
import {
    AreaSeries,
    LineSeries,
    createChart,
} from 'lightweight-charts';

const parseSeries = (raw) => {
    if (!raw) return [];
    try {
        return JSON.parse(raw).map((value) => Number(value)).filter((value) => Number.isFinite(value));
    } catch (_error) {
        return [];
    }
};

const buildTimeline = (length, periodDays, periodEndIso) => {
    const safeLength = Math.max(1, Number(length) || 1);
    const days = Math.max(1, Number(periodDays) || 365);
    const endDate = periodEndIso ? new Date(periodEndIso) : new Date();
    const endMs = Number.isFinite(endDate.getTime()) ? endDate.getTime() : Date.now();
    const startMs = endMs - (days * 24 * 60 * 60 * 1000);

    if (safeLength === 1) {
        return [Math.floor(endMs / 1000)];
    }

    const step = (endMs - startMs) / (safeLength - 1);
    return Array.from({ length: safeLength }, (_, index) => Math.floor((startMs + (step * index)) / 1000));
};

const formatMoney = (value) => new Intl.NumberFormat(undefined, {
    maximumFractionDigits: 2,
}).format(value);

const formatPercent = (value) => `${value >= 0 ? '+' : ''}${value.toFixed(2)}%`;

const applyResponsiveChartOptions = (chart, container) => {
    const width = container.clientWidth || 600;
    const isMobile = width < 768;
    chart.applyOptions({
        timeScale: {
            barSpacing: isMobile ? 5 : 8,
            minBarSpacing: isMobile ? 3 : 5,
            rightOffset: 0,
            fixLeftEdge: true,
            fixRightEdge: true,
            lockVisibleTimeRangeOnResize: true,
        },
        rightPriceScale: {
            visible: true,
            borderColor: 'rgba(255,255,255,0.08)',
            scaleMargins: isMobile ? { top: 0.08, bottom: 0.1 } : { top: 0.06, bottom: 0.08 },
        },
    });
};

const initPerformanceTerminalChart = () => {
    const roots = document.querySelectorAll('[data-performance-equity-root="true"]');
    if (!roots.length) return;

    roots.forEach((root) => {
        if (root.dataset.lwcBound === 'true') return;

        const rawValues = parseSeries(root.getAttribute('data-equity-series'));
        if (rawValues.length < 2) return;

        const container = root.querySelector('[data-lwc-equity-container]');
        const loading = root.querySelector('[data-lwc-loading]');
        const changeEl = root.querySelector('[data-lwc-change]');
        const lastValueEl = root.querySelector('[data-lwc-last-value]');
        if (!container) return;

        const periodDays = Math.max(1, Number(root.getAttribute('data-equity-period-days') || 365));
        const periodEnd = root.getAttribute('data-equity-period-end');
        const timeline = buildTimeline(rawValues.length, periodDays, periodEnd);

        const points = rawValues.map((value, index) => ({
            time: timeline[index],
            value,
        }));

        const chart = createChart(container, {
            width: container.clientWidth || 600,
            height: container.clientHeight || 320,
            layout: {
                background: { type: 'solid', color: 'transparent' },
                textColor: '#a3a3a3',
            },
            grid: {
                vertLines: { color: 'rgba(255,255,255,0.06)' },
                horzLines: { color: 'rgba(255,255,255,0.08)' },
            },
            rightPriceScale: {
                borderColor: 'rgba(255,255,255,0.08)',
            },
            timeScale: {
                borderColor: 'rgba(255,255,255,0.08)',
                timeVisible: true,
                secondsVisible: false,
            },
            crosshair: {
                mode: 0,
            },
            handleScroll: {
                mouseWheel: true,
                pressedMouseMove: true,
                horzTouchDrag: true,
                vertTouchDrag: false,
            },
            handleScale: {
                axisPressedMouseMove: true,
                mouseWheel: true,
                pinch: true,
            },
        });
        chart.applyOptions(chartTimezoneOptions());

        const areaSeries = chart.addSeries(AreaSeries, {
            priceLineVisible: false,
            lastValueVisible: false,
            lineColor: '#00ff88',
            topColor: 'rgba(0,255,136,0.24)',
            bottomColor: 'rgba(0,255,136,0.04)',
            lineWidth: 2,
        });

        const mainLine = chart.addSeries(LineSeries, {
            color: '#00ff88',
            lineWidth: 2.6,
            priceLineVisible: true,
            lastValueVisible: true,
        });

        areaSeries.setData(points);
        mainLine.setData(points);
        applyResponsiveChartOptions(chart, container);
        chart.timeScale().fitContent();
        const visibleBars = Math.max(50, Math.min(220, Math.floor(points.length * 0.24)));
        chart.timeScale().setVisibleLogicalRange({
            from: Math.max(0, points.length - visibleBars),
            to: points.length - 1,
        });
        let isClampingRange = false;
        chart.timeScale().subscribeVisibleLogicalRangeChange((range) => {
            if (!range || isClampingRange) return;
            const min = 0;
            const max = points.length - 1;
            const span = Math.max(2, range.to - range.from);
            let from = range.from;
            let to = range.to;

            if (from < min) {
                from = min;
                to = from + span;
            }
            if (to > max) {
                to = max;
                from = to - span;
            }
            from = Math.max(min, from);
            to = Math.min(max, to);

            if (Math.abs(from - range.from) > 0.001 || Math.abs(to - range.to) > 0.001) {
                isClampingRange = true;
                chart.timeScale().setVisibleLogicalRange({ from, to });
                isClampingRange = false;
            }
        });

        const start = rawValues[0];
        const last = rawValues[rawValues.length - 1];
        const totalPct = start > 0 ? ((last / start) - 1) * 100 : 0;

        if (changeEl) {
            changeEl.textContent = `Изменение: ${formatPercent(totalPct)}`;
            changeEl.style.color = totalPct >= 0 ? '#00ff88' : '#ff4d4f';
        }

        if (lastValueEl) {
            lastValueEl.textContent = `Баланс: $${formatMoney(last)}`;
            lastValueEl.style.color = '#ffffff';
        }

        chart.subscribeCrosshairMove((param) => {
            if (!param?.time || !changeEl || !lastValueEl) return;
            const idx = timeline.findIndex((item) => Number(item) === Number(param.time));
            if (idx < 0) return;

            const value = rawValues[idx];
            const pct = start > 0 ? ((value / start) - 1) * 100 : 0;
            const date = formatChartTime(param.time);

            changeEl.textContent = `${date} | ${formatPercent(pct)}`;
            changeEl.style.color = pct >= 0 ? '#00ff88' : '#ff4d4f';
            lastValueEl.textContent = `Баланс: $${formatMoney(value)}`;
        });

        if (loading) {
            loading.classList.add('hidden');
        }

        if (typeof ResizeObserver !== 'undefined') {
            const observer = new ResizeObserver(() => {
                chart.applyOptions({
                    width: container.clientWidth || 600,
                    height: container.clientHeight || 520,
                });
                applyResponsiveChartOptions(chart, container);
            });
            observer.observe(container);
        }

        root.dataset.lwcBound = 'true';
    });
};

const CHART_COLOR = '#0f766e';
const buildSeries = (rawSeries) => rawSeries.map((value, index) => ({ time: index + 1, value }));
const parseNumber = (value) => {
    const parsed = Number.parseFloat(value);
    return Number.isFinite(parsed) ? parsed : null;
};
const simulateEquity = (series, capital, riskPercent) => {
    if (capital === null || riskPercent === null) return series;
    const riskAmount = capital * (riskPercent / 100);
    return series.map((value) => capital + (value - 1) * riskAmount);
};

const initLegacyEquityCharts = () => {
    const containers = document.querySelectorAll('[data-equity-chart="true"]');
    if (!containers.length) return;

    containers.forEach((container) => {
        if (container.dataset.chartReady === 'true') return;

        const raw = container.getAttribute('data-equity-curve');
        if (!raw) return;

        let series;
        try {
            series = JSON.parse(raw);
        } catch (_error) {
            return;
        }

        if (!Array.isArray(series) || series.length < 2) return;
        series = series.map((value) => Number(value)).filter((value) => Number.isFinite(value));
        if (series.length < 2) return;

        container.dataset.chartReady = 'true';
        container.innerHTML = '';

        const chart = createChart(container, {
            height: container.clientHeight || 180,
            width: container.clientWidth || 600,
            layout: {
                background: { type: 'solid', color: 'transparent' },
                textColor: '#0f172a',
            },
            grid: {
                vertLines: { visible: false },
                horzLines: { color: '#e2e8f0' },
            },
            leftPriceScale: { visible: false },
            rightPriceScale: { visible: false },
            timeScale: { visible: false },
            crosshair: {
                vertLine: { visible: false },
                horzLine: { visible: false },
            },
        });
        chart.applyOptions(chartTimezoneOptions());

        const areaSeries = chart.addSeries(AreaSeries, {
            lineColor: CHART_COLOR,
            topColor: 'rgba(15,118,110,0.35)',
            bottomColor: 'rgba(15,118,110,0)',
            lineWidth: 2,
        });

        const wrapper = container.closest('[data-equity-sim="true"]');
        const capitalInput = wrapper?.querySelector('[data-equity-input="capital"]');
        const riskInput = wrapper?.querySelector('[data-equity-input="risk"]');

        const applySeries = () => {
            const capital = capitalInput ? parseNumber(capitalInput.value) : null;
            const risk = riskInput ? parseNumber(riskInput.value) : null;
            const simulated = simulateEquity(series, capital, risk);
            areaSeries.setData(buildSeries(simulated));
            chart.timeScale().fitContent();
        };

        applySeries();

        if (capitalInput) capitalInput.addEventListener('input', applySeries);
        if (riskInput) riskInput.addEventListener('input', applySeries);

        if (typeof ResizeObserver !== 'undefined') {
            const resizeObserver = new ResizeObserver(() => {
                chart.applyOptions({
                    width: container.clientWidth || 600,
                    height: container.clientHeight || 180,
                });
            });
            resizeObserver.observe(container);
        }
    });
};

const initAllEquityCharts = () => {
    initLegacyEquityCharts();
    initPerformanceTerminalChart();
};

document.addEventListener('DOMContentLoaded', initAllEquityCharts);
document.addEventListener('livewire:navigated', initAllEquityCharts);
