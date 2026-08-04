(() => {
  'use strict';

  const Engine = window.BubbleRoyalePractice;
  if (!Engine) return;

  const canvas = document.querySelector('#practice-canvas');
  const context = canvas.getContext('2d');
  const scoreValue = document.querySelector('#score-value');
  const shotsValue = document.querySelector('#shots-value');
  const comboValue = document.querySelector('#combo-value');
  const bestScoreValue = document.querySelector('#best-score');
  const objectiveValue = document.querySelector('#objective-value');
  const shotMeter = document.querySelector('#shot-meter');
  const nextBubble = document.querySelector('#next-bubble');
  const nextBubbleLabel = document.querySelector('#next-bubble-label');
  const sessionSeed = document.querySelector('#session-seed');
  const currentStatus = document.querySelector('#current-status');
  const statusPill = document.querySelector('#status-pill');
  const shootButton = document.querySelector('#shoot-button');
  const newRunButton = document.querySelector('#new-run');
  const toast = document.querySelector('#game-toast');
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const bestScoreKey = 'bubble-royale.practice.best-score';
  const colors = {
    cyan: { base: '#15bfe9', dark: '#075181', glow: '#27d9ff' },
    violet: { base: '#a64cff', dark: '#3b197f', glow: '#b87aff' },
    magenta: { base: '#f34dca', dark: '#6e1550', glow: '#ff79d8' },
    gold: { base: '#ffc44d', dark: '#75420e', glow: '#ffd96b' },
    green: { base: '#56df8c', dark: '#126342', glow: '#78eeaa' },
  };

  let geometry = null;
  let state = null;
  let runIndex = 0;
  let aimAngle = 0;
  let movingShot = null;
  let toastTimer = 0;
  let animationFrame = 0;
  let initialBubbleCount = 1;

  function clamp(value, min, max) {
    return Math.max(min, Math.min(max, value));
  }

  function formatNumber(value) {
    return Number(value || 0).toLocaleString('en-US');
  }

  function readBestScore() {
    try {
      return Number(window.localStorage.getItem(bestScoreKey) || 0);
    } catch (error) {
      return 0;
    }
  }

  function saveBestScore(score) {
    try {
      window.localStorage.setItem(bestScoreKey, String(score));
    } catch (error) {
      // Local persistence is optional; the practice session still works without it.
    }
  }

  function showToast(message) {
    if (!toast) return;
    toast.textContent = message;
    toast.classList.add('is-visible');
    window.clearTimeout(toastTimer);
    toastTimer = window.setTimeout(() => toast.classList.remove('is-visible'), 3000);
  }

  function resizeCanvas() {
    const rect = canvas.getBoundingClientRect();
    const dpr = Math.min(window.devicePixelRatio || 1, 2);
    canvas.width = Math.max(320, Math.floor(rect.width * dpr));
    canvas.height = Math.max(320, Math.floor(rect.height * dpr));
    context.setTransform(dpr, 0, 0, dpr, 0, 0);

    const width = rect.width;
    const height = rect.height;
    const radius = clamp(width / 29, 14, 26);
    const spacing = radius * 2.06;
    const rowStep = radius * 1.74;
    const boardWidth = ((state ? state.cols : Engine.DEFAULT_COLS) - 1) * spacing + radius * 3;
    const left = (width - boardWidth) / 2;
    const top = Math.max(37, height * .075);

    geometry = {
      width,
      height,
      radius,
      spacing,
      rowStep,
      left,
      top,
      shooterX: width / 2,
      shooterY: height - Math.max(55, height * .105),
    };
  }

  function gridPoint(row, col) {
    return {
      x: geometry.left + geometry.radius + col * geometry.spacing + (row % 2 ? geometry.radius : 0),
      y: geometry.top + geometry.radius + row * geometry.rowStep,
    };
  }

  function nearestEmptyForAim(path) {
    if (path.hit) {
      const hitPoint = gridPoint(path.hit.row, path.hit.col);
      const direction = { x: Math.sin(aimAngle), y: -Math.cos(aimAngle) };
      const desiredPoint = {
        x: hitPoint.x + direction.x * geometry.spacing,
        y: hitPoint.y + direction.y * geometry.spacing,
      };
      const candidates = Engine.getNeighbors(state.board, path.hit.row, path.hit.col)
        .filter(({ row, col }) => !state.board[row][col])
        .map((candidate) => {
          const point = gridPoint(candidate.row, candidate.col);
          return {
            ...candidate,
            distance: Math.hypot(point.x - desiredPoint.x, point.y - desiredPoint.y),
          };
        })
        .sort((first, second) => first.distance - second.distance);

      if (candidates[0]) return candidates[0];
      return Engine.findNearestEmpty(state.board, path.hit.row, path.hit.col);
    }

    const firstRowX = geometry.left + geometry.radius;
    const preferredCol = clamp(Math.round((path.endX - firstRowX) / geometry.spacing), 0, state.cols - 1);
    if (!state.board[0][preferredCol]) return { row: 0, col: preferredCol };
    return Engine.findNearestEmpty(state.board, 0, preferredCol);
  }

  function traceAim() {
    const points = [{ x: geometry.shooterX, y: geometry.shooterY }];
    let x = geometry.shooterX;
    let y = geometry.shooterY;
    let velocityX = Math.sin(aimAngle);
    let velocityY = -Math.cos(aimAngle);
    let hit = null;
    const step = Math.max(3, geometry.radius / 5);

    for (let distance = 0; distance < geometry.height * 2.2; distance += step) {
      x += velocityX * step;
      y += velocityY * step;

      if (x <= geometry.radius + 7 || x >= geometry.width - geometry.radius - 7) {
        velocityX *= -1;
        x = clamp(x, geometry.radius + 7, geometry.width - geometry.radius - 7);
      }

      if (distance % (step * 3) < step) points.push({ x, y });
      if (y <= geometry.top + geometry.radius * .45) {
        points.push({ x, y: geometry.top + geometry.radius * .45 });
        break;
      }

      for (let row = 0; row < state.rows; row += 1) {
        for (let col = 0; col < state.cols; col += 1) {
          if (!state.board[row][col]) continue;
          const bubblePoint = gridPoint(row, col);
          if (Math.hypot(x - bubblePoint.x, y - bubblePoint.y) <= geometry.radius * 1.83) {
            hit = { row, col };
            points.push({ x, y });
            return { points, endX: x, endY: y, hit };
          }
        }
      }
    }

    return { points, endX: x, endY: y, hit };
  }

  function pointAlong(points, progress) {
    if (points.length === 1) return points[0];
    const lengths = [];
    let total = 0;
    for (let index = 1; index < points.length; index += 1) {
      const length = Math.hypot(points[index].x - points[index - 1].x, points[index].y - points[index - 1].y);
      lengths.push(length);
      total += length;
    }

    let remaining = total * clamp(progress, 0, 1);
    for (let index = 0; index < lengths.length; index += 1) {
      if (remaining <= lengths[index]) {
        const start = points[index];
        const end = points[index + 1];
        const ratio = lengths[index] ? remaining / lengths[index] : 1;
        return { x: start.x + (end.x - start.x) * ratio, y: start.y + (end.y - start.y) * ratio };
      }
      remaining -= lengths[index];
    }
    return points[points.length - 1];
  }

  function drawBubble(x, y, radius, colorName, alpha = 1, scale = 1) {
    const palette = colors[colorName] || colors.cyan;
    const bubbleRadius = radius * scale;
    const gradient = context.createRadialGradient(
      x - bubbleRadius * .36,
      y - bubbleRadius * .42,
      bubbleRadius * .08,
      x + bubbleRadius * .15,
      y + bubbleRadius * .18,
      bubbleRadius * 1.14,
    );
    gradient.addColorStop(0, '#ffffff');
    gradient.addColorStop(.07, palette.glow);
    gradient.addColorStop(.48, palette.base);
    gradient.addColorStop(1, palette.dark);

    context.save();
    context.globalAlpha = alpha;
    context.shadowColor = palette.glow;
    context.shadowBlur = bubbleRadius * .72;
    context.beginPath();
    context.arc(x, y, bubbleRadius, 0, Math.PI * 2);
    context.fillStyle = gradient;
    context.fill();
    context.shadowBlur = 0;
    context.lineWidth = Math.max(1, bubbleRadius * .06);
    context.strokeStyle = 'rgba(255, 255, 255, .42)';
    context.stroke();
    context.beginPath();
    context.arc(x - bubbleRadius * .28, y - bubbleRadius * .3, bubbleRadius * .2, 0, Math.PI * 2);
    context.fillStyle = 'rgba(255, 255, 255, .52)';
    context.fill();
    context.restore();
  }

  function drawArenaDecor() {
    const { width, height, radius } = geometry;
    const glow = context.createRadialGradient(width * .5, height * .3, 20, width * .5, height * .3, width * .6);
    glow.addColorStop(0, 'rgba(42, 123, 255, .12)');
    glow.addColorStop(1, 'rgba(42, 123, 255, 0)');
    context.fillStyle = glow;
    context.fillRect(0, 0, width, height);

    context.save();
    context.globalAlpha = .24;
    context.fillStyle = '#7ea3ff';
    for (let row = 0; row < 11; row += 1) {
      for (let col = 0; col < 12; col += 1) {
        const x = 24 + col * 62 + (row % 2 ? 31 : 0);
        const y = 25 + row * 51;
        if (x < width - 18 && y < height - 18) {
          context.beginPath();
          context.arc(x, y, row % 3 === 0 ? 1.2 : .7, 0, Math.PI * 2);
          context.fill();
        }
      }
    }
    context.restore();

    const ceilingY = geometry.top + radius * .18;
    context.save();
    context.strokeStyle = 'rgba(98, 190, 255, .24)';
    context.lineWidth = 1;
    context.setLineDash([3, 10]);
    context.beginPath();
    context.moveTo(geometry.left - radius * 1.5, ceilingY);
    context.lineTo(width - geometry.left + radius * .5, ceilingY);
    context.stroke();
    context.restore();
  }

  function drawAim(path) {
    if (!path || state.status !== 'playing' || movingShot) return;
    context.save();
    context.strokeStyle = 'rgba(164, 222, 255, .56)';
    context.lineWidth = 1.4;
    context.setLineDash([3, 8]);
    context.beginPath();
    path.points.forEach((point, index) => {
      if (index === 0) context.moveTo(point.x, point.y);
      else context.lineTo(point.x, point.y);
    });
    context.stroke();
    context.restore();
  }

  function drawShooter() {
    const { shooterX, shooterY, radius } = geometry;
    const directionX = Math.sin(aimAngle);
    const directionY = -Math.cos(aimAngle);
    const baseY = shooterY + radius * .55;

    context.save();
    context.translate(shooterX, baseY);
    context.rotate(aimAngle);
    context.fillStyle = 'rgba(40, 70, 151, .85)';
    context.strokeStyle = 'rgba(103, 199, 255, .65)';
    context.lineWidth = 1.4;
    context.beginPath();
    context.moveTo(-radius * .55, radius * .65);
    context.lineTo(0, -radius * 1.5);
    context.lineTo(radius * .55, radius * .65);
    context.closePath();
    context.fill();
    context.stroke();
    context.restore();

    context.save();
    context.globalAlpha = .35;
    context.strokeStyle = '#27d9ff';
    context.lineWidth = 2;
    context.beginPath();
    context.moveTo(shooterX, shooterY);
    context.lineTo(shooterX + directionX * radius * 2.1, shooterY + directionY * radius * 2.1);
    context.stroke();
    context.restore();

    drawBubble(shooterX, shooterY, radius * 1.03, state.currentColor);
  }

  function drawBoard() {
    state.board.forEach((row, rowIndex) => {
      row.forEach((bubble, colIndex) => {
        if (!bubble) return;
        const point = gridPoint(rowIndex, colIndex);
        drawBubble(point.x, point.y, geometry.radius, bubble.color);
      });
    });
  }

  function drawMovingShot() {
    if (!movingShot) return;
    const point = pointAlong(movingShot.points, movingShot.progress);
    drawBubble(point.x, point.y, geometry.radius, movingShot.color, 1, 1.04);
  }

  function drawStatusOverlay() {
    if (state.status === 'playing' || movingShot) return;
    const title = state.status === 'cleared' ? 'BOARD CLEARED' : 'RUN COMPLETE';
    const subtitle = state.status === 'cleared'
      ? 'Precision wins. Start another deterministic practice seed.'
      : 'Your virtual session is complete. Review your score and try again.';
    context.save();
    context.fillStyle = 'rgba(3, 7, 20, .72)';
    context.fillRect(0, 0, geometry.width, geometry.height);
    context.textAlign = 'center';
    context.fillStyle = '#f7f9ff';
    context.font = '900 26px Inter, system-ui, sans-serif';
    context.fillText(title, geometry.width / 2, geometry.height * .45);
    context.fillStyle = '#a8b0c9';
    context.font = '600 12px Inter, system-ui, sans-serif';
    context.fillText(subtitle, geometry.width / 2, geometry.height * .45 + 28);
    context.restore();
  }

  function render() {
    if (!geometry || !state) return;
    context.clearRect(0, 0, geometry.width, geometry.height);
    drawArenaDecor();
    drawAim(traceAim());
    drawBoard();
    drawShooter();
    drawMovingShot();
    drawStatusOverlay();
    animationFrame = window.requestAnimationFrame(render);
  }

  function updatePanel() {
    scoreValue.textContent = formatNumber(state.score);
    shotsValue.textContent = String(state.shotsLeft);
    comboValue.textContent = `×${state.bestCombo}`;
    bestScoreValue.textContent = formatNumber(Math.max(readBestScore(), state.score));
    const clearedPercentage = Math.round((1 - Engine.boardCount(state.board) / initialBubbleCount) * 100);
    objectiveValue.textContent = `${clamp(clearedPercentage, 0, 100)}%`;
    shotMeter.style.width = `${(state.shotsLeft / 38) * 100}%`;
    nextBubble.className = `bubble-token bubble-${state.nextColor}`;
    nextBubbleLabel.textContent = `${state.nextColor} on deck`;

    const statusLabel = state.status === 'playing' ? 'Ready' : state.status === 'cleared' ? 'Cleared' : 'Complete';
    const headerLabel = state.status === 'playing' ? 'Live session' : state.status === 'cleared' ? 'Board cleared' : 'Run complete';
    currentStatus.textContent = statusLabel;
    statusPill.textContent = headerLabel;
    shootButton.disabled = state.status !== 'playing' || Boolean(movingShot);
    newRunButton.textContent = state.status === 'playing' ? 'New run' : 'Play again';

    if (state.score > readBestScore()) saveBestScore(state.score);
  }

  function startNewRun() {
    runIndex += 1;
    state = Engine.createGame({ seed: Engine.DEFAULT_SEED + runIndex * 7919 });
    initialBubbleCount = Engine.boardCount(state.board);
    movingShot = null;
    aimAngle = 0;
    sessionSeed.textContent = `BR-${state.seed}`;
    updatePanel();
    showToast('New deterministic practice run ready.');
  }

  function finishShot() {
    const shot = movingShot;
    movingShot = null;
    const applied = Engine.applyShot(state, shot.placement.row, shot.placement.col);
    state = applied.game;
    updatePanel();

    if (applied.result.outcome === 'match') {
      showToast(`Match cleared · +${formatNumber(applied.result.scoreDelta)} virtual points`);
    } else if (applied.result.outcome === 'drop') {
      showToast(`Cascade drop · +${formatNumber(applied.result.scoreDelta)} virtual points`);
    } else if (applied.result.penalty) {
      showToast('Five misses added a penalty row. Reset your angle and read the board.');
    }
  }

  function animateShot(timestamp) {
    if (!movingShot) return;
    if (!movingShot.startedAt) movingShot.startedAt = timestamp;
    const elapsed = timestamp - movingShot.startedAt;
    const duration = reducedMotion ? 1 : 300;
    const linear = clamp(elapsed / duration, 0, 1);
    movingShot.progress = 1 - Math.pow(1 - linear, 3);
    if (linear >= 1) {
      finishShot();
      return;
    }
    window.requestAnimationFrame(animateShot);
  }

  function fireShot() {
    if (movingShot || state.status !== 'playing') return;
    const path = traceAim();
    const placement = nearestEmptyForAim(path);
    if (!placement) {
      showToast('The board is full. Start a new practice run.');
      return;
    }

    const target = gridPoint(placement.row, placement.col);
    const targetPath = path.points.concat([{ x: target.x, y: target.y }]);
    movingShot = {
      color: state.currentColor,
      placement,
      points: targetPath,
      progress: 0,
      startedAt: 0,
    };
    updatePanel();
    window.requestAnimationFrame(animateShot);
  }

  function updateAimFromPointer(event) {
    const rect = canvas.getBoundingClientRect();
    const x = event.clientX - rect.left;
    const y = event.clientY - rect.top;
    aimAngle = clamp(Math.atan2(x - geometry.shooterX, geometry.shooterY - y), -1.34, 1.34);
  }

  canvas.addEventListener('pointermove', updateAimFromPointer, { passive: true });
  canvas.addEventListener('pointerdown', (event) => {
    event.preventDefault();
    updateAimFromPointer(event);
    fireShot();
  });
  shootButton.addEventListener('click', fireShot);
  newRunButton.addEventListener('click', startNewRun);

  window.addEventListener('keydown', (event) => {
    if (event.key === 'ArrowLeft') {
      aimAngle = clamp(aimAngle - .08, -1.34, 1.34);
      event.preventDefault();
    } else if (event.key === 'ArrowRight') {
      aimAngle = clamp(aimAngle + .08, -1.34, 1.34);
      event.preventDefault();
    } else if (event.key === ' ' || event.key === 'Enter') {
      fireShot();
      event.preventDefault();
    } else if (event.key.toLowerCase() === 'n') {
      startNewRun();
    }
  });

  window.addEventListener('resize', resizeCanvas);
  state = Engine.createGame({ seed: Engine.DEFAULT_SEED });
  initialBubbleCount = Engine.boardCount(state.board);
  resizeCanvas();
  updatePanel();
  render();
})();
