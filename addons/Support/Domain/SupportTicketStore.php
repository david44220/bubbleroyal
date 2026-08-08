<?php

declare(strict_types=1);

namespace Addons\Support\Domain;

interface SupportTicketStore
{
    /** @param array<string,mixed> $ticket */
    public function save(array $ticket): void;

    /** @return array<string,mixed>|null */
    public function find(string $ticketId): ?array;

    /** @return list<array<string,mixed>> */
    public function all(): array;
}
