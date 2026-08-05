<?php

declare(strict_types=1);

namespace Addons\Admin\Domain;

use InvalidArgumentException;

final class KillSwitchService
{
    /** @var array<string,bool> */
    private array $flags = [
        'virtual_gameplay' => true,
        'virtual_progression' => true,
        'virtual_tournaments' => true,
        'virtual_wallet' => true,
        'sandbox_payments' => true,
        'cash_mode' => false,
    ];

    public function isEnabled(string $flag): bool
    {
        if (!array_key_exists($flag, $this->flags)) {
            throw new InvalidArgumentException('Unknown kill switch.');
        }
        return $this->flags[$flag];
    }

    /** @return array<string,bool> */
    public function all(): array
    {
        return $this->flags;
    }

    public function set(string $flag, bool $enabled): array
    {
        if (!array_key_exists($flag, $this->flags)) {
            throw new InvalidArgumentException('Unknown kill switch.');
        }
        if ($flag === 'cash_mode' && $enabled) {
            throw new InvalidArgumentException('Cash mode cannot be enabled by the phase 15 command center.');
        }
        $this->flags[$flag] = $enabled;
        return $this->all();
    }
}
