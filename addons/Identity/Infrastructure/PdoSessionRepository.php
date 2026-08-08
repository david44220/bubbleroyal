<?php

declare(strict_types=1);

namespace Addons\Identity\Infrastructure;

use Addons\Identity\Domain\SessionRepository;
use PDO;

final class PdoSessionRepository implements SessionRepository
{
    public function __construct(
        private readonly PDO $connection,
    ) {
    }

    public function create(array $session): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO br_auth_sessions '
            . '(session_id, user_id, token_hash, csrf_hash, ip_hash, user_agent, created_at, last_seen_at, expires_at) '
            . 'VALUES (:session_id, :user_id, :token_hash, :csrf_hash, :ip_hash, :user_agent, '
            . 'FROM_UNIXTIME(:created_at), FROM_UNIXTIME(:last_seen_at), FROM_UNIXTIME(:expires_at))',
        );
        $statement->execute([
            'session_id' => $session['session_id'],
            'user_id' => $session['user_id'],
            'token_hash' => $session['token_hash'],
            'csrf_hash' => $session['csrf_hash'],
            'ip_hash' => $session['ip_hash'],
            'user_agent' => $session['user_agent'],
            'created_at' => $session['created_at'],
            'last_seen_at' => $session['last_seen_at'],
            'expires_at' => $session['expires_at'],
        ]);
    }

    public function findActiveByTokenHash(string $tokenHash, int $now): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT * FROM br_auth_sessions '
            . 'WHERE token_hash = :token_hash AND revoked_at IS NULL AND expires_at > FROM_UNIXTIME(:now) LIMIT 1',
        );
        $statement->execute(['token_hash' => $tokenHash, 'now' => $now]);
        $session = $statement->fetch();
        if (!is_array($session)) {
            return null;
        }
        foreach (['created_at', 'last_seen_at', 'expires_at', 'revoked_at'] as $key) {
            if (array_key_exists($key, $session) && $session[$key] !== null && is_string($session[$key])) {
                $session[$key] = strtotime($session[$key]);
            }
        }
        return $session;
    }

    public function touch(string $sessionId, int $at): void
    {
        $statement = $this->connection->prepare(
            'UPDATE br_auth_sessions SET last_seen_at = FROM_UNIXTIME(:at) WHERE session_id = :id AND revoked_at IS NULL',
        );
        $statement->execute(['id' => $sessionId, 'at' => $at]);
    }

    public function revoke(string $sessionId, int $at): void
    {
        $statement = $this->connection->prepare(
            'UPDATE br_auth_sessions SET revoked_at = FROM_UNIXTIME(:at) WHERE session_id = :id AND revoked_at IS NULL',
        );
        $statement->execute(['id' => $sessionId, 'at' => $at]);
    }
}
