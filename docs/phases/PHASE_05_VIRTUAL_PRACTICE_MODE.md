# Phase 05 — Virtual Practice Mode

## Status

Implemented as the first browser-playable Bubble Royale milestone.

## Scope

- virtual practice only;
- no entry fee, cash wallet, payment, withdrawal or tournament submission;
- reuse of the established homepage logo and visual language;
- no new raster assets.

## Delivered

- `prototype/practice/index.html` — responsive practice arena;
- `prototype/practice/practice.css` — premium dark/cyan/violet/gold game shell;
- `prototype/practice/engine.js` — deterministic board and scoring rules;
- `prototype/practice/practice.js` — canvas renderer, pointer/touch/keyboard controls and local session UI;
- `prototype/practice/README.md` — run instructions and trust boundary;
- `tests/practice-engine.test.mjs` — deterministic engine tests;
- modular homepage CTAs linked to the practice arena;
- project status and roadmap updated.

## Rules implemented

- fixed visible seed per run;
- staggered hex-grid neighbors;
- match-three cluster detection;
- floating-bubble cascade detection;
- combo and cascade virtual scoring;
- five-miss penalty-row rule;
- shot counter and clear/run-complete states;
- replay parity for the same seed and shot choices.

## Validation gate

- JavaScript syntax checks pass;
- engine unit tests pass;
- homepage standalone remains self-contained;
- no cash or financial settlement code is present in the practice client;
- no external CDN or runtime dependency is required.

## Security and fairness boundary

The browser owns presentation only. Local scores are not trusted, published or convertible to value. The next phase must define a signed session envelope, server-side seed issuance, replay events and a PHP score verifier before any competitive virtual tournament is connected.

## Rollback

Revert the phase commit or remove `prototype/practice/`, `tests/practice-engine.test.mjs` and the modular homepage practice links. The homepage prototype remains independently usable.
