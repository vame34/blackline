import unittest
from regime_study import HOUR, features, generate, portfolio

class StudyTest(unittest.TestCase):
    def rows(self):
        rows = []
        price = 100
        for i in range(850):
            close = price + (.3 if i < 500 else -.35)
            rows.append([i*HOUR, price, max(price, close)+.1, min(price, close)-.1, close, 100])
            price = close
        return rows

    def test_future_prices_do_not_change_completed_trades(self):
        rows = self.rows()
        prefix = rows[:650]
        a = generate(rows, features(rows), 'BTCUSDT', 200*HOUR, 850*HOUR, ('breakout20', 20, False, False))
        b = generate(prefix, features(prefix), 'BTCUSDT', 200*HOUR, 650*HOUR, ('breakout20', 20, False, False))
        completed = lambda trades: [t for t in trades if t['exit_t'] < 649*HOUR]
        self.assertTrue(completed(a))
        self.assertEqual(completed(a), completed(b))

    def test_boundary_position_is_liquidated(self):
        rows = self.rows()[:450]
        trades = generate(rows, features(rows), 'BTCUSDT', 200*HOUR, 450*HOUR, ('breakout20', 20, False, False))
        self.assertEqual(450*HOUR, trades[-1]['exit_t'])

    def test_double_costs_reduce_single_trade_return(self):
        market = {'BTCUSDT': ([[0, 100, 101, 99, 100, 1], [HOUR, 100, 111, 99, 110, 1]], [0, HOUR])}
        trades = [dict(symbol='BTCUSDT', direction=1, entry=100, exit=110, stop_distance=5, entry_t=0, exit_t=2*HOUR)]
        base = portfolio(trades, market, 0, 2*HOUR)
        stress = portfolio(trades, market, 0, 2*HOUR, 2)
        self.assertLess(stress['roi_pct'], base['roi_pct'])
        self.assertLessEqual(base['max_open_risk_pct'], 2)

if __name__ == '__main__':
    unittest.main()
