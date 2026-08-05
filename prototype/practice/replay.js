(function attachBubbleRoyaleReplay(root) {
  'use strict';

  const Engine = root.BubbleRoyalePractice;
  if (!Engine) return;

  const PROTOCOL_VERSION = 'br-replay-v1';

  function clone(value) {
    if (Array.isArray(value)) return value.map(clone);
    if (value && typeof value === 'object') {
      return Object.fromEntries(Object.entries(value).map(([key, item]) => [key, clone(item)]));
    }
    return value;
  }

  function canonicalize(value) {
    if (Array.isArray(value)) return `[${value.map(canonicalize).join(',')}]`;
    if (value && typeof value === 'object') {
      return `{${Object.keys(value).sort().map((key) => `${JSON.stringify(key)}:${canonicalize(value[key])}`).join(',')}}`;
    }
    return JSON.stringify(value);
  }

  function createReplay(game) {
    return {
      protocol_version: PROTOCOL_VERSION,
      rules_version: Engine.RULES_VERSION,
      mode: 'practice',
      value_type: 'virtual',
      seed: game.seed,
      rows: game.rows,
      cols: game.cols,
      initial_board_signature: Engine.boardSignature(game.board),
      shots: [],
    };
  }

  function createShotEvent(beforeGame, afterGame, placement, result) {
    return {
      index: beforeGame.shotIndex,
      row: placement.row,
      col: placement.col,
      color: beforeGame.currentColor,
      outcome: result.outcome,
      score_delta: result.scoreDelta,
      score_after: afterGame.score,
      combo_after: afterGame.combo,
      misses_after: afterGame.misses,
      penalty: Boolean(result.penalty),
      penalty_rows_after: afterGame.penaltyRows,
      shots_left_after: afterGame.shotsLeft,
      board_count_after: Engine.boardCount(afterGame.board),
      board_signature_after: Engine.boardSignature(afterGame.board),
    };
  }

  function appendShot(replay, beforeGame, afterGame, placement, result) {
    const nextReplay = clone(replay);
    nextReplay.shots.push(createShotEvent(beforeGame, afterGame, placement, result));
    return nextReplay;
  }

  function finalize(replay, game) {
    const nextReplay = clone(replay);
    nextReplay.final = {
      score: game.score,
      status: game.status,
      combo: game.combo,
      best_combo: game.bestCombo,
      misses: game.misses,
      penalty_rows: game.penaltyRows,
      shots_left: game.shotsLeft,
      board_count: Engine.boardCount(game.board),
      board_signature: Engine.boardSignature(game.board),
    };
    return nextReplay;
  }

  function serialize(replay) {
    return canonicalize(replay);
  }

  function validate(replay) {
    const errors = [];
    if (!replay || replay.protocol_version !== PROTOCOL_VERSION) errors.push('protocol_version');
    if (!replay || replay.rules_version !== Engine.RULES_VERSION) errors.push('rules_version');
    if (!replay || replay.mode !== 'practice' || replay.value_type !== 'virtual') errors.push('value_boundary');
    if (!replay || !Array.isArray(replay.shots)) errors.push('shots');
    if (errors.length) return { valid: false, errors };

    let game = Engine.createGame({ seed: replay.seed, rows: replay.rows, cols: replay.cols });
    if (replay.shots.length > game.shotsLeft) errors.push('shots_limit');
    if (Engine.boardSignature(game.board) !== replay.initial_board_signature) errors.push('initial_board_signature');

    replay.shots.forEach((event, index) => {
      if (event.index !== index) {
        errors.push(`shot_${index}_index`);
        return;
      }

      const before = game;
      const applied = Engine.applyShot(game, event.row, event.col);
      game = applied.game;
      const checks = {
        color: before.currentColor,
        outcome: applied.result.outcome,
        score_delta: applied.result.scoreDelta,
        score_after: game.score,
        combo_after: game.combo,
        misses_after: game.misses,
        penalty: Boolean(applied.result.penalty),
        penalty_rows_after: game.penaltyRows,
        shots_left_after: game.shotsLeft,
        board_count_after: Engine.boardCount(game.board),
        board_signature_after: Engine.boardSignature(game.board),
      };

      Object.entries(checks).forEach(([key, expected]) => {
        if (event[key] !== expected) errors.push(`shot_${index}_${key}`);
      });
    });

    if (replay.final) {
      const finalChecks = {
        score: game.score,
        status: game.status,
        combo: game.combo,
        best_combo: game.bestCombo,
        misses: game.misses,
        penalty_rows: game.penaltyRows,
        shots_left: game.shotsLeft,
        board_count: Engine.boardCount(game.board),
        board_signature: Engine.boardSignature(game.board),
      };
      Object.entries(finalChecks).forEach(([key, expected]) => {
        if (replay.final[key] !== expected) errors.push(`final_${key}`);
      });
    }

    return { valid: errors.length === 0, errors, game };
  }

  root.BubbleRoyaleReplay = Object.freeze({
    PROTOCOL_VERSION,
    appendShot,
    canonicalize,
    createReplay,
    createShotEvent,
    finalize,
    serialize,
    validate,
  });
})(typeof window !== 'undefined' ? window : globalThis);
