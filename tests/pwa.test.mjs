import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

const read = (path) => readFileSync(new URL(`../${path}`, import.meta.url), 'utf8');

test('homepage and practice expose installable manifests using existing assets', () => {
  const homepage = JSON.parse(read('prototype/homepage/manifest.webmanifest'));
  const practice = JSON.parse(read('prototype/practice/manifest.webmanifest'));
  assert.equal(homepage.display, 'standalone');
  assert.equal(practice.display, 'standalone');
  assert.match(homepage.icons[0].src, /bubble-royale-logo\.png$/);
  assert.match(practice.icons[0].src, /bubble-royale-logo\.png$/);
});

test('service workers cache the virtual practice shell and provide offline fallbacks', () => {
  const homeWorker = read('prototype/homepage/sw.js');
  const practiceWorker = read('prototype/practice/sw.js');
  assert.match(homeWorker, /bubble-royale-home-v1/);
  assert.match(practiceWorker, /bubble-royale-practice-v1/);
  assert.match(practiceWorker, /offline\.html/);
  assert.match(practiceWorker, /engine\.js/);
});
