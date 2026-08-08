# Bubble Royale production runbook

This runbook is for the production-shaped virtual platform. The approved launch boundary is virtual-only: `cash_mode` must remain `false`, there are no withdrawals or conversions, and the sandbox payment adapter is not a live payment integration.

## Release gate

Before the first public deployment, the operator must have:

- a managed TLS endpoint with HTTP redirected to HTTPS;
- a randomly generated `BUBBLE_ROYALE_SESSION_SECRET` of at least 32 characters, stored in a secret manager and rotated through a planned session invalidation window;
- `APP_ENV=production`, `COOKIE_SECURE=1`, `RUN_MIGRATIONS=0` on web replicas, and no committed `.env` file;
- private MySQL 8 and Redis 7 endpoints with network policy restricting access to the application and migration jobs;
- a dedicated MySQL application account with the minimum required privileges; it must not have `DROP`, `ALTER`, `GRANT OPTION`, or `FILE`, and append-only tables must deny `UPDATE` and `DELETE`;
- encrypted backups with point-in-time recovery and a documented restore drill;
- centralized access/error logs, request-ID preservation, alerting on 5xx responses, `/health` 503 responses, Redis failures, migration failures and growing risk cases;
- an operator owner for kill switches, incident response, player support and rollback decisions.

Email delivery, account recovery, KYC/KYB, age verification and any future real-value provider are external integrations. They are not silently simulated by this repository and are not prerequisites for the virtual-only runtime to keep cash disabled.

## Configuration

Set the following values through the deployment platform, never in source control:

```dotenv
APP_ENV=production
APP_DEBUG=0
BUBBLE_ROYALE_SESSION_SECRET=<secret-manager-value>
DB_HOST=<private-mysql-host>
DB_PORT=3306
DB_DATABASE=bubble_royale
DB_USERNAME=<least-privilege-application-user>
DB_PASSWORD=<secret-manager-value>
REDIS_HOST=<private-redis-host>
REDIS_PORT=6379
REDIS_PASSWORD=<secret-manager-value>
COOKIE_SECURE=1
RUN_MIGRATIONS=0
MIGRATION_RETRIES=30
```

The runtime refuses to report production readiness when MySQL or the shared Redis rate limiter is unavailable. A local file limiter is only a local-development fallback.

## Deployment order

1. Build the immutable image from the commit to be released. CI must pass JavaScript tests, the repository audit, PHP/MySQL tests, the container build and `apachectl -t`.
2. Run the migration command as a single release task using the image and production database credentials:

   ```sh
   RUN_MIGRATIONS=1 php tools/migrate.php
   ```

   The migration runner takes a MySQL advisory lock. Do not run destructive schema changes during a live rollout.

3. Start web replicas with `RUN_MIGRATIONS=0` and health-gate traffic on `/health`.
4. Verify the response contains `cash_mode: false`, `persistence: "mysql"`, `rate_limiting: "redis"` and all expected virtual features.
5. Perform a smoke test: register a disposable account, issue a practice session, complete one deterministic replay, verify it twice, and confirm the second response is idempotent.
6. Confirm that the public lobby and leaderboard expose only stable public player references, never internal player IDs, session tokens or replay payloads.
7. Record the release commit, migration versions, image digest and operator in the deployment log.

## Rollback and incident controls

- Prefer rolling back the application image while leaving completed additive migrations in place. Use a forward migration for schema repair; do not manually delete ledger, audit, risk or replay-receipt rows.
- Disable `virtual_gameplay`, `virtual_progression`, `virtual_tournaments` or `virtual_wallet` with the admin command center when an incident requires a feature stop. `cash_mode` is immutable off.
- Preserve `X-Request-ID` when escalating a failed request. Correlate it with application, Apache, MySQL and Redis logs.
- If persistent storage is degraded, keep the service out of traffic: production endpoints fail closed rather than using in-memory state.
- Treat replay rejection, suspicious risk cases, rate-limit failures and unexpected virtual-ledger deltas as operational alerts. Never “fix” them by editing append-only rows.

## Backup and recovery drill

At least once before public launch and on a recurring schedule:

1. restore MySQL into an isolated environment;
2. apply the recorded migrations and verify the schema version table;
3. run the PHP persistence suite against the restored database;
4. verify ledger balances from append-only entries, audit event uniqueness, game-session final responses and kill-switch state;
5. verify Redis can be recreated without changing durable user, game or virtual-economy state;
6. document restore time, data-loss point and the operator sign-off.

## Explicit non-goals

This release does not activate cash tournaments, money deposits, withdrawals, cash balances, payment-provider credentials, currency conversion or real-value settlement. Any future cash pilot requires a new reviewed change, legal/compliance approval, provider certification, age/KYC/KYB controls, reconciliation, manual review, responsible-play controls and a separate deployment gate.
