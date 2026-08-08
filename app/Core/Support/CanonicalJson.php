<?php

declare(strict_types=1);

namespace App\Core\Support;

use JsonException;

final class CanonicalJson
{
    /**
     * Encode JSON with recursively sorted object keys.
     *
     * Replay documents use this representation whenever a hash or signature
     * is calculated. List ordering is preserved; associative keys are sorted.
     */
    public static function encode(mixed $value): string
    {
        return json_encode(
            self::normalize($value),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR,
        );
    }

    /** @return mixed */
    private static function normalize(mixed $value): mixed
    {
        if (is_object($value)) {
            $value = get_object_vars($value);
        }

        if (!is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(self::normalize(...), $value);
        }

        $keys = array_keys($value);
        usort($keys, static fn (int|string $left, int|string $right): int => strcmp((string) $left, (string) $right));

        $normalized = [];
        foreach ($keys as $key) {
            $normalized[(string) $key] = self::normalize($value[$key]);
        }

        return $normalized;
    }

    /** @throws JsonException */
    public static function hash(string $value): string
    {
        return hash('sha256', $value);
    }
}
