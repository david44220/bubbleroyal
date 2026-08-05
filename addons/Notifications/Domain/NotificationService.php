<?php

declare(strict_types=1);

namespace Addons\Notifications\Domain;

use InvalidArgumentException;

final class NotificationService
{
    public const POLICY_VERSION = 'br-notifications-1';

    public function __construct(
        private readonly NotificationOutboxStore $store,
    ) {
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function enqueue(
        string $recipientId,
        string $channel,
        string $template,
        array $payload,
        string $dedupeKey,
        ?int $createdAt = null,
    ): array {
        $recipientId = $this->assertIdentifier($recipientId, 'Recipient ID');
        $template = $this->assertIdentifier($template, 'Notification template');
        $dedupeKey = $this->assertIdentifier($dedupeKey, 'Notification dedupe key');
        if (!in_array($channel, ['in_app', 'email', 'push'], true)) {
            throw new InvalidArgumentException('Unsupported notification channel.');
        }

        $existing = $this->store->findByDedupeKey($dedupeKey);
        if ($existing !== null) {
            return ['idempotent' => true, 'notification' => $existing];
        }

        $createdAt ??= time();
        $notification = [
            'notification_id' => hash('sha256', self::POLICY_VERSION . '|' . $dedupeKey),
            'dedupe_key' => $dedupeKey,
            'recipient_id' => $recipientId,
            'channel' => $channel,
            'template' => $template,
            'payload' => $this->safePayload($payload),
            'status' => 'queued',
            'created_at' => $createdAt,
            'policy_version' => self::POLICY_VERSION,
        ];
        $this->store->append($notification);
        return ['idempotent' => false, 'notification' => $notification];
    }

    /** @return list<array<string,mixed>> */
    public function outbox(): array
    {
        return $this->store->all();
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    private function safePayload(array $payload): array
    {
        $result = [];
        foreach ($payload as $key => $value) {
            $result[(string) $key] = is_scalar($value) || $value === null ? $value : '[structured]';
        }
        return $result;
    }

    private function assertIdentifier(string $value, string $label): string
    {
        $value = trim($value);
        if ($value === '' || strlen($value) > 191) {
            throw new InvalidArgumentException($label . ' must contain 1 to 191 characters.');
        }
        return $value;
    }
}
