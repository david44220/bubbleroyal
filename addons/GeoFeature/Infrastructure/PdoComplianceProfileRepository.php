<?php

declare(strict_types=1);

namespace Addons\GeoFeature\Infrastructure;

use Addons\GeoFeature\Domain\ComplianceProfileRepository;
use PDO;

final class PdoComplianceProfileRepository implements ComplianceProfileRepository
{
    public function __construct(
        private readonly PDO $connection,
    ) {
    }

    public function find(string $playerId): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT player_id, country_code, region_code, age_years, age_verified, kyc_status, kyb_status, '
            . 'risk_score, responsible_play_status, UNIX_TIMESTAMP(cooling_off_until) AS cooling_off_until, '
            . 'minutes_today, sessions_today, daily_minutes_limit, daily_sessions_limit '
            . 'FROM br_player_compliance_profiles WHERE player_id = :player_id LIMIT 1',
        );
        $statement->execute(['player_id' => $playerId]);
        $profile = $statement->fetch();
        if (!is_array($profile)) {
            return null;
        }
        foreach (['age_years', 'risk_score', 'cooling_off_until', 'minutes_today', 'sessions_today', 'daily_minutes_limit', 'daily_sessions_limit'] as $key) {
            if ($profile[$key] !== null) {
                $profile[$key] = (int) $profile[$key];
            }
        }
        $profile['age_verified'] = (bool) $profile['age_verified'];
        return $profile;
    }
}
