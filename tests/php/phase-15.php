<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Bootstrap.php';

use Addons\Admin\Application\AdminCommandCenter;
use Addons\Admin\Domain\AuditLogService;
use Addons\Admin\Domain\InMemoryAuditLogStore;
use Addons\Admin\Domain\KillSwitchService;

function phase15True(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function phase15Throws(Closure $callback, string $message): void
{
    try {
        $callback();
    } catch (Throwable) {
        return;
    }
    throw new RuntimeException($message);
}

$audit = new AuditLogService(new InMemoryAuditLogStore());
$commandCenter = new AdminCommandCenter($audit, new KillSwitchService());

phase15Throws(
    static fn (): array => $commandCenter->setKillSwitch('user-a', 'risk_operator', 'virtual_wallet', false, 'No write access.', 'admin-event-denied'),
    'Risk operators must not mutate kill switches.',
);

$changed = $commandCenter->setKillSwitch(
    'admin-a',
    'admin',
    'virtual_wallet',
    false,
    'Temporarily disable wallet during a controlled review.',
    'admin-event-wallet-off',
    1700000200,
);
phase15True($changed['switches']['virtual_wallet'] === false, 'Admin must be able to disable a virtual feature.');
phase15True($changed['cash_mode'] === false, 'The command center must report cash mode as disabled.');
phase15True($changed['audit']['event']['metadata']['reason'] !== '[redacted]', 'Operational reasons must remain auditable.');

$secretEvent = $audit->record('admin-a', 'test.redaction', 'test', [
    'token' => 'should-not-persist',
    'password' => 'should-not-persist',
    'safe_value' => 'kept',
], 'admin-event-redaction', 1700000201);
phase15True($secretEvent['event']['metadata']['token'] === '[redacted]', 'Secrets must be redacted from audit metadata.');
phase15True($secretEvent['event']['metadata']['safe_value'] === 'kept', 'Safe audit metadata must remain available.');

$duplicate = $commandCenter->setKillSwitch(
    'admin-a',
    'admin',
    'virtual_wallet',
    false,
    'Repeated event.',
    'admin-event-wallet-off',
    1700000202,
);
phase15True($duplicate['audit']['idempotent'] === true, 'Repeated admin event IDs must be idempotent.');

phase15Throws(
    static fn (): array => $commandCenter->setKillSwitch('admin-a', 'admin', 'cash_mode', true, 'Not allowed.', 'admin-event-cash'),
    'Cash mode must not be activatable by the command center.',
);
phase15True(count($commandCenter->auditTrail('admin')) === 2, 'Audit trail must retain operator events.');

echo "PHP phase 15 tests passed.\n";
