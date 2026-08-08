<?php

declare(strict_types=1);

namespace Addons\GameSessions\Domain;

final class InMemoryGameSessionStore implements GameSessionStore
{
    /** @var array<string,array<string,mixed>> */
    private array $sessions = [];

    public function create(array $claims, string $tokenHash, ?string $playerId): void
    {
        $this->sessions[(string) $claims['session_id']] = [
            'session_id' => $claims['session_id'],
            'player_id' => $playerId,
            'token_hash' => $tokenHash,
            'expires_at' => $claims['expires_at'],
            'consumed_at' => null,
        ];
    }

    public function find(string $sessionId): ?array
    {
        return $this->sessions[$sessionId] ?? null;
    }

    public function consume(string $sessionId, int $at): bool
    {
        if (!isset($this->sessions[$sessionId]) || $this->sessions[$sessionId]['consumed_at'] !== null) {
            return false;
        }
        $this->sessions[$sessionId]['consumed_at'] = $at;
        return true;
    }
}
