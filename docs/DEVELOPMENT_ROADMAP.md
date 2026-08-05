# Bubble Royale Development Roadmap

## 1. Product scope

Bubble Royale is a skill-based Bubble Shooter platform. The initial product focuses on a polished game loop, fair scoring, tournaments and virtual rewards. Cash tournaments are a later, geo-gated capability and remain disabled by default until the relevant legal, age, KYC/KYB, payment-provider, risk and anti-fraud conditions are satisfied.

## 2. Target architecture

```text
public/                 HTTP entry point and PWA shell
app/Core/               shared kernel, configuration and contracts
addons/Identity/        authentication and account security
addons/Game/            Bubble Shooter rules and game definitions
addons/GameSessions/    signed sessions, replays and score events
addons/Tournaments/     lobbies, entries, scheduling and settlement
addons/Leaderboards/    ranking and result publication
addons/Wallet/          virtual wallet and future cash-wallet boundary
addons/Ledger/          append-only financial journal
addons/Payments/        provider adapters and reconciliation
addons/Compliance/      age, KYC/KYB, responsible-play and case workflow
addons/GeoFeature/      country, region, provider and feature gates
addons/AntiFraud/       anti-cheat, risk scoring and review cases
addons/Rewards/         missions, streaks, tickets and achievements
addons/Revenue/         confirmed revenue and prize-pool contributions
addons/Notifications/   email, push and in-app notifications
addons/Support/         tickets, disputes and player communication
addons/Admin/            command center and kill switches
addons/Analytics/       product, game and financial reporting
database/migrations/    versioned MySQL schema changes
tests/                  unit, integration, API, security and game replay tests
prototype/              validated design and frontend prototypes
```

Recommended runtime stack: PHP 8.5, MySQL 8, Redis, S3/CDN, PWA service worker and a TypeScript/Canvas game client. The frontend client is never trusted for score or financial decisions.

## 3. Release stages

### V0 — Design and prototype

- homepage and visual source of truth;
- isolated assets;
- responsive standalone preview;
- validated UX direction.

### V1 — Virtual Skill Arena

- account and onboarding;
- practice mode (implemented in `prototype/practice/`);
- playable Bubble Shooter client;
- deterministic scoring;
- virtual tournaments;
- leaderboards;
- virtual rewards;
- admin configuration;
- English/French i18n;
- PWA shell.

### V1.5 — Sandbox economy

- virtual wallet;
- test ledger;
- simulated deposits and withdrawals;
- prize-pool calculations;
- GeoFeature and KYC sandbox;
- anti-cheat and anti-fraud review;
- staging VPS with no real value.

### V2 — Controlled cash pilot

- approved payment providers;
- geo-gated cash tournaments;
- KYC and withdrawal controls;
- pending settlement and manual review;
- reconciled prize pools;
- risk limits and responsible-play tools;
- support and dispute workflows.

### V3 — Growth ecosystem

- seasons and clans;
- sponsored tournaments;
- referrals;
- creator events;
- team competitions;
- push notifications;
- developer API;
- white-label tournament tools;
- additional skill-based games.

## 4. Ordered implementation phases

1. Product rules, game fairness rules and cash-mode boundaries.
2. Repository conventions, CI, environment templates and PHP bootstrap.
3. Design tokens, i18n, responsive shell and authenticated layout patterns.
4. Authentication, sessions, profile and security center.
5. Local Bubble Shooter game engine and practice screen (initial client implemented).
6. Deterministic board seeds, replay format and score model (implemented with `br-replay-v1`).
7. Signed game sessions and server-side score verification (implemented for virtual practice only).
8. Practice challenges, XP, tickets and achievements (virtual domain implemented; durable account storage pending).
9. Tournament definitions, lobbies, entries and schedules (free virtual domain implemented; durable persistence pending).
10. Leaderboards, tie-breaking, result publication and replay review (virtual domain implemented; operator workflow pending).
11. Virtual rewards and virtual wallet.
12. Append-only ledger and sandbox prize-pool settlement.
13. GeoFeature, age, KYC/KYB and responsible-play rule engine.
14. Anti-cheat, anti-fraud, risk cases and manual review tools.
15. Admin Command Center, audit logs and kill switches.
16. Payment provider abstraction, webhooks and reconciliation in sandbox.
17. Notifications, support, disputes and operational reporting.
18. PWA performance, caching, offline practice and device compatibility.
19. Full security, fairness, load and regression audit.
20. Closed virtual alpha on staging VPS.
21. Controlled cash pilot only after external compliance validation.

## 5. Phase acceptance rule

Every phase must include its migration changes, automated tests, security checklist, UX/design verification, documentation, changelog, rollback notes and a short phase report. No phase is considered complete if it contains placeholder business logic, an unverified money flow or a hidden design deviation.

## Virtual competition checkpoint

Phases 08–10 are now implemented as framework-free, virtual-only domain services with explicit in-memory adapters and PHP tests. They are a validated foundation for the next authenticated/persistent integration; they do not claim production durability, cash eligibility or operator review readiness.
