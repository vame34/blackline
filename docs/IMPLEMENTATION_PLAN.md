# Backtesting terminal implementation plan

Keep the existing terminal UI, services/repositories/DTOs and RU/EN copy consistent. No autotrading or broker connectivity.

Completed foundations:
- Historical candles, closed-context causal signals and target levels.
- Signal lifecycle history and realtime browser events.
- Net fee/R accounting, portfolio risk limits and drawdown controls.
- Historical portfolio/symbol backtests and trade inspection.
- Standalone authentication and API access.

Completed standalone-terminal slice:
- Remove commercial, identity-provider and outbound signal-delivery modules.
- Register Smart Volume behind a strategy interface and adapter registry.
- Select strategies in generation, feed and backtest without mixing datasets.
- Document the local setup and strategy adapter interface.

Completed display-preferences slice:
- Persist an account/session display timezone with browser-auto mode.
- Format signal and backtest timestamps/charts consistently and filter local calendar days.
- Remove the profile page and session-tracking module; manage tokens on the API page and logout in navigation.

Next research slice:
- Replay open positions with mark-to-market equity.
- Add instrument-appropriate funding/borrow/mark data.
- Freeze hypotheses and validate chronologically before promoting new strategies.

Current research has not established a positive edge. The 40% drawdown target is a modeling threshold, not a guarantee of full market drawdown.
