# Strategy audit / Аудит стратегии — 2026-10-08

Исторический отчёт при цели просадки 15%. Позже пользователь изменил её на 40%; актуальные настройки и результаты — в [DRAWDOWN_40_2026-10-08.md](DRAWDOWN_40_2026-10-08.md).

Historical report at the original 15% target. The user subsequently changed it to 40%; see [the updated study](DRAWDOWN_40_2026-10-08.md).

## Вывод

Положительное математическое ожидание текущей Smart Volume не подтверждено. Пересчитано 3670 завершённых сигналов по 25 монетам за два года; в последнем году — 2061. После исправления причинных ошибок и окна уровней годовое ожидание составляет **-0.2628R** после комиссий, до funding. Нельзя компенсировать отрицательное ожидание увеличением риска или лестницей.

Средняя выигрышная сделка: +3.753R; средняя убыточная: −1.278R. Доля выигрышей 20.18%; точка безубыточности при этих средних величинах 25.41%. Проверяем всю формулу `E[R] = p × avg_win_R − (1 − p) × avg_loss_R`, а не win rate отдельно. R — фактический чистый результат, делённый на начальный денежный стоп. Поэтому стоп с проскальзыванием может быть хуже −1R.

При равном первоначальном стоп-риске годовой profit factor 0.7425. Интервал ожидания по bootstrap календарных недель: [-0.4307, -0.076]; он относится к наблюдаемым завершённым сигналам, а не гарантирует будущий результат. Совместные сделки разных криптомонет внутри недели не считаются независимыми наблюдениями.

## Что исправлено

1. Комиссия входа ранее вычиталась из баланса, но отсутствовала в результате отдельной сделки, классификации win/loss и profit factor. Теперь обе комиссии учитываются во всех этих показателях.
2. `avg_r` был процентной доходностью, а не R. Теперь знаменатель — первоначальный денежный стоп; средний процент отдельно сохранён в интерфейсе.
3. Комиссия искусственно возрастала с плечом, хотя размер позиции уже учтён в notional. Множитель убран; комиссия выхода рассчитывается по фактической стоимости закрываемого количества.
4. Расстояние от обычного открытия свечи до стопа ошибочно считалось гэпом. Дополнительный гэп учитывается только при открытии за стопом против позиции, одинаково в истории и автозакрытии сигналов.
5. Уровни целей жили бесконечно, тогда как уровни входа ограничены `lookback=600`. Теперь цели используют то же окно. Это исправляет зависимость от произвольной длины загруженной истории, но не доказывает прибыльность. Перекос 9 long / 237 short в прежней годовой выборке сменился на 752 long / 1309 short.
6. Сделки, начатые до выбранного периода, больше не финансируются заново из стартового капитала периода. Будущие закрытия исключены.
7. Устаревшие snapshots отвергаются по версии расчёта и хэшу риск-модели. Порядок одновременных входов стабилен по символу, затем ID; события закрытия обрабатываются первыми.

## Ограничения риска в терминале

- Максимальный плановый риск сделки 0.5% капитала, общего портфеля 2%, одного направления 1.5%.
- Одна открытая позиция по каждому символу. Все монеты делят один портфельный бюджет; 25 отдельных депозитов не суммируются как один портфель.
- В размер позиции заложены обе комиссии и максимальное настроенное стоп-проскальзывание. Гэп сверх стопа этим резервом не ограничен.
- При просадке реализованного капитала 7.5% риск уменьшается вдвое; при 12% новые входы прекращаются до конца теста. Выходы уже открытых позиций продолжают исполняться.
- Лестница 0.5–2% сохранена как внутреннее состояние для совместимости API, но фактический риск ограничен сверху 0.5%. Это не оценка Kelly. При отрицательном ожидаемом R у стратегии нет основания для положительной ставки Kelly в рамках этой модели.

Годовая симуляция портфеля, капитал $10000, плечо ×10: ROI **-8.7553%**, реализованная просадка **12.2381%**, 424 исполненных сделок, 1637 пропущенных по риску, PF 0.8614. Остановка входов сработала. Это показатель контроля потерь, не доказательство рабочего преимущества. Сравнение с прежними −46% смешивает исправления исполнения, изменения выборки и размера риска; нельзя приписывать его улучшению входов.

Предел 15% — цель, а не гарантия. Страница терминала пока использует завершённые сделки и реализованный капитал: плавающий PnL, незавершённые сигналы, funding, стоимость заимствования и ликвидации не включены. Поэтому измеренная просадка не является полной рыночной просадкой. Ограничение явно показано в RU/EN интерфейсе.

## Проверка переключения стратегий

Два хронологических отрезка: 2024-10-08 → 2025-10-08 для разработки и 2025-10-08 → 2026-10-08 для проверки. Это **исследовательская проверка, не нетронутый holdout**: исходную стратегию на последнем году уже изучали. 25 монет сохранены без отбора победителей; параметры общие, индивидуального перебора нет.

Часовые признаки известны только после закрытия свечи, вход на следующем открытии. Базовые расходы — 0.04% комиссии и 0.02% проскальзывания на сторону. Повторная проверка удваивает оба расхода и заново рассчитывает размер и допуск позиций: набор исполненных сделок может измениться. Максимальное суммарное gross notional — один капитал. Short на spot OHLCV — аналитическая аппроксимация, не исполнимая spot-сделка.

Варианты: Donchian breakout за 20/55 часов; те же пробои с ADX≥25 и EMA50/200 по направлению; mean reversion при ADX<20, сближенных EMA, RSI30/70 и отклонении от средней на 2ATR; router объединяет trend55 и range, пропуская переходный режим. Trend: стоп и трейл 3ATR, максимальное удержание 720 часов. Range: стоп 2ATR, цель — средняя на входе, максимум 48 часов. Стоп на касании high/low, трейл действует только со следующей свечи. Остаточные позиции закрываются на границе периода. Просадка исследовательских вариантов считается с часовым mark-to-market.

| Вариант | ROI разработки | ROI проверки | DD проверки | PF проверки | ROI при двойных расходах |
|---|---:|---:|---:|---:|---:|
| breakout20 | -3.86% | -6.08% | 12.13% | 0.776 | -4.74% |
| breakout55 | -2.69% | -4.68% | 12.66% | 0.874 | -4.77% |
| trend20 | -8.49% | -4.76% | 12.54% | 0.829 | -6.63% |
| trend55 | -4.00% | -5.06% | 12.07% | 0.788 | -5.88% |
| range | -4.59% | -10.51% | 12.07% | 0.665 | -10.71% |
| router | -6.62% | -4.66% | 12.05% | 0.837 | -6.04% |

Ни один вариант не проходит даже базовое требование положительного результата в обоих годах. Router в терминале не включён. У исходных сигналов ожидание по режимам также отрицательно: trend -0.3341R, range -0.1362R, transition -0.2004R. Простого ADX-переключателя недостаточно.

## Правило дальнейшей разработки

Сначала полноценный причинный replay с открытыми позициями, 15m исполнением, mark-to-market и данными того инструмента, который моделируем. Для perpetual нужны реальные funding/mark/index данные; для short spot — стоимость и доступность займа. [Binance описывает funding как периодические платежи по размеру позиции](https://www.binance.com/en/support/faq/detail/360033525031).

До проверки фиксируем небольшое семейство гипотез и даты. Раздельно оцениваем трендовый модуль, модуль боковика и решение оставаться без позиции. Router подключает модуль только при доказанном преимуществе в его режиме; неопределённые и слишком волатильные состояния пропускает. Не подбираем монеты и параметры по последнему году и не называем его новым holdout повторно.

Критерий допуска к экспериментальному портфелю: положительное net expectancy с нижней границей временного bootstrap выше нуля, PF>1.2 после расходов, положительные результаты на нескольких последовательных проверочных отрезках, прибыльность при удвоенных расходах, полная MTM-просадка ≤15%. Следом проверяем устойчивость соседних параметров и отдельный forward paper-период. Эти пороги — заранее выбранные инженерные требования, не универсальные финансовые константы. Текущие варианты отклонены. Повышать риск до прохождения этих проверок нельзя обосновать математикой текущей выборки.

## Воспроизведение

Архивы остаются в приватном storage и не включены в git. Агрегаты и SHA-256 архивов: [strategy-audit-2026-10-08.json](strategy-audit-2026-10-08.json).

```bash
php scripts/research/export-signals.php
python3 scripts/research/audit_trades.py --signals storage/app/private/market-data/research/baseline-signals.json --data storage/app/private/market-data/two-years --output storage/app/private/market-data/research/trade-audit.json
python3 scripts/research/regime_study.py --max-drawdown-percent 15 --data storage/app/private/market-data/two-years --output storage/app/private/market-data/research/regimes.json
python3 -m unittest discover -s scripts/research -p 'test_*.py'
```

## English review

No positive edge has been validated. The current corrected strategy produces -0.2628R per completed signal after commissions in the validation year; average winning/losing trades are +3.753R / −1.278R. Risk sizing cannot turn a negative edge positive.

Fixed entry-fee omission in individual results/PF, incorrect R units, leverage-driven fee inflation, exit-notional fees, false adverse stop gaps, unlimited target-level lifetime, period entry boundaries and stale snapshots. Target and entry volume levels now share the same 600-bar window. The analytical feed remains Smart Volume; none of the experimental alternatives was promoted.

Portfolio policy now caps planned trade/total/directional risk at 0.5%/2%/1.5%, forbids duplicate symbol positions, reserves fees and configured worst stop slippage, halves risk at 7.5% realized drawdown and permanently stops new entries for that simulation at 12%. Open positions continue exiting. The legacy internal ladder is capped and is not Kelly. Gaps remain capable of exceeding the budget. Terminal annual result is -8.7553% with 12.2381% realized drawdown and PF 0.8614; this completed-trade view excludes floating PnL, unresolved signals and funding, and does not prove compliance with a full 15% market drawdown limit.

The six declared hourly variants above all fail positive annual validation after costs. The study uses a chronological development/validation split, causal next-open entries, boundary liquidation, hourly marked equity and doubled-cost stress. Spot-derived short prices are research proxies. The validation year was already inspected for the incumbent, so this is exploratory evidence, not a pristine holdout. No symbol selection or per-symbol optimization was used.

Before promotion, implement a full replay of all open positions with 15m fills and appropriate funding/borrow/mark data. Freeze hypotheses before evaluation; require positive net expectancy with a positive lower temporal-bootstrap bound, PF>1.2, multiple positive chronological validation windows, positive doubled-cost performance, full MTM drawdown ≤15%, neighboring-parameter stability and a separate forward paper period. A regime with no validated module stays in cash. These are engineering admission criteria, not guaranteed returns. See the reproducible commands and aggregate JSON above.
