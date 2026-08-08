<?php

declare(strict_types=1);

namespace Addons\Admin\Infrastructure;

use Addons\Admin\Domain\KillSwitchStore;
use PDO;

final class PdoKillSwitchStore implements KillSwitchStore
{
    /** @var array<string,bool> */
    private const DEFAULTS = [
        'virtual_gameplay' => true,
        'virtual_progression' => true,
        'virtual_tournaments' => true,
        'virtual_wallet' => true,
        'sandbox_payments' => true,
        'cash_mode' => false,
    ];

    public function __construct(
        private readonly PDO $connection,
    ) {
        foreach (self::DEFAULTS as $flag => $enabled) {
            $statement = $this->connection->prepare(
                'INSERT IGNORE INTO br_kill_switches '
                . '(flag_key, enabled, reason, updated_by, updated_at) VALUES (:flag, :enabled, NULL, NULL, UTC_TIMESTAMP(6))',
            );
            $statement->execute(['flag' => $flag, 'enabled' => $enabled ? 1 : 0]);
        }
    }

    public function all(): array
    {
        $rows = $this->connection->query('SELECT flag_key, enabled FROM br_kill_switches')->fetchAll();
        $flags = [];
        foreach ($rows as $row) {
            $flags[(string) $row['flag_key']] = (bool) $row['enabled'];
        }
        return $flags;
    }

    public function set(string $flag, bool $enabled, ?string $reason = null, ?string $updatedBy = null, ?int $at = null): void
    {
        $statement = $this->connection->prepare(
            'UPDATE br_kill_switches SET enabled = :enabled, reason = :reason, updated_by = :updated_by, '
            . 'updated_at = FROM_UNIXTIME(:at) WHERE flag_key = :flag',
        );
        $statement->execute([
            'enabled' => $enabled ? 1 : 0,
            'reason' => $reason,
            'updated_by' => $updatedBy,
            'at' => $at ?? time(),
            'flag' => $flag,
        ]);
    }
}
