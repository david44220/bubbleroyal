<?php

declare(strict_types=1);

namespace Addons\Rewards\Domain;

final class ChallengeCatalog
{
    public const RULES_VERSION = 'br-rewards-1';

    /** @return list<array<string, mixed>> */
    public static function definitions(): array
    {
        return [
            [
                'id' => 'first-verified-run',
                'name' => 'First Verified Run',
                'description' => 'Complete one server-verified practice run.',
                'metric' => 'verified_runs',
                'target' => 1,
                'xp' => 100,
                'tickets' => 1,
                'achievement_id' => 'first-burst',
                'value_type' => 'virtual',
            ],
            [
                'id' => 'combo-builder',
                'name' => 'Combo Builder',
                'description' => 'Reach a best combo of three in a verified run.',
                'metric' => 'best_combo',
                'target' => 3,
                'xp' => 200,
                'tickets' => 2,
                'achievement_id' => 'combo-apprentice',
                'value_type' => 'virtual',
            ],
            [
                'id' => 'precision-session',
                'name' => 'Precision Session',
                'description' => 'Verify a run with at least five recorded shots.',
                'metric' => 'shots_verified',
                'target' => 5,
                'xp' => 150,
                'tickets' => 1,
                'achievement_id' => null,
                'value_type' => 'virtual',
            ],
            [
                'id' => 'arena-finisher',
                'name' => 'Arena Finisher',
                'description' => 'Clear the board in a verified practice run.',
                'metric' => 'board_cleared',
                'target' => 1,
                'xp' => 500,
                'tickets' => 3,
                'achievement_id' => 'arena-finisher',
                'value_type' => 'virtual',
            ],
        ];
    }

    /** @return list<array<string, string>> */
    public static function achievements(): array
    {
        return [
            [
                'id' => 'first-burst',
                'name' => 'First Burst',
                'description' => 'Your first verified run is on the board.',
            ],
            [
                'id' => 'combo-apprentice',
                'name' => 'Combo Apprentice',
                'description' => 'Build a three-pop combo.',
            ],
            [
                'id' => 'arena-finisher',
                'name' => 'Arena Finisher',
                'description' => 'Clear a practice board.',
            ],
        ];
    }

    /** @return array<string, string>|null */
    public static function achievement(string $achievementId): ?array
    {
        foreach (self::achievements() as $achievement) {
            if ($achievement['id'] === $achievementId) {
                return $achievement;
            }
        }

        return null;
    }
}
