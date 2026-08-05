<?php

declare(strict_types=1);

namespace Addons\Rewards\Domain;

final class InMemoryProgressionStore implements ProgressionStore
{
    /** @var array<string, array<string, mixed>> */
    private array $states = [];

    public function load(string $playerId): array
    {
        return $this->states[$playerId] ?? [];
    }

    public function save(string $playerId, array $state): void
    {
        $this->states[$playerId] = $state;
    }
}
