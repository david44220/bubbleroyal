<?php

declare(strict_types=1);

namespace Addons\Admin\Domain;

use InvalidArgumentException;

final class AuditLogService
{
    public const POLICY_VERSION = 'br-audit-1';

    public function __construct(
        private readonly AuditLogStore $store,
    ) {
    }

    /** @param array<string,mixed> $metadata @return array<string,mixed> */
    public function record(
        string $actorId,
        string $action,
        string $resource,
        array $metadata,
        string $eventId,
        ?int $createdAt = null,
    ): array {
        $actorId = $this->assertIdentifier($actorId, 'Actor ID');
        $action = $this->assertIdentifier($action, 'Audit action');
        $resource = $this->assertIdentifier($resource, 'Audit resource');
        $eventId = $this->assertIdentifier($eventId, 'Audit event ID');
        $existing = $this->store->findByEventId($eventId);
        if ($existing !== null) {
            return ['idempotent' => true, 'event' => $existing];
        }

        $createdAt ??= time();
        $event = [
            'audit_id' => hash('sha256', self::POLICY_VERSION . '|' . $eventId),
            'event_id' => $eventId,
            'actor_id' => $actorId,
            'action' => $action,
            'resource' => $resource,
            'metadata' => $this->sanitize($metadata),
            'created_at' => $createdAt,
            'policy_version' => self::POLICY_VERSION,
        ];
        $this->store->append($event);
        return ['idempotent' => false, 'event' => $event];
    }

    /** @return list<array<string,mixed>> */
    public function all(): array
    {
        return $this->store->all();
    }

    private function sanitize(array $metadata): array
    {
        $sanitized = [];
        foreach ($metadata as $key => $value) {
            $normalizedKey = strtolower((string) $key);
            if (preg_match('/secret|token|password|authorization|private|document/', $normalizedKey) === 1) {
                $sanitized[(string) $key] = '[redacted]';
                continue;
            }
            $sanitized[(string) $key] = is_scalar($value) || $value === null ? $value : '[structured]';
        }
        return $sanitized;
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
