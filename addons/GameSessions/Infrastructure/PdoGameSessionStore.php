<?php

declare(strict_types=1);

namespace Addons\GameSessions\Infrastructure;

use Addons\GameSessions\Domain\GameSessionStore;
use PDO;

final class PdoGameSessionStore implements GameSessionStore
{
    public function __construct(
        private readonly PDO $connection,
    ) {
    }

    public function create(array $claims, string $tokenHash, ?string $playerId): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO br_game_sessions '
            . '(session_id, player_id, token_hash, mode, value_type, rules_version, seed, rows_count, cols_count, '
            . 'issued_at, expires_at) VALUES (:session_id, :player_id, :token_hash, :mode, :value_type, :rules_version, '
            . ':seed, :rows_count, :cols_count, FROM_UNIXTIME(:issued_at), FROM_UNIXTIME(:expires_at))',
        );
        $statement->execute([
            'session_id' => $claims['session_id'],
            'player_id' => $playerId,
            'token_hash' => $tokenHash,
            'mode' => $claims['mode'],
            'value_type' => $claims['value_type'],
            'rules_version' => $claims['rules_version'],
            'seed' => $claims['seed'],
            'rows_count' => $claims['rows'],
            'cols_count' => $claims['cols'],
            'issued_at' => $claims['issued_at'],
            'expires_at' => $claims['expires_at'],
        ]);
    }

    public function find(string $sessionId): ?array
    {
        $statement = $this->connection->prepare('SELECT * FROM br_game_sessions WHERE session_id = :id LIMIT 1');
        $statement->execute(['id' => $sessionId]);
        $session = $statement->fetch();
        if (!is_array($session)) {
            return null;
        }
        foreach (['issued_at', 'expires_at', 'consumed_at'] as $key) {
            if (array_key_exists($key, $session) && $session[$key] !== null && is_string($session[$key])) {
                $session[$key] = strtotime($session[$key]);
            }
        }
        return $session;
    }

    public function consume(string $sessionId, int $at): bool
    {
        $statement = $this->connection->prepare(
            'UPDATE br_game_sessions SET consumed_at = FROM_UNIXTIME(:consumed_at) '
            . 'WHERE session_id = :id AND consumed_at IS NULL AND expires_at > FROM_UNIXTIME(:expires_check)',
        );
        $statement->execute(['id' => $sessionId, 'consumed_at' => $at, 'expires_check' => $at]);
        return $statement->rowCount() === 1;
    }
}
