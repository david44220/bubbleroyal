<?php

declare(strict_types=1);

namespace Addons\Admin\Domain;

interface AuditLogStore
{
    /** @param array<string,mixed> $event */
    public function append(array $event): void;

    /** @return list<array<string,mixed>> */
    public function all(): array;

    /** @return array<string,mixed>|null */
    public function findByEventId(string $eventId): ?array;
}
