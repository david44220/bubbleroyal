<?php

declare(strict_types=1);

namespace Addons\Admin\Application;

use Addons\Admin\Domain\AuditLogService;
use Addons\Admin\Domain\KillSwitchService;
use InvalidArgumentException;

final class AdminCommandCenter
{
    public function __construct(
        private readonly AuditLogService $audit,
        private readonly KillSwitchService $killSwitches,
    ) {
    }

    /** @return array<string,mixed> */
    public function setKillSwitch(
        string $actorId,
        string $role,
        string $flag,
        bool $enabled,
        string $reason,
        string $eventId,
        ?int $createdAt = null,
    ): array {
        $this->authorize($role, 'kill_switch.write');
        $reason = trim($reason);
        if ($reason === '' || strlen($reason) > 1000) {
            throw new InvalidArgumentException('A kill-switch change requires a reason.');
        }
        $before = $this->killSwitches->all();
        $after = $this->killSwitches->set($flag, $enabled, $reason, $actorId, $createdAt);
        $audit = $this->audit->record($actorId, 'kill_switch.update', $flag, [
            'reason' => $reason,
            'enabled' => $enabled,
            'previous_enabled' => $before[$flag] ?? null,
        ], $eventId, $createdAt);

        return [
            'switches' => $after,
            'audit' => $audit,
            'cash_mode' => false,
        ];
    }

    /** @return array<string,bool> */
    public function readKillSwitches(string $role): array
    {
        $this->authorize($role, 'kill_switch.read');
        return $this->killSwitches->all();
    }

    /** @return list<array<string,mixed>> */
    public function auditTrail(string $role): array
    {
        $this->authorize($role, 'audit.read');
        return $this->audit->all();
    }

    private function authorize(string $role, string $permission): void
    {
        $permissions = [
            'admin' => ['kill_switch.write', 'kill_switch.read', 'audit.read'],
            'risk_operator' => ['kill_switch.read', 'audit.read'],
            'compliance_operator' => ['kill_switch.read', 'audit.read'],
        ];
        if (!in_array($permission, $permissions[$role] ?? [], true)) {
            throw new InvalidArgumentException('Admin permission denied.');
        }
    }
}
