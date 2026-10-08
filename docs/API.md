# Signal API

Open `/api/docs` or inspect `openapi.yaml`. Create an API token in `/api/docs`. Send `Authorization: Bearer <token>` or `X-API-Key: <token>`; optional trusted-IP restrictions apply.

Endpoints under `/api/v1`: `me`, `instruments`, `signals`, `signals/{id}`, `signals/stream`, `risk-model`, `risk-model/version`. Signal listing supports symbol, timeframe, direction, status and strategy filters. SSE is bounded by configured duration/concurrency limits; REST remains available.

Tokens are stored as hashes and may expire or be revoked. Banned users cannot access the terminal or API. The API serves analysis data and does not place exchange orders. Risk metadata describes backtest assumptions.
