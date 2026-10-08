export const resolveTimezone = (preference = 'auto', browser = Intl.DateTimeFormat().resolvedOptions().timeZone) => {
    const zone = preference === 'auto' || !preference ? browser : preference;
    try { return new Intl.DateTimeFormat('en', { timeZone: zone }).resolvedOptions().timeZone; }
    catch { return 'UTC'; }
};

export const displayTimezone = () => resolveTimezone(document.documentElement.dataset.displayTimezone);

export const formatDateTime = (value, timezone = displayTimezone(), includeYear = false) => {
    const date = new Date(value);
    if (!value || Number.isNaN(date.getTime())) return null;
    const parts = Object.fromEntries(new Intl.DateTimeFormat('en-GB', {
        timeZone: timezone, day: '2-digit', month: '2-digit', year: 'numeric',
        hour: '2-digit', minute: '2-digit', hourCycle: 'h23',
    }).formatToParts(date).map(part => [part.type, part.value]));
    return `${parts.day}.${parts.month}${includeYear ? `.${parts.year}` : ''} ${parts.hour}:${parts.minute}`;
};

export const formatUtcOffset = (timezone = displayTimezone(), date = new Date()) => {
    const offset = new Intl.DateTimeFormat('en', { timeZone: timezone, timeZoneName: 'longOffset' })
        .formatToParts(date).find(part => part.type === 'timeZoneName')?.value || 'GMT';
    return offset === 'GMT' ? 'UTC+00:00' : offset.replace('GMT', 'UTC');
};

export const formatChartTime = (time) => {
    if (typeof time === 'number') return formatDateTime(time * 1000, displayTimezone(), true) || '';
    // A BusinessDay represents a calendar day, rather than a UTC instant.
    if (typeof time === 'object' && time) return `${String(time.day).padStart(2, '0')}.${String(time.month).padStart(2, '0')}.${time.year}`;
    return String(time || '');
};

export const chartTimezoneOptions = () => ({
    localization: { timeFormatter: formatChartTime },
    timeScale: { timeVisible: true, tickMarkFormatter: time => typeof time === 'number' ? formatDateTime(time * 1000) : formatChartTime(time) },
});

export const initializeTimezone = () => {
    const browser = resolveTimezone('auto');
    document.querySelectorAll('[data-timezone-form] [name="browser_timezone"]').forEach(input => { input.value = browser; });
    const root = document.documentElement;
    if (root.dataset.timezonePreference !== 'auto' || root.dataset.browserTimezone === browser || root.dataset.timezoneSyncing) return;
    const form = document.querySelector('[data-timezone-form]');
    if (!form) return;
    root.dataset.timezoneSyncing = '1';
    fetch(form.action, {
        method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json' }, body: new FormData(form),
    }).then(response => {
        if (response.ok) {
            root.dataset.browserTimezone = browser;
            window.Livewire?.dispatch('display-timezone-synchronized');
        }
    })
        .catch(() => {}).finally(() => { delete root.dataset.timezoneSyncing; });
};
