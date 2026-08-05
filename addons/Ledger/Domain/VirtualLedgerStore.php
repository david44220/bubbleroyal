<?php

declare(strict_types=1);

namespace Addons\Ledger\Domain;

interface VirtualLedgerStore
{
    /** @return list<array<string, mixed>> */
    public function all(): array;

    /** @param list<array<string, mixed>> $entries */
    public function appendMany(array $entries): void;

    /** @return list<array<string, mixed>> */
    public function findByEventId(string $eventId): array;
}
