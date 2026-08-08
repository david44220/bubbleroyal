<?php

declare(strict_types=1);

namespace Addons\Ledger\Domain;

interface TransactionalVirtualLedgerStore extends VirtualLedgerStore
{
    /** @return array{created:bool,entries:list<array<string,mixed>>} */
    public function appendIssue(array $entry): array;

    /** @param list<array<string,mixed>> $entries @return array{created:bool,entries:list<array<string,mixed>>} */
    public function appendTransfer(array $entries, string $fromAccount, string $unitType, int $units): array;
}
