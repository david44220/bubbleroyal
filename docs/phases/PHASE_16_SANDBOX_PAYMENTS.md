# Phase 16 — Payment provider abstraction, webhooks and reconciliation sandbox

## Delivered

- Provider interface with a deterministic in-memory sandbox implementation.
- Integer minor-unit payment intents with idempotent creation, capture and refund transitions.
- HMAC-signed webhook payload verification with canonical payload matching.
- Provider/local reconciliation report.
- MySQL preparation for sandbox intents and webhook events.

## Rules

- Only `local`, `sandbox` and `staging` environments are accepted.
- No provider credentials, live API calls, withdrawal, cash wallet bridge or real settlement exists.
- Sandbox minor units are test data only and are never written to the virtual ledger.
- Webhook event IDs and intent idempotency keys are unique.

## Rollback

Disable the sandbox provider adapter and retain intent/webhook records for reconciliation. Do not activate a live provider through configuration alone; it requires a separate compliance release.
