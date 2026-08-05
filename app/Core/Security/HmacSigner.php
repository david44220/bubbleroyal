<?php

declare(strict_types=1);

namespace App\Core\Security;

use App\Core\Support\CanonicalJson;
use InvalidArgumentException;

final class HmacSigner
{
    public static function issue(array $claims, string $secret): string
    {
        self::assertSecret($secret);

        $payload = self::base64UrlEncode(CanonicalJson::encode($claims));
        $signature = self::base64UrlEncode(hash_hmac('sha256', $payload, $secret, true));

        return $payload . '.' . $signature;
    }

    /** @return array<string, mixed> */
    public static function verify(string $token, string $secret): array
    {
        self::assertSecret($secret);
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            throw new InvalidArgumentException('Malformed signed token.');
        }

        [$payloadPart, $signaturePart] = $parts;
        $expected = self::base64UrlEncode(hash_hmac('sha256', $payloadPart, $secret, true));
        if (!hash_equals($expected, $signaturePart)) {
            throw new InvalidArgumentException('Invalid signed token.');
        }

        $json = self::base64UrlDecode($payloadPart);
        $claims = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($claims)) {
            throw new InvalidArgumentException('Signed token claims must be an object.');
        }

        return $claims;
    }

    private static function assertSecret(string $secret): void
    {
        if (strlen($secret) < 32) {
            throw new InvalidArgumentException('Session secret must contain at least 32 characters.');
        }
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): string
    {
        $padding = strlen($value) % 4;
        if ($padding > 0) {
            $value .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        if ($decoded === false) {
            throw new InvalidArgumentException('Malformed signed token payload.');
        }

        return $decoded;
    }
}
