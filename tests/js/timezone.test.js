import test from 'node:test';
import assert from 'node:assert/strict';
import { resolveTimezone, formatDateTime, formatUtcOffset, formatChartTime } from '../../resources/js/timezone.js';

test('explicit preference wins over browser timezone and crosses the UTC day boundary', () => {
    const zone = resolveTimezone('Asia/Novokuznetsk', 'America/New_York');
    assert.equal(formatDateTime('2026-01-01T20:15:00Z', zone, true), '02.01.2026 03:15');
    assert.equal(formatDateTime('2026-01-01T20:15:00Z', 'UTC', true), '01.01.2026 20:15');
    assert.equal(formatDateTime('2026-01-01T12:00:00Z', resolveTimezone('auto', 'Asia/Kolkata')), '01.01 17:30');
    assert.equal(resolveTimezone('invalid-zone', 'UTC'), 'UTC');
});

test('historical times follow DST and fractional offsets instead of todays browser offset', () => {
    assert.equal(formatDateTime('2026-01-01T12:00:00Z', 'America/New_York'), '01.01 07:00');
    assert.equal(formatDateTime('2026-07-01T12:00:00Z', 'America/New_York'), '01.07 08:00');
    assert.equal(formatDateTime('2026-03-08T06:59:00Z', 'America/New_York'), '08.03 01:59');
    assert.equal(formatDateTime('2026-03-08T07:00:00Z', 'America/New_York'), '08.03 03:00');
    assert.equal(formatUtcOffset('Asia/Kathmandu'), 'UTC+05:45');
    assert.equal(formatChartTime({ year: 2026, month: 1, day: 1 }), '01.01.2026');
    assert.equal(formatDateTime('garbage', 'UTC'), null);
});
