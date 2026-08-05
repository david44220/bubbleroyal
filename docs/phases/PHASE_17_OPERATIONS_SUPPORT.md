# Phase 17 — Notifications, support, disputes and operational reporting

## Delivered

- Idempotent notification outbox for in-app, email and push channels.
- Bounded support tickets for technical, result and responsible-play requests.
- Status transitions with ticket history.
- Aggregated operations report separating virtual and cash-mode event counts.
- MySQL preparation for outbox and support persistence.

## Rules

- Notification providers are not called by the domain adapter; dispatch belongs to a later infrastructure worker.
- Payloads are scalar-safe and deduplicated before enqueue.
- Result disputes create support workflow state only; they cannot directly alter scores, ledger entries or payouts.
- Cash events remain zero while cash mode is disabled.

## Rollback

Pause outbox dispatch and support transitions while retaining tickets and notification records. Resolve disputes through new audited actions, not destructive edits.
