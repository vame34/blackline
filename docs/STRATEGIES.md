# Strategy adapters / Адаптеры стратегий

Implement `App\Contracts\Trading\StrategyAdapter::generate(InstrumentData $data, array $configuration, bool $simulateExits): StrategyResult`. Return `StrategySignal` DTOs with instrument, direction, decision time, Entry/SL/TP and optional completed exit. Metadata must be serializable and must not contain credentials. The DTO validates prices and timestamps.

Реализуйте `StrategyAdapter::generate(...)` и верните `StrategyResult` с DTO `StrategySignal`: инструмент, направление, время решения, Entry/SL/TP и необязательный завершённый выход. Метаданные должны сериализоваться и не содержать секретов. DTO проверяет цены и времена.

Register the adapter in `config/strategies.php`:

```php
'my_strategy_v1' => [
    'label' => 'My strategy',
    'adapter' => App\Services\Trading\Adapters\MyStrategyAdapter::class,
    'enabled' => true,
    'config' => 'my_strategy', // Optional configuration path.
    'defaults' => [],         // Optional local defaults.
    'symbol_overrides' => 'my_strategy_symbols', // Optional.
],
```

Choose the generator through `STRATEGY_ACTIVE=my_strategy_v1` or:

```bash
php artisan signals:run-strategy --strategy=my_strategy_v1 --symbol=BTCUSDT --dry-run
php artisan signals:import-volume-market --strategy=my_strategy_v1 --help
php artisan signals:backfill-volume --strategy=my_strategy_v1 --help
php artisan performance:refresh-terminal-backtests --strategy=my_strategy_v1
```

В ленте и бэктесте выберите стратегию в списке. Фильтр `strategy` входит в URL, запросы и ключи кеша. API: `/api/v1/signals?strategy=my_strategy_v1`. Старые имена команд импорта сохранены для совместимости; сам импорт больше не зависит от Smart Volume.

Select a strategy in the feed/backtest dropdown. The `strategy` URL filter scopes queries and cache keys. API: `/api/v1/signals?strategy=my_strategy_v1`. Legacy import command names remain compatible; their importer is strategy-independent.

Version the key when decision rules change so historical samples retain their origin. Do not silently reuse another strategy's signals. Unknown or disabled keys fail explicitly. Shared fees, execution defaults and portfolio sizing/risk limits live in `config/trading.php`, outside adapters. The current execution protocol is fixed Entry/SL/TP with wick stops and close targets; extend the simulator and live parity tests before adding different exit types. Execution parameters are stored with newly imported signals and used for live closure; old Smart Volume signals retain legacy defaults.

При изменении правил создавайте новый ключ версии. Общий риск-менеджмент остаётся вне адаптера. Сейчас контракт поддерживает фиксированные Entry/SL/TP: стоп по фитилю, тейк по закрытию. Другие типы выхода требуют расширения симулятора и тестов совпадения live/backtest. Параметры исполнения сохраняются с новыми сигналами; старые сигналы Smart Volume используют прежние настройки.

Use only information available at each entry, including completed context candles. Test future-data independence, LONG/SHORT and stop/target overlap. Do not calibrate parameters from the full future candle window. Keep unvalidated algorithms separate from evidence of profitability.

Используйте только доступные на момент входа данные, включая завершённые контекстные свечи. Проверяйте независимость от будущего, LONG/SHORT и одновременное касание стопа/тейка. Не подбирайте параметры по будущему окну свечей. Подключение алгоритма не доказывает его прибыльность.
