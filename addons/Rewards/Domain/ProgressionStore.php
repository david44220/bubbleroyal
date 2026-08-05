<?php

declare(strict_types=1);

namespace Addons\Rewards\Domain;

interface ProgressionStore
{
    /** @return array<string, mixed> */
    public function load(string $playerId): array;

    /** @param array<string, mixed> $state */
    public function save(string $playerId, array $state): void;
}
