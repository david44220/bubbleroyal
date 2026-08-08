<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Bootstrap.php';

use Addons\Payments\Application\SandboxPaymentService;
use Addons\Payments\Domain\SandboxPaymentProvider;

function phase16True(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function phase16Throws(Closure $callback, string $message): void
{
    try {
        $callback();
    } catch (Throwable) {
        return;
    }
    throw new RuntimeException($message);
}

$service = new SandboxPaymentService(
    new SandboxPaymentProvider(),
    'sandbox-webhook-secret-change-me-0123456789',
);
$intent = $service->createIntent('player-a', 'EUR', 1299, 'payment-a', 'sandbox');
phase16True($intent['status'] === 'requires_confirmation', 'Sandbox intent must start in confirmation state.');
phase16True($intent['live_enabled'] === false && $intent['cash_mode'] === false, 'Sandbox provider must be live-disabled.');

$sameIntent = $service->createIntent('player-a', 'EUR', 1299, 'payment-a', 'sandbox');
phase16True($sameIntent['provider_intent_id'] === $intent['provider_intent_id'], 'Intent creation must be idempotent.');

$captured = $service->capture($intent['provider_intent_id'], 'payment-event-capture');
phase16True($captured['status'] === 'captured', 'Sandbox capture transition must be explicit.');
$refunded = $service->refund($intent['provider_intent_id'], 'payment-event-refund');
phase16True($refunded['status'] === 'refunded', 'Sandbox refund transition must be explicit.');

$payload = [
    'event_id' => 'payment-event-capture',
    'type' => 'payment.captured',
    'provider_intent_id' => $intent['provider_intent_id'],
];
$signed = $service->signWebhook($payload);
phase16True($service->verifyWebhook($payload, $signed['signature'])['event_id'] === $payload['event_id'], 'Valid webhook signatures must verify canonical payloads.');
$tampered = $payload;
$tampered['type'] = 'payment.refunded';
phase16Throws(
    static fn (): array => $service->verifyWebhook($tampered, $signed['signature']),
    'Tampered webhook payloads must be rejected.',
);

$report = $service->reconcile();
phase16True($report['status'] === 'reconciled', 'Sandbox provider events must reconcile with local intents.');
phase16Throws(
    static fn (): array => $service->createIntent('player-a', 'EUR', 1299, 'payment-live', 'production'),
    'Live payment environments must remain unavailable.',
);

echo "PHP phase 16 tests passed.\n";
