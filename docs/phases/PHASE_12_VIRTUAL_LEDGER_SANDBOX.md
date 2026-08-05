# Phase 12 — Append-only ledger and sandbox prize-pool settlement

## Delivered

- `VirtualLedgerService` supports append-only virtual issue and double-entry transfers.
- Event IDs make issue and transfer operations idempotent and protect against duplicate credits.
- `SandboxPrizePoolService` seeds and settles virtual units only after an immutable published virtual result.
- Manual/rejected replay filtering is inherited from the phase 10 publication contract.
- A MySQL migration prepares ledger, pool and compliance decision tables.

## Rules

- Ledger entries are immutable domain records with positive integer units, direction, reference and policy version.
- A transfer creates one debit and one credit with the same event ID.
- A sandbox pool cannot distribute more units than its ledger-derived balance.
- Repeating a settlement returns the existing transfer pairs; it never creates a second allocation.
- `cash_mode=false` and `value_type=virtual` are required at every settlement boundary.

## Security and operational boundary

- The migration deliberately has no mutable balance column. Production database roles must deny `UPDATE` and `DELETE` on append-only tables.
- The current in-memory store is a deterministic test adapter; production wiring must use a transaction and a unique event constraint.
- Sandbox units have no promised monetary value and cannot be withdrawn or converted.

## Design verification

The settlement output is structured for future tournament-result surfaces and does not alter the approved homepage/practice design source.

## Rollback

Disable sandbox settlement commands and keep the append-only records. Do not reverse history by mutation; a future correction must be a compensating virtual entry with a new event ID.
