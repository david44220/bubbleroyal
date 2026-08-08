# Project Status

## Version

`v0.7 — Production virtual platform foundation`

## Completed

- Bubble Royale brand direction established.
- Premium homepage mockup created in English.
- Six visual assets generated and cleanly isolated as RGBA PNGs:
  - logo;
  - Bubble Shooter board;
  - launcher;
  - `$5,000` prize orb;
  - tournament trophy;
  - decorative bubble cluster.
- Responsive homepage assembled with CSS 2D/3D transforms and floating animations.
- Mobile navigation, tournament countdown, reveal animations and CTA interactions added.
- Standalone Android-compatible HTML version produced with embedded assets.
- First playable virtual practice arena added under `prototype/practice/`.
- Deterministic staggered-board engine added with match clusters, floating-bubble cascades, combo scoring and penalty rows.
- Pointer, touch and keyboard controls added with responsive premium game shell.
- Homepage modular preview now links to the practice arena.
- Node test coverage added for determinism, matching, cascades, penalty rows and replay parity.
- Deterministic `br-replay-v1` protocol added with local validation, tamper detection and replay export.
- PHP 8.5 rules engine added with a cross-language deterministic seed vector.
- HMAC-signed virtual practice sessions and server-side replay score verification added.
- Framework-free front controller, `.env.example`, PHP protocol tests and GitHub Actions CI added.
- Virtual challenges, XP, tickets and achievements added with idempotent verified-result grants.
- Free virtual tournament catalog, schedules, lobbies and duplicate-entry protection added.
- Replay review decisions, deterministic leaderboard ordering and idempotent virtual result publication added.
- Addon manifests and phase reports added for Rewards, Tournaments and Leaderboards.
- Ledger-backed virtual wallet for tickets and prize units added.
- Append-only virtual ledger and sandbox prize-pool settlement added.
- Configurable GeoFeature, age, KYC/KYB, risk and responsible-play gates added.
- MySQL 8 migration added for virtual economy and privacy-minimized compliance profiles.
- Durable identity, authentication sessions, game sessions, progression, tournament entries, audit events, risk cases and virtual ledger adapters wired into the public PHP runtime.
- Redis-backed shared rate limiting, fail-closed production storage checks and security response headers added.
- Durable replay response receipts added so verified practice and tournament requests are safely retryable after a timeout.
- Container, Apache and compose validation added to CI.

## Current limitations

- The gameplay engine is a validated virtual foundation, not yet a full-scale live-game service with matchmaking, CDN asset delivery or real-time presence.
- Virtual wallet access remains fail-closed until a verified compliance profile exists; no real-value balance, payment, withdrawal or conversion exists.
- Email verification, account recovery, support tooling and KYC/KYB provider integrations require external services before a broad public launch.
- Production still requires operator-managed secrets, TLS termination, backups/PITR, monitoring, alert routing and a restore drill.
- No cash mode is enabled and the repository intentionally contains no activation path for it.

## Next milestone

Complete the runbook acceptance checklist, execute a closed virtual alpha and validate the external operational controls. Keep any cash capability disabled until legal, provider, compliance and settlement validation is complete.
