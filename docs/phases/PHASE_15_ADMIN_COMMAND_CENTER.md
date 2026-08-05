# Phase 15 — Admin Command Center, audit logs and kill switches

## Delivered

- Role-gated command-center primitives for audit access and kill-switch updates.
- Append-only, idempotent audit events with sensitive metadata redaction.
- Virtual gameplay, progression, tournament, wallet and sandbox-payment switches.
- Cash mode is immutable-off from this command center.
- MySQL preparation for audit events and switch state.

## Rules

- Every switch change requires an authorized role, a reason and an event ID.
- Admin, risk-operator and compliance-operator permissions are separated.
- Secrets, tokens, passwords and document-like metadata are redacted before audit storage.
- A kill switch can stop a virtual feature, but cannot activate cash mode.

## Rollback

Disable command-center writes and preserve the audit trail. Restore a feature through a new audited action, never by editing history.
