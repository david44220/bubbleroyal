<?php

declare(strict_types=1);

namespace Addons\Rewards\Domain;

use LogicException;

final class TransactionalInMemoryProgressionStore implements TransactionalProgressionStore
{
    /** @var array<string,array<string,mixed>> */
    private array $states = [];

    /** @var array<string,array{player_id:string,payload_hash:string}> */
    private array $events = [];

    public function load(string $playerId): array
    {
        return $this->states[$playerId] ?? [];
    }

    public function save(string $playerId, array $state): void
    {
        $this->states[$playerId] = $state;
    }

    public function transaction(string $playerId, callable $operation): array
    {
        return $operation();
    }

    public function claimEvent(string $eventId, string $playerId, string $payloadHash): bool
    {
        $existing = $this->events[$eventId] ?? null;
        if ($existing !== null) {
            if ($existing['player_id'] !== $playerId || !hash_equals($existing['payload_hash'], $payloadHash)) {
                throw new LogicException('Progression event already exists with another payload.');
            }
            return false;
        }
        $this->events[$eventId] = ['player_id' => $playerId, 'payload_hash' => $payloadHash];
        return true;
    }
}
