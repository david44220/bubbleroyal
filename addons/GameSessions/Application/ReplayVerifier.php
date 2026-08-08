<?php

declare(strict_types=1);

namespace Addons\GameSessions\Application;

use Addons\Game\Domain\BubblePracticeEngine;
use InvalidArgumentException;

final class ReplayVerifier
{
    /** @return array<string, mixed> */
    public function verify(array $replay, array $sessionClaims): array
    {
        $errors = [];
        $this->requireValue($replay, 'protocol_version', 'br-replay-v1', $errors);
        $this->requireValue($replay, 'rules_version', BubblePracticeEngine::RULES_VERSION, $errors);
        $this->requireValue($replay, 'mode', 'practice', $errors);
        $this->requireValue($replay, 'value_type', 'virtual', $errors);

        foreach (['seed', 'rows', 'cols', 'initial_board_signature', 'shots', 'final'] as $key) {
            if (!array_key_exists($key, $replay)) {
                $errors[] = $key . '_missing';
            }
        }
        if ($errors !== []) {
            return $this->invalid($errors);
        }

        $this->requireValue($replay, 'seed', $sessionClaims['seed'] ?? null, $errors);
        $this->requireValue($replay, 'rows', $sessionClaims['rows'] ?? null, $errors);
        $this->requireValue($replay, 'cols', $sessionClaims['cols'] ?? null, $errors);
        if (!is_array($replay['shots']) || count($replay['shots']) > BubblePracticeEngine::DEFAULT_SHOTS) {
            $errors[] = 'shots_limit';
        }
        if (!is_array($replay['final'])) {
            $errors[] = 'final_invalid';
        }
        if ($errors !== []) {
            return $this->invalid($errors);
        }

        $game = BubblePracticeEngine::createGame((int) $replay['seed'], (int) $replay['rows'], (int) $replay['cols']);
        if ($replay['initial_board_signature'] !== BubblePracticeEngine::boardSignature($game['board'])) {
            $errors[] = 'initial_board_signature';
        }

        foreach ($replay['shots'] as $index => $event) {
            if (!is_array($event)) {
                $errors[] = 'shot_' . $index . '_invalid';
                continue;
            }
            foreach (['index', 'row', 'col', 'color', 'outcome', 'score_delta', 'score_after', 'combo_after', 'misses_after', 'penalty', 'penalty_rows_after', 'shots_left_after', 'board_count_after', 'board_signature_after'] as $key) {
                if (!array_key_exists($key, $event)) {
                    $errors[] = 'shot_' . $index . '_' . $key . '_missing';
                }
            }
            if ($errors !== [] && end($errors) !== null && str_starts_with((string) end($errors), 'shot_' . $index . '_')) {
                continue;
            }
            if ($event['index'] !== $index || !is_int($event['row']) || !is_int($event['col'])) {
                $errors[] = 'shot_' . $index . '_coordinates';
                continue;
            }
            if ($event['row'] < 0 || $event['row'] >= $game['rows'] || $event['col'] < 0 || $event['col'] >= $game['cols']) {
                $errors[] = 'shot_' . $index . '_bounds';
                continue;
            }

            $before = $game;
            $applied = BubblePracticeEngine::applyShot($game, $event['row'], $event['col']);
            $game = $applied['game'];
            $expected = [
                'color' => $before['current_color'],
                'outcome' => $applied['result']['outcome'],
                'score_delta' => $applied['result']['scoreDelta'],
                'score_after' => $game['score'],
                'combo_after' => $game['combo'],
                'misses_after' => $game['misses'],
                'penalty' => (bool) ($applied['result']['penalty'] ?? false),
                'penalty_rows_after' => $game['penalty_rows'],
                'shots_left_after' => $game['shots_left'],
                'board_count_after' => BubblePracticeEngine::boardCount($game['board']),
                'board_signature_after' => BubblePracticeEngine::boardSignature($game['board']),
            ];
            foreach ($expected as $key => $expectedValue) {
                if (($event[$key] ?? null) !== $expectedValue) {
                    $errors[] = 'shot_' . $index . '_' . $key;
                }
            }
        }

        $final = $replay['final'];
        $expectedFinal = [
            'score' => $game['score'],
            'status' => $game['status'],
            'combo' => $game['combo'],
            'best_combo' => $game['best_combo'],
            'misses' => $game['misses'],
            'penalty_rows' => $game['penalty_rows'],
            'shots_left' => $game['shots_left'],
            'board_count' => BubblePracticeEngine::boardCount($game['board']),
            'board_signature' => BubblePracticeEngine::boardSignature($game['board']),
        ];
        foreach ($expectedFinal as $key => $expectedValue) {
            if (($final[$key] ?? null) !== $expectedValue) {
                $errors[] = 'final_' . $key;
            }
        }

        if ($errors !== []) {
            return $this->invalid(array_values(array_unique($errors)));
        }

        return [
            'valid' => true,
            'rules_version' => BubblePracticeEngine::RULES_VERSION,
            'mode' => 'practice',
            'value_type' => 'virtual',
            'session_id' => $sessionClaims['session_id'],
            'shots_verified' => count($replay['shots']),
            'score' => $game['score'],
            'status' => $game['status'],
            'best_combo' => $game['best_combo'],
            'combo' => $game['combo'],
            'misses' => $game['misses'],
            'penalty_rows' => $game['penalty_rows'],
            'shots_left' => $game['shots_left'],
            'board_count' => BubblePracticeEngine::boardCount($game['board']),
            'board_signature' => BubblePracticeEngine::boardSignature($game['board']),
        ];
    }

    /** @param list<string> $errors */
    private function requireValue(array $values, string $key, mixed $expected, array &$errors): void
    {
        if (($values[$key] ?? null) !== $expected) {
            $errors[] = $key . '_mismatch';
        }
    }

    /** @param list<string> $errors @return array<string,mixed> */
    private function invalid(array $errors): array
    {
        return ['valid' => false, 'errors' => array_values(array_unique($errors))];
    }
}
