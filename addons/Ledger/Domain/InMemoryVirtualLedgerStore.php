<?php

declare(strict_types=1);

namespace Addons\Ledger\Domain;

use LogicException;

final class InMemoryVirtualLedgerStore implements VirtualLedgerStore
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
}
