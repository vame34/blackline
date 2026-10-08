import { displayTimezone } from '../timezone';
const TV_SCRIPT_SRC = '/vendor/tradingview/tv.js';

let tvScriptPromise = null;
let initDebounceTimer = null;

const shouldCleanupEmbedsOnCurrentPage = () => window.location.pathname.startsWith('/performance/terminal');

const cleanupTradingViewEmbeds = () => {
    document
        .querySelectorAll('iframe[src*="tradingview.com"], iframe[src*="s.tradingview.com"]')
        .forEach((iframe) => {
            iframe.remove();
        });
};

const loadTradingView = () => {
    if (window.TradingView) {
        return Promise.resolve(window.TradingView);
    }

    if (tvScriptPromise) {
        return tvScriptPromise;
    }

    tvScriptPromise = new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = TV_SCRIPT_SRC;
        script.async = true;
        script.onload = () => resolve(window.TradingView);
        script.onerror = () => reject(new Error('TradingView script failed to load.'));
        document.head.appendChild(script);
    });

    return tvScriptPromise;
};

const parseStudies = (raw) => {
    if (!raw) {
        return [];
    }

    try {
        const parsed = JSON.parse(raw);
        return Array.isArray(parsed) ? parsed : [];
    } catch {
        return [];
    }
};

const resolveTradingViewTimezone = () => {
    const zone = displayTimezone();
    // Fixed-offset regions use a supported equivalent; DST regions retain their IANA rules.
    const aliases = {
        'UTC': 'Etc/UTC', 'Asia/Novokuznetsk': 'Asia/Bangkok',
        'Asia/Novosibirsk': 'Asia/Bangkok', 'Asia/Krasnoyarsk': 'Asia/Bangkok',
        'Asia/Yekaterinburg': 'Asia/Karachi', 'Europe/Samara': 'Asia/Dubai',
        'Asia/Omsk': 'Asia/Dhaka', 'Asia/Irkutsk': 'Asia/Shanghai',
        'Asia/Yakutsk': 'Asia/Tokyo', 'Asia/Vladivostok': 'Australia/Brisbane',
    };
    return aliases[zone] || zone;
};

const initTradingViewCharts = () => {
    const containers = document.querySelectorAll('[data-tradingview-chart="true"]');
    if (!containers.length) {
        // Livewire can temporarily remove containers during patching.
        // Cleanup only on pages where TradingView is intentionally disabled.
        if (shouldCleanupEmbedsOnCurrentPage()) {
            cleanupTradingViewEmbeds();
        }
        return;
    }

    loadTradingView()
        .then(() => {
            containers.forEach((container) => {
                const symbol = container.dataset.symbol;
                const interval = container.dataset.interval || '60';
                const locale = container.dataset.locale || 'en';
                const studies = parseStudies(container.dataset.studies);
                const hideTopToolbar = container.dataset.hideTopToolbar !== 'false';
                const hideSideToolbar = container.dataset.hideSideToolbar !== 'false';
                const withDateRanges = container.dataset.withDateRanges === 'true';
                const allowSymbolChange = container.dataset.allowSymbolChange === 'true';
                const theme = container.dataset.theme === 'dark' ? 'dark' : 'light';
                const timezone = resolveTradingViewTimezone();

                if (!symbol) {
                    return;
                }

                const sameSymbol = container.dataset.chartSymbol === symbol;
                const sameInterval = container.dataset.chartInterval === interval;
                const sameStudies = container.dataset.chartStudies === JSON.stringify(studies);
                const sameTimezone = container.dataset.chartTimezone === timezone;

                if (
                    container.dataset.chartReady === 'true'
                    && sameSymbol
                    && sameInterval
                    && sameStudies
                    && sameTimezone
                ) {
                    return;
                }

                container.dataset.chartReady = 'true';
                container.dataset.chartSymbol = symbol;
                container.dataset.chartInterval = interval;
                container.dataset.chartStudies = JSON.stringify(studies);
                container.dataset.chartTimezone = timezone;
                container.innerHTML = '';

                new window.TradingView.widget({
                    autosize: true,
                    symbol,
                    interval,
                    timezone,
                    theme,
                    style: '1',
                    locale,
                    studies,
                    enable_publishing: false,
                    hide_top_toolbar: hideTopToolbar,
                    hide_side_toolbar: hideSideToolbar,
                    allow_symbol_change: allowSymbolChange,
                    withdateranges: withDateRanges,
                    container_id: container.id,
                });
            });
        })
        .catch(() => {
            // Ignore TradingView load errors in dev.
        });
};

const observeTradingView = () => {
    if (typeof MutationObserver === 'undefined') {
        return;
    }

    const observer = new MutationObserver((mutations) => {
        let shouldReinit = false;

        for (const mutation of mutations) {
            if (mutation.type === 'childList') {
                shouldReinit = true;
                break;
            }

            if (
                mutation.type === 'attributes' &&
                mutation.target instanceof HTMLElement &&
                mutation.target.dataset.tradingviewChart === 'true'
            ) {
                // Force refresh when Livewire only updates data-interval/data-symbol attributes.
                mutation.target.dataset.chartReady = 'false';
                shouldReinit = true;
                break;
            }
        }

        if (!shouldReinit) {
            return;
        }

        if (initDebounceTimer) {
            clearTimeout(initDebounceTimer);
        }
        initDebounceTimer = setTimeout(() => {
            initTradingViewCharts();
        }, 80);
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ['data-symbol', 'data-interval', 'data-theme'],
    });
};

document.addEventListener('DOMContentLoaded', () => {
    initTradingViewCharts();
    observeTradingView();
});
document.addEventListener('livewire:navigated', () => {
    // Wait until DOM is fully patched after wire:navigate transition.
    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            initTradingViewCharts();
        });
    });
});

window.addEventListener('blackline:timezone-updated', (event) => {
    const timezone = event?.detail?.timezone;
    if (typeof timezone !== 'string' || timezone === '') {
        return;
    }

    window.BLACKLINE_USER_TIMEZONE = timezone;
    document.querySelectorAll('[data-tradingview-chart="true"]').forEach((container) => {
        if (!(container instanceof HTMLElement)) {
            return;
        }

        container.dataset.chartReady = 'false';
    });

    initTradingViewCharts();
});
