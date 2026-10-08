# Signals Live/Backtest Parity

## RU

### Цель
Обеспечить максимально близкое поведение `live`-сигналов к результатам бэктеста на той же стратегии.

### Текущий pipeline
1. Планировщик запускает `signals:run-volume` по расписанию.
2. Команда забирает рынок через `MarketDataService`:
   - entry timeframe (по умолчанию `1m`);
   - context timeframe (по умолчанию `5m`);
   - rolling lookback окно (по умолчанию 30 дней, `signals.runtime.lookback_days`).
3. Перед расчетом отбрасывается незакрытая текущая свеча:
   - свеча валидна только если `openTime + interval <= now(UTC)`.
4. `VolumeSignalImportService` запускает `VolumeStrategy`:
   - входы: `generateEntrySignals(...)`;
   - закрытия активных: `generateSignals(..., simulateExits=true, updateExistingOnly=true, closeOnTargetOnly=true)`.
5. Публикация и дедупликация:
   - для новых live-входов используется `publishLatestOnly=true`;
   - создание идет через `firstOrCreate` по identity:
     `exchange + symbol + timeframe + direction + entry_at + strategy_key`;
   - это защищает от дублей одного и того же сигнала между запусками scheduler.

### Почему live ближе к бэктесту
- Одинаковый провайдер данных (`binance` по умолчанию в live).
- Одинаковая стратегия (`VolumeStrategy`) и конфиг (`signals.volume_strategy`).
- Одинаковый исторический контекст (rolling full-range окно, а не только короткий `limit`).
- Расчет только по закрытым свечам (без "мигания" на формирующейся свече).

### Что может давать остаточные расхождения
- Разные данные у внешнего источника в разные моменты времени.
- Временные сетевые ошибки провайдера.
- Отличия между "моментом live-запуска" и "моментом оффлайн-бэктеста", если сравнение делается не по закрытым свечам.

### Операционные рекомендации
- Для точной сверки используйте один провайдер (`binance`) и один timezone (`UTC`).
- Сравнивайте по закрытым свечам и одинаковому временному диапазону.
- Если нужны строгие регрессии, проверяйте выборочно `symbol/day` через dry-run и фиксируйте результат.

## EN

### Goal
Keep `live` signal generation behavior as close as possible to backtest results for the same strategy.

### Current pipeline
1. Scheduler runs `signals:run-volume` on cadence.
2. The command fetches market data via `MarketDataService`:
   - entry timeframe (default `1m`);
   - context timeframe (default `5m`);
   - rolling lookback window (default 30 days, `signals.runtime.lookback_days`).
3. Before strategy evaluation, the current unclosed candle is removed:
   - a candle is valid only if `openTime + interval <= now(UTC)`.
4. `VolumeSignalImportService` executes `VolumeStrategy`:
   - entries: `generateEntrySignals(...)`;
   - active position closing: `generateSignals(..., simulateExits=true, updateExistingOnly=true, closeOnTargetOnly=true)`.
5. Publish and dedup:
   - new live entries use `publishLatestOnly=true`;
   - persistence uses `firstOrCreate` with identity:
     `exchange + symbol + timeframe + direction + entry_at + strategy_key`;
   - this prevents duplicate inserts for the same signal across scheduler runs.

### Why live is closer to backtest now
- Same data provider (`binance` default in live).
- Same strategy (`VolumeStrategy`) and config (`signals.volume_strategy`).
- Same historical context (rolling full-range window instead of a short `limit` slice).
- Closed-candle-only evaluation (no noisy signals from an unfinished candle).

### Remaining mismatch sources
- Provider-side data differences over time.
- Temporary provider network failures.
- Different run moments between live and offline backtest if comparison is not limited to closed candles.

### Operational guidance
- For strict parity checks, use one provider (`binance`) and one timezone (`UTC`).
- Compare only closed candles in the same date range.
- For regression control, run targeted dry-runs by `symbol/day` and store expected outcomes.
