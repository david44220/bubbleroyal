<?php

declare(strict_types=1);

namespace Addons\Tournaments\Domain;

interface VirtualTournamentStore
{
    /** @param list<array<string,mixed>> $templates */
    public function syncCatalog(array $templates, int $periodStart): void;

    /** @return list<array<string,mixed>> */
    public function list(int $at): array;

    /** @return array<string,mixed>|null */
    public function find(string $tournamentId): ?array;

    /** @return list<array<string,mixed>> */
    public function entries(string $tournamentId): array;

    /** @return array<string,mixed>|null */
    public function findPlayerEntry(string $tournamentId, string $playerId): ?array;

    /** @return array{created:bool,entry:array<string,mixed>} */
    public function createEntry(array $entry, int $capacity): array;
}
