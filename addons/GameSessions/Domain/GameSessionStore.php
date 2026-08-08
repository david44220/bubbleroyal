<?php

declare(strict_types=1);

namespace Addons\GameSessions\Domain;

interface GameSessionStore
{
    /** @param array<string,mixed> $claims */
    public function create(array $claims, string $tokenHash, ?string $playerId): void;

    /** @return array<string,mixed>|null */
    public function find(string $sessionId): ?array;

    /** @param array<string,mixed> $result */
    public function finalize(string $sessionId, array $result, int $at): bool;

    /** @return array<string,mixed>|null */
    public function result(string $sessionId): ?array;

    public function consume(string $sessionId, int $at): bool;
}
