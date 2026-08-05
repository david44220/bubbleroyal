<?php

declare(strict_types=1);

namespace Addons\Wallet\Domain;

use Addons\GeoFeature\Domain\CompliancePolicyEngine;
use Addons\Ledger\Domain\VirtualLedgerService;
use InvalidArgumentException;

final class VirtualWalletService
{
    public const WALLET_VERSION = 'br-virtual-wallet-1';
    public const TICKET_UNIT = 'virtual_ticket';
    public const PRIZE_UNIT = 'virtual_prize_unit';

    public function __construct(
        private readonly VirtualLedgerService $ledger,
        private readonly CompliancePolicyEngine $policy,
    ) {
    }

    /** @return array<string,mixed> */
    public function snapshot(string $playerId, array $context): array
    {
        $playerId = $this->assertPlayerId($playerId);
        $this->assertAllowed($context);

        return [
            'wallet_version' => self::WALLET_VERSION,
            'player_id' => $playerId,
            'value_type' => 'virtual',
            'cash_mode' => false,
            'balances' => [
                self::TICKET_UNIT => $this->ledger->balance($this->accountKey($playerId), self::TICKET_UNIT),
                self::PRIZE_UNIT => $this->ledger->balance($this->accountKey($playerId), self::PRIZE_UNIT),
            ],
        ];
    }

    /**
     * Convert a verified progression grant into virtual tickets. The caller
     * supplies the grant produced by ProgressionService; the client cannot
     * choose an arbitrary amount or bypass the compliance gate.
     *
     * @param array<string,mixed> $grant
     * @return array<string,mixed>
     */
    public function creditProgressionTickets(
        string $playerId,
        array $grant,
        string $eventId,
        array $context,
        ?int $createdAt = null,
    ): array {
        $playerId = $this->assertPlayerId($playerId);
        $this->assertAllowed($context);
        if (($grant['value_type'] ?? null) !== 'virtual'
            || ($grant['idempotent'] ?? false) !== false) {
            throw new InvalidArgumentException('Only a new virtual progression grant can fund the wallet.');
        }

        $units = (int) ($grant['rewards']['tickets_delta'] ?? 0);
        if ($units <= 0) {
            return [
                'idempotent' => false,
                'wallet' => $this->snapshot($playerId, $context),
                'ledger_entries' => [],
            ];
        }

        $result = $this->ledger->issue(
            $this->accountKey($playerId),
            self::TICKET_UNIT,
            $units,
            'verified_progression',
            (string) ($grant['event_id'] ?? $eventId),
            $eventId,
            $createdAt,
        );

        return [
            'idempotent' => $result['idempotent'],
            'wallet' => $this->snapshot($playerId, $context),
            'ledger_entries' => $result['entries'],
        ];
    }

    /** @return array<string,mixed> */
    public function debitTickets(
        string $playerId,
        int $units,
        string $referenceId,
        string $eventId,
        array $context,
        ?int $createdAt = null,
    ): array {
        $playerId = $this->assertPlayerId($playerId);
        $this->assertAllowed($context);
        if ($units <= 0) {
            throw new InvalidArgumentException('Ticket debit must be positive.');
        }

        $result = $this->ledger->transfer(
            $this->accountKey($playerId),
            'virtual_sink:tickets',
            self::TICKET_UNIT,
            $units,
            'virtual_entry',
            $this->assertReferenceId($referenceId),
            $eventId,
            $createdAt,
        );

        return [
            'idempotent' => $result['idempotent'],
            'wallet' => $this->snapshot($playerId, $context),
            'ledger_entries' => $result['entries'],
        ];
    }

    public function accountKey(string $playerId): string
    {
        return 'player:' . $this->assertPlayerId($playerId);
    }

    /** @param array<string,mixed> $context */
    private function assertAllowed(array $context): void
    {
        $decision = $this->policy->evaluate('virtual_wallet', $context);
        if ($decision['allowed'] !== true) {
            throw new InvalidArgumentException('Virtual wallet access denied by compliance policy.');
        }
    }

    private function assertPlayerId(string $playerId): string
    {
        $playerId = trim($playerId);
        if ($playerId === '' || strlen($playerId) > 128) {
            throw new InvalidArgumentException('Player ID must contain 1 to 128 characters.');
        }
        return $playerId;
    }

    private function assertReferenceId(string $referenceId): string
    {
        $referenceId = trim($referenceId);
        if ($referenceId === '' || strlen($referenceId) > 191) {
            throw new InvalidArgumentException('Reference ID must contain 1 to 191 characters.');
        }
        return $referenceId;
    }
}
