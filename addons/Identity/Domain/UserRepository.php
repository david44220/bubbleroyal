<?php

declare(strict_types=1);

namespace Addons\Identity\Domain;

interface UserRepository
{
    /** @return array<string,mixed>|null */
    public function findById(string $userId): ?array;

    /** @return array<string,mixed>|null */
    public function findByEmail(string $email): ?array;

    /** @param array<string,mixed> $user */
    public function create(array $user): void;

    public function touchLogin(string $userId, int $at): void;
}
