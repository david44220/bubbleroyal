import { execFileSync } from 'node:child_process';
import assert from 'node:assert/strict';
import { test } from 'node:test';

test('repository security and money-boundary audit passes', () => {
  const output = execFileSync(process.execPath, ['tools/audit.mjs'], { encoding: 'utf8' });
  const report = JSON.parse(output);

  assert.equal(report.status, 'passed');
  assert.ok(report.files_scanned > 0);
  assert.deepEqual(report.findings, []);
});
