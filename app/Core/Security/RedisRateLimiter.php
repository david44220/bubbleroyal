<?php

declare(strict_types=1);

namespace App\Core\Security;

use RuntimeException;

final class RedisRateLimiter implements RateLimiterInterface
{
    public function __construct(
        private readonly object $redis,
        private readonly string $prefix = 'bubble-royale:rate-limit:',
    ) {
    }

    public function allow(string $key, int $limit, int $windowSeconds): bool
    {
        if ($limit < 1 || $windowSeconds < 1) {
            throw new RuntimeException('Rate-limit configuration is invalid.');
        }

        try {
            $result = $this->redis->eval(
                'local count = redis.call("INCR", KEYS[1]); '
                . 'if count == 1 then redis.call("EXPIRE", KEYS[1], ARGV[1]); end; return count;',
                [$this->prefix . hash('sha256', $key), (string) $windowSeconds],
                1,
            );
            return is_int($result) || is_string($result)
                ? (int) $result <= $limit
                : false;
        } catch (\Throwable) {
            // A shared limiter is a security dependency. Fail closed when it
            // cannot be reached instead of silently reverting to local state.
            return false;
        }
    }
}
