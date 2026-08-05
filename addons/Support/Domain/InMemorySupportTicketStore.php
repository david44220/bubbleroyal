<?php

declare(strict_types=1);

namespace Addons\Support\Domain;

final class InMemorySupportTicketStore implements SupportTicketStore
{
    /** @var array<string,array<string,mixed>> */
    private array $tickets = [];

    public function save(array $ticket): void
    {
        $this->tickets[(string) $ticket['ticket_id']] = $ticket;
    }

    public function find(string $ticketId): ?array
    {
        return $this->tickets[$ticketId] ?? null;
    }

    public function all(): array
    {
        return array_values($this->tickets);
    }
}
