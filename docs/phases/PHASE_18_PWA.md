# Phase 18 — Installable PWA and offline practice shell

## Delivered

- Installable manifests for the homepage and the virtual practice arena.
- Cache-first service workers using the existing logo, board and launcher assets.
- An offline practice fallback page that keeps the product boundary explicit.
- Registration guards that avoid service-worker calls from `file:` previews.

## Rules

- Offline mode is limited to local practice and cached presentation assets.
- Server verification, tournament entry, leaderboard publication, wallet actions and any future cash operation require a network connection.
- The service workers never create ledger entries or mutate account balances.
- No new bitmap assets are introduced in this phase; the existing repository assets are reused.

## Rollback

Remove the manifest links and unregister the workers at the hosting layer. Cached browser data is disposable and is not an authoritative product record.
