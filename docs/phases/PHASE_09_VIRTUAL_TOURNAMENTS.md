# Phase 09 — Virtual tournaments, lobbies, entries and schedules

## Delivered

- `VirtualTournamentCatalog` defines the `Daily Precision` and `Weekly Cascade` virtual competitions.
- `VirtualTournamentService` creates UTC schedule windows, reports `scheduled`, `open` and `closed` states, exposes lobby capacity and prevents duplicate player entries.
- Entries require a server-verified practice result and carry `entry_type=free_virtual`, `ticket_cost=0`, `value_type=virtual` and `cash_mode=false`.
- Each player gets one entry per tournament in the phase adapter.
- Tournament manifests document permissions, policy hooks and the persistence boundary.

## Rules

- There is no entry fee, deposit, payment provider, wallet mutation or prize settlement.
- A score is never accepted from the client as an authority; the entry stores the verified session ID and server-derived metrics.
- Lobby capacity and schedule checks happen before an entry is created.
- Duplicate entry attempts are idempotent and return the existing entry.

## Security and operational boundary

- `VirtualTournamentService` is intentionally an in-memory phase adapter. It is not presented as production persistence across PHP workers.
- A production adapter must use a unique `(tournament_id, player_id)` constraint and an atomic capacity check.
- Authentication, rate limits, abuse controls and durable audit storage remain integration work before a public alpha.

## Design verification

The catalog vocabulary maps to the existing tournament cards, trophy and arena direction in the homepage/practice prototypes. No additional image generation is required.

## Rollback

Remove `addons/Tournaments/` and its manifest. Practice sessions and server replay verification remain independent.
