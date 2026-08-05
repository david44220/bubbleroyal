# Phase 07 — Signed Sessions and Server-side Score Verification

## Status

Implemented as a pure PHP 8.5 validation boundary. It is not connected to tournaments or financial settlement.

## Security boundary

- the server issues a short-lived HMAC-SHA256 session token;
- the token pins the practice mode, virtual value type, rules version, seed and board dimensions;
- the browser sends the ordered `br-replay-v1` document to the verifier;
- PHP reconstructs the board and recomputes every outcome and score from the seed and shot coordinates;
- client-provided score fields are compared against server-derived values and rejected on any mismatch;
- expired, forged, cross-mode and cross-rules-version tokens are rejected;
- no verified score is published to a leaderboard or converted to value in this phase.

## API contract

The front controller is `public/index.php`.

### `GET /health`

Returns the service status and explicitly reports `cash_mode: false`.

### `POST /api/v1/practice/sessions`

Issues a short-lived virtual practice session. The response contains `session_token` and non-secret session claims.

### `POST /api/v1/practice/sessions/verify`

Accepts:

```json
{
  "session_token": "payload.signature",
  "replay": {
    "protocol_version": "br-replay-v1",
    "rules_version": "br-practice-1",
    "mode": "practice",
    "value_type": "virtual",
    "seed": 424242,
    "rows": 12,
    "cols": 11,
    "initial_board_signature": "...",
    "shots": [],
    "final": {}
  }
}
```

The endpoint returns a verified server score only when the complete replay is deterministic and consistent with the signed session.

## Delivered

- `app/Core/Bootstrap.php` — lightweight framework-free autoloading;
- `app/Core/Support/CanonicalJson.php` — stable JSON representation;
- `app/Core/Security/HmacSigner.php` — HMAC token signing and constant-time verification;
- `addons/Game/Domain/BubblePracticeEngine.php` — PHP rules engine matching the browser seed vector;
- `addons/GameSessions/Application/SignedPracticeSessionService.php` — session issuance and validation;
- `addons/GameSessions/Application/ReplayVerifier.php` — server-side replay and score verifier;
- `public/index.php` — health, session issue and verification endpoints;
- `tests/php/run.php` — PHP protocol, parity, tamper and expiry checks;
- `.env.example` and GitHub Actions CI for Node plus PHP 8.5.

## Explicit non-goals

- no authentication or user identity;
- no tournament entry or submission;
- no wallet, payment, withdrawal or cash mode;
- no leaderboard publication;
- no persistence of verified results yet.

## Next phase

Phase 08 can add virtual challenges, XP, tickets and achievements while keeping the server-verified session boundary intact.
