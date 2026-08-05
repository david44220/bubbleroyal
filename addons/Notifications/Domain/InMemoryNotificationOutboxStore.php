<?php

declare(strict_types=1);

namespace Addons\Notifications\Domain;

use LogicException;

final class InMemoryNotificationOutboxStore implements NotificationOutboxStore
{
    /** @var list<array<string,mixed>> */
    private array $notifications = [];

    public function append(array $notification): void
    {
        if ($this->findByDedupeKey((string) $notification['dedupe_key']) !== null) {
            throw new LogicException('Notification dedupe keys must be unique.');
        }
        $this->notifications[] = $notification;
    }

    public function findByDedupeKey(string $dedupeKey): ?array
    {
        foreach ($this->notifications as $notification) {
            if ($notification['dedupe_key'] === $dedupeKey) {
                return $notification;
            }
        }
        return null;
    }

    public function all(): array
    {
        return $this->notifications;
    }
}
