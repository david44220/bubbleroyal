<?php

declare(strict_types=1);

namespace Addons\Compliance\Domain;

final class CashPilotGate
{
    public const POLICY_VERSION = 'br-cash-readiness-1';

    /** @var list<string> */
    public const REQUIRED_CONTROLS = [
        'legal_approval',
        'geo_rules_approved',
        'age_verification',
        'kyc_provider',
        'kyb_provider',
        'risk_engine',
        'responsible_play',
        'payment_provider',
        'reconciliation',
        'support_disputes',
        'audit_logs',
        'kill_switches',
        'security_review',
    ];

    /** @param array<string,mixed> $attestations @return array<string,mixed> */
    public function evaluate(string $environment, array $attestations, bool $externalApproval = false): array
    {
        $missing = [];
        foreach (self::REQUIRED_CONTROLS as $control) {
            if (($attestations[$control] ?? false) !== true) {
                $missing[] = $control;
            }
        }

        $reasons = [];
        if ($environment !== 'pilot') {
            $reasons[] = 'pilot_environment_required';
        }
        if ($missing !== []) {
            $reasons[] = 'required_controls_missing';
        }
        if (!$externalApproval) {
            $reasons[] = 'external_approval_required';
        }

        $ready = $reasons === [];
        return [
            'status' => $ready ? 'ready_for_external_review' : 'blocked',
            'ready_for_external_review' => $ready,
            'activation_allowed' => false,
            'environment' => $environment,
            'policy_version' => self::POLICY_VERSION,
            'value_type' => 'virtual',
            'cash_mode' => false,
            'missing_controls' => $missing,
            'reasons' => $reasons,
        ];
    }
}
