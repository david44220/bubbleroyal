# Bubble Royale

Bubble Royale is a premium skill-based Bubble Shooter tournament platform with a mobile-first PWA experience, virtual rewards and a future geo-gated cash tournament mode.

## Current status

The repository currently contains the validated homepage, a playable virtual practice arena, deterministic replay tooling and a pure PHP server-validation boundary under [`prototype/`](prototype/) and [`public/`](public/).

The homepage is available in two forms:

- `index.html`: fully self-contained standalone preview. CSS, JavaScript and PNG assets are embedded so it works when opened directly from Android downloads.
- `index-modular.html`: maintainable source version referencing `styles.css`, `app.js` and `assets/`.
- `prototype/practice/index.html`: playable virtual practice mode with deterministic board rules and local score only.
- `prototype/practice/replay.js`: canonical replay recording, validation and export.
- `public/index.php`: framework-free health, signed-session and replay-verification entry point.

## Product principles

- English-first product with French support from the beginning.
- ChatGPT owns the visual direction, mockups, assets and UX source of truth.
- Pure PHP 8.5 modular-monolith backend, with addons designed for later extraction.
- MySQL 8, Redis, S3/CDN and PWA-ready delivery.
- Server-authoritative gameplay and deterministic score validation.
- Virtual economy and future cash wallet remain strictly separated.
- Cash tournaments are disabled by default and controlled by GeoFeature, age, KYC/KYB, risk and legal-provider readiness.
- No financial amount is stored as `FLOAT` or `DOUBLE`.

## Roadmap

The full staged roadmap is documented in [`docs/DEVELOPMENT_ROADMAP.md`](docs/DEVELOPMENT_ROADMAP.md). Phases 06 and 07 establish the deterministic replay and server-validation boundary; the next implementation target is virtual challenges, XP, tickets and achievements.
