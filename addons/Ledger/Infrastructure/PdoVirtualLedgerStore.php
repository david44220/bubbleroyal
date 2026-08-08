<?php

declare(strict_types=1);

namespace Addons\Ledger\Infrastructure;

use Addons\Ledger\Domain\TransactionalVirtualLedgerStore;
use App\Core\Support\CanonicalJson;
use InvalidArgumentException;
use LogicException;
use PDO;

final class PdoVirtualLedgerStore implements TransactionalVirtualLedgerStore
{
    public function __construct(
        private readonly PDO $connection,
    ) {
    }

    public function all(): array
    {
        $rows = $this->connection->query(
            'SELECT entry_id, event_id, account_key, unit_type, direction, units, reference_type, reference_id, '
            . 'UNIX_TIMESTAMP(created_at) AS created_at, policy_version '
            . 'FROM br_virtual_ledger_entries ORDER BY id ASC',
        )->fetchAll();
        return $this->normaliseRows($rows);
    }

    public function appendMany(array $entries): void
    {
        $this->connection->beginTransaction();
        try {
            foreach ($entries as $entry) {
                $statement = $this->connection->prepare(
                    'INSERT INTO br_virtual_ledger_entries '
                    . '(entry_id, event_id, account_key, unit_type, direction, units, reference_type, reference_id, '
                    . 'metadata, created_at, policy_version) VALUES (:entry_id, :event_id, :account_key, :unit_type, '
                    . ':direction, :units, :reference_type, :reference_id, JSON_OBJECT(), FROM_UNIXTIME(:created_at), '
                    . ':policy_version)',
                );
                $statement->execute($this->parameters($entry));
            }
            $this->connection->commit();
        } catch (\Throwable $exception) {
            $this->connection->rollBack();
            throw $exception;
        }
    }

    public function findByEventId(string $eventId): array
    {
        $statement = $this->connection->prepare(
            'SELECT entry_id, event_id, account_key, unit_type, direction, units, reference_type, reference_id, '
            . 'UNIX_TIMESTAMP(created_at) AS created_at, policy_version FROM br_virtual_ledger_entries '
            . 'WHERE event_id = :event_id ORDER BY id ASC',
        );
        $statement->execute(['event_id' => $eventId]);
        return $this->normaliseRows($statement->fetchAll());
    }

    public function appendIssue(array $entry): array
    {
        $this->connection->beginTransaction();
        try {
            $existing = $this->lockedEventEntries((string) $entry['event_id']);
            if ($existing !== []) {
                $this->connection->commit();
                $this->assertPayload($existing, [$entry]);
                return ['created' => false, 'entries' => $existing];
            }
            $created = $this->claimReceipt((string) $entry['event_id'], [$entry]);
            if (!$created) {
                $existing = $this->lockedEventEntries((string) $entry['event_id']);
                $this->connection->commit();
                $this->assertPayload($existing, [$entry]);
                return ['created' => false, 'entries' => $existing];
            }
            $this->insertEntries([$entry]);
            $this->connection->commit();
            return ['created' => true, 'entries' => [$entry]];
        } catch (\Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }

    public function appendTransfer(array $entries, string $fromAccount, string $unitType, int $units): array
    {
        $eventId = (string) ($entries[0]['event_id'] ?? '');
        $this->connection->beginTransaction();
        try {
            $existing = $this->lockedEventEntries($eventId);
            if ($existing !== []) {
                $this->connection->commit();
                $this->assertPayload($existing, $entries);
                return ['created' => false, 'entries' => $existing];
            }

            // Claim the event before locking the source account. A concurrent
            // retry for the same idempotency key must wait on this receipt,
            // then return the original pair instead of failing an otherwise
            // valid retry with an insufficient-balance error.
            $created = $this->claimReceipt($eventId, $entries);
            if (!$created) {
                $existing = $this->lockedEventEntries($eventId);
                $this->connection->commit();
                $this->assertPayload($existing, $entries);
                return ['created' => false, 'entries' => $existing];
            }

            $balanceStatement = $this->connection->prepare(
                'SELECT direction, units FROM br_virtual_ledger_entries '
                . 'WHERE account_key = :account_key AND unit_type = :unit_type FOR UPDATE',
            );
            $balanceStatement->execute(['account_key' => $fromAccount, 'unit_type' => $unitType]);
            $balance = 0;
            foreach ($balanceStatement->fetchAll() as $balanceRow) {
                $balance += $balanceRow['direction'] === 'credit'
                    ? (int) $balanceRow['units']
                    : -(int) $balanceRow['units'];
            }
            if ($balance < $units) {
                throw new InvalidArgumentException('Insufficient virtual units for transfer.');
            }

            $this->insertEntries($entries);
            $this->connection->commit();
            return ['created' => true, 'entries' => $entries];
        } catch (\Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }

    /** @return list<array<string,mixed>> */
    private function lockedEventEntries(string $eventId): array
    {
        $statement = $this->connection->prepare(
            'SELECT entry_id, event_id, account_key, unit_type, direction, units, reference_type, reference_id, '
            . 'UNIX_TIMESTAMP(created_at) AS created_at, policy_version FROM br_virtual_ledger_entries '
            . 'WHERE event_id = :event_id ORDER BY id ASC FOR UPDATE',
        );
        $statement->execute(['event_id' => $eventId]);
        return $this->normaliseRows($statement->fetchAll());
    }

    /** @param list<array<string,mixed>> $entries */
    private function claimReceipt(string $eventId, array $entries): bool
    {
        $statement = $this->connection->prepare(
            'INSERT IGNORE INTO br_runtime_event_receipts (event_id, event_type, payload_hash, created_at) '
            . 'VALUES (:event_id, :event_type, :payload_hash, UTC_TIMESTAMP(6))',
        );
        $payloadHash = hash('sha256', CanonicalJson::encode($entries));
        $statement->execute([
            'event_id' => $eventId,
            'event_type' => 'virtual_ledger',
            'payload_hash' => $payloadHash,
        ]);
        if ($statement->rowCount() === 1) {
            return true;
        }

        $existing = $this->connection->prepare(
            'SELECT payload_hash FROM br_runtime_event_receipts WHERE event_id = :event_id FOR UPDATE',
        );
        $existing->execute(['event_id' => $eventId]);
        $existingHash = $existing->fetchColumn();
        if (!is_string($existingHash) || !hash_equals($existingHash, $payloadHash)) {
            throw new LogicException('The runtime event already exists with another payload.');
        }
        return false;
    }

    /** @param list<array<string,mixed>> $entries */
    private function insertEntries(array $entries): void
    {
        foreach ($entries as $entry) {
            $statement = $this->connection->prepare(
                'INSERT INTO br_virtual_ledger_entries '
                . '(entry_id, event_id, account_key, unit_type, direction, units, reference_type, reference_id, '
                . 'metadata, created_at, policy_version) VALUES (:entry_id, :event_id, :account_key, :unit_type, '
                . ':direction, :units, :reference_type, :reference_id, JSON_OBJECT(), FROM_UNIXTIME(:created_at), '
                . ':policy_version)',
            );
            $statement->execute($this->parameters($entry));
        }
    }

    /** @param list<array<string,mixed>> $existing @param list<array<string,mixed>> $expected */
    private function assertPayload(array $existing, array $expected): void
    {
        if (CanonicalJson::encode($existing) !== CanonicalJson::encode($expected)) {
            throw new LogicException('The virtual ledger event already exists with another payload.');
        }
    }

    /** @return array<string,mixed> */
    private function parameters(array $entry): array
    {
        return [
            'entry_id' => $entry['entry_id'],
            'event_id' => $entry['event_id'],
            'account_key' => $entry['account_key'],
            'unit_type' => $entry['unit_type'],
            'direction' => $entry['direction'],
            'units' => $entry['units'],
            'reference_type' => $entry['reference_type'],
            'reference_id' => $entry['reference_id'],
            'created_at' => $entry['created_at'],
            'policy_version' => $entry['policy_version'],
        ];
    }

    /** @param list<array<string,mixed>> $rows @return list<array<string,mixed>> */
    private function normaliseRows(array $rows): array
    {
        foreach ($rows as &$row) {
            $row['units'] = (int) $row['units'];
            $row['created_at'] = (int) $row['created_at'];
        }
        unset($row);
        return $rows;
    }
}
