# Bubble Royale staging / production-shaped deployment

This directory describes the containerized, virtual-only runtime. It does not configure a payment provider, withdrawals or cash mode. Follow [`docs/PRODUCTION_RUNBOOK.md`](../../docs/PRODUCTION_RUNBOOK.md) before exposing it to users.

## Local use

1. Copy `.env.example` to `.env` in this directory and replace every placeholder with local-only values.
2. Run `docker compose --env-file .env up --build` from this directory.
3. Check `http://localhost:8080/health` and confirm `cash_mode` is `false`, `persistence` is `mysql` and `rate_limiting` is `redis`.

The database and Redis services are required runtime dependencies in staging and production. Migrations run once at container start under a MySQL advisory lock; for a multi-replica production rollout, run migrations as a one-off release task and set `RUN_MIGRATIONS=0` on web replicas.
