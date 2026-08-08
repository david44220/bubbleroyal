<?php

declare(strict_types=1);

namespace Addons\Identity\Domain;

use InvalidArgumentException;

final class IdentityService
{
    public function __construct(
        private readonly UserRepository $users,
    ) {
    }

    /** @return array<string,mixed> */
    public function register(string $email, string $password, string $displayName, ?int $createdAt = null): array
    {
        $email = $this->normaliseEmail($email);
        $displayName = $this->text($displayName, 'Display name', 2, 80);
        $this->assertPassword($password);
        if ($this->users->findByEmail($email) !== null) {
            throw new InvalidArgumentException('An account already exists for this email.');
        }

        $createdAt ??= time();
        $user = [
            'id' => bin2hex(random_bytes(16)),
            'email' => $email,
            'email_normalized' => $email,
            'display_name' => $displayName,
            'password_hash' => $this->hashPassword($password),
            'role' => 'player',
            'status' => 'active',
            'locale' => 'en',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
            'last_login_at' => null,
        ];
        $this->users->create($user);
        return $this->publicUser($user);
    }

    /** @return array<string,mixed>|null */
    public function authenticate(string $email, string $password, ?int $at = null): ?array
    {
        $email = $this->normaliseEmail($email);
        $user = $this->users->findByEmail($email);
        if ($user === null || ($user['status'] ?? null) !== 'active'
            || !password_verify($password, (string) ($user['password_hash'] ?? ''))) {
            return null;
        }

        $at ??= time();
        $algorithm = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
        if (password_needs_rehash((string) $user['password_hash'], $algorithm)) {
            $replacementHash = $this->hashPassword($password);
            $this->users->updatePasswordHash((string) $user['id'], $replacementHash, $at);
            $user['password_hash'] = $replacementHash;
        }
        $this->users->touchLogin((string) $user['id'], $at);
        $user['last_login_at'] = $at;
        return $this->publicUser($user);
    }

    /** @return array<string,mixed> */
    public function publicUser(array $user): array
    {
        return [
            'id' => (string) $user['id'],
            'email' => (string) $user['email'],
            'display_name' => (string) $user['display_name'],
            'role' => (string) $user['role'],
            'status' => (string) $user['status'],
            'locale' => (string) ($user['locale'] ?? 'en'),
            'created_at' => (int) $user['created_at'],
        ];
    }

    private function normaliseEmail(string $email): string
    {
        $email = strtolower(trim($email));
        if ($email === '' || strlen($email) > 320 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('A valid email address is required.');
        }
        return $email;
    }

    private function assertPassword(string $password): void
    {
        if (strlen($password) < 12 || strlen($password) > 200) {
            throw new InvalidArgumentException('Password must contain 12 to 200 characters.');
        }
    }

    private function hashPassword(string $password): string
    {
        $algorithm = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
        $hash = password_hash($password, $algorithm);
        if ($hash === false) {
            throw new InvalidArgumentException('Password hashing is unavailable.');
        }
        return $hash;
    }

    private function text(string $value, string $label, int $minimum, int $maximum): string
    {
        $value = trim($value);
        $length = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
        if ($length < $minimum || $length > $maximum) {
            throw new InvalidArgumentException($label . ' has an invalid length.');
        }
        return $value;
    }
}
