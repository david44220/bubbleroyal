<?php

declare(strict_types=1);

namespace Addons\Game\Domain;

final class BubblePracticeEngine
{
    public const RULES_VERSION = 'br-practice-1';
    public const DEFAULT_SEED = 81031;
    public const DEFAULT_ROWS = 12;
    public const DEFAULT_COLS = 11;
    public const DEFAULT_INITIAL_ROWS = 6;
    public const DEFAULT_SHOTS = 38;
    public const MISS_LIMIT = 5;

    /** @var list<string> */
    public const COLORS = ['cyan', 'violet', 'magenta', 'gold', 'green'];

    /** @return array<string, mixed> */
    public static function createGame(int $seed = self::DEFAULT_SEED, int $rows = self::DEFAULT_ROWS, int $cols = self::DEFAULT_COLS): array
    {
        $normalizedSeed = self::normalizeSeed($seed);

        return [
            'mode' => 'practice',
            'value_type' => 'virtual',
            'rules_version' => self::RULES_VERSION,
            'seed' => $normalizedSeed,
            'rows' => $rows,
            'cols' => $cols,
            'board' => self::createInitialBoard($rows, $cols, self::DEFAULT_INITIAL_ROWS, $normalizedSeed),
            'current_color' => self::colorFor($normalizedSeed, 0),
            'next_color' => self::colorFor($normalizedSeed, 1),
            'shots_left' => self::DEFAULT_SHOTS,
            'score' => 0,
            'combo' => 0,
            'best_combo' => 0,
            'misses' => 0,
            'shot_index' => 0,
            'status' => 'playing',
            'penalty_rows' => 0,
        ];
    }

    /** @return array<string, mixed> */
    public static function applyShot(array $game, int $row, int $col): array
    {
        if (($game['status'] ?? null) !== 'playing') {
            return [
                'game' => $game,
                'result' => ['outcome' => 'inactive', 'scoreDelta' => 0, 'matched' => [], 'floating' => []],
            ];
        }

        $result = self::resolveShot($game['board'], $row, $col, $game['current_color'], (int) $game['combo']);
        $nextGame = $game;
        $nextGame['board'] = $result['board'];
        $nextGame['current_color'] = $game['next_color'];
        $nextGame['next_color'] = self::colorFor((int) $game['seed'], (int) $game['shot_index'] + 2);
        $nextGame['shots_left'] = max(0, (int) $game['shots_left'] - 1);
        $nextGame['score'] = (int) $game['score'] + (int) $result['scoreDelta'];
        $nextGame['combo'] = (int) $result['combo'];
        $nextGame['best_combo'] = max((int) $game['best_combo'], (int) $result['combo']);
        $nextGame['misses'] = $result['outcome'] === 'miss' ? (int) $game['misses'] + 1 : 0;
        $nextGame['shot_index'] = (int) $game['shot_index'] + 1;

        if ($result['outcome'] === 'miss' && $nextGame['misses'] >= self::MISS_LIMIT) {
            $nextGame['board'] = self::addPenaltyRow(
                $nextGame['board'],
                (int) $nextGame['seed'],
                (int) $nextGame['shot_index'],
            );
            $nextGame['misses'] = 0;
            $nextGame['penalty_rows'] = (int) $nextGame['penalty_rows'] + 1;
            $result['penalty'] = true;
            $result['signature'] = self::boardSignature($nextGame['board']);
        }

        if (self::boardCount($nextGame['board']) === 0) {
            $nextGame['status'] = 'cleared';
        } elseif ($nextGame['shots_left'] === 0) {
            $nextGame['status'] = 'out';
        }

        return ['game' => $nextGame, 'result' => $result];
    }

    /** @return array<string, mixed> */
    public static function resolveShot(array $board, int $row, int $col, string $color, int $combo = 0): array
    {
        $nextBoard = self::cloneBoard($board);
        $placement = null;
        if (self::isInside($nextBoard, $row, $col) && $nextBoard[$row][$col] === null) {
            $placement = ['row' => $row, 'col' => $col];
        } else {
            $placement = self::findNearestEmpty($nextBoard, $row, $col);
        }

        if ($placement === null) {
            return [
                'board' => $nextBoard,
                'placement' => null,
                'matched' => [],
                'floating' => [],
                'scoreDelta' => 0,
                'combo' => 0,
                'outcome' => 'blocked',
                'signature' => self::boardSignature($nextBoard),
            ];
        }

        $nextBoard[$placement['row']][$placement['col']] = [
            'color' => $color,
            'id' => 'shot-' . $placement['row'] . '-' . $placement['col'] . '-' . self::boardCount($nextBoard),
        ];
        $cluster = self::findColorCluster($nextBoard, $placement['row'], $placement['col']);

        if (count($cluster) < 3) {
            return [
                'board' => $nextBoard,
                'placement' => $placement,
                'matched' => [],
                'floating' => [],
                'scoreDelta' => 0,
                'combo' => 0,
                'outcome' => 'miss',
                'signature' => self::boardSignature($nextBoard),
            ];
        }

        foreach ($cluster as $match) {
            $nextBoard[$match['row']][$match['col']] = null;
        }

        $floating = self::findFloatingBubbles($nextBoard);
        foreach ($floating as $bubble) {
            $nextBoard[$bubble['row']][$bubble['col']] = null;
        }

        $nextCombo = $combo + 1;
        $scoreDelta = count($cluster) * 100 + count($floating) * 150;
        if ($nextCombo > 1) {
            $scoreDelta += $nextCombo * 75;
        }

        return [
            'board' => $nextBoard,
            'placement' => $placement,
            'matched' => $cluster,
            'floating' => $floating,
            'scoreDelta' => $scoreDelta,
            'combo' => $nextCombo,
            'outcome' => count($floating) > 0 ? 'drop' : 'match',
            'signature' => self::boardSignature($nextBoard),
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function createInitialBoard(int $rows, int $cols, int $initialRows, int $seed): array
    {
        $board = self::createEmptyBoard($rows, $cols);
        $rng = self::createRng($seed);
        $index = 0;

        for ($row = 0; $row < min($initialRows, $rows); $row++) {
            for ($col = 0; $col < $cols; $col++) {
                $shouldLeaveGap = $row >= 4 && $rng() < 0.08;
                if ($shouldLeaveGap) {
                    continue;
                }

                $previous = $col >= 1 ? $board[$row][$col - 1] : null;
                $previousPrevious = $col >= 2 ? $board[$row][$col - 2] : null;
                $color = self::COLORS[(int) floor($rng() * count(self::COLORS))];
                if ($previous !== null && $previousPrevious !== null
                    && $previous['color'] === $previousPrevious['color']
                    && $color === $previous['color']) {
                    $color = self::COLORS[(array_search($color, self::COLORS, true) + 1) % count(self::COLORS)];
                }

                $board[$row][$col] = [
                    'color' => $color,
                    'id' => self::bubbleId($row, $col, $seed, $index),
                ];
                $index++;
            }
        }

        return $board;
    }

    /** @return list<array<string, mixed>> */
    public static function findColorCluster(array $board, int $row, int $col): array
    {
        if (!self::isInside($board, $row, $col) || $board[$row][$col] === null) {
            return [];
        }

        $start = $board[$row][$col];
        return self::collectConnected($board, $row, $col, static fn (array $bubble, array $ignoredStart): bool => $bubble['color'] === $start['color']);
    }

    /** @return list<array<string, mixed>> */
    public static function findFloatingBubbles(array $board): array
    {
        $connected = [];
        $queue = [];
        foreach ($board[0] as $col => $bubble) {
            if ($bubble !== null) {
                $queue[] = ['row' => 0, 'col' => $col];
            }
        }

        while ($queue !== []) {
            $current = array_shift($queue);
            $key = $current['row'] . ':' . $current['col'];
            if (isset($connected[$key]) || $board[$current['row']][$current['col']] === null) {
                continue;
            }
            $connected[$key] = true;
            foreach (self::getNeighbors($board, $current['row'], $current['col']) as $neighbor) {
                $neighborKey = $neighbor['row'] . ':' . $neighbor['col'];
                if (!isset($connected[$neighborKey]) && $board[$neighbor['row']][$neighbor['col']] !== null) {
                    $queue[] = $neighbor;
                }
            }
        }

        $floating = [];
        foreach ($board as $row => $cells) {
            foreach ($cells as $col => $bubble) {
                if ($bubble !== null && !isset($connected[$row . ':' . $col])) {
                    $floating[] = ['row' => $row, 'col' => $col, 'bubble' => $bubble];
                }
            }
        }

        return $floating;
    }

    /** @return list<array{row:int,col:int}> */
    public static function getNeighbors(array $board, int $row, int $col): array
    {
        $offsets = $row % 2 === 0
            ? [[-1, -1], [-1, 0], [0, -1], [0, 1], [1, -1], [1, 0]]
            : [[-1, 0], [-1, 1], [0, -1], [0, 1], [1, 0], [1, 1]];
        $neighbors = [];
        foreach ($offsets as [$rowOffset, $colOffset]) {
            $neighborRow = $row + $rowOffset;
            $neighborCol = $col + $colOffset;
            if (self::isInside($board, $neighborRow, $neighborCol)) {
                $neighbors[] = ['row' => $neighborRow, 'col' => $neighborCol];
            }
        }

        return $neighbors;
    }

    public static function boardCount(array $board): int
    {
        $total = 0;
        foreach ($board as $row) {
            foreach ($row as $bubble) {
                if ($bubble !== null) {
                    $total++;
                }
            }
        }
        return $total;
    }

    public static function boardSignature(array $board): string
    {
        $rows = [];
        foreach ($board as $row) {
            $cells = '';
            foreach ($row as $bubble) {
                $cells .= $bubble === null ? '.' : substr((string) $bubble['color'], 0, 1);
            }
            $rows[] = $cells;
        }
        return implode('/', $rows);
    }

    /** @return callable(): float */
    private static function createRng(int $seed): callable
    {
        $state = self::normalizeSeed($seed);
        return static function () use (&$state): float {
            $state = self::i32($state + 0x6D2B79F5);
            $value = self::imul(self::i32($state ^ (self::u32($state) >> 15)), 1 | $state);
            $value = self::i32($value ^ self::i32(
                $value + self::imul(self::i32($value ^ (self::u32($value) >> 7)), 61 | $value),
            ));
            return self::u32($value ^ (self::u32($value) >> 14)) / 4294967296;
        };
    }

    private static function colorFor(int $seed, int $index): string
    {
        $mixed = self::u32(self::normalizeSeed($seed) ^ self::imul($index + 1, 0x9E3779B9));
        $rng = self::createRng($mixed);
        return self::COLORS[(int) floor($rng() * count(self::COLORS))];
    }

    private static function normalizeSeed(int $seed): int
    {
        $normalized = self::u32($seed);
        return $normalized === 0 ? self::DEFAULT_SEED : $normalized;
    }

    /** @return list<list<array<string, mixed>|null>> */
    private static function createEmptyBoard(int $rows, int $cols): array
    {
        return array_fill(0, $rows, array_fill(0, $cols, null));
    }

    /** @return list<list<array<string, mixed>|null>> */
    private static function cloneBoard(array $board): array
    {
        $copy = [];
        foreach ($board as $row) {
            $copy[] = array_map(static fn (mixed $bubble): mixed => $bubble === null ? null : [...$bubble], $row);
        }
        return $copy;
    }

    private static function isInside(array $board, int $row, int $col): bool
    {
        return $row >= 0 && $row < count($board) && isset($board[0]) && $col >= 0 && $col < count($board[0]);
    }

    /** @return list<array<string, mixed>> */
    private static function collectConnected(array $board, int $row, int $col, callable $predicate): array
    {
        $start = $board[$row][$col];
        $matches = [];
        $visited = [];
        $queue = [['row' => $row, 'col' => $col]];

        while ($queue !== []) {
            $current = array_shift($queue);
            $key = $current['row'] . ':' . $current['col'];
            if (isset($visited[$key])) {
                continue;
            }
            $visited[$key] = true;
            $bubble = $board[$current['row']][$current['col']];
            if ($bubble === null || !$predicate($bubble, $start)) {
                continue;
            }
            $matches[] = ['row' => $current['row'], 'col' => $current['col'], 'bubble' => $bubble];
            foreach (self::getNeighbors($board, $current['row'], $current['col']) as $neighbor) {
                $neighborKey = $neighbor['row'] . ':' . $neighbor['col'];
                if (!isset($visited[$neighborKey])) {
                    $queue[] = $neighbor;
                }
            }
        }

        return $matches;
    }

    /** @return array{row:int,col:int}|null */
    private static function findNearestEmpty(array $board, int $preferredRow, int $preferredCol): ?array
    {
        $candidates = [];
        foreach ($board as $row => $cells) {
            foreach ($cells as $col => $bubble) {
                if ($bubble === null) {
                    $distance = abs($row - $preferredRow) * 1.2 + abs($col - $preferredCol);
                    $candidates[] = ['row' => $row, 'col' => $col, 'distance' => $distance];
                }
            }
        }
        usort($candidates, static fn (array $first, array $second): int =>
            ($first['distance'] <=> $second['distance'])
            ?: ($first['row'] <=> $second['row'])
            ?: ($first['col'] <=> $second['col']));

        return $candidates[0] ?? null;
    }

    /** @return list<list<array<string, mixed>|null>> */
    private static function addPenaltyRow(array $board, int $seed, int $shotIndex): array
    {
        $rows = count($board);
        $cols = count($board[0]);
        $nextBoard = self::createEmptyBoard($rows, $cols);
        for ($row = $rows - 1; $row > 0; $row--) {
            $nextBoard[$row] = array_map(static fn (mixed $bubble): mixed => $bubble === null ? null : [...$bubble], $board[$row - 1]);
        }
        for ($col = 0; $col < $cols; $col++) {
            $nextBoard[0][$col] = [
                'color' => self::colorFor($seed, 1000 + $shotIndex * $cols + $col),
                'id' => self::bubbleId(0, $col, $seed, 1000 + $shotIndex * $cols + $col),
            ];
        }
        return $nextBoard;
    }

    private static function bubbleId(int $row, int $col, int $seed, int $index): string
    {
        return self::toBase36(self::normalizeSeed($seed)) . '-' . self::toBase36($index) . '-' . self::toBase36($row) . self::toBase36($col);
    }

    private static function toBase36(int $value): string
    {
        return base_convert((string) self::u32($value), 10, 36);
    }

    private static function u32(int $value): int
    {
        $value %= 4294967296;
        return $value < 0 ? $value + 4294967296 : $value;
    }

    private static function i32(int $value): int
    {
        $value = self::u32($value);
        return $value >= 2147483648 ? $value - 4294967296 : $value;
    }

    private static function imul(int $left, int $right): int
    {
        $left = self::u32($left);
        $right = self::u32($right);
        $leftLow = $left & 0xFFFF;
        $leftHigh = $left >> 16;
        $rightLow = $right & 0xFFFF;
        $rightHigh = $right >> 16;
        return self::i32(self::u32($leftLow * $rightLow + ($leftLow * $rightHigh + $leftHigh * $rightLow) * 65536));
    }
}
