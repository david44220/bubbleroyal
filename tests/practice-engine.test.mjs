import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import vm from 'node:vm';

const engineSource = readFileSync(new URL('../prototype/practice/engine.js', import.meta.url), 'utf8');
const sandbox = { console };
vm.createContext(sandbox);
vm.runInContext(engineSource, sandbox, { filename: 'prototype/practice/engine.js' });
const Engine = sandbox.BubbleRoyalePractice;

test('practice engine exposes a virtual-only deterministic contract', () => {
  const first = Engine.createGame({ seed: 424242 });
  const second = Engine.createGame({ seed: 424242 });

  assert.equal(first.mode, 'practice');
  assert.equal(first.valueType, 'virtual');
  assert.equal(first.seed, 424242);
  assert.equal(Engine.boardSignature(first.board), Engine.boardSignature(second.board));
  assert.equal(first.currentColor, second.currentColor);
  assert.equal(first.nextColor, second.nextColor);
});

test('hex neighbors remain inside the board', () => {
  const board = Engine.createEmptyBoard(5, 5);
  const neighbors = Engine.getNeighbors(board, 0, 0);

  assert.ok(neighbors.length > 0);
  assert.ok(neighbors.every(({ row, col }) => row >= 0 && row < 5 && col >= 0 && col < 5));
});

test('three matching bubbles are removed and award virtual points', () => {
  const board = Engine.createEmptyBoard(5, 5);
  board[0][0] = { color: 'cyan', id: 'a' };
  board[0][1] = { color: 'cyan', id: 'b' };

  const result = Engine.resolveShot(board, 0, 2, 'cyan');

  assert.equal(result.outcome, 'match');
  assert.equal(result.matched.length, 3);
  assert.equal(result.floating.length, 0);
  assert.equal(result.scoreDelta, 300);
  assert.equal(Engine.boardCount(result.board), 0);
});

test('unsupported bubbles fall as a cascade after a match', () => {
  const board = Engine.createEmptyBoard(5, 5);
  board[1][1] = { color: 'violet', id: 'a' };
  board[1][2] = { color: 'violet', id: 'b' };
  board[2][2] = { color: 'violet', id: 'c' };
  board[2][3] = { color: 'gold', id: 'drop' };

  const result = Engine.resolveShot(board, 1, 0, 'violet');

  assert.equal(result.outcome, 'drop');
  assert.equal(result.matched.length, 4);
  assert.equal(result.floating.length, 1);
  assert.equal(result.scoreDelta, 550);
  assert.equal(Engine.boardCount(result.board), 0);
});

test('penalty rows preserve board dimensions and shift the old board down', () => {
  const board = Engine.createEmptyBoard(4, 4);
  board[0][0] = { color: 'green', id: 'old-top' };
  const shifted = Engine.addPenaltyRow(board, { seed: 99, shotIndex: 2 });

  assert.equal(shifted.length, 4);
  assert.ok(shifted.every((row) => row.length === 4));
  assert.equal(shifted[1][0].id, 'old-top');
  assert.ok(shifted[0].every(Boolean));
});

test('the same seed and shot choices produce the same replay state', () => {
  let first = Engine.createGame({ seed: 123456 });
  let second = Engine.createGame({ seed: 123456 });

  for (let shot = 0; shot < 8; shot += 1) {
    const firstCell = Engine.findNearestEmpty(first.board, shot % first.rows, (shot * 2) % first.cols);
    const secondCell = Engine.findNearestEmpty(second.board, shot % second.rows, (shot * 2) % second.cols);
    if (!firstCell || !secondCell) break;

    first = Engine.applyShot(first, firstCell.row, firstCell.col).game;
    second = Engine.applyShot(second, secondCell.row, secondCell.col).game;
    assert.equal(Engine.boardSignature(first.board), Engine.boardSignature(second.board));
    assert.equal(first.score, second.score);
    assert.equal(first.shotsLeft, second.shotsLeft);
    assert.equal(first.nextColor, second.nextColor);
  }
});
