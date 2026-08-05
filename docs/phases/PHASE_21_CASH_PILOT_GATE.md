# Phase 21 — Cash pilot readiness gate

## Delivered

- A complete control checklist covering legal approval, geo rules, age verification, KYC/KYB, risk, responsible play, payments, reconciliation, support, audit, kill switches and security review.
- A readiness decision that can report whether the project is ready for external review in a dedicated pilot environment.
- Review metadata migration with evidence references rather than raw identity or payment documents.

## Explicit boundary

This phase does not activate cash mode. Even when every control is attested and external approval is recorded, `activation_allowed` remains `false`, `cash_mode` remains `false`, and `value_type` remains `virtual`. A real-money launch would require a separately reviewed implementation, jurisdiction-specific legal sign-off, verified providers, security testing and operational approval.

## Rollback

Mark attestations pending or rejected and keep the runtime gate disabled. Never convert existing virtual units into cash units and never treat a readiness record as a payment or withdrawal authorization.
