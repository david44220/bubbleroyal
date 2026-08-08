<?php

declare(strict_types=1);

namespace Addons\GeoFeature\Domain;

interface ComplianceProfileRepository
{
    /** @return array<string,mixed>|null */
    public function find(string $playerId): ?array;
}
