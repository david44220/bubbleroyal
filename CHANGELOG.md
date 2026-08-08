# Changelog

## 0.7.0 — Production virtual-platform hardening

- Added durable, idempotent replay response receipts for verified practice and tournament requests.
- Added password-hash rehash-on-login persistence and migration locking for concurrent deploys.
- Made production readiness fail closed when the shared Redis limiter is missing.
- Redacted internal player identifiers from public tournament and leaderboard responses.
- Hardened Apache/container defaults and added Docker/compose checks to CI.
- Added the production runbook covering secrets, least privilege, backups, monitoring, rollback and the virtual-only boundary.

## 0.6.0 — Risk controls, operations, PWA and staged alpha foundations

- Added anti-cheat signals, risk cases, admin audit logs and immutable kill switches.
- Added sandbox-only payment provider contracts, signed webhooks and reconciliation checks.
- Added notifications, support/dispute workflow and operational reporting foundations.
- Added installable homepage/practice PWA shells with virtual-only offline fallback.
- Added repository security/fairness/load audits and a staging-only allowlisted virtual alpha blueprint.
- Added a cash-pilot readiness checklist that explicitly cannot activate cash mode.
- Cash mode, withdrawals, conversion and real-value settlement remain disabled.

## 0.5.0 — Virtual wallet, ledger sandbox and compliance gates

- Added ledger-backed virtual tickets and prize units with idempotent events.
- Added append-only virtual ledger transfers and sandbox prize-pool settlement.
- Added configurable GeoFeature, age, KYC/KYB, risk and responsible-play policy evaluation.
- Added MySQL 8 migration for virtual economy and privacy-minimized compliance data.
- Cash mode and real-value settlement remain disabled.

## 0.4.0 — Virtual competition foundations

- Added virtual challenges, XP, tickets and achievements with idempotent verified-result grants.
- Added free virtual tournament definitions, schedules, lobbies and one-entry-per-player protection.
- Added replay review decisions, deterministic leaderboard tie-breaking and immutable virtual result publication.
- Added PHP coverage for phases 08–10 and explicit addon manifests.
- Cash mode, wallets, payments and settlement remain disabled.
