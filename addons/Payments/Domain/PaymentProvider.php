<?php

declare(strict_types=1);

namespace Addons\Payments\Domain;

interface PaymentProvider
{
    /** @return array<string,mixed> */
    public function createIntent(string $idempotencyKey, string $currency, int $minorUnits): array;

    /** @return array<string,mixed> */
    public function capture(string $providerIntentId, string $eventId): array;

    /** @return array<string,mixed> */
    public function refund(string $providerIntentId, string $eventId): array;

    /** @return list<array<string,mixed>> */
    public function events(): array;
}
