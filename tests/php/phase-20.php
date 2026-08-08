<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Bootstrap.php';

use Addons\Alpha\Domain\AlphaAccessPolicy;

function phase20True(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function phase20Same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
    }
}

$policy = new AlphaAccessPolicy();
$context = [
    'environment' => 'staging',
    'alpha_enabled' => true,
    'allowlist' => ['player-alpha'],
    'value_type' => 'virtual',
    'cash_mode' => false,
    'risk_status' => 'accepted',
];

$allowed = $policy->evaluate('player-alpha', $context);
phase20True($allowed['allowed'] === true, 'An accepted allowlisted staging player must pass virtual alpha access.');
phase20Same(false, $allowed['cash_mode'], 'Alpha access must report cash mode disabled.');
phase20Same('virtual', $allowed['value_type'], 'Alpha access must remain virtual-only.');

$notAllowlisted = $policy->evaluate('player-other', $context);
phase20True(in_array('player_not_allowlisted', $notAllowlisted['reasons'], true), 'The alpha allowlist must deny unknown players.');

$production = $context;
$production['environment'] = 'production';
$productionDecision = $policy->evaluate('player-alpha', $production);
phase20True(in_array('alpha_environment_required', $productionDecision['reasons'], true), 'Production must not be an alpha environment.');

$cash = $context;
$cash['cash_mode'] = true;
$cashDecision = $policy->evaluate('player-alpha', $cash);
phase20True($cashDecision['allowed'] === false, 'Cash mode must block alpha access.');
phase20True(in_array('cash_mode_disabled', $cashDecision['reasons'], true), 'Cash denial must be explicit.');

$risk = $context;
$risk['risk_status'] = 'review';
$riskDecision = $policy->evaluate('player-alpha', $risk);
phase20True(in_array('risk_acceptance_required', $riskDecision['reasons'], true), 'Players under risk review must not enter alpha.');

echo "PHP phase 20 tests passed.\n";
