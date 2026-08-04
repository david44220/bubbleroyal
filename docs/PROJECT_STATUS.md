# Project Status

## Version

`v0.2 — Homepage prototype and playable virtual practice arena`

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

## Current limitations

- The gameplay is not yet a production game engine.
- Tournament values are visual/demo data only.
- No authentication, wallet, payment, KYC or withdrawal flow is active.
- No cash mode is enabled.
- Practice scores are local-only and are not submitted to a server or leaderboard.

## Next milestone

Move practice sessions to signed server validation with replay events and a deterministic score verifier. Keep the virtual-only boundary in place until the replay protocol, anti-cheat rules and security tests are complete.
