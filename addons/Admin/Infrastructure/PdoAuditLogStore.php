<?php

declare(strict_types=1);

namespace Addons\Admin\Infrastructure;

use Addons\Admin\Domain\AuditLogStore;
use PDO;

final class PdoAuditLogStore implements AuditLogStore
{
    public function __construct(
        private readonly PDO $connection,
    ) {
    }

    public function append(array $event): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO br_admin_audit_events '
            . '(audit_id, event_id, actor_id, action, resource, metadata, policy_version, created_at) '
            . 'VALUES (:audit_id, :event_id, :actor_id, :action, :resource, :metadata, :policy_version, '
            . 'FROM_UNIXTIME(:created_at))',
        );
        $statement->execute([
            'audit_id' => $event['audit_id'],
            'event_id' => $event['event_id'],
            'actor_id' => $event['actor_id'],
            'action' => $event['action'],
            'resource' => $event['resource'],
            'metadata' => json_encode($event['metadata'] ?? [], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            'policy_version' => $event['policy_version'],
            'created_at' => $event['created_at'],
        ]);
    }

    public function all(): array
    {
        $rows = $this->connection->query(
            'SELECT audit_id, event_id, actor_id, action, resource, metadata, policy_version, '
            . 'UNIX_TIMESTAMP(created_at) AS created_at FROM br_admin_audit_events ORDER BY id DESC LIMIT 500',
        )->fetchAll();
        return array_map(fn (array $row): array => $this->normalise($row), $rows);
    }

    public function findByEventId(string $eventId): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT audit_id, event_id, actor_id, action, resource, metadata, policy_version, '
            . 'UNIX_TIMESTAMP(created_at) AS created_at FROM br_admin_audit_events WHERE event_id = :event_id LIMIT 1',
        );
        $statement->execute(['event_id' => $eventId]);
        $row = $statement->fetch();
        return is_array($row) ? $this->normalise($row) : null;
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function normalise(array $row): array
    {
        $row['metadata'] = is_string($row['metadata']) && trim($row['metadata']) !== ''
            ? json_decode($row['metadata'], true, 32, JSON_THROW_ON_ERROR)
            : [];
        $row['created_at'] = (int) $row['created_at'];
        return $row;
    }
}
