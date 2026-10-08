# Database

Use MariaDB/MySQL and run `php artisan migrate`. Development fixtures are opt-in through `php artisan db:seed` in local/testing environments.

Core tables: users, roles/permissions, api_tokens, signals, signal_categories, signal_updates, watchlists/watchlist_items, performance_snapshots and queue/framework tables. Signals identify their generator through `strategy_key`; parameters store adapter metadata. Entry time/price and exit time/price drive historical accounting.

Back up existing databases before upgrades. The cleanup migration removes legacy commercial and outbound-delivery tables and obsolete identity-provider fields. It intentionally has no data-restoring rollback; restoring those removed modules requires the private backup and old application version.

Never commit database contents or SQL dumps. Tests use a separate database and may reset it. Candle archives are private local data, not development fixtures.
