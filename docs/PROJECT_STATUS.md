# Project Status

## Version

`v0.5 — Virtual economy and compliance foundations`

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

## Current limitations

- The gameplay is not yet a production game engine.
- Tournament values are virtual/demo data only; phase 09 uses an explicit in-memory adapter.
- No authentication, wallet, payment, KYC or withdrawal flow is active.
- No cash mode is enabled.
- Verified scores are not yet connected to durable player accounts or an operator dashboard.
- The phase 08–10 services are not yet wired to durable HTTP persistence across PHP workers.
- Wallet, ledger and policy services use in-memory adapters until authenticated database repositories are wired.
- KYC/KYB is represented only by verified status signals; no identity verification provider is connected.
- The PHP runtime is validated in CI; the local workspace does not currently include a PHP binary.

## Next milestone

Add authenticated account persistence, anti-cheat/risk case workflows and the admin command center. Keep any cash capability disabled until legal, provider, compliance and settlement validation is complete.
