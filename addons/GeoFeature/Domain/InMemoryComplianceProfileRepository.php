<?php

declare(strict_types=1);

namespace Addons\GeoFeature\Domain;

final class InMemoryComplianceProfileRepository implements ComplianceProfileRepository
{
    /** @var array<string,array<string,mixed>> */
    private array $profiles = [];

    /** @param array<string,mixed> $profile */
    public function seed(string $playerId, array $profile): void
    {
        $this->profiles[$playerId] = $profile;
    }

    public function find(string $playerId): ?array
    {
        return $this->profiles[$playerId] ?? null;
    }
}
