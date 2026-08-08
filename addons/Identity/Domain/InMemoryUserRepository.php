<?php

declare(strict_types=1);

namespace Addons\Identity\Domain;

use InvalidArgumentException;

final class InMemoryUserRepository implements UserRepository
{
    /** @var array<string,array<string,mixed>> */
    private array $users = [];

    public function findById(string $userId): ?array
    {
        return $this->users[$userId] ?? null;
    }

    public function findByEmail(string $email): ?array
    {
        $email = strtolower(trim($email));
        foreach ($this->users as $user) {
            if ($user['email_normalized'] === $email) {
                return $user;
            }
        }
        return null;
    }

    public function create(array $user): void
    {
        if (isset($this->users[$user['id']]) || $this->findByEmail((string) $user['email_normalized']) !== null) {
            throw new InvalidArgumentException('User already exists.');
        }
        $this->users[$user['id']] = $user;
    }

    public function updatePasswordHash(string $userId, string $passwordHash, int $at): void
    {
        if (!isset($this->users[$userId])) {
            return;
        }
        $this->users[$userId]['password_hash'] = $passwordHash;
        $this->users[$userId]['updated_at'] = $at;
    }

    public function touchLogin(string $userId, int $at): void
    {
        if (isset($this->users[$userId])) {
            $this->users[$userId]['last_login_at'] = $at;
            $this->users[$userId]['updated_at'] = $at;
        }
    }
}
