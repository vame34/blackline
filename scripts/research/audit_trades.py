#!/usr/bin/env python3
"""Audit exported Signal JSON against archived hourly context; no strategy tuning."""
import argparse
import bisect
from collections import defaultdict
from datetime import datetime
import json
from pathlib import Path
import random
from statistics import mean
from regime_study import HOUR, SPLIT, features


def timestamp(value):
    return int(datetime.fromisoformat(value.replace('Z', '+00:00')).timestamp()*1000)


def summarize(trades):
    if not trades:
        return {'trades': 0}
    wins = [t['net_r'] for t in trades if t['net_r'] > 0]
    losses = [-t['net_r'] for t in trades if t['net_r'] < 0]
    p = len(wins)/len(trades)
    win, loss = mean(wins) if wins else 0, mean(losses) if losses else 0
    # Cluster-bootstrap calendar weeks, keeping simultaneous crypto losses together.
    weeks = defaultdict(list)
    for t in trades:
        weeks[t['entry_t']//(168*HOUR)].append(t['net_r'])
    clusters, rng, samples = list(weeks.values()), random.Random(20261008), []
    for _ in range(2000):
        sample = [r for cluster in rng.choices(clusters, k=len(clusters)) for r in cluster]
        samples.append(mean(sample))
    samples.sort()
    return dict(trades=len(trades), win_rate_pct=round(p*100, 2),
                gross_expectancy_r=round(mean(t['gross_r'] for t in trades), 4),
                net_expectancy_r=round(mean(t['net_r'] for t in trades), 4),
                avg_win_r=round(win, 4), avg_loss_r=round(loss, 4),
                break_even_win_rate_pct=round(loss/(win+loss)*100, 2) if win+loss else None,
                profit_factor_equal_stop_risk=round(sum(wins)/sum(losses), 4) if losses else None,
                weekly_cluster_ci95=[round(samples[49], 4), round(samples[1949], 4)],
                median_holding_hours=round(sorted(t['hours'] for t in trades)[len(trades)//2], 2),
                max_holding_hours=round(max(t['hours'] for t in trades), 2))


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--signals', type=Path, required=True)
    parser.add_argument('--data', type=Path, required=True)
    parser.add_argument('--output', type=Path, required=True)
    args = parser.parse_args()
    raw = json.loads(args.signals.read_text())
    contexts = {}
    for symbol in sorted({s['symbol'] for s in raw}):
        rows = [[int(r[0]), *map(float, r[1:6])] for r in json.loads((args.data/f'{symbol}-1h.json').read_text())]
        contexts[symbol] = ([r[0] for r in rows], features(rows))
    trades = []
    for s in raw:
        if not s['closed_at'] or not s['close_price']:
            continue
        entry, exit, stop = map(float, [s['entry_price'], s['close_price'], s['stop_loss']])
        d = 1 if s['direction'] == 'LONG' else -1
        stop_pct = abs(entry-stop)/entry
        gross = d*(exit-entry)/entry
        net = gross-.0004*(1+exit/entry)
        t = timestamp(s['entry_at'])
        times, fs = contexts[s['symbol']]
        i = bisect.bisect_right(times, t-HOUR)-1
        fast, slow, atr, adx, rsi = fs[max(i, 0)]
        regime = 'trend' if adx >= 25 else 'range' if adx < 20 else 'transition'
        trades.append(dict(symbol=s['symbol'], entry_t=t, gross_r=gross/stop_pct, net_r=net/stop_pct,
                           regime=regime, direction=s['direction'], reason=s['close_reason'],
                           hours=(timestamp(s['closed_at'])-t)/HOUR))
    report = {'fee_per_side': .0004, 'cohort': 'completed imported signals only; not a full equity backtest', 'periods': {}}
    for name, selected in [('all', trades), ('development', [t for t in trades if t['entry_t'] < SPLIT]),
                           ('validation', [t for t in trades if t['entry_t'] >= SPLIT])]:
        report['periods'][name] = {'overall': summarize(selected), 'regimes': {}, 'directions': {}}
        for regime in ['trend', 'range', 'transition']:
            report['periods'][name]['regimes'][regime] = summarize([t for t in selected if t['regime'] == regime])
        for direction in ['LONG', 'SHORT']:
            report['periods'][name]['directions'][direction] = summarize([t for t in selected if t['direction'] == direction])
    args.output.write_text(json.dumps(report, indent=2)+'\n')
    print(json.dumps(report['periods']['validation'], indent=2))

if __name__ == '__main__':
    main()
