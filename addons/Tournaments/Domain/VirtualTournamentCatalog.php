<?php

declare(strict_types=1);

namespace Addons\Tournaments\Domain;

use Addons\Game\Domain\BubblePracticeEngine;

final class VirtualTournamentCatalog
{
    /** @return list<array<string, mixed>> */
    public static function templates(): array
    {
        return [
            [
                'id' => 'daily-precision',
                'name' => 'Daily Precision',
                'description' => 'A free daily board for clean lines and controlled combos.',
                'duration_seconds' => 86400,
                'capacity' => 1000,
                'entry_type' => 'free_virtual',
                'ticket_cost' => 0,
                'mode' => 'practice',
                'value_type' => 'virtual',
                'cash_mode' => false,
                'rules_version' => BubblePracticeEngine::RULES_VERSION,
            ],
            [
                'id' => 'weekly-cascade',
                'name' => 'Weekly Cascade',
                'description' => 'A longer virtual competition focused on drops and consistency.',
                'duration_seconds' => 604800,
                'capacity' => 5000,
                'entry_type' => 'free_virtual',
                'ticket_cost' => 0,
                'mode' => 'practice',
                'value_type' => 'virtual',
                'cash_mode' => false,
                'rules_version' => BubblePracticeEngine::RULES_VERSION,
            ],
        ];
    }
}
