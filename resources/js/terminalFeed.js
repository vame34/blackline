import { displayTimezone, formatDateTime, formatUtcOffset, initializeTimezone } from './timezone';
document.addEventListener('livewire:init', () => {
    if (!window.Livewire) {
        return;
    }

    window.Livewire.on('terminal-feed:scroll-top', () => {
        const feed = document.querySelector('[data-terminal-feed]');
        if (!feed) {
            return;
        }

        feed.scrollTo({ top: 0, behavior: 'smooth' });
    });

    // Re-bind search and re-apply filtering after any Livewire DOM morph.
    if (typeof window.Livewire.hook === 'function') {
        window.Livewire.hook('morph.updated', () => {
            bindInstrumentSearch();
            applyLocalDateTimes();
            initializeTimezone();
            const timezone = applyUserTimezoneLabel();
            window.dispatchEvent(new CustomEvent('blackline:timezone-updated', { detail: { timezone } }));
        });
    }
});

const applyLocalDateTimes = () => {
    document.querySelectorAll('[data-local-datetime]').forEach((node) => {
        if (!(node instanceof HTMLElement)) {
            return;
        }

        const iso = node.dataset.utcDatetime || '';
        const formatted = formatDateTime(iso, displayTimezone(), node.dataset.includeYear === 'true');
        if (!formatted) {
            return;
        }

        node.textContent = formatted;
    });
};

const applyUserTimezoneLabel = () => {
    const timezone = displayTimezone();
    const offset = formatUtcOffset();

    document.querySelectorAll('[data-user-timezone-label]').forEach((node) => {
        if (!(node instanceof HTMLElement)) {
            return;
        }

        const prefix = node.dataset.timezonePrefix || 'Timezone';
        node.textContent = `${prefix}: ${timezone} (${offset})`;
    });

    return timezone;
};

const applyInstrumentSearchFilter = () => {
    const searchInput = document.querySelector('[data-instrument-search]');
    if (!searchInput) {
        return;
    }

    const query = String(searchInput.value || '').trim().toUpperCase();
    const items = Array.from(document.querySelectorAll('[data-instrument-item]'));
    items.forEach((item) => {
        const symbol = String(item.dataset.symbol || '');
        const label = String(item.dataset.label || '');
        const visible = query === '' || symbol.includes(query) || label.includes(query);
        item.style.display = visible ? '' : 'none';
    });
};

const bindInstrumentSearch = () => {
    const searchInput = document.querySelector('[data-instrument-search]');
    if (!searchInput) {
        return;
    }

    if (!searchInput.dataset.instrumentSearchBound) {
        searchInput.addEventListener('input', applyInstrumentSearchFilter);
        searchInput.dataset.instrumentSearchBound = '1';
    }

    applyInstrumentSearchFilter();
};

document.addEventListener('DOMContentLoaded', bindInstrumentSearch);
document.addEventListener('livewire:navigated', bindInstrumentSearch);
document.addEventListener('DOMContentLoaded', applyLocalDateTimes);
document.addEventListener('livewire:navigated', applyLocalDateTimes);
document.addEventListener('DOMContentLoaded', () => {
    initializeTimezone();
    const timezone = applyUserTimezoneLabel();
    window.dispatchEvent(new CustomEvent('blackline:timezone-updated', { detail: { timezone } }));
});
document.addEventListener('livewire:navigated', () => {
    initializeTimezone();
    const timezone = applyUserTimezoneLabel();
    window.dispatchEvent(new CustomEvent('blackline:timezone-updated', { detail: { timezone } }));
});

initializeTimezone();
const timezone = applyUserTimezoneLabel();
window.dispatchEvent(new CustomEvent('blackline:timezone-updated', { detail: { timezone } }));
