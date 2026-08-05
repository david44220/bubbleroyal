<?php

declare(strict_types=1);

namespace Addons\Leaderboards\Application;

use Addons\Leaderboards\Domain\LeaderboardService;
use InvalidArgumentException;
use JsonException;

final class ResultPublicationService
{
    /** @var array<string, array<string, mixed>> */
    private array $publications = [];

    public function __construct(
        private readonly LeaderboardService $leaderboards,
    ) {
    }

    /**
     * Publish a virtual result snapshot after all active replay reviews are
     * resolved. Repeated publication for the same tournament is idempotent.
     *
     * @param list<array<string, mixed>> $entries
     * @return array<string, mixed>
     * @throws JsonException
     */
    public function publish(
        string $tournamentId,
        array $entries,
        ?int $publishedAt = null,
    ): array {
        $tournamentId = trim($tournamentId);
        if ($tournamentId === '' || strlen($tournamentId) > 128) {
            throw new InvalidArgumentException('Tournament ID must contain 1 to 128 characters.');
        }

        $pending = 0;
        $rejected = 0;
        foreach ($entries as $entry) {
            if (!is_array($entry) || ($entry['verified'] ?? false) !== true) {
                continue;
            }
            $review = $entry['replay_review'] ?? null;
            if (in_array($review, ['pending', 'manual_review'], true)) {
                $pending++;
            } elseif ($review === 'rejected') {
                $rejected++;
            }
        }
        if ($pending > 0) {
            throw new InvalidArgumentException('Replay reviews must be resolved before publication.');
        }

        $rows = $this->leaderboards->rank($entries);
        if ($rows === []) {
            throw new InvalidArgumentException('At least one approved verified result is required.');
        }

        $payloadHash = hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
        if (array_key_exists($tournamentId, $this->publications)) {
            $existing = $this->publications[$tournamentId];
            if ($existing['payload_hash'] !== $payloadHash) {
                throw new InvalidArgumentException('Tournament result has already been published with another snapshot.');
            }

            return [
                'idempotent' => true,
                'publication' => $this->publicPublication($existing),
            ];
        }

        $publishedAt ??= time();
        $publication = [
            'publication_id' => hash('sha256', $tournamentId . '|' . $payloadHash),
            'tournament_id' => $tournamentId,
            'status' => 'published',
            'value_type' => 'virtual',
            'cash_mode' => false,
            'leaderboard_rules_version' => LeaderboardService::RULES_VERSION,
            'published_at' => $publishedAt,
            'approved_entry_count' => count($rows),
            'rejected_entry_count' => $rejected,
            'rewards' => [],
            'rows' => $rows,
            'payload_hash' => $payloadHash,
        ];
        $this->publications[$tournamentId] = $publication;

        return [
            'idempotent' => false,
            'publication' => $this->publicPublication($publication),
        ];
    }

    /** @param array<string, mixed> $publication @return array<string, mixed> */
    private function publicPublication(array $publication): array
    {
        $public = $publication;
        unset($public['payload_hash']);
        return $public;
    }
}
