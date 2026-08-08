# Phase 06 — Deterministic Replay Protocol and Score Model

## Status

Implemented on top of the virtual practice arena.

## Scope

- deterministic board seed and rules version pinning;
- canonical replay document format;
- client-side replay recording and export;
- deterministic replay re-execution and tamper detection;
- virtual-only score model with no financial value.

## Protocol

The browser exports a `br-replay-v1` document containing:

- `rules_version`, `mode` and `value_type` boundary fields;
- server-issued or test seed, board dimensions and initial board signature;
- ordered shot events with the requested cell, colour, outcome, score delta, score-after, combo, misses, penalty state, remaining shots and board signature;
- a final state snapshot.

The event fields are derived from the engine state. They are useful for transport and diagnostics, but they are never trusted as proof until the server replays the same seed and shot sequence.

## Delivered

- `prototype/practice/replay.js` — canonical replay creation, serialization and local validation;
- practice arena replay recording and `Export replay` action;
- `tests/replay-protocol.test.mjs` — deterministic parity and tamper tests;
- `BubblePracticeEngine::RULES_VERSION` shared as the compatibility boundary for the PHP verifier;
- fixed deterministic seed vector documented in the PHP protocol tests.

## Acceptance gate

- same seed and shot choices produce the same board signatures and scores;
- modifying a replay score event makes local validation fail;
- replay JSON is virtual-only and contains no wallet, payment or cash settlement fields;
- the next phase consumes this format through signed server sessions.

## Rollback

Revert the phase commit or remove `prototype/practice/replay.js`, `tests/replay-protocol.test.mjs` and the replay export wiring. The local practice game remains playable without exported replays.
