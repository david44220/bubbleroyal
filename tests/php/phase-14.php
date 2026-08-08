<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Bootstrap.php';

use Addons\AntiFraud\Application\AntiCheatService;
use Addons\AntiFraud\Domain\InMemoryRiskCaseStore;
use Addons\AntiFraud\Domain\RiskAssessmentService;
use Addons\AntiFraud\Domain\RiskCaseService;

function phase14Same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
    }
}

function phase14True(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$cases = new RiskCaseService(new InMemoryRiskCaseStore(), new RiskAssessmentService());
$assessment = (new RiskAssessmentService())->assess([
    'automation_indicator' => true,
    'duplicate_replay' => true,
]);
phase14Same(950, $assessment['risk_score'], 'Risk scoring must be deterministic and capped by signal weights.');
phase14Same('critical', $assessment['severity'], 'Critical signals must receive critical severity.');

$opened = $cases->open('player-a', ['automation_indicator' => true, 'duplicate_replay' => true], 'anti_cheat', 'risk-event-a', 1700000100);
phase14Same(false, $opened['idempotent'], 'A new risk event must create a case.');
phase14Same('open', $opened['case']['status'], 'Critical risk cases must open immediately.');
$openedAgain = $cases->open('player-a', ['automation_indicator' => true], 'anti_cheat', 'risk-event-a', 1700000101);
phase14True($openedAgain['idempotent'] === true, 'Risk events must be idempotent.');

$reviewed = $cases->review($opened['case']['case_id'], 'block', 'operator-a', 'Automated cluster requires containment.', 1700000110);
phase14Same('blocked', $reviewed['case']['status'], 'A block review must stop the case subject.');
phase14Same('blocked', $reviewed['case']['review_status'], 'Risk review decisions must be explicit.');
phase14Same(1, count($reviewed['case']['review_history']), 'Risk reviews must retain an audit history.');

$antiCheat = new AntiCheatService($cases);
$clean = $antiCheat->inspect('player-b', [
    'valid' => true,
    'mode' => 'practice',
    'value_type' => 'virtual',
], [], 'risk-event-clean', 1700000120);
phase14True($clean['allowed'] === true, 'A valid clean virtual replay must pass anti-cheat.');

$flagged = $antiCheat->inspect('player-c', [
    'valid' => true,
    'mode' => 'practice',
    'value_type' => 'virtual',
], ['automation_indicator' => 1, 'duplicate_replay' => true], 'risk-event-flagged', 1700000130);
phase14True($flagged['review_required'] === true, 'High-risk telemetry must require manual review.');
phase14True($flagged['allowed'] === false, 'Critical risk must block downstream actions.');

echo "PHP phase 14 tests passed.\n";
