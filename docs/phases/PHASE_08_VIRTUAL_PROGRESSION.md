# Phase 08 — Virtual challenges, XP, tickets and achievements

## Delivered

- `addons/Rewards/Domain/ChallengeCatalog.php` defines four virtual-only challenges and three achievements.
- `ProgressionService` applies rewards only to a `valid=true`, `mode=practice`, `value_type=virtual` server result.
- XP and tickets are granted once per caller-supplied event ID.
- The progression state exposes no wallet, cash, payout or monetary amount.
- `ProgressionStore` and `InMemoryProgressionStore` make the future MySQL/Redis persistence boundary explicit.
- The server replay result now exposes `best_combo`, `shots_verified`, board status and other non-financial metrics required by progression.

## Rules

- XP thresholds are deterministic: level 1 starts at 0 XP; the next thresholds are 250, 750, 1,500, then continue by adding `level × 250`.
- Challenge completion is monotonic and rewards are issued on the first completion only.
- The event ID is the idempotency key. A duplicate verified replay event returns zero deltas.
- Tickets are virtual access units only. They do not represent cash and are not debited by this phase.

## Security and fairness checks

- Client-submitted scores cannot grant progression directly.
- Invalid, non-practice or non-virtual results are rejected before state changes.
- The future durable adapter must persist the event ID in the same transaction as progression changes.
- No migration is included because this phase deliberately uses an in-memory adapter; the durable schema belongs with the authenticated account/persistence integration.

## Design verification

The phase consumes the existing practice result contract and follows the visual source of truth in `prototype/homepage/` and `prototype/practice/`. No new raster asset is introduced.

## Rollback

Remove the `addons/Rewards/` directory and revert the replay-result metric additions. Existing practice replay verification remains valid because the added response fields are additive.
