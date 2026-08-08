<?php

declare(strict_types=1);

namespace Addons\Tournaments\Domain;

use InvalidArgumentException;

final class InMemoryVirtualTournamentStore implements VirtualTournamentStore
{
    /** @var array<string,array<string,mixed>> */
    private array $tournaments = [];

    /** @var array<string,list<array<string,mixed>>> */
    private array $entries = [];

    public function syncCatalog(array $templates, int $periodStart): void
    {
        foreach ($templates as $template) {
            $tournamentId = (string) ($template['id'] ?? '');
            if ($tournamentId === '') {
                throw new InvalidArgumentException('Virtual tournament template ID is required.');
            }
            if (isset($this->tournaments[$tournamentId])) {
                continue;
            }
            $this->tournaments[$tournamentId] = $template + [
                'starts_at_unix' => $periodStart,
                'ends_at_unix' => $periodStart + (int) $template['duration_seconds'],
            ];
            $this->entries[$tournamentId] = [];
        }
    }

    public function list(int $at): array
    {
        return array_values($this->tournaments);
    }

    public function find(string $tournamentId): ?array
    {
        return $this->tournaments[$tournamentId] ?? null;
    }

    public function entries(string $tournamentId): array
    {
        return $this->entries[$tournamentId] ?? [];
    }

    public function findPlayerEntry(string $tournamentId, string $playerId): ?array
    {
        foreach ($this->entries($tournamentId) as $entry) {
            if (($entry['player_id'] ?? null) === $playerId) {
                return $entry;
            }
        }
        return null;
    }

    public function createEntry(array $entry, int $capacity): array
    {
        $tournamentId = (string) $entry['tournament_id'];
        $existing = $this->findPlayerEntry($tournamentId, (string) $entry['player_id']);
        if ($existing !== null) {
            return ['created' => false, 'entry' => $existing];
        }
        if (count($this->entries($tournamentId)) >= $capacity) {
            throw new InvalidArgumentException('Tournament lobby is full.');
        }
        foreach ($this->entries($tournamentId) as $existingEntry) {
            if (($existingEntry['verified_session_id'] ?? null) === $entry['verified_session_id']) {
                throw new InvalidArgumentException('A verified session can only enter one tournament once.');
            }
        }
        $this->entries[$tournamentId][] = $entry;
        return ['created' => true, 'entry' => $entry];
    }
}
