<?php

declare(strict_types=1);

namespace Addons\Identity\Domain;

interface SessionRepository
{
    /** @param array<string,mixed> $session */
    public function create(array $session): void;

    /** @return array<string,mixed>|null */
    public function findActiveByTokenHash(string $tokenHash, int $now): ?array;

    public function touch(string $sessionId, int $at): void;

    public function revoke(string $sessionId, int $at): void;
}
