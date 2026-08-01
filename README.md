# Bubble Royale

Bubble Royale is a premium skill-based Bubble Shooter tournament platform with a mobile-first PWA experience, virtual rewards and a future geo-gated cash tournament mode.

## Current status

The repository currently contains the validated homepage prototype and its transparent 2D/3D visual assets under [`prototype/homepage/`](prototype/homepage/).

The homepage is available in two forms:

- `index.html`: fully self-contained standalone preview. CSS, JavaScript and PNG assets are embedded so it works when opened directly from Android downloads.
- `index-modular.html`: maintainable source version referencing `styles.css`, `app.js` and `assets/`.

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

The full staged roadmap is documented in [`docs/DEVELOPMENT_ROADMAP.md`](docs/DEVELOPMENT_ROADMAP.md). The next implementation target is the playable virtual-mode Bubble Shooter client, followed by server-validated sessions and virtual tournaments.
