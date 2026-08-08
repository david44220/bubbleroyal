<?php

declare(strict_types=1);

namespace Addons\Rewards\Domain;

interface TransactionalProgressionStore extends ProgressionStore
{
    /** @return array<string,mixed> */
    public function transaction(string $playerId, callable $operation): array;

    public function claimEvent(string $eventId, string $playerId, string $payloadHash): bool;
}
