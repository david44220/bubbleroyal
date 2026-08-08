<?php

declare(strict_types=1);

namespace App\Core\Security;

interface RateLimiterInterface
{
    public function allow(string $key, int $limit, int $windowSeconds): bool;
}
