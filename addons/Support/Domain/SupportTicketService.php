<?php

declare(strict_types=1);

namespace Addons\Support\Domain;

use InvalidArgumentException;

final class SupportTicketService
{
    public const POLICY_VERSION = 'br-support-1';

    public function __construct(
        private readonly SupportTicketStore $store,
    ) {
    }

    /** @return array<string,mixed> */
    public function create(
        string $playerId,
        string $category,
        string $subject,
        string $message,
        string $eventId,
        ?int $createdAt = null,
    ): array {
        $playerId = $this->assertIdentifier($playerId, 'Player ID');
        $subject = $this->assertText($subject, 'Ticket subject', 160);
        $message = $this->assertText($message, 'Ticket message', 5000);
        if (!in_array($category, ['support', 'technical', 'result_dispute', 'responsible_play'], true)) {
            throw new InvalidArgumentException('Unsupported support ticket category.');
        }
        $eventId = $this->assertIdentifier($eventId, 'Support event ID');
        $ticketId = hash('sha256', self::POLICY_VERSION . '|' . $eventId);
        $existing = $this->store->find($ticketId);
        if ($existing !== null) {
            return ['idempotent' => true, 'ticket' => $existing];
        }

        $createdAt ??= time();
        $ticket = [
            'ticket_id' => $ticketId,
            'player_id' => $playerId,
            'category' => $category,
            'subject' => $subject,
            'status' => 'open',
            'messages' => [[
                'author' => 'player',
                'message' => $message,
                'created_at' => $createdAt,
            ]],
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
            'policy_version' => self::POLICY_VERSION,
        ];
        $this->store->save($ticket);
        return ['idempotent' => false, 'ticket' => $ticket];
    }

    /** @return array<string,mixed> */
    public function transition(string $ticketId, string $status, string $actorId, ?int $at = null): array
    {
        $ticket = $this->get($ticketId);
        if (!in_array($status, ['open', 'pending', 'resolved', 'closed'], true)) {
            throw new InvalidArgumentException('Unsupported support ticket status.');
        }
        $actorId = $this->assertIdentifier($actorId, 'Actor ID');
        $at ??= time();
        $ticket['status'] = $status;
        $ticket['updated_at'] = $at;
        $ticket['messages'][] = ['author' => $actorId, 'message' => 'status:' . $status, 'created_at' => $at];
        $this->store->save($ticket);
        return $ticket;
    }

    /** @return list<array<string,mixed>> */
    public function all(): array
    {
        return $this->store->all();
    }

    /** @return array<string,mixed> */
    private function get(string $ticketId): array
    {
        $ticket = $this->store->find($this->assertIdentifier($ticketId, 'Ticket ID'));
        if ($ticket === null) {
            throw new InvalidArgumentException('Support ticket not found.');
        }
        return $ticket;
    }

    private function assertText(string $value, string $label, int $maxLength): string
    {
        $value = trim($value);
        if ($value === '' || strlen($value) > $maxLength) {
            throw new InvalidArgumentException($label . ' has an invalid length.');
        }
        return $value;
    }

    private function assertIdentifier(string $value, string $label): string
    {
        $value = trim($value);
        if ($value === '' || strlen($value) > 191) {
            throw new InvalidArgumentException($label . ' must contain 1 to 191 characters.');
        }
        return $value;
    }
}
