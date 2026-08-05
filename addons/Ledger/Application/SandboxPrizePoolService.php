<?php

declare(strict_types=1);

namespace Addons\Ledger\Application;

use Addons\GeoFeature\Domain\CompliancePolicyEngine;
use Addons\Ledger\Domain\VirtualLedgerService;
use InvalidArgumentException;

final class SandboxPrizePoolService
{
    public const UNIT_TYPE = 'virtual_prize_unit';

    public function __construct(
        private readonly VirtualLedgerService $ledger,
        private readonly CompliancePolicyEngine $policy,
    ) {
    }

    /** @return array<string,mixed> */
    public function seedPool(
        string $tournamentId,
        int $units,
        array $context,
        ?int $createdAt = null,
    ): array {
        $tournamentId = $this->assertIdentifier($tournamentId, 'Tournament ID');
        $this->assertAllowed($context);
        if ($units <= 0) {
            throw new InvalidArgumentException('Sandbox pool units must be positive.');
        }

        $result = $this->ledger->issue(
            $this->poolAccount($tournamentId),
            self::UNIT_TYPE,
            $units,
            'sandbox_pool_seed',
            $tournamentId,
            'sandbox-pool-seed|' . $tournamentId,
            $createdAt,
        );

        return [
            'idempotent' => $result['idempotent'],
            'tournament_id' => $tournamentId,
            'value_type' => 'virtual',
            'cash_mode' => false,
            'pool_balance' => $this->ledger->balance($this->poolAccount($tournamentId), self::UNIT_TYPE),
            'ledger_entries' => $result['entries'],
        ];
    }

    /**
     * Allocate virtual units from a seeded sandbox pool to approved rows.
     * The publication is deliberately required as an immutable input.
     *
     * @param array<string,mixed> $publication
     * @param array<int|string,int> $allocations rank => virtual units
     * @return array<string,mixed>
     */
    public function settle(
        string $tournamentId,
        array $publication,
        array $allocations,
        array $context,
        ?int $createdAt = null,
    ): array {
        $tournamentId = $this->assertIdentifier($tournamentId, 'Tournament ID');
        $this->assertAllowed($context);
        if (($publication['status'] ?? null) !== 'published'
            || ($publication['value_type'] ?? null) !== 'virtual'
            || ($publication['cash_mode'] ?? true) !== false
            || !is_string($publication['publication_id'] ?? null)) {
            throw new InvalidArgumentException('Only an immutable published virtual result can be settled.');
        }
        if (!is_array($publication['rows'] ?? null) || $publication['rows'] === []) {
            throw new InvalidArgumentException('A published virtual result must contain rows.');
        }

        $rowsByRank = [];
        foreach ($publication['rows'] as $row) {
            if (!is_array($row) || !is_int($row['rank'] ?? null) || !is_string($row['player_id'] ?? null)) {
                throw new InvalidArgumentException('Published leaderboard rows are invalid.');
            }
            $rowsByRank[$row['rank']] = $row['player_id'];
        }

        $totalUnits = 0;
        foreach ($allocations as $rank => $units) {
            $rank = (int) $rank;
            if (!array_key_exists($rank, $rowsByRank) || !is_int($units) || $units <= 0) {
                throw new InvalidArgumentException('Sandbox allocation must target a published rank with positive units.');
            }
            $totalUnits += $units;
        }
        if ($totalUnits <= 0) {
            throw new InvalidArgumentException('At least one sandbox allocation is required.');
        }

        $createdAt ??= time();
        $settlementId = hash('sha256', $tournamentId . '|' . $publication['publication_id']);
        $poolAccount = $this->poolAccount($tournamentId);
        $newUnitsRequired = 0;
        foreach ($allocations as $rank => $units) {
            $rank = (int) $rank;
            $eventId = 'sandbox-settlement|' . $settlementId . '|rank|' . $rank;
            if ($this->ledger->eventEntries($eventId) === []) {
                $newUnitsRequired += $units;
            }
        }
        if ($this->ledger->balance($poolAccount, self::UNIT_TYPE) < $newUnitsRequired) {
            throw new InvalidArgumentException('Sandbox prize pool has insufficient virtual units.');
        }

        $transfers = [];
        $allIdempotent = true;
        foreach ($allocations as $rank => $units) {
            $rank = (int) $rank;
            $playerId = $rowsByRank[$rank];
            $result = $this->ledger->transfer(
                $poolAccount,
                'player:' . $playerId,
                self::UNIT_TYPE,
                $units,
                'sandbox_settlement',
                $settlementId . '|rank|' . $rank,
                'sandbox-settlement|' . $settlementId . '|rank|' . $rank,
                $createdAt,
            );
            $allIdempotent = $allIdempotent && $result['idempotent'];
            $transfers[] = [
                'rank' => $rank,
                'player_id' => $playerId,
                'units' => $units,
                'ledger_entries' => $result['entries'],
            ];
        }

        return [
            'idempotent' => $allIdempotent,
            'settlement_id' => $settlementId,
            'tournament_id' => $tournamentId,
            'publication_id' => $publication['publication_id'],
            'status' => 'sandbox_settled',
            'value_type' => 'virtual',
            'cash_mode' => false,
            'total_units' => $totalUnits,
            'remaining_pool_units' => $this->ledger->balance($poolAccount, self::UNIT_TYPE),
            'allocations' => $transfers,
        ];
    }

    public function poolAccount(string $tournamentId): string
    {
        return 'pool:' . $this->assertIdentifier($tournamentId, 'Tournament ID');
    }

    /** @param array<string,mixed> $context */
    private function assertAllowed(array $context): void
    {
        $decision = $this->policy->evaluate('sandbox_prize_pool', $context);
        if ($decision['allowed'] !== true) {
            throw new InvalidArgumentException('Sandbox prize-pool access denied by compliance policy.');
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
}
