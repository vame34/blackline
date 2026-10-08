# Local operations

Use the README setup and retain `.env` privately. Serve HTTP on `127.0.0.1:8080`. Run Horizon for queue jobs, scheduler for candle/signal refresh and Reverb for browser updates. Do not run overlapping schedulers against the same database.

`php artisan signals:run-strategy --dry-run` validates generation without persisting. Inspect command help before large historical imports. Back up the local database outside Git before migrations or replay changes.

`/health` checks the database, Redis, Horizon and scheduler heartbeat; if workers or scheduler are intentionally stopped, the result may be degraded. Check `storage/logs` locally without publishing logs containing account or request data.
