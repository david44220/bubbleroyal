<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Bootstrap.php';

use Addons\Analytics\Domain\OperationsReportService;
use Addons\Notifications\Domain\InMemoryNotificationOutboxStore;
use Addons\Notifications\Domain\NotificationService;
use Addons\Support\Domain\InMemorySupportTicketStore;
use Addons\Support\Domain\SupportTicketService;

function phase17True(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$notifications = new NotificationService(new InMemoryNotificationOutboxStore());
$queued = $notifications->enqueue('player-a', 'in_app', 'replay_approved', ['tournament' => 'daily-precision'], 'notify-a', 1700000300);
phase17True($queued['notification']['status'] === 'queued', 'Notifications must enter the outbox before dispatch.');
$queuedAgain = $notifications->enqueue('player-a', 'in_app', 'replay_approved', ['tournament' => 'daily-precision'], 'notify-a', 1700000301);
phase17True($queuedAgain['idempotent'] === true, 'Notifications must be deduplicated.');

$tickets = new SupportTicketService(new InMemorySupportTicketStore());
$ticket = $tickets->create('player-a', 'result_dispute', 'Replay review question', 'Please review my virtual result.', 'ticket-a', 1700000302);
phase17True($ticket['ticket']['status'] === 'open', 'Support tickets must start open.');
$ticketAgain = $tickets->create('player-a', 'result_dispute', 'Replay review question', 'Please review my virtual result.', 'ticket-a', 1700000303);
phase17True($ticketAgain['idempotent'] === true, 'Support ticket creation must be idempotent.');
$resolved = $tickets->transition($ticket['ticket']['ticket_id'], 'resolved', 'operator-a', 1700000304);
phase17True($resolved['status'] === 'resolved', 'Operators must be able to resolve a support ticket.');
phase17True(count($resolved['messages']) === 2, 'Ticket status changes must be retained in history.');

$report = (new OperationsReportService())->summarize([
    ['type' => 'notification', 'status' => 'queued', 'value_type' => 'virtual', 'cash_mode' => false],
    ['type' => 'support_ticket', 'status' => 'resolved', 'value_type' => 'virtual', 'cash_mode' => false],
]);
phase17True($report['event_count'] === 2, 'Operations reports must count input events.');
phase17True($report['virtual_events'] === 2 && $report['cash_events'] === 0, 'Operations reports must preserve virtual/cash separation.');

echo "PHP phase 17 tests passed.\n";
