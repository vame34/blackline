# Architecture

Laravel 12, Livewire 3/Volt, Filament 3, MariaDB/MySQL, Redis/Horizon, Reverb and Vite. Controllers and Livewire compose services; repositories encapsulate queries; DTOs carry inputs and strategy outputs.

Market providers normalize candles into `InstrumentData`. The strategy registry resolves a configured adapter. Adapters return generic strategy signals; the import service persists each signal with its strategy key and execution metadata. The backtester applies shared accounting and portfolio risk controls to completed trades from the selected strategy. Strategy selection belongs in queries and cache keys to prevent cross-strategy statistics.

Smart Volume remains a standalone trading algorithm behind its adapter. Adapters use closed candles and must not derive decisions from future data. All current adapters emit fixed Entry/SL/TP scenarios. Custom trailing orders or execution types require extending the shared simulation contract and parity tests first.

Published/closed events update the authenticated terminal over Reverb. The API offers paginated REST and bounded SSE; tokens are hashed, expirable and optionally IP restricted. Profile authentication retains password recovery and email verification.

Operational data and candle archives live outside tracked source. Compose binds published ports to loopback. Read the README for local setup and `STRATEGIES.md` for the adapter contract.
