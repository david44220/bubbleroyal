# Phase 14 — Anti-cheat, anti-fraud, risk cases and manual review

## Delivered

- Deterministic weighted risk assessment for replay, automation, velocity, geo and account-cluster signals.
- `AntiCheatService` consumes only server verification plus bounded telemetry; it never trusts a client score.
- High and critical signals open review cases and can block downstream progression.
- Risk cases support explicit approve, block, escalate and release decisions with review history.
- MySQL tables prepare risk-case persistence and append-only review history.

## Rules

- A verified replay remains the authority for gameplay metrics.
- Telemetry can increase scrutiny but cannot authorize a score or wallet credit.
- Risk score is an integer from 0 to 1000 with deterministic severity thresholds.
- High risk requires review; critical risk is blocked until an operator decision.

## Rollback

Disable the anti-fraud inspection hook while preserving cases and review history. Corrections must be new review events, not history mutation.
