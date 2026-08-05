import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import vm from 'node:vm';

const engineSource = readFileSync(new URL('../prototype/practice/engine.js', import.meta.url), 'utf8');
const sandbox = { console };
vm.createContext(sandbox);
vm.runInContext(engineSource, sandbox, { filename: 'prototype/practice/engine.js' });
const Engine = sandbox.BubbleRoyalePractice;

function simulate(seed) {
  let game = Engine.createGame({ seed });
  for (let shot = 0; shot < 38 && game.status === 'playing'; shot += 1) {
    const placement = Engine.findNearestEmpty(game.board, shot % game.rows, (shot * 7) % game.cols);
    if (!placement) break;
    game = Engine.applyShot(game, placement.row, placement.col).game;
  }
  return game;
}

test('virtual practice engine remains bounded across a deterministic load sample', () => {
  const firstBatch = Array.from({ length: 250 }, (_, index) => simulate(1000 + index));
  const secondBatch = Array.from({ length: 250 }, (_, index) => simulate(1000 + index));

  firstBatch.forEach((game, index) => {
    assert.equal(game.mode, 'practice');
    assert.equal(game.valueType, 'virtual');
    assert.ok(Number.isInteger(game.score));
    assert.ok(game.score >= 0);
    assert.ok(game.shotsLeft >= 0 && game.shotsLeft <= 38);
    assert.ok(Engine.boardCount(game.board) <= game.rows * game.cols);
    assert.equal(Engine.boardSignature(game.board), Engine.boardSignature(secondBatch[index].board));
    assert.equal(game.score, secondBatch[index].score);
  });
});
