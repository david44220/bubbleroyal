<?php

declare(strict_types=1);

namespace Addons\Rewards\Infrastructure;

use Addons\Rewards\Domain\TransactionalProgressionStore;
use LogicException;
use PDO;

final class PdoProgressionStore implements TransactionalProgressionStore
{
    private bool $transactionActive = false;

    public function __construct(
        private readonly PDO $connection,
    ) {
    }

    public function load(string $playerId): array
    {
        $statement = $this->connection->prepare(
            'SELECT state_json FROM br_virtual_progression_states WHERE player_id = :player_id '
            . 'LIMIT 1 ' . ($this->transactionActive ? 'FOR UPDATE' : ''),
        );
        $statement->execute(['player_id' => $playerId]);
        $json = $statement->fetchColumn();
        if (!is_string($json) || trim($json) === '') {
            return [];
        }
        $state = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        return is_array($state) ? $state : [];
    }

    public function save(string $playerId, array $state): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO br_virtual_progression_states '
            . '(player_id, state_json, version, created_at, updated_at) VALUES (:player_id, :state_json, 1, '
            . 'UTC_TIMESTAMP(6), UTC_TIMESTAMP(6)) ON DUPLICATE KEY UPDATE state_json = VALUES(state_json), '
            . 'version = version + 1, updated_at = UTC_TIMESTAMP(6)',
        );
        $statement->execute([
            'player_id' => $playerId,
            'state_json' => json_encode($state, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        ]);
    }

    public function transaction(string $playerId, callable $operation): array
    {
        $this->connection->beginTransaction();
        $this->transactionActive = true;
        try {
            $seed = $this->connection->prepare(
                'INSERT IGNORE INTO br_virtual_progression_states '
                . '(player_id, state_json, version, created_at, updated_at) VALUES (:player_id, JSON_OBJECT(), 0, '
                . 'UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))',
            );
            $seed->execute(['player_id' => $playerId]);
            $result = $operation();
            $this->connection->commit();
            return $result;
        } catch (\Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        } finally {
            $this->transactionActive = false;
        }
    }

    public function claimEvent(string $eventId, string $playerId, string $payloadHash): bool
    {
        $statement = $this->connection->prepare(
            'INSERT IGNORE INTO br_virtual_progression_events '
            . '(event_id, player_id, payload_hash, created_at) VALUES (:event_id, :player_id, :payload_hash, UTC_TIMESTAMP(6))',
        );
        $statement->execute([
            'event_id' => $eventId,
            'player_id' => $playerId,
            'payload_hash' => $payloadHash,
        ]);
        if ($statement->rowCount() === 1) {
            return true;
        }

        $existing = $this->connection->prepare(
            'SELECT player_id, payload_hash FROM br_virtual_progression_events WHERE event_id = :event_id FOR UPDATE',
        );
        $existing->execute(['event_id' => $eventId]);
        $row = $existing->fetch();
        if (!is_array($row) || (string) $row['player_id'] !== $playerId
            || !hash_equals((string) $row['payload_hash'], $payloadHash)) {
            throw new LogicException('Progression event already exists with another payload.');
        }
        return false;
    }
}
