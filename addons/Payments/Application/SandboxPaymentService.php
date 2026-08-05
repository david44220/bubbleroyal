<?php

declare(strict_types=1);

namespace Addons\Payments\Application;

use Addons\Payments\Domain\PaymentProvider;
use App\Core\Security\HmacSigner;
use App\Core\Support\CanonicalJson;
use InvalidArgumentException;

final class SandboxPaymentService
{
    /** @var array<string,array<string,mixed>> */
    private array $localIntents = [];

    public function __construct(
        private readonly PaymentProvider $provider,
        private readonly string $webhookSecret,
    ) {
        if (strlen($this->webhookSecret) < 32) {
            throw new InvalidArgumentException('Sandbox webhook secret must contain at least 32 characters.');
        }
    }

    /** @return array<string,mixed> */
    public function createIntent(
        string $playerId,
        string $currency,
        int $sandboxMinorUnits,
        string $idempotencyKey,
        string $environment = 'sandbox',
    ): array {
        $this->assertSandbox($environment);
        $playerId = $this->assertIdentifier($playerId, 'Player ID');
        $intent = $this->provider->createIntent($idempotencyKey, $currency, $sandboxMinorUnits);
        $intent['player_id'] = $playerId;
        $intent['value_type'] = 'sandbox_payment';
        $this->localIntents[$intent['provider_intent_id']] = $intent;
        return $intent;
    }

    /** @return array<string,mixed> */
    public function capture(string $providerIntentId, string $eventId): array
    {
        $this->assertLocalIntent($providerIntentId);
        $captured = $this->provider->capture($providerIntentId, $eventId);
        $this->localIntents[$providerIntentId]['status'] = $captured['status'];
        return $captured;
    }

    /** @return array<string,mixed> */
    public function refund(string $providerIntentId, string $eventId): array
    {
        $this->assertLocalIntent($providerIntentId);
        $refunded = $this->provider->refund($providerIntentId, $eventId);
        $this->localIntents[$providerIntentId]['status'] = $refunded['status'];
        return $refunded;
    }

    /** @param array<string,mixed> $payload @return array{payload:array<string,mixed>,signature:string} */
    public function signWebhook(array $payload): array
    {
        return [
            'payload' => $payload,
            'signature' => HmacSigner::issue($payload, $this->webhookSecret),
        ];
    }

    /** @param array<string,mixed> $payload */
    public function verifyWebhook(array $payload, string $signature): array
    {
        $claims = HmacSigner::verify($signature, $this->webhookSecret);
        if (CanonicalJson::encode($claims) !== CanonicalJson::encode($payload)) {
            throw new InvalidArgumentException('Signed sandbox webhook payload does not match the request.');
        }
        return $claims;
    }

    /** @return array<string,mixed> */
    public function reconcile(): array
    {
        $providerEvents = $this->provider->events();
        $seen = [];
        foreach ($providerEvents as $event) {
            $providerIntentId = (string) ($event['provider_intent_id'] ?? '');
            $seen[$providerIntentId] = true;
        }

        $missingProviderEvents = [];
        foreach ($this->localIntents as $providerIntentId => $intent) {
            if (in_array($intent['status'], ['captured', 'refunded'], true) && !isset($seen[$providerIntentId])) {
                $missingProviderEvents[] = $providerIntentId;
            }
        }

        return [
            'provider' => 'sandbox',
            'status' => $missingProviderEvents === [] ? 'reconciled' : 'mismatch',
            'live_enabled' => false,
            'cash_mode' => false,
            'provider_event_count' => count($providerEvents),
            'missing_provider_events' => $missingProviderEvents,
            'local_intent_count' => count($this->localIntents),
        ];
    }

    private function assertSandbox(string $environment): void
    {
        if (!in_array($environment, ['local', 'sandbox', 'staging'], true)) {
            throw new InvalidArgumentException('Payment provider is available only in sandbox environments.');
        }
    }

    private function assertLocalIntent(string $providerIntentId): void
    {
        if (!array_key_exists($providerIntentId, $this->localIntents)) {
            throw new InvalidArgumentException('Sandbox payment intent is not owned by this service.');
        }
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
