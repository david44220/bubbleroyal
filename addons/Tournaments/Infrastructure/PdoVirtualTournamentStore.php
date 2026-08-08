<?php

declare(strict_types=1);

namespace Addons\Tournaments\Infrastructure;

use Addons\Tournaments\Domain\VirtualTournamentStore;
use InvalidArgumentException;
use PDO;
use PDOException;

final class PdoVirtualTournamentStore implements VirtualTournamentStore
{
    public function __construct(
        private readonly PDO $connection,
    ) {
    }

    public function syncCatalog(array $templates, int $periodStart): void
    {
        foreach ($templates as $template) {
            $baseId = (string) ($template['id'] ?? '');
            if ($baseId === '') {
                throw new InvalidArgumentException('Virtual tournament template ID is required.');
            }
            // Each recurring period receives a new immutable ID. Results and
            // publications can therefore never be overwritten by the next day.
            $tournamentId = $baseId . '-' . gmdate('Ymd', $periodStart);
            if (strlen($tournamentId) > 128) {
                throw new InvalidArgumentException('Virtual tournament ID is too long.');
            }
            $statement = $this->connection->prepare(
                'INSERT IGNORE INTO br_virtual_tournaments '
                . '(tournament_id, name, description, duration_seconds, capacity, starts_at, ends_at, entry_type, '
                . 'ticket_cost, mode, value_type, cash_mode, rules_version, created_at, updated_at) '
                . 'VALUES (:id, :name, :description, :duration, :capacity, FROM_UNIXTIME(:starts), '
                . 'FROM_UNIXTIME(:ends), :entry_type, :ticket_cost, :mode, :value_type, :cash_mode, :rules_version, '
                . 'UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))',
            );
            $statement->execute([
                'id' => $tournamentId,
                'name' => $template['name'],
                'description' => $template['description'],
                'duration' => $template['duration_seconds'],
                'capacity' => $template['capacity'],
                'starts' => $periodStart,
                'ends' => $periodStart + (int) $template['duration_seconds'],
                'entry_type' => $template['entry_type'],
                'ticket_cost' => $template['ticket_cost'],
                'mode' => $template['mode'],
                'value_type' => $template['value_type'],
                'cash_mode' => ($template['cash_mode'] ?? false) ? 1 : 0,
                'rules_version' => $template['rules_version'],
            ]);
        }
    }

    public function list(int $at): array
    {
        $statement = $this->connection->prepare(
            'SELECT tournament_id, name, description, duration_seconds, capacity, UNIX_TIMESTAMP(starts_at) '
            . 'AS starts_at_unix, UNIX_TIMESTAMP(ends_at) AS ends_at_unix, entry_type, ticket_cost, mode, value_type, '
            . 'cash_mode, rules_version FROM br_virtual_tournaments '
            . 'WHERE starts_at <= FROM_UNIXTIME(:at + 604800) AND ends_at >= FROM_UNIXTIME(:at - 604800) '
            . 'ORDER BY tournament_id ASC',
        );
        $statement->execute(['at' => $at]);
        return $this->normaliseTournaments($statement->fetchAll());
    }

    public function find(string $tournamentId): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT tournament_id, name, description, duration_seconds, capacity, UNIX_TIMESTAMP(starts_at) '
            . 'AS starts_at_unix, UNIX_TIMESTAMP(ends_at) AS ends_at_unix, entry_type, ticket_cost, mode, value_type, '
            . 'cash_mode, rules_version FROM br_virtual_tournaments WHERE tournament_id = :id LIMIT 1',
        );
        $statement->execute(['id' => $tournamentId]);
        $row = $statement->fetch();
        if (!is_array($row)) {
            return null;
        }
        return $this->normaliseTournaments([$row])[0] ?? null;
    }

    public function entries(string $tournamentId): array
    {
        $statement = $this->connection->prepare(
            'SELECT entry_id, tournament_id, player_id, verified_session_id, verified, mode, value_type, entry_type, '
            . 'ticket_cost, cash_mode, score, status, best_combo, shots_used, replay_review, '
            . 'UNIX_TIMESTAMP(verified_at) AS verified_at, reviewed_by, UNIX_TIMESTAMP(reviewed_at) AS reviewed_at, review_id '
            . 'FROM br_virtual_tournament_entries WHERE tournament_id = :id ORDER BY score DESC, verified_at ASC, entry_id ASC',
        );
        $statement->execute(['id' => $tournamentId]);
        return $this->normaliseEntries($statement->fetchAll());
    }

    public function findPlayerEntry(string $tournamentId, string $playerId): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT entry_id, tournament_id, player_id, verified_session_id, verified, mode, value_type, entry_type, '
            . 'ticket_cost, cash_mode, score, status, best_combo, shots_used, replay_review, '
            . 'UNIX_TIMESTAMP(verified_at) AS verified_at, reviewed_by, UNIX_TIMESTAMP(reviewed_at) AS reviewed_at, review_id '
            . 'FROM br_virtual_tournament_entries WHERE tournament_id = :tournament_id AND player_id = :player_id LIMIT 1',
        );
        $statement->execute(['tournament_id' => $tournamentId, 'player_id' => $playerId]);
        $row = $statement->fetch();
        if (!is_array($row)) {
            return null;
        }
        return $this->normaliseEntries([$row])[0] ?? null;
    }

    public function createEntry(array $entry, int $capacity): array
    {
        $this->connection->beginTransaction();
        try {
            $tournament = $this->connection->prepare(
                'SELECT capacity FROM br_virtual_tournaments WHERE tournament_id = :id FOR UPDATE',
            );
            $tournament->execute(['id' => $entry['tournament_id']]);
            $configuredCapacity = $tournament->fetchColumn();
            if ($configuredCapacity === false) {
                throw new InvalidArgumentException('Unknown virtual tournament.');
            }

            $existing = $this->findPlayerEntry((string) $entry['tournament_id'], (string) $entry['player_id']);
            if ($existing !== null) {
                $this->connection->commit();
                return ['created' => false, 'entry' => $existing];
            }

            $count = $this->connection->prepare(
                'SELECT COUNT(*) FROM br_virtual_tournament_entries WHERE tournament_id = :id',
            );
            $count->execute(['id' => $entry['tournament_id']]);
            if ((int) $count->fetchColumn() >= min($capacity, (int) $configuredCapacity)) {
                throw new InvalidArgumentException('Tournament lobby is full.');
            }

            $insert = $this->connection->prepare(
                'INSERT INTO br_virtual_tournament_entries '
                . '(entry_id, tournament_id, player_id, verified_session_id, verified, mode, value_type, entry_type, '
                . 'ticket_cost, cash_mode, score, status, best_combo, shots_used, replay_review, verified_at) '
                . 'VALUES (:entry_id, :tournament_id, :player_id, :verified_session_id, :verified, :mode, :value_type, '
                . ':entry_type, :ticket_cost, :cash_mode, :score, :status, :best_combo, :shots_used, :replay_review, '
                . 'FROM_UNIXTIME(:verified_at))',
            );
            $insert->execute([
                'entry_id' => $entry['entry_id'],
                'tournament_id' => $entry['tournament_id'],
                'player_id' => $entry['player_id'],
                'verified_session_id' => $entry['verified_session_id'],
                'verified' => ($entry['verified'] ?? false) ? 1 : 0,
                'mode' => $entry['mode'],
                'value_type' => $entry['value_type'],
                'entry_type' => $entry['entry_type'],
                'ticket_cost' => $entry['ticket_cost'],
                'cash_mode' => ($entry['cash_mode'] ?? false) ? 1 : 0,
                'score' => $entry['score'],
                'status' => $entry['status'],
                'best_combo' => $entry['best_combo'],
                'shots_used' => $entry['shots_used'],
                'replay_review' => $entry['replay_review'],
                'verified_at' => $entry['verified_at'],
            ]);
            $this->connection->commit();
            return ['created' => true, 'entry' => $entry];
        } catch (PDOException $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            $bySession = $this->connection->prepare(
                'SELECT player_id FROM br_virtual_tournament_entries WHERE verified_session_id = :session LIMIT 1',
            );
            $bySession->execute(['session' => $entry['verified_session_id']]);
            $existingPlayer = $bySession->fetchColumn();
            if ($existingPlayer !== false && (string) $existingPlayer !== (string) $entry['player_id']) {
                throw new InvalidArgumentException('A verified session can only enter one tournament once.', 0, $exception);
            }
            throw $exception;
        } catch (\Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }

    /** @param list<array<string,mixed>> $rows @return list<array<string,mixed>> */
    private function normaliseTournaments(array $rows): array
    {
        foreach ($rows as &$row) {
            $row['id'] = (string) $row['tournament_id'];
            unset($row['tournament_id']);
            foreach (['duration_seconds', 'capacity', 'ticket_cost', 'starts_at_unix', 'ends_at_unix'] as $key) {
                $row[$key] = (int) $row[$key];
            }
            $row['cash_mode'] = (bool) $row['cash_mode'];
        }
        unset($row);
        return $rows;
    }

    /** @param list<array<string,mixed>> $rows @return list<array<string,mixed>> */
    private function normaliseEntries(array $rows): array
    {
        foreach ($rows as &$row) {
            foreach (['verified', 'cash_mode'] as $key) {
                $row[$key] = (bool) $row[$key];
            }
            foreach (['ticket_cost', 'score', 'best_combo', 'shots_used', 'verified_at', 'reviewed_at'] as $key) {
                if ($row[$key] !== null) {
                    $row[$key] = (int) $row[$key];
                }
            }
        }
        unset($row);
        return $rows;
    }
}
