<?php

declare(strict_types=1);

namespace Addons\Ledger\Domain;

use LogicException;

final class InMemoryVirtualLedgerStore implements TransactionalVirtualLedgerStore
{
    /** @var list<array<string, mixed>> */
    private array $entries = [];

    public function all(): array
    {
        return $this->entries;
    }

    public function appendMany(array $entries): void
    {
        $knownEntryIds = [];
        foreach ($this->entries as $entry) {
            $knownEntryIds[(string) $entry['entry_id']] = true;
        }

        foreach ($entries as $entry) {
            $entryId = (string) ($entry['entry_id'] ?? '');
            if ($entryId === '' || isset($knownEntryIds[$entryId])) {
                throw new LogicException('Virtual ledger entry IDs must be unique.');
            }
            $knownEntryIds[$entryId] = true;
        }

        foreach ($entries as $entry) {
            $this->entries[] = $entry;
        }
    }

    public function findByEventId(string $eventId): array
    {
        return array_values(array_filter(
            $this->entries,
            static fn (array $entry): bool => ($entry['event_id'] ?? null) === $eventId,
        ));
    }

    public function appendIssue(array $entry): array
    {
        $existing = $this->findByEventId((string) $entry['event_id']);
        if ($existing !== []) {
            return ['created' => false, 'entries' => $existing];
        }
        $this->appendMany([$entry]);
        return ['created' => true, 'entries' => [$entry]];
    }

    public function appendTransfer(array $entries, string $fromAccount, string $unitType, int $units): array
    {
        $eventId = (string) ($entries[0]['event_id'] ?? '');
        $existing = $this->findByEventId($eventId);
        if ($existing !== []) {
            return ['created' => false, 'entries' => $existing];
        }
        $balance = 0;
        foreach ($this->entries as $entry) {
            if ($entry['account_key'] === $fromAccount && $entry['unit_type'] === $unitType) {
                $balance += $entry['direction'] === 'credit' ? (int) $entry['units'] : -(int) $entry['units'];
            }
        }
        if ($balance < $units) {
            throw new \InvalidArgumentException('Insufficient virtual units for transfer.');
        }
        $this->appendMany($entries);
        return ['created' => true, 'entries' => $entries];
    }
}
