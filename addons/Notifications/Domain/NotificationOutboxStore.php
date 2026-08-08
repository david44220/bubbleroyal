<?php

declare(strict_types=1);

namespace Addons\Notifications\Domain;

interface NotificationOutboxStore
{
    /** @param array<string,mixed> $notification */
    public function append(array $notification): void;

    /** @return array<string,mixed>|null */
    public function findByDedupeKey(string $dedupeKey): ?array;

    /** @return list<array<string,mixed>> */
    public function all(): array;
}
