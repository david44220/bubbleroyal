<?php

declare(strict_types=1);

namespace Addons\AntiFraud\Domain;

use InvalidArgumentException;

final class RiskAssessmentService
{
    public const POLICY_VERSION = 'br-risk-1';

    /** @var array<string,int> */
    private const WEIGHTS = [
        'replay_invalid' => 900,
        'automation_indicator' => 450,
        'duplicate_replay' => 500,
        'score_velocity' => 350,
        'device_velocity' => 200,
        'geo_mismatch' => 200,
        'account_cluster' => 250,
        'failed_auth_velocity' => 100,
    ];

    /** @param array<string,mixed> $signals @return array<string,mixed> */
    public function assess(array $signals): array
    {
        $factors = [];
        $score = 0;
        foreach (self::WEIGHTS as $signal => $weight) {
            $value = $signals[$signal] ?? false;
            $factor = $this->factorValue($value);
            if ($factor <= 0) {
                continue;
            }
            $contribution = (int) min($weight, round($weight * $factor));
            $score += $contribution;
            $factors[] = [
                'signal' => $signal,
                'factor' => $factor,
                'weight' => $weight,
                'contribution' => $contribution,
            ];
        }

        $score = min(1000, $score);
        return [
            'policy_version' => self::POLICY_VERSION,
            'risk_score' => $score,
            'severity' => $this->severity($score),
            'factors' => $factors,
        ];
    }

    private function factorValue(mixed $value): float
    {
        if ($value === true) {
            return 1.0;
        }
        if ($value === false || $value === null) {
            return 0.0;
        }
        if (is_int($value) || is_float($value)) {
            if ($value < 0) {
                throw new InvalidArgumentException('Risk signal values cannot be negative.');
            }
            return min(1.0, (float) $value);
        }

        throw new InvalidArgumentException('Risk signals must be boolean or numeric factors.');
    }

    private function severity(int $score): string
    {
        return match (true) {
            $score >= 750 => 'critical',
            $score >= 500 => 'high',
            $score >= 250 => 'medium',
            default => 'low',
        };
    }
}
