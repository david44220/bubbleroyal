import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

const read = (path) => readFileSync(new URL(`../${path}`, import.meta.url), 'utf8');

test('phase 11-13 migration keeps virtual economy integer-only and append-oriented', () => {
  const migration = read('database/migrations/0011_virtual_economy_and_compliance.sql');

  assert.match(migration, /br_virtual_ledger_entries/);
  assert.match(migration, /BIGINT UNSIGNED NOT NULL/);
  assert.match(migration, /chk_br_virtual_ledger_units_positive/);
  assert.match(migration, /br_compliance_decisions/);
  assert.doesNotMatch(migration, /\b(FLOAT|DOUBLE)\b/i);
});

test('production identity and session migrations preserve server-side boundaries', () => {
  const tracking = read('database/migrations/0001_schema_tracking.sql');
  const identity = read('database/migrations/0022_identity_and_game_sessions.sql');
  const integrity = read('database/migrations/0023_runtime_integrity.sql');
  assert.match(tracking, /br_schema_migrations/);
  assert.match(identity, /br_users/);
  assert.match(identity, /br_auth_sessions/);
  assert.match(identity, /br_game_sessions/);
  assert.match(identity, /UNIQUE KEY uq_br_game_session_token/);
  assert.match(integrity, /br_runtime_event_receipts/);
  assert.doesNotMatch(identity, /FLOAT|DOUBLE/i);
});

test('phase 11-13 addon manifests expose virtual-only safety hooks', () => {
  for (const path of ['addons/Wallet/addon.json', 'addons/Ledger/addon.json', 'addons/GeoFeature/addon.json']) {
    const manifest = JSON.parse(read(path));
    assert.ok(manifest.id);
    assert.ok(Array.isArray(manifest.permissions));
    assert.ok(Array.isArray(manifest.policy_hooks));
    assert.ok(manifest.persistence_boundary);
  }

  const ledger = JSON.parse(read('addons/Ledger/addon.json'));
  assert.ok(ledger.policy_hooks.includes('no_cash_settlement'));
});

test('runtime migration persists progression, tournaments and immutable periods', () => {
  const runtime = read('database/migrations/0024_virtual_runtime.sql');
  assert.match(runtime, /br_virtual_progression_states/);
  assert.match(runtime, /br_virtual_progression_events/);
  assert.match(runtime, /br_virtual_tournament_entries/);
  assert.match(runtime, /UNIQUE KEY uq_br_virtual_entry_player/);
  assert.match(runtime, /UNIQUE KEY uq_br_virtual_entry_session/);
  assert.match(runtime, /chk_br_virtual_tournament_cash CHECK \(cash_mode = 0\)/);
  assert.match(runtime, /chk_br_virtual_entry_cost CHECK \(ticket_cost = 0\)/);
  assert.doesNotMatch(runtime, /FLOAT|DOUBLE/i);
});
