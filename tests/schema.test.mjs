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
