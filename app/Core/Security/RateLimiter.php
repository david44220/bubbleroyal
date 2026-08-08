<?php

declare(strict_types=1);

namespace App\Core\Security;

use RuntimeException;

final class RateLimiter implements RateLimiterInterface
{
    public function __construct(
        private readonly string $directory,
    ) {
    }

    public function allow(string $key, int $limit, int $windowSeconds): bool
    {
        if ($limit < 1 || $windowSeconds < 1) {
            throw new RuntimeException('Rate-limit configuration is invalid.');
        }

        if (!is_dir($this->directory) && !mkdir($this->directory, 0700, true) && !is_dir($this->directory)) {
            return false;
        }

        $path = rtrim($this->directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . hash('sha256', $key) . '.json';
        $handle = fopen($path, 'c+');
        if ($handle === false || !flock($handle, LOCK_EX)) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            return false;
        }

        try {
            $raw = stream_get_contents($handle);
            $state = is_string($raw) && trim($raw) !== '' ? json_decode($raw, true) : null;
            $now = time();
            if (!is_array($state) || !is_int($state['started_at'] ?? null)
                || $now - $state['started_at'] >= $windowSeconds) {
                $state = ['started_at' => $now, 'count' => 0];
            }

            $allowed = (int) $state['count'] < $limit;
            if ($allowed) {
                $state['count']++;
            }

            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, json_encode($state, JSON_THROW_ON_ERROR));
            fflush($handle);
            return $allowed;
        } catch (\Throwable) {
            return false;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
