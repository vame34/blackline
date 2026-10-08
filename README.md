<div align="center">
  <img src="public/favicon.png" width="88" alt="BlackLine logo" />
  <h1>BlackLine</h1>
  <p><strong>Strategy backtesting · Portfolio risk · Realtime signals</strong></p>
  <p>A local research terminal for cryptocurrency strategies.</p>
  <p>
    <img alt="PHP 8.2+" src="https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white" />
    <img alt="Laravel 12" src="https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white" />
    <img alt="Livewire 3" src="https://img.shields.io/badge/Livewire-3-FB70A9?logo=livewire&logoColor=white" />
    <img alt="License MIT" src="https://img.shields.io/badge/License-MIT-00C98D" />
  </p>
  <p><strong>🇬🇧 English</strong> · <a href="README.ru.md">🇷🇺 Русский</a></p>
  <p><a href="#-quick-start">Quick start</a> · <a href="docs/STRATEGIES.md">Strategy adapters</a> · <a href="docs/API.md">API</a> · <a href="docs/research/DRAWDOWN_40_2026-10-08.md">Research</a></p>
</div>

---

## ✨ What is BlackLine?

BlackLine helps you inspect a trading idea as a system: historical entries and exits, transaction costs, position sizing and portfolio drawdown. The same terminal displays live signals for inspection. Smart Volume is included as a replaceable strategy adapter.

| | Capability | What you can inspect |
| :-- | :-- | :-- |
| 📊 | **Backtesting** | Portfolio or selected coins; periods up to two years when history is available. |
| 🔎 | **Trade history** | Entry, exit, direction, fees, returns and simulated balance. |
| 🛡️ | **Risk management** | Per-trade and portfolio limits, risk ladder and drawdown controls. |
| 🧩 | **Strategy adapters** | Add an adapter, register it and select it in the terminal. |
| 📡 | **Live signal viewer** | Authenticated browser updates over WebSocket; REST and SSE API. |
| 🌍 | **RU / EN interface** | Terminal copy and project documentation in both languages. |

This is research software. The current strategy audit **did not establish a positive edge**. Historical results do not guarantee future profitability. BlackLine does not place exchange orders.

## 🚀 Quick start

### Requirements

- **PHP 8.2+** with BCMath, Intl, PDO MySQL, PCNTL and Zip; Composer.
- **Node.js 20.19+ or 22.12+**; npm.
- **MariaDB/MySQL** and **Redis**; Docker Compose is an optional way to run them.

### 1. Install and configure

From the project directory:

```bash
cp .env.example .env
composer install
php artisan key:generate
npm ci
```

Set your local `DB_*`, `DB_ROOT_PASSWORD` and `REDIS_*` values in `.env`. The example uses MariaDB at `127.0.0.1:33316` and Redis at `127.0.0.1:6379`. Replace the example database passwords before use.

### 2. Start the database and Redis

```bash
docker compose up -d mariadb redis
```

If you already run these services locally, use those instances and adjust `.env` instead.

### 3. Initialize and run

```bash
php artisan migrate --seed
npm run build
php artisan serve --host=127.0.0.1 --port=8080
```

Open **[localhost:8080](http://localhost:8080)**.

The seeder creates these **development-only** accounts in `local` / `testing`:

| Account | Email | Password |
| :-- | :-- | :-- |
| User | `user@example.com` | `password` |
| Administrator | `admin@example.com` | `password` |

Change development passwords for your installation. Other environments do not receive these accounts; regular registration is at `/register`.

<details>
<summary><strong>🐳 Run the complete stack in Docker</strong></summary>

After creating `.env` and installing PHP/frontend dependencies:

```bash
docker compose up -d --build
docker compose exec php php artisan key:generate
docker compose exec php php artisan migrate --seed
```

Compose runs Nginx, PHP, the database, Redis, workers, scheduler and Reverb. PHP containers use internal `mariadb:3306` and `redis:6379` addresses. Published ports bind to `127.0.0.1`.

The `node` service runs Vite in development mode. For a compiled frontend, run `npm run build` and stop the `node` service. Run either the Docker HTTP service or the native Artisan server on port 8080.

</details>

## 🔑 API keys: what you actually need

**Binance API keys are not required for the current application.** The candle provider reads public spot-market candles from `GET /api/v3/klines`. Historical imports and live market-data refresh use that endpoint without signed requests or access to an exchange account. You only need an internet connection and access to the provider; public API rate limits still apply.

| Key or token | Where it comes from | Required for |
| :-- | :-- | :-- |
| Binance API key / secret | Your own Binance account, if you implement a future authenticated data provider. | Not used by the current public candle provider. |
| BlackLine API token | Create your own token in `/api/docs`. | Calling this installation's REST / SSE API. |
| Reverb app ID / key / secret | Generate your own values and store them in this installation's `.env`. | Realtime WebSocket updates. |

The BlackLine API token and Reverb credentials are **application credentials, not Binance credentials**. Keep private keys and secrets in your own `.env`; it is excluded from Git. Adding a Binance key to `.env` alone does not enable private exchange endpoints.

## 🕯️ Load historical data

Candle archives and user databases are **not included** in the repository. Start with BTC and a short date range before a larger import.

```bash
php artisan signals:backfill-volume --help
php artisan signals:import-volume-market --help
```

Example: replay a calendar year of BTC, including exits. Timestamps are in milliseconds, UTC:

```bash
php artisan signals:backfill-volume \
  --symbol=BTCUSDT \
  --strategy=smart_volume_v1 \
  --interval=15m \
  --context-interval=1h \
  --start-time=1735689600000 \
  --end-time=1767225600000 \
  --full-range \
  --simulate-exits \
  --dry-run
```

This example covers **2025-01-01 → 2026-01-01**. Remove `--dry-run` to persist the results. Choose your dates and symbols, then open `/performance/terminal` to inspect the backtest. Large imports require provider availability and may take time.

## 📡 Enable realtime updates

For a native local setup, run workers and the scheduler in separate terminals:

```bash
php artisan horizon
php artisan schedule:work
```

Set `BROADCAST_CONNECTION=reverb` and configure `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`, `REVERB_HOST=127.0.0.1`, `REVERB_PORT=8081` and `REVERB_SCHEME=http` privately in `.env`. Then run:

```bash
php artisan config:clear
php artisan reverb:start --host=127.0.0.1 --port=8081
```

The terminal reads WebSocket connection settings at runtime. Signal updates stay in the browser. Authentication retains password recovery and email verification; the default local mailer writes those messages to the log.

## 🧩 Add or switch a strategy

1. Implement `App\Contracts\Trading\StrategyAdapter`.
2. Return generic `StrategySignal` objects inside `StrategyResult`.
3. Register the adapter in `config/strategies.php`.
4. Select it in the feed/backtest, or use `--strategy=your_strategy_v1`.

```bash
php artisan signals:run-strategy --strategy=smart_volume_v1 --symbol=BTCUSDT --dry-run
```

`STRATEGY_ACTIVE` selects the default generator. Shared fees, execution assumptions and portfolio limits live in `config/trading.php`. Queries and cache keys keep strategy datasets separate.

**[Read the adapter guide →](docs/STRATEGIES.md)**

## 🧮 Understand the results

Smart Volume uses only data available at the entry decision, including completed context candles. Stops use candle **high/low**; targets use the **close**. Stop/target overlap defaults to the conservative stop outcome. Entry/exit fees and slippage are included.

The drawdown target is **40%**. Risk reduction begins at **20%**, and new entries halt at **32%**. These controls do not guarantee a maximum market drawdown.

Equity uses realized results from completed trades. Open PnL, funding, borrowing and liquidations are not fully modeled. Spot candles do not validate futures short execution. Review the [strategy audit](docs/research/DRAWDOWN_40_2026-10-08.md) before interpreting the numbers.

## 🧪 Checks

Use a **separate test database**: the test suite resets its data.

```bash
DB_HOST=127.0.0.1 DB_PORT=33316 DB_DATABASE=blackline_test \
DB_USERNAME=blackline DB_PASSWORD=your-local-password php artisan test

vendor/bin/pint --dirty --test
npm run build
composer audit
npm audit
python3 -m unittest discover -s scripts/research -p 'test_*.py'
```

## 📚 Documentation

| Guide | Contents |
| :-- | :-- |
| [Strategy adapters](docs/STRATEGIES.md) | Contract, registry, configuration and causal-data requirements. |
| [Architecture](docs/ARCHITECTURE.md) | Data flow, services, persistence and shared accounting. |
| [API](docs/API.md) · [OpenAPI](docs/openapi.yaml) | Tokens, signal queries, SSE and risk metadata. |
| [Database](docs/DB.md) | Schema, development fixtures and migration notes. |
| [Local operations](docs/RUNBOOK.md) | Workers, scheduler, health and local logs. |

Choose **Automatic (browser)**, **UTC** or an IANA timezone in the top menu. Signed-in accounts retain the preference across sessions; guests retain it for their browser session. Signal times, trade history, native charts and date filters use this zone. Candle timestamps, API responses and backtest calculations stay in UTC. Embedded TradingView charts depend on the widget's supported zones. API tokens are managed on `/api/docs`; there is no separate profile page.

API documentation is also available at `/api/docs`. Create an API token in `/api/docs`; only its hash is stored.

## 🤝 Contributing

Keep changes focused and include meaningful checks. Update RU and EN copy together. New strategies need causal-data and execution-parity tests; adding an algorithm is separate from demonstrating profitability.

Keep `.env`, credentials, databases, candle archives, logs and private backups outside Git.

## 📄 License

[MIT](LICENSE) · BlackLine contributors.
