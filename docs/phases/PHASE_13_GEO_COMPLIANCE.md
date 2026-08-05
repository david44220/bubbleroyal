# Phase 13 — GeoFeature, age, KYC/KYB and responsible play gates

## Delivered

- `CompliancePolicyEngine` evaluates environment, country, age verification, KYC/KYB status and risk score.
- `ResponsiblePlayPolicy` enforces active status, cooling-off periods and daily time/session limits.
- Default policy keeps cash tournaments disabled regardless of context.
- Wallet and sandbox prize-pool services call the policy engine before changing virtual state.
- The migration prepares configurable feature rules, privacy-minimized compliance profiles and auditable decisions.

## Rules

- A missing country, age verification or risk score fails closed for gated features.
- Country allow/deny lists and thresholds are configuration, not hardcoded legal conclusions.
- This phase stores status signals only; it does not collect identity documents or perform KYC/KYB verification.
- Responsible-play restrictions block access even when the user otherwise passes age and risk checks.
- Cash eligibility is not enabled by this phase and requires later legal/provider/compliance work.

## Security and operational boundary

- Do not store birth dates, identity documents or raw KYC/KYB evidence in this domain adapter.
- Compliance decisions carry a policy version and reasons for auditability.
- The production adapter must protect profiles and decisions with least-privilege database roles and encrypted operational storage.

## Design verification

The gate is backend-only and does not introduce new assets or bypass the existing design source of truth.

## Rollback

Disable the new gated actions and preserve decision records. Reverting a policy decision must create a new versioned decision rather than editing history.
