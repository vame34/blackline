import { chartTimezoneOptions } from '../timezone';
import {
    createChart,
    createSeriesMarkers,
    LineSeries,
    HistogramSeries,
    CandlestickSeries,
} from 'lightweight-charts';

const BINANCE_WS_BASE = 'wss://stream.binance.com:443/ws';
const BYBIT_WS_BASE = 'wss://stream.bybit.com/v5/public';
const BINANCE_EXCHANGE = 'binance';
const BYBIT_EXCHANGE = 'bybit';

const activeSockets = new Map();

const LEVELS = [
    { key: 'entry', color: '#0f766e', lineStyle: 2 },
    { key: 'stop', color: '#ef4444', lineStyle: 2 },
    { key: 'tp1', color: '#22c55e', lineStyle: 0 },
    { key: 'tp2', color: '#16a34a', lineStyle: 0 },
    { key: 'close', color: '#0ea5e9', lineStyle: 1 },
];

const INDICATOR_COLORS = {
    ema_fast: '#38bdf8',
    ema_mid: '#f59e0b',
    ema_slow: '#0f766e',
    atr: '#64748b',
    volume: '#94a3b8',
};

const normalizeCandles = (candles) => candles
    .map((candle) => ({
        time: Number(candle.time),
        open: Number(candle.open),
        high: Number(candle.high),
        low: Number(candle.low),
        close: Number(candle.close),
    }))
    .filter((candle) => Number.isFinite(candle.time));

const normalizeMarkers = (markers) => markers
    .map((marker) => ({
        time: Number(marker.time),
        position: marker.position || 'aboveBar',
        color: marker.color || '#0f766e',
        shape: marker.shape || 'circle',
        text: marker.text || '',
    }))
    .filter((marker) => Number.isFinite(marker.time));

const labelKeyFor = (key) => `label${key.charAt(0).toUpperCase()}${key.slice(1)}`;

const normalizeSeries = (items) => items
    .map((item) => ({
        time: Number(item.time),
        value: Number(item.value),
        color: item.color,
    }))
    .filter((item) => Number.isFinite(item.time) && Number.isFinite(item.value));

const cleanupSockets = () => {
    activeSockets.forEach((socket, container) => {
        if (!document.contains(container)) {
            socket.close(1000, 'disposed');
            activeSockets.delete(container);
        }
    });
};

const closeAllSockets = () => {
    activeSockets.forEach((socket) => socket.close(1000, 'unload'));
    activeSockets.clear();
};

const attachIndicatorControls = (container, indicatorSeries) => {
    const panel = container.closest('[data-indicator-panel="true"]');
    if (!panel) {
        return;
    }

    const toggles = panel.querySelectorAll('[data-indicator-toggle]');
    toggles.forEach((toggle) => {
        if (toggle.dataset.indicatorBound === 'true') {
            return;
        }

        toggle.dataset.indicatorBound = 'true';
        const key = toggle.dataset.indicatorToggle;
        const seriesList = indicatorSeries[key] || [];
        const applyVisibility = () => {
            seriesList.forEach((series) => {
                series.applyOptions({ visible: toggle.checked });
            });
        };

        toggle.addEventListener('change', applyVisibility);
        applyVisibility();
    });
};

const buildStreamUrl = (base, symbol, interval) => {
    if (!symbol || !interval) {
        return null;
    }
    const streamName = `${symbol.toLowerCase()}@kline_${interval.toLowerCase()}`;
    return `${base.replace(/\/$/, '')}/${streamName}`;
};

const mapBybitInterval = (interval) => {
    switch (interval) {
        case '1m':
            return '1';
        case '3m':
            return '3';
        case '5m':
            return '5';
        case '15m':
            return '15';
        case '30m':
            return '30';
        case '1h':
            return '60';
        case '2h':
            return '120';
        case '4h':
            return '240';
        case '6h':
            return '360';
        case '12h':
            return '720';
        case '1d':
            return 'D';
        case '1w':
            return 'W';
        default:
            return null;
    }
};

const parseKlineMessage = (payload) => {
    const data = payload?.data ?? payload;
    if (!data || data.e !== 'kline' || !data.k) {
        return null;
    }

    const kline = data.k;
    const time = Math.floor(Number(kline.t) / 1000);
    const open = Number(kline.o);
    const high = Number(kline.h);
    const low = Number(kline.l);
    const close = Number(kline.c);

    if (!Number.isFinite(time) || !Number.isFinite(open)) {
        return null;
    }

    return {
        time,
        open,
        high,
        low,
        close,
    };
};

const parseBybitKlineMessage = (payload, topic) => {
    if (!payload || payload.topic !== topic || !Array.isArray(payload.data)) {
        return null;
    }

    const kline = payload.data[0];
    if (!kline) {
        return null;
    }

    const time = Math.floor(Number(kline.start) / 1000);
    const open = Number(kline.open);
    const high = Number(kline.high);
    const low = Number(kline.low);
    const close = Number(kline.close);

    if (!Number.isFinite(time) || !Number.isFinite(open)) {
        return null;
    }

    return {
        time,
        open,
        high,
        low,
        close,
    };
};

const attachBybitStream = (container, candleSeries) => {
    const symbol = (container.dataset.symbol || '').trim();
    const interval = (container.dataset.interval || '').trim();
    const mappedInterval = mapBybitInterval(interval);
    if (!symbol || !mappedInterval) {
        return;
    }

    const category = (container.dataset.bybitCategory || 'linear').toLowerCase();
    const base = container.dataset.bybitWsBase || `${BYBIT_WS_BASE}/${category}`;
    const topic = `kline.${mappedInterval}.${symbol.toUpperCase()}`;

    const socket = new WebSocket(base);
    activeSockets.set(container, socket);

    socket.addEventListener('open', () => {
        socket.send(JSON.stringify({
            op: 'subscribe',
            args: [topic],
        }));
    });

    socket.addEventListener('message', (event) => {
        try {
            const payload = JSON.parse(event.data);
            if (payload?.op === 'ping') {
                socket.send(JSON.stringify({ op: 'pong' }));
                return;
            }

            const candle = parseBybitKlineMessage(payload, topic);
            if (!candle) {
                return;
            }

            candleSeries.update(candle);
        } catch (error) {
            // Ignore malformed payloads.
        }
    });

    socket.addEventListener('close', () => {
        activeSockets.delete(container);
    });
};

const attachLiveStream = (container, candleSeries) => {
    const exchange = (container.dataset.exchange || '').toLowerCase();
    if (!exchange || exchange === BINANCE_EXCHANGE) {
        const symbol = (container.dataset.symbol || '').trim();
        const interval = (container.dataset.interval || '').trim();
        const wsBase = container.dataset.wsBase || BINANCE_WS_BASE;
        const streamUrl = buildStreamUrl(wsBase, symbol, interval);
        if (!streamUrl) {
            return;
        }

        const socket = new WebSocket(streamUrl);
        activeSockets.set(container, socket);

        socket.addEventListener('message', (event) => {
            try {
                const payload = JSON.parse(event.data);
                const candle = parseKlineMessage(payload);
                if (!candle) {
                    return;
                }
                candleSeries.update(candle);
            } catch (error) {
                // Ignore malformed payloads.
            }
        });

        socket.addEventListener('close', () => {
            activeSockets.delete(container);
        });
        return;
    }

    if (exchange === BYBIT_EXCHANGE) {
        attachBybitStream(container, candleSeries);
    }
};

const initSignalCharts = () => {
    cleanupSockets();
    const containers = document.querySelectorAll('[data-signal-chart="true"]');
    if (!containers.length) {
        return;
    }

    containers.forEach((container) => {
        if (container.dataset.chartReady === 'true') {
            return;
        }

        const rawCandles = container.getAttribute('data-candles');
        if (!rawCandles) {
            return;
        }

        let candles;
        try {
            candles = JSON.parse(rawCandles);
        } catch (error) {
            return;
        }

        if (!Array.isArray(candles)) {
            return;
        }

        const seriesData = normalizeCandles(candles);
        const hasInitialData = seriesData.length >= 2;
        const exchange = (container.dataset.exchange || '').toLowerCase();
        const canStream = Boolean(
            container.dataset.symbol
            && container.dataset.interval
            && (!exchange || exchange === BINANCE_EXCHANGE || exchange === BYBIT_EXCHANGE),
        );

        if (!hasInitialData && !canStream) {
            return;
        }

        container.dataset.chartReady = 'true';
        container.innerHTML = '';

        const chart = createChart(container, {
            height: container.clientHeight || 240,
            width: container.clientWidth || 640,
            layout: {
                background: { type: 'solid', color: 'transparent' },
                textColor: '#0f172a',
            },
            grid: {
                vertLines: { color: '#e2e8f0' },
                horzLines: { color: '#e2e8f0' },
            },
            rightPriceScale: {
                borderColor: '#e2e8f0',
            },
            timeScale: {
                borderColor: '#e2e8f0',
            },
            crosshair: {
                vertLine: { color: 'rgba(148, 163, 184, 0.4)' },
                horzLine: { color: 'rgba(148, 163, 184, 0.4)' },
            },
        });
        chart.applyOptions(chartTimezoneOptions());

        const candleSeries = chart.addSeries(CandlestickSeries, {
            upColor: '#0f766e',
            downColor: '#ef4444',
            wickUpColor: '#0f766e',
            wickDownColor: '#ef4444',
            borderVisible: false,
        });

        if (seriesData.length) {
            candleSeries.setData(seriesData);
        }

        const rawLevels = container.getAttribute('data-levels');
        if (rawLevels) {
            try {
                const levels = JSON.parse(rawLevels);
                LEVELS.forEach(({ key, color, lineStyle }) => {
                    const price = Number(levels?.[key]);
                    if (!Number.isFinite(price)) {
                        return;
                    }
                    const labelKey = labelKeyFor(key);
                    const title = container.dataset[labelKey] || key.toUpperCase();
                    candleSeries.createPriceLine({
                        price,
                        color,
                        lineWidth: 2,
                        lineStyle,
                        axisLabelVisible: true,
                        title,
                    });
                });
            } catch (error) {
                // Ignore malformed levels payload.
            }
        }

        const rawMarkers = container.getAttribute('data-markers');
        if (rawMarkers) {
            try {
                const markers = normalizeMarkers(JSON.parse(rawMarkers));
                if (markers.length) {
                    createSeriesMarkers(candleSeries, markers);
                }
            } catch (error) {
                // Ignore malformed markers payload.
            }
        }

        if (seriesData.length) {
            chart.timeScale().fitContent();
        }

        if (canStream) {
            attachLiveStream(container, candleSeries);
        }

        const indicatorSeries = {};
        const rawIndicators = container.getAttribute('data-indicators');
        if (rawIndicators) {
            try {
                const indicators = JSON.parse(rawIndicators);
                const emaFast = normalizeSeries(indicators?.ema_fast ?? []);
                const emaMid = normalizeSeries(indicators?.ema_mid ?? []);
                const emaSlow = normalizeSeries(indicators?.ema_slow ?? []);
                const atrSeriesData = normalizeSeries(indicators?.atr ?? []);
                const volumeSeriesData = normalizeSeries(indicators?.volume ?? []);

                if (emaFast.length) {
                    const series = chart.addSeries(LineSeries, {
                        color: INDICATOR_COLORS.ema_fast,
                        lineWidth: 1,
                        priceLineVisible: false,
                    });
                    series.setData(emaFast);
                    indicatorSeries.ema = [...(indicatorSeries.ema ?? []), series];
                }

                if (emaMid.length) {
                    const series = chart.addSeries(LineSeries, {
                        color: INDICATOR_COLORS.ema_mid,
                        lineWidth: 1,
                        priceLineVisible: false,
                    });
                    series.setData(emaMid);
                    indicatorSeries.ema = [...(indicatorSeries.ema ?? []), series];
                }

                if (emaSlow.length) {
                    const series = chart.addSeries(LineSeries, {
                        color: INDICATOR_COLORS.ema_slow,
                        lineWidth: 1.5,
                        priceLineVisible: false,
                    });
                    series.setData(emaSlow);
                    indicatorSeries.ema = [...(indicatorSeries.ema ?? []), series];
                }

                if (atrSeriesData.length) {
                    chart.priceScale('atr').applyOptions({
                        scaleMargins: { top: 0.8, bottom: 0 },
                        visible: false,
                    });

                    const series = chart.addSeries(LineSeries, {
                        color: INDICATOR_COLORS.atr,
                        lineWidth: 1,
                        priceLineVisible: false,
                        priceScaleId: 'atr',
                    });
                    series.setData(atrSeriesData);
                    indicatorSeries.atr = [series];
                }

                if (volumeSeriesData.length) {
                    chart.priceScale('volume').applyOptions({
                        scaleMargins: { top: 0.7, bottom: 0 },
                        visible: false,
                    });

                    const series = chart.addSeries(HistogramSeries, {
                        priceScaleId: 'volume',
                        color: INDICATOR_COLORS.volume,
                        priceLineVisible: false,
                    });
                    series.setData(volumeSeriesData);
                    indicatorSeries.volume = [series];
                }
            } catch (error) {
                // Ignore malformed indicators payload.
            }
        }

        attachIndicatorControls(container, indicatorSeries);

        if (typeof ResizeObserver !== 'undefined') {
            const resizeObserver = new ResizeObserver(() => {
                chart.applyOptions({
                    width: container.clientWidth || 640,
                    height: container.clientHeight || 240,
                });
            });
            resizeObserver.observe(container);
        }
    });
};

const observeSignalCharts = () => {
    if (typeof MutationObserver === 'undefined') {
        return;
    }

    const observer = new MutationObserver(() => {
        initSignalCharts();
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true,
    });
};

document.addEventListener('DOMContentLoaded', () => {
    initSignalCharts();
    observeSignalCharts();
});
document.addEventListener('livewire:navigated', initSignalCharts);
document.addEventListener('livewire:navigating', cleanupSockets);
window.addEventListener('pagehide', closeAllSockets);
