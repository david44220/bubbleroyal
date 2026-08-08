<?php

declare(strict_types=1);

namespace Addons\Admin\Domain;

interface KillSwitchStore
{
    /** @return array<string,bool> */
    public function all(): array;

    public function set(string $flag, bool $enabled, ?string $reason = null, ?string $updatedBy = null, ?int $at = null): void;
}
