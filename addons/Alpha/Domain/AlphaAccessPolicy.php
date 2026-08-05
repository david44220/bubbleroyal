<?php

declare(strict_types=1);

namespace Addons\Alpha\Domain;

use InvalidArgumentException;

final class AlphaAccessPolicy
{
    public const POLICY_VERSION = 'br-alpha-1';

    /** @param array<string,mixed> $context @return array<string,mixed> */
    public function evaluate(string $playerId, array $context): array
    {
        $playerId = trim($playerId);
        if ($playerId === '' || strlen($playerId) > 191) {
            throw new InvalidArgumentException('Player ID must contain 1 to 191 characters.');
        }

        $reasons = [];
        $environment = (string) ($context['environment'] ?? '');
        if (!in_array($environment, ['local', 'sandbox', 'staging'], true)) {
            $reasons[] = 'alpha_environment_required';
        }
        if (($context['alpha_enabled'] ?? false) !== true) {
            $reasons[] = 'alpha_disabled';
        }

        $allowlist = $context['allowlist'] ?? [];
        if (!is_array($allowlist)) {
            $reasons[] = 'alpha_allowlist_invalid';
        } elseif ($allowlist !== [] && !in_array($playerId, $allowlist, true)) {
            $reasons[] = 'player_not_allowlisted';
        }

        if (($context['value_type'] ?? 'virtual') !== 'virtual') {
            $reasons[] = 'virtual_value_type_required';
        }
        if (($context['cash_mode'] ?? false) !== false) {
            $reasons[] = 'cash_mode_disabled';
        }
        if (($context['risk_status'] ?? '') !== 'accepted') {
            $reasons[] = 'risk_acceptance_required';
        }

        return [
            'allowed' => $reasons === [],
            'player_id' => $playerId,
            'feature' => 'virtual_alpha',
            'environment' => $environment,
            'policy_version' => self::POLICY_VERSION,
            'value_type' => 'virtual',
            'cash_mode' => false,
            'reasons' => array_values(array_unique($reasons)),
        ];
    }
}
