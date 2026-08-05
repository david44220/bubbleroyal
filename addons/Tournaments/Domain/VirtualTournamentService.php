<?php

declare(strict_types=1);

namespace Addons\Tournaments\Domain;

use InvalidArgumentException;

final class VirtualTournamentService
{
    /** @var \Closure():int */
    private readonly \Closure $clock;

    /** @var array<string, array<string, mixed>> */
    private array $tournaments = [];

    /** @var array<string, list<array<string, mixed>>> */
    private array $entries = [];

    public function __construct(?\Closure $clock = null)
    {
        $this->clock = $clock ?? static fn (): int => time();
        $now = ($this->clock)();
        $periodStart = intdiv($now, 86400) * 86400;

        foreach (VirtualTournamentCatalog::templates() as $template) {
            $tournamentId = (string) $template['id'];
            $this->tournaments[$tournamentId] = $template + [
                'starts_at_unix' => $periodStart,
                'ends_at_unix' => $periodStart + (int) $template['duration_seconds'],
            ];
            $this->entries[$tournamentId] = [];
        }
    }

    /** @return list<array<string, mixed>> */
    public function list(?int $at = null): array
    {
        $at ??= ($this->clock)();
        $tournaments = [];
        foreach ($this->tournaments as $tournament) {
            $tournaments[] = $this->publicTournament($tournament, $at);
        }

        usort($tournaments, static fn (array $left, array $right): int => strcmp(
            (string) $left['id'],
            (string) $right['id'],
        ));

        return $tournaments;
    }

    /** @return list<array<string, mixed>> */
    public function listOpen(?int $at = null): array
    {
        return array_values(array_filter(
            $this->list($at),
            static fn (array $tournament): bool => $tournament['status'] === 'open',
        ));
    }

    /** @return array<string, mixed> */
    public function lobby(string $tournamentId, ?int $at = null): array
    {
        $tournament = $this->tournament($tournamentId);
        $at ??= ($this->clock)();
        $entries = $this->entries[$tournamentId] ?? [];

        return [
            'tournament' => $this->publicTournament($tournament, $at),
            'entry_count' => count($entries),
            'remaining_capacity' => max(0, (int) $tournament['capacity'] - count($entries)),
            'entries' => array_values($entries),
        ];
    }

    /**
     * Add a free virtual entry backed by a server-verified practice result.
     * There is deliberately no cash amount, payment, wallet or settlement field.
     *
     * @param array<string, mixed> $verification
     * @return array<string, mixed>
     */
    public function enter(
        string $playerId,
        string $tournamentId,
        array $verification,
        ?int $enteredAt = null,
    ): array {
        $playerId = $this->assertId($playerId, 'Player ID');
        $tournament = $this->tournament($tournamentId);
        $enteredAt ??= ($this->clock)();
        if ($this->statusAt($tournament, $enteredAt) !== 'open') {
            throw new InvalidArgumentException('Tournament lobby is not open.');
        }

        $isVerified = ($verification['valid'] ?? false) === true
            || ($verification['verified'] ?? false) === true;
        if (!$isVerified || ($verification['mode'] ?? null) !== 'practice'
            || ($verification['value_type'] ?? null) !== 'virtual') {
            throw new InvalidArgumentException('Only verified virtual practice results may enter.');
        }

        $sessionId = $verification['session_id'] ?? null;
        if (!is_string($sessionId) || trim($sessionId) === '') {
            throw new InvalidArgumentException('A verified session ID is required.');
        }

        foreach ($this->entries[$tournamentId] as $existing) {
            if ($existing['player_id'] === $playerId) {
                return [
                    'idempotent' => true,
                    'entry' => $existing,
                    'lobby' => $this->lobby($tournamentId, $enteredAt),
                ];
            }
        }

        if (count($this->entries[$tournamentId]) >= (int) $tournament['capacity']) {
            throw new InvalidArgumentException('Tournament lobby is full.');
        }

        $entry = [
            'entry_id' => hash('sha256', $tournamentId . '|' . $playerId . '|' . $sessionId),
            'tournament_id' => $tournamentId,
            'player_id' => $playerId,
            'verified_session_id' => $sessionId,
            'verified' => true,
            'mode' => 'practice',
            'value_type' => 'virtual',
            'entry_type' => 'free_virtual',
            'ticket_cost' => 0,
            'cash_mode' => false,
            'score' => max(0, (int) ($verification['score'] ?? 0)),
            'status' => (string) ($verification['status'] ?? 'out'),
            'best_combo' => max(0, (int) ($verification['best_combo'] ?? 0)),
            'shots_used' => max(0, (int) ($verification['shots_verified'] ?? 0)),
            'replay_review' => 'pending',
            'verified_at' => $enteredAt,
        ];
        $this->entries[$tournamentId][] = $entry;

        return [
            'idempotent' => false,
            'entry' => $entry,
            'lobby' => $this->lobby($tournamentId, $enteredAt),
        ];
    }

    /** @return array<string, mixed> */
    private function tournament(string $tournamentId): array
    {
        $tournamentId = trim($tournamentId);
        if (!array_key_exists($tournamentId, $this->tournaments)) {
            throw new InvalidArgumentException('Unknown virtual tournament.');
        }

        return $this->tournaments[$tournamentId];
    }

    /** @param array<string, mixed> $tournament @return array<string, mixed> */
    private function publicTournament(array $tournament, int $at): array
    {
        $public = $tournament;
        unset($public['starts_at_unix'], $public['ends_at_unix'], $public['duration_seconds']);
        $public['starts_at'] = gmdate(DATE_ATOM, (int) $tournament['starts_at_unix']);
        $public['ends_at'] = gmdate(DATE_ATOM, (int) $tournament['ends_at_unix']);
        $public['status'] = $this->statusAt($tournament, $at);
        return $public;
    }

    /** @param array<string, mixed> $tournament */
    private function statusAt(array $tournament, int $at): string
    {
        if ($at < (int) $tournament['starts_at_unix']) {
            return 'scheduled';
        }
        if ($at >= (int) $tournament['ends_at_unix']) {
            return 'closed';
        }

        return 'open';
    }

    private function assertId(string $value, string $label): string
    {
        $value = trim($value);
        if ($value === '' || strlen($value) > 128) {
            throw new InvalidArgumentException($label . ' must contain 1 to 128 characters.');
        }

        return $value;
    }
}
