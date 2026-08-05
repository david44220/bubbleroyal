<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Bootstrap.php';

use Addons\GeoFeature\Domain\CompliancePolicyEngine;
use Addons\Ledger\Application\SandboxPrizePoolService;
use Addons\Ledger\Domain\InMemoryVirtualLedgerStore;
use Addons\Ledger\Domain\VirtualLedgerService;
use Addons\Wallet\Domain\VirtualWalletService;

function economyExpectTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function economyExpectSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
    }
}

/** @param Closure():void $callback */
function economyExpectThrows(Closure $callback, string $message): void
{
    try {
        $callback();
    } catch (Throwable) {
        return;
    }

    throw new RuntimeException($message);
}

$context = [
    'environment' => 'sandbox',
    'country' => 'FR',
    'region' => 'IDF',
    'age_years' => 25,
    'age_verified' => true,
    'kyc_status' => 'not_required',
    'kyb_status' => 'not_applicable',
    'risk_score' => 100,
    'responsible_play_status' => 'active',
    'cooling_off_until' => 0,
    'minutes_today' => 10,
    'daily_minutes_limit' => 60,
    'sessions_today' => 1,
    'daily_sessions_limit' => 10,
    'now' => 1700000100,
];

$policy = new CompliancePolicyEngine();
$virtualWalletDecision = $policy->evaluate('virtual_wallet', $context);
economyExpectTrue($virtualWalletDecision['allowed'] === true, 'A compliant sandbox player must access the virtual wallet.');
economyExpectSame(false, $virtualWalletDecision['cash_mode'], 'Compliance decisions must remain cash-disabled.');

$ledger = new VirtualLedgerService(new InMemoryVirtualLedgerStore());
$wallet = new VirtualWalletService($ledger, $policy);
$grant = [
    'value_type' => 'virtual',
    'idempotent' => false,
    'event_id' => 'replay-a',
    'rewards' => ['tickets_delta' => 7],
];

$credited = $wallet->creditProgressionTickets('player-a', $grant, 'wallet-grant-a', $context, 1700000101);
economyExpectSame(7, $credited['wallet']['balances'][VirtualWalletService::TICKET_UNIT], 'Progression tickets must be ledger-backed.');
economyExpectSame(false, $credited['idempotent'], 'The first virtual reward credit must be new.');

$creditedAgain = $wallet->creditProgressionTickets('player-a', $grant, 'wallet-grant-a', $context, 1700000102);
economyExpectTrue($creditedAgain['idempotent'] === true, 'Repeated reward credits must be idempotent.');
economyExpectSame(7, $creditedAgain['wallet']['balances'][VirtualWalletService::TICKET_UNIT], 'Repeated credits must not inflate the balance.');

$debited = $wallet->debitTickets('player-a', 2, 'virtual-entry-a', 'ticket-debit-a', $context, 1700000103);
economyExpectSame(5, $debited['wallet']['balances'][VirtualWalletService::TICKET_UNIT], 'Ticket debits must be append-only ledger transfers.');
$debitedAgain = $wallet->debitTickets('player-a', 2, 'virtual-entry-a', 'ticket-debit-a', $context, 1700000104);
economyExpectTrue($debitedAgain['idempotent'] === true, 'Repeated ticket debits must be idempotent.');
economyExpectThrows(
    static fn (): array => $wallet->debitTickets('player-a', 6, 'virtual-entry-b', 'ticket-debit-b', $context, 1700000105),
    'A wallet debit must reject an insufficient virtual balance.',
);

$ledgerEntries = $ledger->entries();
$ledgerEntries[0]['units'] = 999999;
economyExpectSame(5, $ledger->balance('player:player-a', VirtualWalletService::TICKET_UNIT), 'Ledger reads must not expose mutable stored entries.');

$poolService = new SandboxPrizePoolService($ledger, $policy);
$seeded = $poolService->seedPool('daily-precision', 1000, $context, 1700000110);
economyExpectSame(1000, $seeded['pool_balance'], 'Sandbox pool seeding must create virtual units only.');

$publication = [
    'publication_id' => 'publication-daily-precision-1',
    'status' => 'published',
    'value_type' => 'virtual',
    'cash_mode' => false,
    'rows' => [
        ['rank' => 1, 'player_id' => 'player-a'],
        ['rank' => 2, 'player_id' => 'player-b'],
    ],
];
$settled = $poolService->settle('daily-precision', $publication, [1 => 600, 2 => 300], $context, 1700000120);
economyExpectSame('sandbox_settled', $settled['status'], 'Sandbox settlement must publish a virtual settlement state.');
economyExpectSame(900, $settled['total_units'], 'Sandbox allocation totals must be deterministic.');
economyExpectSame(100, $settled['remaining_pool_units'], 'The remaining pool must be derived from ledger entries.');
economyExpectSame(600, $ledger->balance('player:player-a', SandboxPrizePoolService::UNIT_TYPE), 'Rank-one virtual units must reach the player account.');
economyExpectSame(300, $ledger->balance('player:player-b', SandboxPrizePoolService::UNIT_TYPE), 'Rank-two virtual units must reach the player account.');

$settledAgain = $poolService->settle('daily-precision', $publication, [1 => 600, 2 => 300], $context, 1700000121);
economyExpectTrue($settledAgain['idempotent'] === true, 'Repeated sandbox settlement must not duplicate virtual units.');
economyExpectSame(100, $settledAgain['remaining_pool_units'], 'Idempotent settlement must preserve the pool balance.');

$ageBlocked = $context;
$ageBlocked['age_verified'] = false;
$ageDecision = $policy->evaluate('virtual_wallet', $ageBlocked);
economyExpectTrue(in_array('age_verification_required', $ageDecision['reasons'], true), 'Unverified age must block the virtual wallet.');

$riskBlocked = $context;
$riskBlocked['risk_score'] = 900;
$riskDecision = $policy->evaluate('virtual_wallet', $riskBlocked);
economyExpectTrue(in_array('risk_score_too_high', $riskDecision['reasons'], true), 'High-risk contexts must be denied.');

$coolingOff = $context;
$coolingOff['responsible_play_status'] = 'cooling_off';
$coolingDecision = $policy->evaluate('virtual_wallet', $coolingOff);
economyExpectTrue(in_array('responsible_play_cooling_off', $coolingDecision['reasons'], true), 'Cooling-off status must block wallet access.');

$cashDecision = $policy->evaluate('cash_tournaments', $context);
economyExpectTrue($cashDecision['allowed'] === false, 'Cash tournaments must remain disabled by policy.');
economyExpectTrue(in_array('cash_mode_disabled', $cashDecision['reasons'], true), 'Cash denial must be explicit and auditable.');

$regulatedRules = CompliancePolicyEngine::defaultRules();
$regulatedRules['regulated_sandbox'] = [
    'enabled' => true,
    'environments' => ['sandbox'],
    'allowed_countries' => [],
    'blocked_countries' => [],
    'minimum_age' => 18,
    'requires_age_verification' => true,
    'requires_kyc' => true,
    'requires_kyb' => true,
    'maximum_risk_score' => 500,
    'requires_responsible_play' => true,
];
$regulatedPolicy = new CompliancePolicyEngine($regulatedRules);
$regulatedDecision = $regulatedPolicy->evaluate('regulated_sandbox', $context);
economyExpectTrue(in_array('kyc_required', $regulatedDecision['reasons'], true), 'KYC status must be evaluated by configurable policy rules.');
economyExpectTrue(in_array('kyb_required', $regulatedDecision['reasons'], true), 'KYB status must be evaluated by configurable policy rules.');

echo "PHP phases 11-13 tests passed.\n";
