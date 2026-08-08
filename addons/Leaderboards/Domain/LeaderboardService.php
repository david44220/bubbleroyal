<?php

declare(strict_types=1);

namespace Addons\Leaderboards\Domain;

final class LeaderboardService
{
    public const RULES_VERSION = 'br-leaderboard-1';

    /**
     * Rank only server-verified entries whose replay review is approved.
     * The final player ID comparison makes the result deterministic even when
     * every gameplay metric is identical.
     *
     * @param list<array<string, mixed>> $entries
     * @return list<array<string, mixed>>
     */
    public function rank(array $entries): array
    {
        $eligible = array_values(array_filter(
            $entries,
            static fn (mixed $entry): bool => is_array($entry)
                && ($entry['verified'] ?? false) === true
                && ($entry['value_type'] ?? null) === 'virtual'
                && ($entry['mode'] ?? null) === 'practice'
                && ($entry['replay_review'] ?? null) === 'approved'
                && is_string($entry['player_id'] ?? null)
                && is_string($entry['entry_id'] ?? null),
        ));

        usort($eligible, fn (array $left, array $right): int => $this->compare($left, $right));

        $rows = [];
        foreach ($eligible as $index => $entry) {
            $score = max(0, (int) ($entry['score'] ?? 0));
            $shotsUsed = max(0, (int) ($entry['shots_used'] ?? $entry['shots_verified'] ?? 0));
            $bestCombo = max(0, (int) ($entry['best_combo'] ?? 0));
            $cleared = ($entry['status'] ?? null) === 'cleared';
            $verifiedAt = (int) ($entry['verified_at'] ?? 0);

            $rows[] = [
                'rank' => $index + 1,
                'player_id' => $entry['player_id'],
                'entry_id' => $entry['entry_id'],
                'score' => $score,
                'best_combo' => $bestCombo,
                'shots_used' => $shotsUsed,
                'status' => (string) ($entry['status'] ?? 'out'),
                'tie_break_key' => [
                    'score_desc' => $score,
                    'shots_used_asc' => $shotsUsed,
                    'best_combo_desc' => $bestCombo,
                    'cleared_desc' => $cleared ? 1 : 0,
                    'verified_at_asc' => $verifiedAt,
                    'player_id_asc' => $entry['player_id'],
                ],
            ];
        }

        return $rows;
    }

    /** @param array<string, mixed> $left @param array<string, mixed> $right */
    private function compare(array $left, array $right): int
    {
        $score = (int) ($right['score'] ?? 0) <=> (int) ($left['score'] ?? 0);
        if ($score !== 0) {
            return $score;
        }

        $shots = (int) ($left['shots_used'] ?? $left['shots_verified'] ?? PHP_INT_MAX)
            <=> (int) ($right['shots_used'] ?? $right['shots_verified'] ?? PHP_INT_MAX);
        if ($shots !== 0) {
            return $shots;
        }

        $combo = (int) ($right['best_combo'] ?? 0) <=> (int) ($left['best_combo'] ?? 0);
        if ($combo !== 0) {
            return $combo;
        }

        $cleared = (int) (($right['status'] ?? null) === 'cleared')
            <=> (int) (($left['status'] ?? null) === 'cleared');
        if ($cleared !== 0) {
            return $cleared;
        }

        $verifiedAt = (int) ($left['verified_at'] ?? PHP_INT_MAX)
            <=> (int) ($right['verified_at'] ?? PHP_INT_MAX);
        if ($verifiedAt !== 0) {
            return $verifiedAt;
        }

        $player = strcmp((string) $left['player_id'], (string) $right['player_id']);
        if ($player !== 0) {
            return $player;
        }

        return strcmp((string) $left['entry_id'], (string) $right['entry_id']);
    }
}
