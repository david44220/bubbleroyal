<?php

declare(strict_types=1);

namespace Addons\AntiFraud\Application;

use Addons\AntiFraud\Domain\RiskCaseService;

final class AntiCheatService
{
    public function __construct(
        private readonly RiskCaseService $riskCases,
    ) {
    }

    /**
     * Check a server verification result plus bounded telemetry signals.
     * The client remains untrusted; telemetry can increase scrutiny but never
     * authorizes a score or a wallet operation.
     *
     * @param array<string,mixed> $verification
     * @param array<string,mixed> $telemetry
     * @return array<string,mixed>
     */
    public function inspect(
        string $playerId,
        array $verification,
        array $telemetry,
        string $eventId,
        ?int $occurredAt = null,
    ): array {
        $signals = [
            'replay_invalid' => ($verification['valid'] ?? false) !== true,
            'duplicate_replay' => ($telemetry['duplicate_replay'] ?? false) === true,
            'score_velocity' => $this->factor($telemetry['score_velocity'] ?? 0),
            'device_velocity' => $this->factor($telemetry['device_velocity'] ?? 0),
            'geo_mismatch' => ($telemetry['geo_mismatch'] ?? false) === true,
            'automation_indicator' => $this->factor($telemetry['automation_indicator'] ?? 0),
            'account_cluster' => $this->factor($telemetry['account_cluster'] ?? 0),
            'failed_auth_velocity' => $this->factor($telemetry['failed_auth_velocity'] ?? 0),
        ];
        $hasSignals = array_filter($signals, static fn (mixed $signal): bool => $signal !== false && $signal !== 0);
        if ($hasSignals === []) {
            return [
                'allowed' => ($verification['valid'] ?? false) === true,
                'review_required' => false,
                'risk_case' => null,
                'risk_score' => 0,
            ];
        }

        $riskCase = $this->riskCases->open($playerId, $signals, 'anti_cheat', $eventId, $occurredAt);
        $riskScore = (int) $riskCase['case']['risk_score'];
        return [
            'allowed' => ($verification['valid'] ?? false) === true && $riskScore < 750,
            'review_required' => $riskScore >= 500,
            'risk_case' => $riskCase['case'],
            'risk_score' => $riskScore,
        ];
    }

    private function factor(mixed $value): mixed
    {
        if (is_bool($value) || is_int($value) || is_float($value)) {
            return max(0, min(1, $value));
        }
        return 0;
    }
}
