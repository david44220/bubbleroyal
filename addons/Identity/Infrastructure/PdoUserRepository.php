<?php

declare(strict_types=1);

namespace Addons\Identity\Infrastructure;

use Addons\Identity\Domain\UserRepository;
use PDO;
use PDOException;
use RuntimeException;

final class PdoUserRepository implements UserRepository
{
    public function __construct(
        private readonly PDO $connection,
    ) {
    }

    public function findById(string $userId): ?array
    {
        $statement = $this->connection->prepare('SELECT * FROM br_users WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $userId]);
        $user = $statement->fetch();
        return is_array($user) ? $this->normalise($user) : null;
    }

    public function findByEmail(string $email): ?array
    {
        $statement = $this->connection->prepare('SELECT * FROM br_users WHERE email_normalized = :email LIMIT 1');
        $statement->execute(['email' => strtolower(trim($email))]);
        $user = $statement->fetch();
        return is_array($user) ? $this->normalise($user) : null;
    }

    public function create(array $user): void
    {
        try {
            $statement = $this->connection->prepare(
                'INSERT INTO br_users '
                . '(id, email, email_normalized, display_name, password_hash, role, status, locale, created_at, updated_at) '
                . 'VALUES (:id, :email, :email_normalized, :display_name, :password_hash, :role, :status, :locale, '
                . 'FROM_UNIXTIME(:created_at), FROM_UNIXTIME(:updated_at))',
            );
            $statement->execute([
                'id' => $user['id'],
                'email' => $user['email'],
                'email_normalized' => $user['email_normalized'],
                'display_name' => $user['display_name'],
                'password_hash' => $user['password_hash'],
                'role' => $user['role'],
                'status' => $user['status'],
                'locale' => $user['locale'],
                'created_at' => $user['created_at'],
                'updated_at' => $user['updated_at'],
            ]);
        } catch (PDOException $exception) {
            throw new RuntimeException('Unable to create user.', 0, $exception);
        }
    }

    public function updatePasswordHash(string $userId, string $passwordHash, int $at): void
    {
        $statement = $this->connection->prepare(
            'UPDATE br_users SET password_hash = :password_hash, updated_at = FROM_UNIXTIME(:updated_at) '
            . 'WHERE id = :id',
        );
        $statement->execute([
            'id' => $userId,
            'password_hash' => $passwordHash,
            'updated_at' => $at,
        ]);
    }

    public function touchLogin(string $userId, int $at): void
    {
        $statement = $this->connection->prepare(
            'UPDATE br_users SET last_login_at = FROM_UNIXTIME(:login_at), updated_at = FROM_UNIXTIME(:updated_at) WHERE id = :id',
        );
        $statement->execute(['id' => $userId, 'login_at' => $at, 'updated_at' => $at]);
    }

    /** @return array<string,mixed> */
    private function normalise(array $user): array
    {
        foreach (['created_at', 'updated_at', 'last_login_at'] as $key) {
            if (array_key_exists($key, $user) && $user[$key] !== null && is_string($user[$key])) {
                $user[$key] = strtotime($user[$key]);
            }
        }
        return $user;
    }
}
