<?php

declare(strict_types=1);

namespace Addons\GameSessions\Application;

use Addons\Game\Domain\BubblePracticeEngine;
use App\Core\Security\HmacSigner;
use InvalidArgumentException;

final class SignedPracticeSessionService
{
    public function __construct(
        private readonly string $secret,
        private readonly int $ttlSeconds = 900,
    ) {
        if ($this->ttlSeconds < 60 || $this->ttlSeconds > 3600) {
            throw new InvalidArgumentException('Practice session TTL must be between 60 and 3600 seconds.');
        }
    }

    /** @return array{token:string,claims:array<string,mixed>} */
    public function issue(?int $seed = null, ?int $issuedAt = null, ?string $playerId = null): array
    {
        $issuedAt ??= time();
        $seed ??= random_int(1, 2147483647);
        $claims = [
            'version' => 1,
            'issuer' => 'bubble-royale',
            'session_id' => bin2hex(random_bytes(16)),
            'mode' => 'practice',
            'value_type' => 'virtual',
            'rules_version' => BubblePracticeEngine::RULES_VERSION,
            'seed' => $seed,
            'rows' => BubblePracticeEngine::DEFAULT_ROWS,
            'cols' => BubblePracticeEngine::DEFAULT_COLS,
            'issued_at' => $issuedAt,
            'expires_at' => $issuedAt + $this->ttlSeconds,
        ];
        if ($playerId !== null) {
            $playerId = trim($playerId);
            if ($playerId === '' || strlen($playerId) > 128) {
                throw new InvalidArgumentException('Practice session player ID is invalid.');
            }
            $claims['player_id'] = $playerId;
        }

        return [
            'token' => HmacSigner::issue($claims, $this->secret),
            'claims' => $claims,
        ];
    }

    /** @return array<string, mixed> */
    public function verify(string $token, ?int $now = null, ?string $expectedPlayerId = null): array
    {
        $claims = HmacSigner::verify($token, $this->secret);
        $now ??= time();
        $required = [
            'version', 'issuer', 'session_id', 'mode', 'value_type', 'rules_version',
            'seed', 'rows', 'cols', 'issued_at', 'expires_at',
        ];

        foreach ($required as $key) {
            if (!array_key_exists($key, $claims)) {
                throw new InvalidArgumentException('Signed session is missing a required claim.');
            }
        }

        if ($claims['version'] !== 1 || $claims['issuer'] !== 'bubble-royale') {
            throw new InvalidArgumentException('Unsupported signed session.');
        }
        if ($claims['mode'] !== 'practice' || $claims['value_type'] !== 'virtual') {
            throw new InvalidArgumentException('Only virtual practice sessions are accepted.');
        }
        if ($claims['rules_version'] !== BubblePracticeEngine::RULES_VERSION) {
            throw new InvalidArgumentException('Session rules version is no longer supported.');
        }
        if (!is_int($claims['issued_at']) || !is_int($claims['expires_at']) || $now < $claims['issued_at'] - 30) {
            throw new InvalidArgumentException('Invalid signed session timestamps.');
        }
        if ($now >= $claims['expires_at']) {
            throw new InvalidArgumentException('Signed practice session has expired.');
        }
        if ($claims['rows'] !== BubblePracticeEngine::DEFAULT_ROWS || $claims['cols'] !== BubblePracticeEngine::DEFAULT_COLS) {
            throw new InvalidArgumentException('Unsupported practice board dimensions.');
        }
        if (array_key_exists('player_id', $claims) && !is_string($claims['player_id'])) {
            throw new InvalidArgumentException('Invalid signed session player binding.');
        }
        if ($expectedPlayerId !== null && (($claims['player_id'] ?? null) !== $expectedPlayerId)) {
            throw new InvalidArgumentException('Signed practice session belongs to another player.');
        }

        return $claims;
    }
}
