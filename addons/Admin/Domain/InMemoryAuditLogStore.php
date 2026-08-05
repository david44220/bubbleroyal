<?php

declare(strict_types=1);

namespace Addons\Admin\Domain;

use LogicException;

final class InMemoryAuditLogStore implements AuditLogStore
{
    /** @var list<array<string,mixed>> */
    private array $events = [];

    public function append(array $event): void
    {
        if ($this->findByEventId((string) $event['event_id']) !== null) {
            throw new LogicException('Audit event IDs must be unique.');
        }
        $this->events[] = $event;
    }

    public function all(): array
    {
        return $this->events;
    }

    public function findByEventId(string $eventId): ?array
    {
        foreach ($this->events as $event) {
            if ($event['event_id'] === $eventId) {
                return $event;
            }
        }
        return null;
    }
}
