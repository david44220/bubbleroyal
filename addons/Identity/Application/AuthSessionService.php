<?php

declare(strict_types=1);

namespace Addons\Identity\Application;

use Addons\Identity\Domain\SessionRepository;
use Addons\Identity\Domain\UserRepository;
use InvalidArgumentException;

final class AuthSessionService
{
    public const TTL_SECONDS = 2592000;

    public function __construct(
        private readonly UserRepository $users,
        private readonly SessionRepository $sessions,
    ) {
    }

    /** @return array{token:string,csrf_token:string,session:array<string,mixed>} */
    public function create(string $userId, string $ipAddress, string $userAgent, ?int $now = null): array
    {
        $userId = $this->identifier($userId, 'User ID');
        $now ??= time();
        $token = bin2hex(random_bytes(32));
        $csrf = bin2hex(random_bytes(32));
        $session = [
            'session_id' => bin2hex(random_bytes(16)),
            'user_id' => $userId,
            'token_hash' => hash('sha256', $token),
            'csrf_hash' => hash('sha256', $csrf),
            'ip_hash' => hash('sha256', trim($ipAddress)),
            'user_agent' => substr(trim($userAgent), 0, 500),
            'created_at' => $now,
            'last_seen_at' => $now,
            'expires_at' => $now + self::TTL_SECONDS,
            'revoked_at' => null,
        ];
        $this->sessions->create($session);

        return [
            'token' => $token,
            'csrf_token' => $csrf,
            'session' => [
                'session_id' => $session['session_id'],
                'user_id' => $userId,
                'expires_at' => $session['expires_at'],
            ],
        ];
    }

    /** @return array{session:array<string,mixed>,user:array<string,mixed>}|null */
    public function authenticate(string $token, ?int $now = null): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        $now ??= time();
        $session = $this->sessions->findActiveByTokenHash(hash('sha256', $token), $now);
        if ($session === null) {
            return null;
        }
        $user = $this->users->findById((string) $session['user_id']);
        if ($user === null || ($user['status'] ?? null) !== 'active') {
            return null;
        }
        $this->sessions->touch((string) $session['session_id'], $now);
        return ['session' => $session, 'user' => $user];
    }

    public function assertCsrf(array $session, string $csrfToken): void
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $csrfToken)
            || !hash_equals((string) ($session['csrf_hash'] ?? ''), hash('sha256', $csrfToken))) {
            throw new InvalidArgumentException('CSRF validation failed.');
        }
    }

    public function revoke(array $session, ?int $now = null): void
    {
        $now ??= time();
        $this->sessions->revoke((string) $session['session_id'], $now);
    }

    private function identifier(string $value, string $label): string
    {
        $value = trim($value);
        if ($value === '' || strlen($value) > 191) {
            throw new InvalidArgumentException($label . ' is invalid.');
        }
        return $value;
    }
}
