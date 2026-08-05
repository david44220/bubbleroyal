# Bubble Royale staging alpha blueprint

This directory describes a local/staging-only stack for the virtual alpha. It is not a production deployment and it does not configure a payment provider, withdrawals or cash mode.

## Local use

1. Copy `.env.example` to `.env` in this directory and replace every placeholder with local-only values.
2. Run `docker compose --env-file .env up --build` from this directory.
3. Check `http://localhost:8080/health`.

The database and Redis services are included as integration boundaries. The current public endpoint still exposes the verified virtual practice flow only; wiring persistence or external providers requires a separate reviewed change.
