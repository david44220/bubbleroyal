<?php

declare(strict_types=1);

namespace Addons\Admin\Domain;

use InvalidArgumentException;

final class KillSwitchService
{
    public function __construct(
        private readonly KillSwitchStore $store = new InMemoryKillSwitchStore(),
    ) {
    }

    public function isEnabled(string $flag): bool
    {
        if (!array_key_exists($flag, $this->flags)) {
            throw new InvalidArgumentException('Unknown kill switch.');
        }
        return $this->all()[$flag];
    }

    /** @return array<string,bool> */
    public function all(): array
    {
        return $this->store->all();
    }

    public function set(string $flag, bool $enabled, ?string $reason = null, ?string $updatedBy = null, ?int $at = null): array
    {
        if (!array_key_exists($flag, $this->flags)) {
            throw new InvalidArgumentException('Unknown kill switch.');
        }
        if ($flag === 'cash_mode' && $enabled) {
            throw new InvalidArgumentException('Cash mode cannot be enabled by the phase 15 command center.');
        }
        $this->store->set($flag, $enabled, $reason, $updatedBy, $at);
        return $this->all();
    }
}
