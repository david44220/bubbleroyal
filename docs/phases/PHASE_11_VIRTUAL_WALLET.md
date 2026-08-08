# Phase 11 — Virtual rewards and virtual wallet

## Delivered

- `VirtualWalletService` exposes virtual tickets and virtual prize units only.
- Wallet credits and debits are represented by ledger entries; there is no mutable balance setter.
- Verified progression grants can credit tickets through an idempotent ledger event.
- Ticket debits use a double-entry transfer to a virtual sink and reject insufficient balances.
- The wallet is protected by the phase 13 compliance feature gate.
- A MySQL migration prepares durable wallet account storage without introducing a cash column.

## Rules

- Unit values are positive integers (`BIGINT` in the migration); no `FLOAT`, `DOUBLE` or monetary amount is used.
- A client cannot choose an arbitrary reward amount. The wallet accepts the server-generated progression grant.
- Repeating the same event ID returns the original ledger result and cannot inflate the balance.
- Virtual tickets and prize units have separate unit types and cannot be mixed.

## Security and operational boundary

- The current adapter is in memory, while `VirtualLedgerStore` and the MySQL migration define the durable integration point.
- Authentication, authorization and database transaction wiring remain required before production deployment.
- No payment, withdrawal, cash wallet or external-value conversion is present.

## Design verification

The wallet data is intended for the existing premium rewards/tournament surfaces and introduces no new image asset.

## Rollback

Disable the wallet addon and stop issuing new reward events. Existing practice, replay and leaderboard verification remains independent; ledger rows must be retained for audit rather than deleted.
