<?php

declare(strict_types=1);

namespace Addons\Identity\Domain;

final class InMemorySessionRepository implements SessionRepository
{
    /** @var array<string,array<string,mixed>> */
    private array $sessions = [];

    public function create(array $session): void
    {
        $this->sessions[(string) $session['session_id']] = $session;
    }

    public function findActiveByTokenHash(string $tokenHash, int $now): ?array
    {
        foreach ($this->sessions as $session) {
            if ($session['token_hash'] === $tokenHash
                && $session['revoked_at'] === null
                && (int) $session['expires_at'] > $now) {
                return $session;
            }
        }
        return null;
    }

    public function touch(string $sessionId, int $at): void
    {
        if (isset($this->sessions[$sessionId])) {
            $this->sessions[$sessionId]['last_seen_at'] = $at;
        }
    }

    public function revoke(string $sessionId, int $at): void
    {
        if (isset($this->sessions[$sessionId])) {
            $this->sessions[$sessionId]['revoked_at'] = $at;
        }
    }
}
