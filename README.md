# Bubble Royale

Bubble Royale is a premium skill-based Bubble Shooter tournament platform with a mobile-first PWA experience, virtual rewards and a future geo-gated cash tournament mode.

## Current status

The repository currently contains the validated homepage, a playable virtual practice arena, deterministic replay tooling, server validation, virtual competition foundations, operational controls, a PWA shell and a staging-only virtual alpha blueprint under [`prototype/`](prototype/), [`addons/`](addons/), [`deploy/`](deploy/) and [`public/`](public/).

The homepage is available in two forms:

- `index.html`: fully self-contained standalone preview. CSS, JavaScript and PNG assets are embedded so it works when opened directly from Android downloads.
- `index-modular.html`: maintainable source version referencing `styles.css`, `app.js` and `assets/`.
- `prototype/practice/index.html`: playable virtual practice mode with deterministic board rules and local score only.
- `prototype/practice/replay.js`: canonical replay recording, validation and export.
- `public/index.php`: framework-free health, signed-session and replay-verification entry point.
- `addons/Rewards/`: verified virtual challenges, XP, tickets and achievements.
- `addons/Tournaments/`: free virtual tournament schedules, lobbies and entries.
- `addons/Leaderboards/`: replay review, deterministic ranking and virtual publication.
- `addons/Wallet/`: ledger-backed virtual tickets and prize units.
- `addons/Ledger/`: append-only virtual ledger and sandbox settlement.
- `addons/GeoFeature/`: fail-closed feature, age, KYC/KYB, risk and responsible-play policy gates.
- `addons/AntiFraud/`, `addons/Admin/`, `addons/Payments/`, `addons/Notifications/`, `addons/Support/` and `addons/Analytics/`: risk cases, operations, sandbox payments, support and reporting foundations.
- `addons/PWA/` and `addons/Alpha/`: installable shells and allowlisted virtual staging alpha policy.
- `addons/Compliance/`: cash-pilot readiness checklist that cannot activate runtime cash mode.
- `database/migrations/`: MySQL 8 schema preparation for the virtual economy, operations, alpha access and compliance attestations.

## Product principles

- English-first product with French support from the beginning.
- ChatGPT owns the visual direction, mockups, assets and UX source of truth.
- Pure PHP 8.5 modular-monolith backend, with addons designed for later extraction.
- MySQL 8, Redis, S3/CDN and PWA-ready delivery.
- Server-authoritative gameplay and deterministic score validation.
- Virtual economy and future cash wallet remain strictly separated.
- Cash tournaments are disabled by default and remain disabled in the current runtime; the last phase only records external-readiness requirements.
- No financial amount is stored as `FLOAT` or `DOUBLE`.

## Roadmap

The full staged roadmap is documented in [`docs/DEVELOPMENT_ROADMAP.md`](docs/DEVELOPMENT_ROADMAP.md). Phases 14–21 now add risk review, operations, PWA, staging alpha and a cash-readiness gate without enabling real-money play. The remaining work is external validation and a separately approved production integration.
