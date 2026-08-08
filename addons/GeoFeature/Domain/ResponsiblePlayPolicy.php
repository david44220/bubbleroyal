<?php

declare(strict_types=1);

namespace Addons\GeoFeature\Domain;

final class ResponsiblePlayPolicy
{
    /**
     * @param array<string,mixed> $context
     * @param array<string,mixed> $rule
     * @return list<string>
     */
    public function violations(array $context, array $rule): array
    {
        if (($rule['requires_responsible_play'] ?? false) !== true) {
            return [];
        }

        $status = (string) ($context['responsible_play_status'] ?? 'unknown');
        $violations = [];
        if ($status !== 'active') {
            $violations[] = 'responsible_play_' . $status;
        }

        $now = (int) ($context['now'] ?? time());
        $coolingOffUntil = (int) ($context['cooling_off_until'] ?? 0);
        if ($coolingOffUntil > $now) {
            $violations[] = 'cooling_off_active';
        }

        $minutesLimit = $context['daily_minutes_limit'] ?? null;
        if (is_int($minutesLimit) && $minutesLimit >= 0
            && (int) ($context['minutes_today'] ?? 0) >= $minutesLimit) {
            $violations[] = 'daily_time_limit_reached';
        }

        $sessionsLimit = $context['daily_sessions_limit'] ?? null;
        if (is_int($sessionsLimit) && $sessionsLimit >= 0
            && (int) ($context['sessions_today'] ?? 0) >= $sessionsLimit) {
            $violations[] = 'daily_session_limit_reached';
        }

        return array_values(array_unique($violations));
    }
}
