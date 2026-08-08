import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import vm from 'node:vm';

const engineSource = readFileSync(new URL('../prototype/practice/engine.js', import.meta.url), 'utf8');
const replaySource = readFileSync(new URL('../prototype/practice/replay.js', import.meta.url), 'utf8');
const sandbox = { console };
vm.createContext(sandbox);
vm.runInContext(engineSource, sandbox, { filename: 'prototype/practice/engine.js' });
vm.runInContext(replaySource, sandbox, { filename: 'prototype/practice/replay.js' });

const Engine = sandbox.BubbleRoyalePractice;
const Replay = sandbox.BubbleRoyaleReplay;

test('replay protocol records a canonical virtual-only session', () => {
  const first = Engine.createGame({ seed: 424242 });
  const second = Engine.createGame({ seed: 424242 });
  const replay = Replay.createReplay(first);
  const placement = Engine.findNearestEmpty(first.board, 0, 0);
  const applied = Engine.applyShot(first, placement.row, placement.col);
  const recorded = Replay.appendShot(replay, first, applied.game, placement, applied.result);
  const document = Replay.finalize(recorded, applied.game);

  assert.equal(document.protocol_version, 'br-replay-v1');
  assert.equal(document.rules_version, Engine.RULES_VERSION);
  assert.equal(document.mode, 'practice');
  assert.equal(document.value_type, 'virtual');
  assert.equal(document.shots.length, 1);
  assert.match(Replay.serialize(document), /"protocol_version":"br-replay-v1"/);
  assert.equal(Engine.boardSignature(second.board), document.initial_board_signature);
});

test('same seed and shot sequence validates; tampering fails deterministically', () => {
  let game = Engine.createGame({ seed: 987654 });
  let replay = Replay.createReplay(game);

  for (let index = 0; index < 6; index += 1) {
    const placement = Engine.findNearestEmpty(game.board, index % game.rows, (index * 3) % game.cols);
    if (!placement) break;
    const before = game;
    const applied = Engine.applyShot(game, placement.row, placement.col);
    game = applied.game;
    replay = Replay.appendShot(replay, before, game, placement, applied.result);
  }

  const valid = Replay.validate(Replay.finalize(replay, game));
  assert.equal(valid.valid, true);

  const tampered = Replay.finalize(replay, game);
  tampered.shots[0].score_after += 1;
  const invalid = Replay.validate(tampered);
  assert.equal(invalid.valid, false);
  assert.ok(invalid.errors.includes('shot_0_score_after'));
});
