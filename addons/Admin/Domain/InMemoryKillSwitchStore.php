<?php

declare(strict_types=1);

namespace Addons\Admin\Domain;

final class InMemoryKillSwitchStore implements KillSwitchStore
{
    /** @var array<string,bool> */
    private array $flags;

    /** @param array<string,bool>|null $defaults */
    public function __construct(?array $defaults = null)
    {
        $this->flags = $defaults ?? [
            'virtual_gameplay' => true,
            'virtual_progression' => true,
            'virtual_tournaments' => true,
            'virtual_wallet' => true,
            'sandbox_payments' => true,
            'cash_mode' => false,
        ];
    }

    public function all(): array
    {
        return $this->flags;
    }

    public function set(string $flag, bool $enabled, ?string $reason = null, ?string $updatedBy = null, ?int $at = null): void
    {
        $this->flags[$flag] = $enabled;
    }
}
