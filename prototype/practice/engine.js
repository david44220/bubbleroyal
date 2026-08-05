(function attachBubbleRoyalePractice(root) {
  'use strict';

  const COLORS = Object.freeze(['cyan', 'violet', 'magenta', 'gold', 'green']);
  const DEFAULT_SEED = 81031;
  const DEFAULT_ROWS = 12;
  const DEFAULT_COLS = 11;
  const DEFAULT_INITIAL_ROWS = 6;
  const DEFAULT_SHOTS = 38;
  const MISS_LIMIT = 5;
  const RULES_VERSION = 'br-practice-1';

  function normalizeSeed(seed) {
    const numericSeed = Number(seed) >>> 0;
    return numericSeed || DEFAULT_SEED;
  }

  function createRng(seed) {
    let state = normalizeSeed(seed);
    return function nextRandom() {
      state = (state + 0x6D2B79F5) | 0;
      let value = Math.imul(state ^ (state >>> 15), 1 | state);
      value ^= value + Math.imul(value ^ (value >>> 7), 61 | value);
      return ((value ^ (value >>> 14)) >>> 0) / 4294967296;
    };
  }

  function colorFor(seed, index) {
    const rng = createRng((normalizeSeed(seed) ^ Math.imul(index + 1, 0x9E3779B9)) >>> 0);
    return COLORS[Math.floor(rng() * COLORS.length)];
  }

  function createEmptyBoard(rows, cols) {
    return Array.from({ length: rows }, () => Array(cols).fill(null));
  }

  function cloneBoard(board) {
    return board.map((row) => row.map((bubble) => (bubble ? { ...bubble } : null)));
  }

  function isInside(board, row, col) {
    return row >= 0 && row < board.length && col >= 0 && col < board[0].length;
  }

  function getNeighbors(board, row, col) {
    const offsets = row % 2 === 0
      ? [[-1, -1], [-1, 0], [0, -1], [0, 1], [1, -1], [1, 0]]
      : [[-1, 0], [-1, 1], [0, -1], [0, 1], [1, 0], [1, 1]];

    return offsets
      .map(([rowOffset, colOffset]) => ({ row: row + rowOffset, col: col + colOffset }))
      .filter(({ row: neighborRow, col: neighborCol }) => isInside(board, neighborRow, neighborCol));
  }

  function bubbleId(row, col, seed, index) {
    return `${normalizeSeed(seed).toString(36)}-${index.toString(36)}-${row.toString(36)}${col.toString(36)}`;
  }

  function createInitialBoard({
    rows = DEFAULT_ROWS,
    cols = DEFAULT_COLS,
    initialRows = DEFAULT_INITIAL_ROWS,
    seed = DEFAULT_SEED,
  } = {}) {
    const board = createEmptyBoard(rows, cols);
    const rng = createRng(seed);
    let index = 0;

    for (let row = 0; row < Math.min(initialRows, rows); row += 1) {
      for (let col = 0; col < cols; col += 1) {
        const shouldLeaveGap = row >= 4 && rng() < 0.08;
        if (shouldLeaveGap) continue;

        const previous = board[row][col - 1];
        const previousPrevious = board[row][col - 2];
        let color = COLORS[Math.floor(rng() * COLORS.length)];

        // Keep the opening board varied without making it impossible to find a match.
        if (previous && previousPrevious && previous.color === previousPrevious.color && color === previous.color) {
          color = COLORS[(COLORS.indexOf(color) + 1) % COLORS.length];
        }

        board[row][col] = { color, id: bubbleId(row, col, seed, index) };
        index += 1;
      }
    }

    return board;
  }

  function collectConnected(board, startRow, startCol, predicate) {
    if (!isInside(board, startRow, startCol) || !board[startRow][startCol]) return [];

    const startBubble = board[startRow][startCol];
    const matches = [];
    const visited = new Set();
    const queue = [{ row: startRow, col: startCol }];

    while (queue.length) {
      const current = queue.shift();
      const key = `${current.row}:${current.col}`;
      if (visited.has(key)) continue;
      visited.add(key);

      const bubble = board[current.row][current.col];
      if (!bubble || !predicate(bubble, startBubble)) continue;
      matches.push({ row: current.row, col: current.col, bubble });

      getNeighbors(board, current.row, current.col).forEach((neighbor) => {
        const neighborKey = `${neighbor.row}:${neighbor.col}`;
        if (!visited.has(neighborKey)) queue.push(neighbor);
      });
    }

    return matches;
  }

  function findColorCluster(board, row, col) {
    return collectConnected(board, row, col, (bubble, startBubble) => bubble.color === startBubble.color);
  }

  function findFloatingBubbles(board) {
    const connectedToCeiling = new Set();
    const queue = [];

    board[0].forEach((bubble, col) => {
      if (bubble) queue.push({ row: 0, col });
    });

    while (queue.length) {
      const current = queue.shift();
      const key = `${current.row}:${current.col}`;
      if (connectedToCeiling.has(key)) continue;
      if (!board[current.row][current.col]) continue;
      connectedToCeiling.add(key);

      getNeighbors(board, current.row, current.col).forEach((neighbor) => {
        const neighborKey = `${neighbor.row}:${neighbor.col}`;
        if (!connectedToCeiling.has(neighborKey) && board[neighbor.row][neighbor.col]) {
          queue.push(neighbor);
        }
      });
    }

    const floating = [];
    board.forEach((row, rowIndex) => {
      row.forEach((bubble, colIndex) => {
        if (bubble && !connectedToCeiling.has(`${rowIndex}:${colIndex}`)) {
          floating.push({ row: rowIndex, col: colIndex, bubble });
        }
      });
    });
    return floating;
  }

  function findNearestEmpty(board, preferredRow, preferredCol) {
    const candidates = [];
    board.forEach((row, rowIndex) => {
      row.forEach((bubble, colIndex) => {
        if (!bubble) {
          const rowDistance = Math.abs(rowIndex - preferredRow);
          const colDistance = Math.abs(colIndex - preferredCol);
          candidates.push({
            row: rowIndex,
            col: colIndex,
            distance: rowDistance * 1.2 + colDistance,
          });
        }
      });
    });

    candidates.sort((first, second) => first.distance - second.distance || first.row - second.row || first.col - second.col);
    return candidates[0] ? { row: candidates[0].row, col: candidates[0].col } : null;
  }

  function addPenaltyRow(board, { seed = DEFAULT_SEED, shotIndex = 0 } = {}) {
    const rows = board.length;
    const cols = board[0].length;
    const nextBoard = createEmptyBoard(rows, cols);

    for (let row = rows - 1; row > 0; row -= 1) {
      nextBoard[row] = board[row - 1].map((bubble) => (bubble ? { ...bubble } : null));
    }

    for (let col = 0; col < cols; col += 1) {
      const color = colorFor(seed, 1000 + shotIndex * cols + col);
      nextBoard[0][col] = { color, id: bubbleId(0, col, seed, 1000 + shotIndex * cols + col) };
    }

    return nextBoard;
  }

  function boardCount(board) {
    return board.reduce((total, row) => total + row.filter(Boolean).length, 0);
  }

  function boardSignature(board) {
    return board.map((row) => row.map((bubble) => (bubble ? bubble.color[0] : '.')).join('')).join('/');
  }

  function resolveShot(board, row, col, color, { combo = 0 } = {}) {
    const nextBoard = cloneBoard(board);
    const placement = nextBoard[row] && !nextBoard[row][col]
      ? { row, col }
      : findNearestEmpty(nextBoard, row, col);

    if (!placement) {
      return {
        board: nextBoard,
        placement: null,
        matched: [],
        floating: [],
        scoreDelta: 0,
        combo: 0,
        outcome: 'blocked',
        signature: boardSignature(nextBoard),
      };
    }

    nextBoard[placement.row][placement.col] = {
      color,
      id: `shot-${placement.row}-${placement.col}-${boardCount(nextBoard)}`,
    };

    const cluster = findColorCluster(nextBoard, placement.row, placement.col);
    if (cluster.length < 3) {
      return {
        board: nextBoard,
        placement,
        matched: [],
        floating: [],
        scoreDelta: 0,
        combo: 0,
        outcome: 'miss',
        signature: boardSignature(nextBoard),
      };
    }

    cluster.forEach(({ row: matchRow, col: matchCol }) => {
      nextBoard[matchRow][matchCol] = null;
    });

    const floating = findFloatingBubbles(nextBoard);
    floating.forEach(({ row: floatingRow, col: floatingCol }) => {
      nextBoard[floatingRow][floatingCol] = null;
    });

    const nextCombo = combo + 1;
    const scoreDelta = (cluster.length * 100) + (floating.length * 150) + (nextCombo > 1 ? nextCombo * 75 : 0);
    return {
      board: nextBoard,
      placement,
      matched: cluster,
      floating,
      scoreDelta,
      combo: nextCombo,
      outcome: floating.length ? 'drop' : 'match',
      signature: boardSignature(nextBoard),
    };
  }

  function createGame({ seed = DEFAULT_SEED, rows = DEFAULT_ROWS, cols = DEFAULT_COLS } = {}) {
    const normalizedSeed = normalizeSeed(seed);
    return {
      mode: 'practice',
      valueType: 'virtual',
      seed: normalizedSeed,
      rows,
      cols,
      board: createInitialBoard({ rows, cols, seed: normalizedSeed }),
      currentColor: colorFor(normalizedSeed, 0),
      nextColor: colorFor(normalizedSeed, 1),
      shotsLeft: DEFAULT_SHOTS,
      score: 0,
      combo: 0,
      bestCombo: 0,
      misses: 0,
      shotIndex: 0,
      status: 'playing',
      penaltyRows: 0,
    };
  }

  function applyShot(game, row, col) {
    if (!game || game.status !== 'playing') {
      return { game, result: { outcome: 'inactive', scoreDelta: 0, matched: [], floating: [] } };
    }

    const result = resolveShot(game.board, row, col, game.currentColor, { combo: game.combo });
    let nextGame = {
      ...game,
      board: result.board,
      currentColor: game.nextColor,
      nextColor: colorFor(game.seed, game.shotIndex + 2),
      shotsLeft: Math.max(0, game.shotsLeft - 1),
      score: game.score + result.scoreDelta,
      combo: result.combo,
      bestCombo: Math.max(game.bestCombo, result.combo),
      misses: result.outcome === 'miss' ? game.misses + 1 : 0,
      shotIndex: game.shotIndex + 1,
    };

    if (result.outcome === 'miss' && nextGame.misses >= MISS_LIMIT) {
      nextGame = {
        ...nextGame,
        board: addPenaltyRow(nextGame.board, { seed: nextGame.seed, shotIndex: nextGame.shotIndex }),
        misses: 0,
        penaltyRows: nextGame.penaltyRows + 1,
      };
      result.penalty = true;
    }

    if (boardCount(nextGame.board) === 0) {
      nextGame.status = 'cleared';
    } else if (nextGame.shotsLeft === 0) {
      nextGame.status = 'out';
    }

    return { game: nextGame, result };
  }

  const api = Object.freeze({
    COLORS,
    DEFAULT_SEED,
    DEFAULT_ROWS,
    DEFAULT_COLS,
    MISS_LIMIT,
    RULES_VERSION,
    addPenaltyRow,
    applyShot,
    boardCount,
    boardSignature,
    cloneBoard,
    createEmptyBoard,
    createGame,
    createInitialBoard,
    createRng,
    findColorCluster,
    findFloatingBubbles,
    findNearestEmpty,
    getNeighbors,
    isInside,
    resolveShot,
  });

  root.BubbleRoyalePractice = api;
})(typeof window !== 'undefined' ? window : globalThis);
