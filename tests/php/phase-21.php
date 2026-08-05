<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Bootstrap.php';

use Addons\Compliance\Domain\CashPilotGate;

function phase21True(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function phase21Same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
    }
}

$gate = new CashPilotGate();
$blocked = $gate->evaluate('staging', []);
phase21True($blocked['ready_for_external_review'] === false, 'An empty checklist must remain blocked.');
phase21True(in_array('required_controls_missing', $blocked['reasons'], true), 'Missing controls must be explicit.');
phase21Same(false, $blocked['activation_allowed'], 'Readiness must never authorize runtime cash activation.');
phase21Same(false, $blocked['cash_mode'], 'The default readiness result must keep cash mode disabled.');

$attestations = array_fill_keys(CashPilotGate::REQUIRED_CONTROLS, true);
$ready = $gate->evaluate('pilot', $attestations, true);
phase21True($ready['ready_for_external_review'] === true, 'A fully attested pilot may be marked ready for external review.');
phase21Same('ready_for_external_review', $ready['status'], 'The readiness status must remain review-oriented.');
phase21Same(false, $ready['activation_allowed'], 'External review readiness must not become an activation switch.');
phase21Same(false, $ready['cash_mode'], 'The fully attested gate must still report cash mode disabled.');
phase21Same('virtual', $ready['value_type'], 'The gate must preserve the virtual value type.');

$withoutApproval = $gate->evaluate('pilot', $attestations, false);
phase21True(in_array('external_approval_required', $withoutApproval['reasons'], true), 'External approval must be a separate required decision.');

$wrongEnvironment = $gate->evaluate('production', $attestations, true);
phase21True(in_array('pilot_environment_required', $wrongEnvironment['reasons'], true), 'Production must not be accepted by the pilot readiness gate.');

echo "PHP phase 21 tests passed.\n";
