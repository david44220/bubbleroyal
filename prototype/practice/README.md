# Bubble Royale — Virtual Practice Arena

This is the first playable Bubble Royale client milestone. It is a browser-only practice mode with no entry fee, wallet, payment, cash settlement or tournament submission.

## Included

- deterministic board generation from a visible session seed;
- pointer, touch and keyboard aiming;
- wall-bounce aiming guide;
- match-three cluster detection on a staggered hex board;
- floating-bubble cascade detection;
- combo scoring and virtual points;
- miss counter with deterministic penalty rows;
- local personal-best score only;
- responsive premium game shell matching the homepage direction;
- no new images: the existing Bubble Royale logo is reused.

## Run locally

Open `index.html` in a modern browser. The page only uses local HTML, CSS and JavaScript files.

## Determinism boundary

`engine.js` owns board rules, scoring and state transitions. The UI only renders the state and sends a chosen board cell to the engine. This separation is intentional: a future PHP session service can validate the same seed, shot sequence, replay events and score before any tournament economy is introduced.
