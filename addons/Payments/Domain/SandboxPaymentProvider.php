<?php

declare(strict_types=1);

namespace Addons\Payments\Domain;

use InvalidArgumentException;

final class SandboxPaymentProvider implements PaymentProvider
{
    /** @var array<string,array<string,mixed>> */
    private array $intents = [];

    /** @var list<array<string,mixed>> */
    private array $providerEvents = [];

    public function createIntent(string $idempotencyKey, string $currency, int $minorUnits): array
    {
        $idempotencyKey = $this->assertIdentifier($idempotencyKey, 'Payment idempotency key');
        $currency = strtoupper(trim($currency));
        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new InvalidArgumentException('Currency must be a three-letter code.');
        }
        if ($minorUnits <= 0) {
            throw new InvalidArgumentException('Sandbox minor units must be positive.');
        }
        if (isset($this->intents[$idempotencyKey])) {
            return $this->intents[$idempotencyKey];
        }

        $intent = [
            'provider' => 'sandbox',
            'provider_intent_id' => 'sbx_' . hash('sha256', $idempotencyKey),
            'idempotency_key' => $idempotencyKey,
            'currency' => $currency,
            'sandbox_minor_units' => $minorUnits,
            'status' => 'requires_confirmation',
            'live_enabled' => false,
            'cash_mode' => false,
        ];
        $this->intents[$idempotencyKey] = $intent;
        return $intent;
    }

    public function capture(string $providerIntentId, string $eventId): array
    {
        return $this->transition($providerIntentId, $eventId, 'captured');
    }

    public function refund(string $providerIntentId, string $eventId): array
    {
        return $this->transition($providerIntentId, $eventId, 'refunded');
    }

    public function events(): array
    {
        return $this->providerEvents;
    }

    /** @return array<string,mixed> */
    private function transition(string $providerIntentId, string $eventId, string $nextStatus): array
    {
        $eventId = $this->assertIdentifier($eventId, 'Payment event ID');
        foreach ($this->intents as $key => $intent) {
            if ($intent['provider_intent_id'] !== $providerIntentId) {
                continue;
            }
            if ($intent['status'] === $nextStatus) {
                return $intent;
            }
            if ($nextStatus === 'captured' && $intent['status'] !== 'requires_confirmation') {
                throw new InvalidArgumentException('Sandbox payment intent cannot be captured from its current state.');
            }
            if ($nextStatus === 'refunded' && $intent['status'] !== 'captured') {
                throw new InvalidArgumentException('Sandbox payment intent must be captured before refund.');
            }

            $intent['status'] = $nextStatus;
            $this->intents[$key] = $intent;
            $this->providerEvents[] = [
                'event_id' => $eventId,
                'type' => 'payment.' . $nextStatus,
                'provider_intent_id' => $providerIntentId,
                'status' => $nextStatus,
                'sandbox_minor_units' => $intent['sandbox_minor_units'],
                'currency' => $intent['currency'],
            ];
            return $intent;
        }

        throw new InvalidArgumentException('Sandbox payment intent not found.');
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
