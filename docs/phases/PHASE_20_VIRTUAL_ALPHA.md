# Phase 20 — Allowlisted virtual alpha on staging

## Delivered

- A small alpha access policy restricted to local, sandbox and staging environments.
- Optional player allowlisting, explicit risk acceptance and virtual-only value type checks.
- A migration for revocable alpha grants with database constraints that keep cash mode off.
- A staging compose blueprint with PHP, MySQL and Redis service placeholders and no production secrets.

## Rules

- Production is not an eligible alpha environment.
- Alpha access never grants cash value, withdrawal rights or a payment entitlement.
- The deployment blueprint is infrastructure preparation; it does not claim that external services are configured or production-ready.
- The existing public client remains a virtual practice client until server-side alpha wiring is intentionally enabled.

## Rollback

Revoke alpha grants, disable the allowlist feature and stop the staging stack. Virtual ledger records remain append-only; do not delete them to roll back access.
