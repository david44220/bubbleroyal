# Phase 10 — Leaderboards, tie-breaking, result publication and replay review

## Delivered

- `ReplayReviewService` supports `approved`, `rejected` and `manual_review` decisions for verified virtual entries.
- `LeaderboardService` filters out unverified, non-virtual, non-practice and non-approved entries.
- Ranking is deterministic: score descending, shots used ascending, best combo descending, board clear before non-clear, verification time ascending, then player ID and entry ID ascending.
- `ResultPublicationService` blocks publication while a verified entry is pending/manual review, excludes rejected entries and makes an immutable publication idempotent.
- Published snapshots contain virtual rows only and an empty rewards array; there is no payout or cash settlement path.

## Rules

- A client replay cannot publish itself. A server verification result and an explicit approved review are both required.
- Manual review is a hard publication gate, not a soft warning.
- The final lexical tie-break ensures two identical gameplay results still produce the same order on every worker.
- A second publication for the same tournament must use the same snapshot hash or it is rejected.

## Security and operational boundary

- Review and publication services currently use in-memory phase data passed by the caller; durable audit records and permissions are required before production use.
- No leaderboard row creates a wallet credit, cash balance, payment, payout or settlement record.
- Anti-cheat, risk cases, account authentication and operator UI are intentionally deferred to roadmap phases 13–15.

## Design verification

The result shape is compatible with the existing premium leaderboard/tournament visual direction and does not create new assets.

## Rollback

Remove `addons/Leaderboards/` and restore the pre-phase `ReplayVerifier` response shape if downstream consumers require strict response schemas. Practice replay verification itself is unaffected by the ranking layer.
