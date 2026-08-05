# Project Status

## Version

`v0.3 — Deterministic replays and signed virtual session verification`

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

## Current limitations

- The gameplay is not yet a production game engine.
- Tournament values are visual/demo data only.
- No authentication, wallet, payment, KYC or withdrawal flow is active.
- No cash mode is enabled.
- Verified practice scores are returned by the validation endpoint but are not submitted to a leaderboard.
- The PHP runtime is validated in CI; the local workspace does not currently include a PHP binary.

## Next milestone

Add virtual challenges, XP, tickets and achievements on top of the verified session boundary. Keep competitive tournaments and any cash capability disabled until anti-cheat, compliance and settlement phases are complete.
