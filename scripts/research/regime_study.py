#!/usr/bin/env python3
"""Offline, chronological hypothesis study. No orders, no parameter search.
Usage: python3 scripts/research/regime_study.py --data storage/app/private/market-data/two-years --output storage/app/private/market-data/research/regimes.json
Uses only Python's standard library and archived Binance spot OHLCV.
"""
import argparse
import bisect
from collections import deque
from datetime import datetime, timezone
import json
from pathlib import Path

HOUR = 3_600_000
SPLIT = int(datetime(2025, 10, 8, tzinfo=timezone.utc).timestamp() * 1000)
# Small, declared hypothesis family. No per-symbol tuning or asset selection.
VARIANTS = [('breakout20', 20, False, False), ('breakout55', 55, False, False),
            ('trend20', 20, True, False), ('trend55', 55, True, False),
            ('range', 20, False, True), ('router', 55, True, True)]


def features(rows):
    """All feature values at i contain only rows <= i."""
    result = []
    fast = slow = atr = plus = minus = adx = gain = loss = 0.0
    for i, r in enumerate(rows):
        o, h, l, c = r[1:5]
        prev = rows[i-1][4] if i else c
        fast = c if not i else fast + 2/51 * (c-fast)
        slow = c if not i else slow + 2/201 * (c-slow)
        tr = max(h-l, abs(h-prev), abs(l-prev))
        up = h-rows[i-1][2] if i else 0
        down = rows[i-1][3]-l if i else 0
        atr += (tr-atr)/14
        plus += ((up if up > down and up > 0 else 0)-plus)/14
        minus += ((down if down > up and down > 0 else 0)-minus)/14
        dx = 100*abs(plus-minus)/(plus+minus) if plus+minus else 0
        adx += (dx-adx)/14
        gain += (max(c-prev, 0)-gain)/14
        loss += (max(prev-c, 0)-loss)/14
        rsi = 100*gain/(gain+loss) if gain+loss else 50
        result.append((fast, slow, atr, adx, rsi))
    return result


def generate(rows, fs, symbol, start, end, variant):
    name, window, trend_filter, use_range = variant
    trades, position = [], None
    for i in range(200, len(rows)-1):
        t, o, h, l, c, _ = rows[i]
        fast, slow, atr, adx, rsi = fs[i]
        if t >= end:
            break
        if position:
            d, entry, stop, initial_risk, entry_t, mode, target = position
            price = None
            if (d == 1 and l <= stop) or (d == -1 and h >= stop):
                price = min(o, stop) if d == 1 else max(o, stop)
            elif mode == 'range' and ((d == 1 and c >= target) or (d == -1 and c <= target)):
                price = rows[i+1][1]
            elif (t-entry_t) >= (48 if mode == 'range' else 720)*HOUR:
                price = rows[i+1][1]
            if price is not None:
                trades.append(dict(symbol=symbol, direction=d, entry=entry, exit=price,
                                   stop_distance=initial_risk, entry_t=entry_t, exit_t=t+HOUR,
                                   regime=mode))
                position = None
                continue
            if mode == 'trend':
                # New trail becomes active only on the next candle.
                stop = max(stop, c-3*atr) if d == 1 else min(stop, c+3*atr)
                position = (d, entry, stop, initial_risk, entry_t, mode, target)
            continue
        decision = t+HOUR
        if decision < start or decision >= end or atr <= 0 or atr/c > .04:
            continue
        direction, mode, target = 0, 'trend', None
        if name != 'range' and (not trend_filter or adx >= 25):
            highest = max(r[2] for r in rows[i-window:i])
            lowest = min(r[3] for r in rows[i-window:i])
            if c > highest and (not trend_filter or fast > slow):
                direction = 1
            elif c < lowest and (not trend_filter or fast < slow):
                direction = -1
        if not direction and use_range and adx < 20 and abs(fast-slow)/c < .005:
            mean = sum(r[4] for r in rows[i-19:i+1])/20
            if rsi < 30 and c < mean-2*atr:
                direction = 1
            elif rsi > 70 and c > mean+2*atr:
                direction = -1
            if direction:
                mode, target = 'range', mean
        if direction:
            entry = rows[i+1][1]
            risk = (2 if mode == 'range' else 3)*atr
            position = (direction, entry, entry-direction*risk, risk, decision, mode, target)
    # Liquidate boundary holdings; never discard unresolved/losing trades.
    if position:
        d, entry, stop, risk, entry_t, mode, target = position
        last = min(len(rows)-1, bisect.bisect_left([r[0] for r in rows], end)-1)
        trades.append(dict(symbol=symbol, direction=d, entry=entry, exit=rows[last][4],
                           stop_distance=risk, entry_t=entry_t, exit_t=min(end, rows[last][0]+HOUR),
                           regime=mode))
    return trades


def portfolio(trades, market, start, end, cost_multiplier=1, target_drawdown_pct=40):
    """Fixed fractional risk, total/directional caps, hourly close mark to market.
    Drawdown stop uses equity, exits remain active. Gaps can exceed the target.
    """
    reduce_equity_ratio = 1 - target_drawdown_pct / 200
    halt_equity_ratio = 1 - target_drawdown_pct * .8 / 100
    fee, slip = .0004*cost_multiplier, .0002*cost_multiplier
    events = {}
    for j, tr in enumerate(trades):
        events.setdefault(tr['entry_t'], []).append(('open', j, tr))
        events.setdefault(tr['exit_t'], []).append(('close', j, tr))
    cash, peak, dd, active, halted = 10000., 10000., 0., {}, False
    pnls, rs, skipped, exposure_peak = [], [], 0, 0.
    def mark(t):
        equity = cash
        for tr, qty, entry_cost, risk_budget in active.values():
            rows, times = market[tr['symbol']]
            ix = bisect.bisect_right(times, t-HOUR)-1
            px = rows[max(ix, 0)][4]
            equity += qty*tr['direction']*(px-tr['entry'])
        return equity
    for t in range(start, end+1, HOUR):
        for kind, j, tr in sorted(events.get(t, []), key=lambda e: (e[0] == 'open', e[2]['symbol'])):
            if kind == 'close':
                if j not in active:
                    continue
                _, qty, entry_cost, risk_budget = active.pop(j)
                gross = qty*tr['direction']*(tr['exit']-tr['entry'])
                exit_cost = qty*tr['exit']*(fee+slip)
                cash += gross-exit_cost
                net = gross-entry_cost-exit_cost
                pnls.append(net)
                rs.append(net/risk_budget)
            else:
                equity = mark(t)
                total_risk = sum(p[3] for p in active.values())
                same_risk = sum(p[3] for p in active.values() if p[0]['direction'] == tr['direction'])
                budget = max(0, equity)*(.0025 if equity < peak*reduce_equity_ratio else .005)
                loss_per_unit = tr['stop_distance']+(fee+slip)*(2*tr['entry']+tr['stop_distance'])
                qty = budget/loss_per_unit
                # Total gross notional <= equity (spot proxy; no imaginary short funding).
                notional = sum(p[1]*p[0]['entry'] for p in active.values())+qty*tr['entry']
                if (halted or any(p[0]['symbol'] == tr['symbol'] for p in active.values())
                        or total_risk+budget > equity*.02 or same_risk+budget > equity*.015
                        or notional > equity or equity <= 0):
                    skipped += 1
                    continue
                cost = qty*tr['entry']*(fee+slip)
                cash -= cost
                active[j] = (tr, qty, cost, budget)
                exposure_peak = max(exposure_peak, (total_risk+budget)/equity)
            equity = mark(t)
            peak = max(peak, equity)
            dd = max(dd, 1-equity/peak)
            if equity <= peak*halt_equity_ratio:
                halted = True
        equity = mark(t)
        peak = max(peak, equity)
        dd = max(dd, 1-equity/peak)
        if equity <= peak*halt_equity_ratio:
            halted = True
    positives = sum(p for p in pnls if p > 0)
    negatives = -sum(p for p in pnls if p < 0)
    return dict(trades=len(pnls), skipped=skipped, roi_pct=round((cash/10000-1)*100, 3),
                max_dd_pct=round(dd*100, 3), profit_factor=round(positives/negatives, 3) if negatives else None,
                expectancy_r=round(sum(rs)/len(rs), 4) if rs else None,
                halted=halted, max_open_risk_pct=round(exposure_peak*100, 3))


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--data', type=Path, required=True)
    parser.add_argument('--output', type=Path, required=True)
    parser.add_argument('--max-drawdown-percent', type=float, default=40)
    args = parser.parse_args()
    if not 0 < args.max_drawdown_percent < 100:
        parser.error('--max-drawdown-percent must be between 0 and 100')
    manifest = json.loads((args.data/'manifest.json').read_text())
    start, end = manifest['start_ms'], manifest['end_ms']//HOUR*HOUR
    market, computed = {}, {}
    for symbol in sorted(manifest['symbols']):
        raw = json.loads((args.data/f'{symbol}-1h.json').read_text())
        rows = [[int(r[0]), *map(float, r[1:6])] for r in raw if int(r[0])+HOUR <= end]
        market[symbol] = (rows, [r[0] for r in rows])
        computed[symbol] = features(rows)
    report = dict(source=manifest['source'], start_ms=start, split_ms=SPLIT, end_ms=end,
                  warning='Exploratory validation, spot OHLCV short proxy; funding/borrow absent. Hourly fills approximate intrabar execution. Not deployable evidence.',
                  assumptions=dict(capital=10000, risk_pct=.5, portfolio_risk_pct=2, directional_risk_pct=1.5,
                                   equity_stop_pct=args.max_drawdown_percent*.8,
                                   reduce_risk_at_dd_pct=args.max_drawdown_percent/2,
                                   target_max_dd_pct=args.max_drawdown_percent, fee_per_side=.0004, slippage_per_side=.0002), results={})
    for variant in VARIANTS:
        result = {}
        for period, a, b in [('development', start, SPLIT), ('validation', SPLIT, end)]:
            trades = []
            for symbol, (rows, _) in market.items():
                trades.extend(generate(rows, computed[symbol], symbol, a, b, variant))
            result[period] = portfolio(trades, market, a, b, target_drawdown_pct=args.max_drawdown_percent)
            result[period+'_double_cost'] = portfolio(trades, market, a, b, 2, target_drawdown_pct=args.max_drawdown_percent)
            result[period]['generated'] = len(trades)
        report['results'][variant[0]] = result
        print(variant[0], json.dumps(result), flush=True)
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(json.dumps(report, indent=2)+'\n')


if __name__ == '__main__':
    main()
