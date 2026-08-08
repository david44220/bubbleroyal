<?php

declare(strict_types=1);

namespace Addons\Rewards\Domain;

use App\Core\Support\CanonicalJson;
use InvalidArgumentException;
use LogicException;

final class ProgressionService
{
    public function __construct(
        private readonly ProgressionStore $store,
    ) {
    }

    /** @return array<string, mixed> */
    public function snapshot(string $playerId): array
    {
        $playerId = $this->assertPlayerId($playerId);

        return $this->publicState($this->normaliseState($this->store->load($playerId), $playerId));
    }

    /**
     * Apply one server-verified result. The event ID is the idempotency boundary
     * that a future database adapter must enforce transactionally.
     *
     * @param array<string, mixed> $verification
     * @return array<string, mixed>
     */
    public function applyVerifiedResult(
        string $playerId,
        array $verification,
        string $eventId,
        ?int $occurredAt = null,
    ): array {
        $playerId = $this->assertPlayerId($playerId);
        $eventId = trim($eventId);
        if ($eventId === '' || strlen($eventId) > 128) {
            throw new InvalidArgumentException('Progression event ID must contain 1 to 128 characters.');
        }
        if (($verification['valid'] ?? false) !== true
            || ($verification['mode'] ?? null) !== 'practice'
            || ($verification['value_type'] ?? null) !== 'virtual') {
            throw new InvalidArgumentException('Only valid virtual practice results can grant progression.');
        }

        $occurredAt ??= time();
        if ($this->store instanceof TransactionalProgressionStore) {
            return $this->store->transaction(
                $playerId,
                fn (): array => $this->applyVerifiedResultInternal($playerId, $verification, $eventId, $occurredAt),
            );
        }

        return $this->applyVerifiedResultInternal($playerId, $verification, $eventId, $occurredAt);
    }

    /** @return array<string, mixed> */
    private function applyVerifiedResultInternal(
        string $playerId,
        array $verification,
        string $eventId,
        int $occurredAt,
    ): array {
        $state = $this->normaliseState($this->store->load($playerId), $playerId);
        if (array_key_exists($eventId, $state['processed_events'])) {
            return [
                'idempotent' => true,
                'event_id' => $eventId,
                'value_type' => 'virtual',
                'rewards' => [
                    'xp_delta' => 0,
                    'tickets_delta' => 0,
                    'completed_challenges' => [],
                    'unlocked_achievements' => [],
                ],
                'progression' => $this->publicState($state),
            ];
        }

        if ($this->store instanceof TransactionalProgressionStore) {
            $payloadHash = hash('sha256', CanonicalJson::encode([
                'player_id' => $playerId,
                'event_id' => $eventId,
                'verification' => $verification,
            ]));
            if (!$this->store->claimEvent($eventId, $playerId, $payloadHash)) {
                throw new LogicException('Progression event receipt exists without a matching progression state.');
            }
        }

        $xpDelta = 0;
        $ticketsDelta = 0;
        $completedChallenges = [];
        $unlockedAchievements = [];
        $metrics = $this->metrics($verification);

        foreach (ChallengeCatalog::definitions() as $definition) {
            $challengeId = (string) $definition['id'];
            $target = (int) $definition['target'];
            $previous = (int) ($state['challenge_progress'][$challengeId] ?? 0);
            $observed = (int) ($metrics[(string) $definition['metric']] ?? 0);
            $progress = $definition['metric'] === 'verified_runs'
                ? min($target, $previous + 1)
                : max($previous, min($target, $observed));

            $state['challenge_progress'][$challengeId] = $progress;
            if ($progress < $target || in_array($challengeId, $state['completed_challenges'], true)) {
                continue;
            }

            $state['completed_challenges'][] = $challengeId;
            $xpDelta += (int) $definition['xp'];
            $ticketsDelta += (int) $definition['tickets'];
            $completedChallenges[] = $challengeId;

            $achievementId = $definition['achievement_id'] ?? null;
            if (is_string($achievementId) && $achievementId !== ''
                && !in_array($achievementId, $state['achievements'], true)
                && ChallengeCatalog::achievement($achievementId) !== null) {
                $state['achievements'][] = $achievementId;
                $unlockedAchievements[] = $achievementId;
            }
        }

        $state['xp'] += $xpDelta;
        $state['tickets'] += $ticketsDelta;
        $state['level'] = self::levelForXp($state['xp']);
        $state['processed_events'][$eventId] = $occurredAt;
        $state['updated_at'] = $occurredAt;
        $this->store->save($playerId, $state);

        return [
            'idempotent' => false,
            'event_id' => $eventId,
            'value_type' => 'virtual',
            'rewards' => [
                'xp_delta' => $xpDelta,
                'tickets_delta' => $ticketsDelta,
                'completed_challenges' => $completedChallenges,
                'unlocked_achievements' => $unlockedAchievements,
            ],
            'progression' => $this->publicState($state),
        ];
    }

    public static function levelForXp(int $xp): int
    {
        $xp = max(0, $xp);
        $level = 1;
        $threshold = 250;

        while ($xp >= $threshold) {
            $level++;
            $threshold += $level * 250;
        }

        return $level;
    }

    /** @param array<string, mixed> $verification @return array<string, int> */
    private function metrics(array $verification): array
    {
        return [
            'verified_runs' => 1,
            'best_combo' => max(0, (int) ($verification['best_combo'] ?? 0)),
            'shots_verified' => max(0, (int) ($verification['shots_verified'] ?? 0)),
            'board_cleared' => ($verification['status'] ?? null) === 'cleared' ? 1 : 0,
        ];
    }

    private function assertPlayerId(string $playerId): string
    {
        $playerId = trim($playerId);
        if ($playerId === '' || strlen($playerId) > 128) {
            throw new InvalidArgumentException('Player ID must contain 1 to 128 characters.');
        }

        return $playerId;
    }

    /** @param array<string, mixed> $state @return array<string, mixed> */
    private function normaliseState(array $state, string $playerId): array
    {
        return [
            'player_id' => $playerId,
            'rules_version' => ChallengeCatalog::RULES_VERSION,
            'value_type' => 'virtual',
            'xp' => max(0, (int) ($state['xp'] ?? 0)),
            'tickets' => max(0, (int) ($state['tickets'] ?? 0)),
            'level' => self::levelForXp((int) ($state['xp'] ?? 0)),
            'challenge_progress' => is_array($state['challenge_progress'] ?? null)
                ? $state['challenge_progress']
                : [],
            'completed_challenges' => is_array($state['completed_challenges'] ?? null)
                ? array_values($state['completed_challenges'])
                : [],
            'achievements' => is_array($state['achievements'] ?? null)
                ? array_values($state['achievements'])
                : [],
            'processed_events' => is_array($state['processed_events'] ?? null)
                ? $state['processed_events']
                : [],
            'updated_at' => (int) ($state['updated_at'] ?? 0),
        ];
    }

    /** @param array<string, mixed> $state @return array<string, mixed> */
    private function publicState(array $state): array
    {
        unset($state['processed_events']);
        return $state;
    }
}
