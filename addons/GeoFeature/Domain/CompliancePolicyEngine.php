<?php

declare(strict_types=1);

namespace Addons\GeoFeature\Domain;

use InvalidArgumentException;

final class CompliancePolicyEngine
{
    public const POLICY_VERSION = 'br-compliance-1';

    /** @var array<string,array<string,mixed>> */
    private readonly array $rules;

    public function __construct(?array $rules = null)
    {
        $this->rules = $rules ?? self::defaultRules();
    }

    /** @return array<string,mixed> */
    public function evaluate(string $feature, array $context): array
    {
        $feature = trim($feature);
        if ($feature === '' || strlen($feature) > 128) {
            throw new InvalidArgumentException('Feature key must contain 1 to 128 characters.');
        }

        $rule = $this->rules[$feature] ?? null;
        $reasons = [];
        if (!is_array($rule)) {
            $reasons[] = 'feature_not_configured';
            return $this->decision($feature, false, $reasons);
        }
        if (($rule['enabled'] ?? false) !== true) {
            $reasons[] = $rule['disabled_reason'] ?? 'feature_disabled';
        }

        $environment = (string) ($context['environment'] ?? '');
        $environments = $rule['environments'] ?? [];
        if (is_array($environments) && $environments !== [] && !in_array($environment, $environments, true)) {
            $reasons[] = 'environment_not_allowed';
        }

        $country = strtoupper((string) ($context['country'] ?? ''));
        $blockedCountries = array_map('strtoupper', is_array($rule['blocked_countries'] ?? null) ? $rule['blocked_countries'] : []);
        $allowedCountries = array_map('strtoupper', is_array($rule['allowed_countries'] ?? null) ? $rule['allowed_countries'] : []);
        if ($country === '') {
            $reasons[] = 'country_missing';
        } elseif (in_array($country, $blockedCountries, true)) {
            $reasons[] = 'country_blocked';
        } elseif ($allowedCountries !== [] && !in_array($country, $allowedCountries, true)) {
            $reasons[] = 'country_not_allowed';
        }

        if (($rule['requires_age_verification'] ?? false) === true
            && ($context['age_verified'] ?? false) !== true) {
            $reasons[] = 'age_verification_required';
        }
        $minimumAge = $rule['minimum_age'] ?? null;
        if (is_int($minimumAge)) {
            $ageYears = $context['age_years'] ?? null;
            if (!is_int($ageYears)) {
                $reasons[] = 'age_missing';
            } elseif ($ageYears < $minimumAge) {
                $reasons[] = 'minimum_age_not_met';
            }
        }

        if (($rule['requires_kyc'] ?? false) === true
            && ($context['kyc_status'] ?? null) !== 'verified') {
            $reasons[] = 'kyc_required';
        }
        if (($rule['requires_kyb'] ?? false) === true
            && ($context['kyb_status'] ?? null) !== 'verified') {
            $reasons[] = 'kyb_required';
        }

        $riskScore = $context['risk_score'] ?? null;
        $maximumRisk = $rule['maximum_risk_score'] ?? null;
        if (!is_int($riskScore)) {
            $reasons[] = 'risk_score_missing';
        } elseif ($riskScore < 0 || $riskScore > 1000) {
            $reasons[] = 'risk_score_invalid';
        } elseif (is_int($maximumRisk) && $riskScore > $maximumRisk) {
            $reasons[] = 'risk_score_too_high';
        }

        $reasons = array_merge($reasons, (new ResponsiblePlayPolicy())->violations($context, $rule));
        return $this->decision($feature, $reasons === [], array_values(array_unique($reasons)));
    }

    /** @return array<string,array<string,mixed>> */
    public static function defaultRules(): array
    {
        return [
            'virtual_wallet' => [
                'enabled' => true,
                'environments' => ['local', 'sandbox', 'staging', 'production'],
                'allowed_countries' => [],
                'blocked_countries' => [],
                'minimum_age' => null,
                'requires_age_verification' => true,
                'requires_kyc' => false,
                'requires_kyb' => false,
                'maximum_risk_score' => 700,
                'requires_responsible_play' => true,
            ],
            'sandbox_prize_pool' => [
                'enabled' => true,
                'environments' => ['local', 'sandbox', 'staging'],
                'allowed_countries' => [],
                'blocked_countries' => [],
                'minimum_age' => null,
                'requires_age_verification' => true,
                'requires_kyc' => false,
                'requires_kyb' => false,
                'maximum_risk_score' => 500,
                'requires_responsible_play' => true,
            ],
            'cash_tournaments' => [
                'enabled' => false,
                'disabled_reason' => 'cash_mode_disabled',
                'environments' => [],
                'allowed_countries' => [],
                'blocked_countries' => [],
                'minimum_age' => null,
                'requires_age_verification' => true,
                'requires_kyc' => true,
                'requires_kyb' => true,
                'maximum_risk_score' => 0,
                'requires_responsible_play' => true,
            ],
        ];
    }

    /** @param list<string> $reasons @return array<string,mixed> */
    private function decision(string $feature, bool $allowed, array $reasons): array
    {
        return [
            'allowed' => $allowed,
            'feature' => $feature,
            'policy_version' => self::POLICY_VERSION,
            'value_type' => 'virtual',
            'cash_mode' => false,
            'reasons' => array_values(array_unique($reasons)),
        ];
    }
}
