<?php

declare(strict_types=1);

namespace Addons\Ledger\Domain;

use InvalidArgumentException;
use LogicException;

final class VirtualLedgerService
{
    public const POLICY_VERSION = 'br-virtual-ledger-1';
    public const DEFAULT_UNIT_TYPE = 'virtual_unit';

    public function __construct(
        private readonly VirtualLedgerStore $store,
    ) {
    }

    public function balance(string $accountKey, string $unitType = self::DEFAULT_UNIT_TYPE): int
    {
        $accountKey = $this->assertIdentifier($accountKey, 'Account key');
        $unitType = $this->assertVirtualUnitType($unitType);
        $balance = 0;

        foreach ($this->store->all() as $entry) {
            if ($entry['account_key'] !== $accountKey || $entry['unit_type'] !== $unitType) {
                continue;
            }

            $units = (int) $entry['units'];
            $balance += $entry['direction'] === 'credit' ? $units : -$units;
        }

        return $balance;
    }

    /** @return list<array<string, mixed>> */
    public function entries(): array
    {
        return $this->store->all();
    }

    /** @return list<array<string,mixed>> */
    public function eventEntries(string $eventId): array
    {
        return $this->store->findByEventId($this->assertIdentifier($eventId, 'Event ID'));
    }

    /** @return list<array<string, mixed>> */
    public function accountEntries(string $accountKey, ?string $unitType = null): array
    {
        $accountKey = $this->assertIdentifier($accountKey, 'Account key');
        if ($unitType !== null) {
            $unitType = $this->assertVirtualUnitType($unitType);
        }

        return array_values(array_filter(
            $this->store->all(),
            static fn (array $entry): bool => $entry['account_key'] === $accountKey
                && ($unitType === null || $entry['unit_type'] === $unitType),
        ));
    }

    /**
     * Issue virtual units from a controlled application-level source.
     * This is not a cash balance and must be called only by an authorized
     * reward or sandbox-prize-pool service.
     *
     * @return array{ idempotent: bool, entries: list<array<string,mixed>> }
     */
    public function issue(
        string $accountKey,
        string $unitType,
        int $units,
        string $referenceType,
        string $referenceId,
        string $eventId,
        ?int $createdAt = null,
    ): array {
        $accountKey = $this->assertIdentifier($accountKey, 'Account key');
        $unitType = $this->assertVirtualUnitType($unitType);
        $referenceType = $this->assertIdentifier($referenceType, 'Reference type');
        $referenceId = $this->assertIdentifier($referenceId, 'Reference ID');
        $eventId = $this->assertIdentifier($eventId, 'Event ID');
        if (!in_array($referenceType, ['verified_progression', 'sandbox_pool_seed'], true)) {
            throw new InvalidArgumentException('Virtual units may only be issued by approved virtual sources.');
        }
        $this->assertUnits($units);
        $createdAt ??= time();

        $existing = $this->store->findByEventId($eventId);
        if ($existing !== []) {
            $this->assertSameIssue($existing, $accountKey, $unitType, $units, $referenceType, $referenceId);
            return ['idempotent' => true, 'entries' => $existing];
        }

        $entry = $this->makeEntry(
            $accountKey,
            $unitType,
            'credit',
            $units,
            $referenceType,
            $referenceId,
            $eventId,
            $createdAt,
        );
        $this->store->appendMany([$entry]);

        return ['idempotent' => false, 'entries' => [$entry]];
    }

    /**
     * Move virtual units between two accounts with a double-entry pair.
     * The pair shares an event ID and is appended atomically by the store.
     *
     * @return array{ idempotent: bool, entries: list<array<string,mixed>> }
     */
    public function transfer(
        string $fromAccount,
        string $toAccount,
        string $unitType,
        int $units,
        string $referenceType,
        string $referenceId,
        string $eventId,
        ?int $createdAt = null,
    ): array {
        $fromAccount = $this->assertIdentifier($fromAccount, 'Source account');
        $toAccount = $this->assertIdentifier($toAccount, 'Destination account');
        if ($fromAccount === $toAccount) {
            throw new InvalidArgumentException('A virtual transfer requires two different accounts.');
        }
        $unitType = $this->assertVirtualUnitType($unitType);
        $referenceType = $this->assertIdentifier($referenceType, 'Reference type');
        $referenceId = $this->assertIdentifier($referenceId, 'Reference ID');
        $eventId = $this->assertIdentifier($eventId, 'Event ID');
        if (!in_array($referenceType, ['virtual_entry', 'sandbox_settlement'], true)) {
            throw new InvalidArgumentException('Virtual transfers may only use approved virtual references.');
        }
        $this->assertUnits($units);
        $createdAt ??= time();

        $existing = $this->store->findByEventId($eventId);
        if ($existing !== []) {
            if (count($existing) !== 2
                || $existing[0]['account_key'] !== $fromAccount
                || $existing[1]['account_key'] !== $toAccount
                || $existing[0]['unit_type'] !== $unitType
                || $existing[1]['unit_type'] !== $unitType
                || (int) $existing[0]['units'] !== $units
                || (int) $existing[1]['units'] !== $units) {
                throw new LogicException('The virtual transfer event already exists with another payload.');
            }

            return ['idempotent' => true, 'entries' => $existing];
        }

        if ($this->balance($fromAccount, $unitType) < $units) {
            throw new InvalidArgumentException('Insufficient virtual units for transfer.');
        }

        $debit = $this->makeEntry(
            $fromAccount,
            $unitType,
            'debit',
            $units,
            $referenceType,
            $referenceId,
            $eventId,
            $createdAt,
            'debit',
        );
        $credit = $this->makeEntry(
            $toAccount,
            $unitType,
            'credit',
            $units,
            $referenceType,
            $referenceId,
            $eventId,
            $createdAt,
            'credit',
        );
        $this->store->appendMany([$debit, $credit]);

        return ['idempotent' => false, 'entries' => [$debit, $credit]];
    }

    /** @return array<string,mixed> */
    private function makeEntry(
        string $accountKey,
        string $unitType,
        string $direction,
        int $units,
        string $referenceType,
        string $referenceId,
        string $eventId,
        int $createdAt,
        string $entryRole = 'issue',
    ): array {
        return [
            'entry_id' => hash('sha256', implode('|', [
                self::POLICY_VERSION,
                $eventId,
                $accountKey,
                $unitType,
                $entryRole,
            ])),
            'event_id' => $eventId,
            'account_key' => $accountKey,
            'unit_type' => $unitType,
            'direction' => $direction,
            'units' => $units,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'created_at' => $createdAt,
            'policy_version' => self::POLICY_VERSION,
        ];
    }

    /** @param list<array<string,mixed>> $existing */
    private function assertSameIssue(
        array $existing,
        string $accountKey,
        string $unitType,
        int $units,
        string $referenceType,
        string $referenceId,
    ): void {
        if (count($existing) !== 1
            || $existing[0]['account_key'] !== $accountKey
            || $existing[0]['unit_type'] !== $unitType
            || $existing[0]['direction'] !== 'credit'
            || (int) $existing[0]['units'] !== $units
            || $existing[0]['reference_type'] !== $referenceType
            || $existing[0]['reference_id'] !== $referenceId) {
            throw new LogicException('The virtual issue event already exists with another payload.');
        }
    }

    private function assertIdentifier(string $value, string $label): string
    {
        $value = trim($value);
        if ($value === '' || strlen($value) > 191) {
            throw new InvalidArgumentException($label . ' must contain 1 to 191 characters.');
        }
        return $value;
    }

    private function assertUnits(int $units): void
    {
        if ($units <= 0) {
            throw new InvalidArgumentException('Virtual units must be a positive integer.');
        }
    }

    private function assertVirtualUnitType(string $unitType): string
    {
        $unitType = $this->assertIdentifier($unitType, 'Unit type');
        if (!str_starts_with($unitType, 'virtual_')) {
            throw new InvalidArgumentException('Only virtual unit types are supported by this ledger.');
        }
        return $unitType;
    }
}
