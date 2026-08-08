<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Bootstrap.php';

use Addons\AntiFraud\Application\AntiCheatService;
use Addons\AntiFraud\Domain\InMemoryRiskCaseStore;
use Addons\AntiFraud\Domain\RiskAssessmentService;
use Addons\AntiFraud\Domain\RiskCaseService;
use Addons\AntiFraud\Infrastructure\PdoRiskCaseStore;
use Addons\Admin\Application\AdminCommandCenter;
use Addons\Admin\Domain\AuditLogService;
use Addons\Admin\Domain\InMemoryAuditLogStore;
use Addons\Admin\Domain\InMemoryKillSwitchStore;
use Addons\Admin\Domain\KillSwitchService;
use Addons\Admin\Infrastructure\PdoAuditLogStore;
use Addons\Admin\Infrastructure\PdoKillSwitchStore;
use Addons\GameSessions\Application\ReplayVerifier;
use Addons\GameSessions\Application\SignedPracticeSessionService;
use Addons\GameSessions\Domain\GameSessionStore;
use Addons\GameSessions\Domain\InMemoryGameSessionStore;
use Addons\GameSessions\Infrastructure\PdoGameSessionStore;
use Addons\GeoFeature\Domain\CompliancePolicyEngine;
use Addons\GeoFeature\Domain\InMemoryComplianceProfileRepository;
use Addons\GeoFeature\Infrastructure\PdoComplianceProfileRepository;
use Addons\Identity\Application\AuthSessionService;
use Addons\Identity\Domain\IdentityService;
use Addons\Identity\Domain\InMemorySessionRepository;
use Addons\Identity\Domain\InMemoryUserRepository;
use Addons\Identity\Domain\SessionRepository;
use Addons\Identity\Domain\UserRepository;
use Addons\Identity\Infrastructure\PdoSessionRepository;
use Addons\Identity\Infrastructure\PdoUserRepository;
use Addons\Leaderboards\Domain\LeaderboardService;
use Addons\Ledger\Domain\InMemoryVirtualLedgerStore;
use Addons\Ledger\Domain\VirtualLedgerService;
use Addons\Rewards\Domain\InMemoryProgressionStore;
use Addons\Rewards\Domain\ProgressionService;
use Addons\Rewards\Infrastructure\PdoProgressionStore;
use Addons\Tournaments\Domain\InMemoryVirtualTournamentStore;
use Addons\Tournaments\Domain\VirtualTournamentService;
use Addons\Tournaments\Infrastructure\PdoVirtualTournamentStore;
use Addons\Wallet\Domain\VirtualWalletService;
use App\Core\Database\Connection;
use App\Core\Support\CanonicalJson;
use App\Core\Security\RateLimiter;
use App\Core\Security\RedisRateLimiter;

/** @return array<string,mixed> */
function runtime(): array
{
    static $runtime;
    if (is_array($runtime)) {
        return $runtime;
    }

    $users = new InMemoryUserRepository();
    $sessions = new InMemorySessionRepository();
    $gameSessions = new InMemoryGameSessionStore();
    $progressionStore = new InMemoryProgressionStore();
    $ledger = new VirtualLedgerService(new InMemoryVirtualLedgerStore());
    $profiles = new InMemoryComplianceProfileRepository();
    $riskCases = new RiskCaseService(new InMemoryRiskCaseStore(), new RiskAssessmentService());
    $tournaments = new VirtualTournamentService(null, new InMemoryVirtualTournamentStore());
    $audit = new AuditLogService(new InMemoryAuditLogStore());
    $killSwitches = new KillSwitchService(new InMemoryKillSwitchStore());
    $database = false;
    $databaseError = null;

    if (databaseConfigured()) {
        try {
            $connection = Connection::fromEnvironment();
            $users = new PdoUserRepository($connection);
            $sessions = new PdoSessionRepository($connection);
            $gameSessions = new PdoGameSessionStore($connection);
            $progressionStore = new PdoProgressionStore($connection);
            $ledger = new VirtualLedgerService(new \Addons\Ledger\Infrastructure\PdoVirtualLedgerStore($connection));
            $profiles = new PdoComplianceProfileRepository($connection);
            $riskCases = new RiskCaseService(new PdoRiskCaseStore($connection), new RiskAssessmentService());
            $tournaments = new VirtualTournamentService(null, new PdoVirtualTournamentStore($connection));
            $audit = new AuditLogService(new PdoAuditLogStore($connection));
            $killSwitches = new KillSwitchService(new PdoKillSwitchStore($connection));
            $database = true;
        } catch (Throwable $exception) {
            $databaseError = $exception->getMessage();
            $users = new InMemoryUserRepository();
            $sessions = new InMemorySessionRepository();
            $gameSessions = new InMemoryGameSessionStore();
            $progressionStore = new InMemoryProgressionStore();
            $ledger = new VirtualLedgerService(new InMemoryVirtualLedgerStore());
            $profiles = new InMemoryComplianceProfileRepository();
            $riskCases = new RiskCaseService(new InMemoryRiskCaseStore(), new RiskAssessmentService());
            $tournaments = new VirtualTournamentService(null, new InMemoryVirtualTournamentStore());
            $audit = new AuditLogService(new InMemoryAuditLogStore());
            $killSwitches = new KillSwitchService(new InMemoryKillSwitchStore());
        }
    }

    $rateLimiter = new RateLimiter(
        (string) (getenv('RATE_LIMIT_PATH') ?: dirname(__DIR__) . '/storage/cache/rate-limits'),
    );
    $redisConfigured = trim((string) (getenv('REDIS_HOST') ?: '')) !== '';
    $redisReady = !$redisConfigured && !isProductionEnvironment();
    $redisError = null;
    if ($redisConfigured) {
        try {
            if (!class_exists('Redis')) {
                throw new RuntimeException('Redis extension is not loaded.');
            }
            $redis = new \Redis();
            if (!$redis->connect(
                (string) getenv('REDIS_HOST'),
                (int) (getenv('REDIS_PORT') ?: 6379),
                1.5,
            )) {
                throw new RuntimeException('Redis connection failed.');
            }
            $redisPassword = (string) (getenv('REDIS_PASSWORD') ?: '');
            if ($redisPassword !== '' && $redis->auth($redisPassword) !== true) {
                throw new RuntimeException('Redis authentication failed.');
            }
            if (!$redis->ping()) {
                throw new RuntimeException('Redis health check failed.');
            }
            $rateLimiter = new RedisRateLimiter($redis);
            $redisReady = true;
        } catch (Throwable $exception) {
            $redisError = $exception->getMessage();
        }
    }

    $runtime = [
        'environment' => strtolower(trim((string) (getenv('APP_ENV') ?: 'local'))),
        'database' => $database,
        'database_error' => $databaseError,
        'redis_configured' => $redisConfigured,
        'redis_ready' => $redisReady,
        'redis_error' => $redisError,
        'dependencies_ready' => $database && $redisReady,
        'users' => $users,
        'sessions' => $sessions,
        'game_sessions' => $gameSessions,
        'progression' => new ProgressionService($progressionStore),
        'ledger' => $ledger,
        'policy' => new CompliancePolicyEngine(),
        'profiles' => $profiles,
        'wallet' => new VirtualWalletService($ledger, new CompliancePolicyEngine()),
        'risk_cases' => $riskCases,
        'anti_cheat' => new AntiCheatService($riskCases),
        'tournaments' => $tournaments,
        'admin' => new AdminCommandCenter($audit, $killSwitches),
        'kill_switches' => $killSwitches,
        'identity' => new IdentityService($users),
        'auth' => new AuthSessionService($users, $sessions),
        'rate_limiter' => $rateLimiter,
        'session_secret' => (string) (getenv('BUBBLE_ROYALE_SESSION_SECRET') ?: ''),
    ];

    return $runtime;
}

function databaseConfigured(): bool
{
    return trim((string) (getenv('DB_DSN') ?: '')) !== ''
        || (trim((string) (getenv('DB_HOST') ?: getenv('MYSQL_HOST') ?: '')) !== ''
            && trim((string) (getenv('DB_DATABASE') ?: getenv('MYSQL_DATABASE') ?: '')) !== '');
}

function appEnvironment(): string
{
    return strtolower(trim((string) (getenv('APP_ENV') ?: 'local')));
}

function isProductionEnvironment(): bool
{
    return in_array(appEnvironment(), ['production', 'staging'], true);
}

function isHttps(): bool
{
    return (($_SERVER['HTTPS'] ?? '') === 'on')
        || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';
}

function requestHeader(string $name): string
{
    $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    return trim((string) ($_SERVER[$key] ?? ''));
}

function requestPath(): string
{
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $path = (string) (parse_url($uri, PHP_URL_PATH) ?: '/');
    return rtrim($path, '/') ?: '/';
}

function requestIp(): string
{
    return trim((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
}

/** @param array<string,mixed> $payload @param array<string,string> $headers @param list<array<string,mixed>> $cookies */
function respond(array $payload, int $status = 200, array $headers = [], array $cookies = []): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'; base-uri 'none'");
    $requestId = requestHeader('X-Request-ID');
    if (preg_match('/^[A-Za-z0-9._-]{1,64}$/', $requestId) !== 1) {
        $requestId = bin2hex(random_bytes(16));
    }
    header('X-Request-ID: ' . $requestId);
    foreach ($headers as $name => $value) {
        header($name . ': ' . $value);
    }
    foreach ($cookies as $cookie) {
        setcookie((string) $cookie['name'], (string) $cookie['value'], [
            'expires' => (int) $cookie['expires'],
            'path' => '/',
            'secure' => (bool) $cookie['secure'],
            'httponly' => (bool) $cookie['httponly'],
            'samesite' => 'Lax',
        ]);
    }
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    exit;
}

function redirectHome(): never
{
    header('Location: /homepage/index-modular.html', true, 302);
    exit;
}

function requireOperationalStorage(array $runtime): void
{
    if (($runtime['dependencies_ready'] ?? false) === true || appEnvironment() === 'local') {
        return;
    }
    respond([
        'error' => 'persistence_unavailable',
        'message' => 'The service is temporarily unavailable because persistent storage is not ready.',
    ], 503);
}

/** @return array<string,mixed> */
function readJsonBody(): array
{
    $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($contentLength > 262144) {
        respond(['error' => 'request_too_large'], 413);
    }

    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    if (strlen($raw) > 262144) {
        respond(['error' => 'request_too_large'], 413);
    }

    $decoded = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) {
        respond(['error' => 'json_object_required'], 400);
    }
    return $decoded;
}

function bearerToken(): string
{
    $authorization = requestHeader('Authorization');
    if (preg_match('/^Bearer ([a-f0-9]{64})$/', $authorization, $matches) === 1) {
        return $matches[1];
    }
    return '';
}

/** @return array{session:array<string,mixed>,user:array<string,mixed>}|null */
function currentAuth(array $runtime): ?array
{
    $token = bearerToken();
    if ($token === '') {
        $cookies = (string) ($_SERVER['HTTP_COOKIE'] ?? '');
        foreach (explode(';', $cookies) as $cookie) {
            [$name, $value] = array_pad(explode('=', trim($cookie), 2), 2, '');
            if ($name === 'br_session') {
                $token = trim($value);
                break;
            }
        }
    }
    if ($token === '') {
        return null;
    }
    return $runtime['auth']->authenticate($token);
}

/** @return array{session:array<string,mixed>,user:array<string,mixed>} */
function requireAuth(array $runtime): array
{
    $auth = currentAuth($runtime);
    if ($auth === null) {
        respond(['error' => 'authentication_required'], 401, ['WWW-Authenticate' => 'Bearer']);
    }
    return $auth;
}

/** @param array{session:array<string,mixed>,user:array<string,mixed>} $auth */
function requireCsrf(array $runtime, array $auth): void
{
    $csrf = requestHeader('X-CSRF-Token');
    try {
        $runtime['auth']->assertCsrf($auth['session'], $csrf);
    } catch (Throwable) {
        respond(['error' => 'csrf_validation_failed'], 403);
    }
}

function rateLimit(array $runtime, string $bucket, int $limit, int $window): void
{
    if (!$runtime['rate_limiter']->allow($bucket . ':' . requestIp(), $limit, $window)) {
        respond(['error' => 'rate_limited'], 429, ['Retry-After' => (string) $window]);
    }
}

function requireVirtualFeature(array $runtime, string $feature): void
{
    if ($runtime['kill_switches']->isEnabled($feature) !== true) {
        respond(['error' => 'feature_temporarily_disabled', 'feature' => $feature], 503);
    }
}

/** @return array<string,mixed> */
function complianceContext(array $runtime, string $playerId): array
{
    $profile = $runtime['profiles']->find($playerId) ?? [];
    return [
        'environment' => appEnvironment(),
        'country' => (string) ($profile['country_code'] ?? ''),
        'region' => (string) ($profile['region_code'] ?? ''),
        'age_years' => isset($profile['age_years']) ? (int) $profile['age_years'] : null,
        'age_verified' => ($profile['age_verified'] ?? false) === true,
        'kyc_status' => (string) ($profile['kyc_status'] ?? 'unknown'),
        'kyb_status' => (string) ($profile['kyb_status'] ?? 'not_applicable'),
        'risk_score' => isset($profile['risk_score']) ? (int) $profile['risk_score'] : null,
        'responsible_play_status' => (string) ($profile['responsible_play_status'] ?? 'unknown'),
        'cooling_off_until' => (int) ($profile['cooling_off_until'] ?? 0),
        'minutes_today' => (int) ($profile['minutes_today'] ?? 0),
        'sessions_today' => (int) ($profile['sessions_today'] ?? 0),
        'daily_minutes_limit' => isset($profile['daily_minutes_limit']) ? (int) $profile['daily_minutes_limit'] : null,
        'daily_sessions_limit' => isset($profile['daily_sessions_limit']) ? (int) $profile['daily_sessions_limit'] : null,
        'now' => time(),
    ];
}

function publicPlayerRef(mixed $playerId): string
{
    return 'player_' . substr(hash('sha256', 'br-public-player|' . (string) $playerId), 0, 12);
}

/** @param array<string,mixed> $entry @return array<string,mixed> */
function publicTournamentEntry(array $entry): array
{
    $public = [];
    foreach ([
        'entry_id', 'tournament_id', 'verified', 'mode', 'value_type', 'entry_type',
        'ticket_cost', 'cash_mode', 'score', 'status', 'best_combo', 'shots_used', 'verified_at',
    ] as $key) {
        if (array_key_exists($key, $entry)) {
            $public[$key] = $entry[$key];
        }
    }
    if (array_key_exists('player_id', $entry)) {
        $public['player_ref'] = publicPlayerRef($entry['player_id']);
    }
    return $public;
}

/** @param array<string,mixed> $lobby @return array<string,mixed> */
function publicTournamentLobby(array $lobby): array
{
    $public = $lobby;
    $public['entries'] = array_map(
        static fn (mixed $entry): array => is_array($entry) ? publicTournamentEntry($entry) : [],
        is_array($lobby['entries'] ?? null) ? $lobby['entries'] : [],
    );
    return $public;
}

/** @param array<string,mixed> $row @return array<string,mixed> */
function publicLeaderboardRow(array $row): array
{
    $public = [];
    foreach (['rank', 'entry_id', 'score', 'best_combo', 'shots_used', 'status'] as $key) {
        if (array_key_exists($key, $row)) {
            $public[$key] = $row[$key];
        }
    }
    if (array_key_exists('player_id', $row)) {
        $public['player_ref'] = publicPlayerRef($row['player_id']);
    }
    return $public;
}

/** @param array<string,mixed> $payload @return array<string,mixed> */
function finalizePracticeResponse(array $runtime, string $sessionId, array $payload): array
{
    /** @var GameSessionStore $gameSessions */
    $gameSessions = $runtime['game_sessions'];
    $created = $gameSessions->finalize($sessionId, $payload, time());
    $stored = $gameSessions->result($sessionId);
    if ($stored === null) {
        respond(['error' => 'session_already_consumed'], 409);
    }
    if (!$created) {
        $stored['idempotent'] = true;
    }
    return $stored;
}

/** @return array{claims:array<string,mixed>,result:array<string,mixed>,stored:array<string,mixed>,final_result:array<string,mixed>|null} */
function inspectPracticeReplay(array $runtime, array $body, ?array $auth): array
{
    $token = $body['session_token'] ?? null;
    $replay = $body['replay'] ?? null;
    if (!is_string($token) || !is_array($replay)) {
        respond(['error' => 'session_token_and_replay_required'], 400);
    }

    $secret = (string) $runtime['session_secret'];
    if (strlen($secret) < 32) {
        respond(['error' => 'session_secret_not_configured'], 503);
    }

    try {
        $sessionService = new SignedPracticeSessionService($secret);
        $claims = $sessionService->verify(
            $token,
            null,
            $auth === null ? null : (string) $auth['user']['id'],
        );
        if (isset($claims['player_id']) && $auth === null) {
            respond(['error' => 'authentication_required_for_bound_session'], 401);
        }
        /** @var GameSessionStore $gameSessions */
        $gameSessions = $runtime['game_sessions'];
        $stored = $gameSessions->find((string) $claims['session_id']);
        if ($stored === null || ($stored['token_hash'] ?? null) !== hash('sha256', $token)) {
            respond(['error' => 'session_not_registered'], 401);
        }
        if (($stored['player_id'] ?? null) !== ($claims['player_id'] ?? null)) {
            respond(['error' => 'session_binding_invalid'], 401);
        }
        $result = (new ReplayVerifier())->verify($replay, $claims);
        if ($result['valid'] !== true) {
            respond(['error' => 'replay_rejected', 'verification' => $result, 'cash_mode' => false], 422);
        }
        /** @var GameSessionStore $gameSessions */
        $finalResult = $gameSessions->result((string) $claims['session_id']);
        if (($stored['consumed_at'] ?? null) !== null && $finalResult === null) {
            respond(['error' => 'session_already_consumed'], 409);
        }
        if ($finalResult !== null
            && is_array($finalResult['verification'] ?? null)
            && CanonicalJson::encode($finalResult['verification']) !== CanonicalJson::encode($result)) {
            respond(['error' => 'session_already_finalized'], 409);
        }
    } catch (JsonException) {
        respond(['error' => 'invalid_json'], 400);
    } catch (Throwable) {
        respond(['error' => 'invalid_or_expired_session'], 401);
    }

    return [
        'claims' => $claims,
        'result' => $result,
        'stored' => $stored,
        'final_result' => $finalResult,
    ];
}

/** @return array<string,mixed>|null */
function applyProgressionIfAuthenticated(array $runtime, ?array $auth, array $verification, string $eventId): ?array
{
    if ($auth === null || $runtime['kill_switches']->isEnabled('virtual_progression') !== true) {
        return null;
    }
    try {
        return $runtime['progression']->applyVerifiedResult(
            (string) $auth['user']['id'],
            $verification,
            $eventId,
        );
    } catch (Throwable) {
        respond(['error' => 'progression_unavailable'], 503);
    }
}

/** @return list<array<string,mixed>> */
function authCookies(string $token, string $csrf, int $expires): array
{
    $secure = getenv('COOKIE_SECURE') === '1' || isHttps();
    return [
        [
            'name' => 'br_session',
            'value' => $token,
            'expires' => $expires,
            'secure' => $secure,
            'httponly' => true,
        ],
        [
            'name' => 'br_csrf',
            'value' => $csrf,
            'expires' => $expires,
            'secure' => $secure,
            'httponly' => false,
        ],
    ];
}

/** @return list<array<string,mixed>> */
function clearAuthCookies(): array
{
    return authCookies('', '', time() - 3600);
}

$runtime = runtime();
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$path = requestPath();

if ($method === 'GET' && $path === '/') {
    redirectHome();
}

if ($method === 'GET' && in_array($path, ['/login', '/register'], true)) {
    header('Location: /auth/?mode=' . ($path === '/register' ? 'register' : 'login'), true, 302);
    exit;
}

if ($method === 'GET' && $path === '/health') {
    $databaseReady = ($runtime['database'] ?? false) === true;
    $dependenciesReady = ($runtime['dependencies_ready'] ?? false) === true;
    respond([
        'status' => $dependenciesReady || appEnvironment() === 'local' ? 'ok' : 'degraded',
        'service' => 'bubble-royale',
        'phase' => 'production-virtual-platform-foundation',
        'environment' => appEnvironment(),
        'cash_mode' => false,
        'persistence' => $databaseReady ? 'mysql' : 'memory',
        'rate_limiting' => $runtime['redis_configured']
            ? ($runtime['redis_ready'] ? 'redis' : 'unavailable')
            : 'local-file',
        'features' => [
            'homepage' => true,
            'verified_practice' => true,
            'identity' => true,
            'virtual_progression' => true,
            'virtual_tournaments' => true,
            'virtual_leaderboards' => true,
            'virtual_wallet' => true,
            'cash_tournaments' => false,
            'cash_readiness_only' => true,
        ],
    ], $dependenciesReady || appEnvironment() === 'local' ? 200 : 503);
}

if ($method === 'GET' && $path === '/api/v1/auth/me') {
    $auth = currentAuth($runtime);
    if ($auth === null) {
        respond(['authenticated' => false]);
    }
    respond([
        'authenticated' => true,
        'user' => $runtime['identity']->publicUser($auth['user']),
        'cash_mode' => false,
    ]);
}

if ($method === 'GET' && $path === '/api/v1/tournaments') {
    requireOperationalStorage($runtime);
    rateLimit($runtime, 'tournaments-list', 60, 60);
    respond([
        'value_type' => 'virtual',
        'cash_mode' => false,
        'tournaments' => $runtime['tournaments']->list(),
    ]);
}

if ($method === 'GET' && preg_match('#^/api/v1/tournaments/([^/]+)/lobby$#', $path, $matches) === 1) {
    requireOperationalStorage($runtime);
    rateLimit($runtime, 'tournament-lobby', 60, 60);
    try {
        $lobby = $runtime['tournaments']->lobby(rawurldecode($matches[1]));
    } catch (InvalidArgumentException) {
        respond(['error' => 'tournament_not_found'], 404);
    }
    respond([
        'value_type' => 'virtual',
        'cash_mode' => false,
        'lobby' => publicTournamentLobby($lobby),
    ]);
}

if ($method === 'GET' && preg_match('#^/api/v1/tournaments/([^/]+)/leaderboard$#', $path, $matches) === 1) {
    requireOperationalStorage($runtime);
    rateLimit($runtime, 'tournament-leaderboard', 60, 60);
    try {
        $lobby = $runtime['tournaments']->lobby(rawurldecode($matches[1]));
        $rows = (new LeaderboardService())->rank($lobby['entries']);
    } catch (InvalidArgumentException) {
        respond(['error' => 'tournament_not_found'], 404);
    }
    respond([
        'tournament' => $lobby['tournament'],
        'value_type' => 'virtual',
        'cash_mode' => false,
        'rows' => array_map(
            static fn (array $row): array => publicLeaderboardRow($row),
            $rows,
        ),
        'publication_status' => 'not_published',
    ]);
}

if ($method === 'GET' && $path === '/api/v1/progression') {
    requireOperationalStorage($runtime);
    rateLimit($runtime, 'progression-read', 60, 60);
    $auth = requireAuth($runtime);
    requireVirtualFeature($runtime, 'virtual_progression');
    respond([
        'value_type' => 'virtual',
        'cash_mode' => false,
        'progression' => $runtime['progression']->snapshot((string) $auth['user']['id']),
    ]);
}

if ($method === 'GET' && $path === '/api/v1/compliance/status') {
    requireOperationalStorage($runtime);
    rateLimit($runtime, 'compliance-status', 30, 60);
    $auth = requireAuth($runtime);
    $decision = $runtime['policy']->evaluate(
        'virtual_wallet',
        complianceContext($runtime, (string) $auth['user']['id']),
    );
    respond([
        'value_type' => 'virtual',
        'cash_mode' => false,
        'wallet_access' => $decision,
        'profile_source' => $runtime['database'] ? 'persistent' : 'memory',
    ]);
}

if ($method === 'GET' && $path === '/api/v1/wallet') {
    requireOperationalStorage($runtime);
    rateLimit($runtime, 'wallet-read', 30, 60);
    $auth = requireAuth($runtime);
    requireVirtualFeature($runtime, 'virtual_wallet');
    $context = complianceContext($runtime, (string) $auth['user']['id']);
    $decision = $runtime['policy']->evaluate('virtual_wallet', $context);
    if ($decision['allowed'] !== true) {
        respond([
            'error' => 'virtual_wallet_unavailable',
            'policy' => $decision,
            'cash_mode' => false,
        ], 403);
    }
    respond([
        'value_type' => 'virtual',
        'cash_mode' => false,
        'wallet' => $runtime['wallet']->snapshot((string) $auth['user']['id'], $context),
    ]);
}

if ($method === 'GET' && $path === '/api/v1/admin/kill-switches') {
    requireOperationalStorage($runtime);
    rateLimit($runtime, 'admin-read', 30, 60);
    $auth = requireAuth($runtime);
    try {
        $switches = $runtime['admin']->readKillSwitches((string) ($auth['user']['role'] ?? ''));
    } catch (InvalidArgumentException) {
        respond(['error' => 'admin_permission_denied'], 403);
    }
    respond(['cash_mode' => false, 'switches' => $switches]);
}

if ($method === 'GET' && $path === '/api/v1/admin/audit') {
    requireOperationalStorage($runtime);
    rateLimit($runtime, 'admin-read', 30, 60);
    $auth = requireAuth($runtime);
    try {
        $events = $runtime['admin']->auditTrail((string) ($auth['user']['role'] ?? ''));
    } catch (InvalidArgumentException) {
        respond(['error' => 'admin_permission_denied'], 403);
    }
    respond(['cash_mode' => false, 'events' => $events]);
}

if ($method !== 'POST') {
    respond(['error' => 'method_not_allowed'], 405, ['Allow' => 'GET, POST']);
}

try {
    $body = readJsonBody();
} catch (JsonException) {
    respond(['error' => 'invalid_json'], 400);
}

if ($path === '/api/v1/auth/register') {
    requireOperationalStorage($runtime);
    rateLimit($runtime, 'auth-register', 5, 3600);
    try {
        $user = $runtime['identity']->register(
            (string) ($body['email'] ?? ''),
            (string) ($body['password'] ?? ''),
            (string) ($body['display_name'] ?? ''),
        );
        $created = $runtime['auth']->create($user['id'], requestIp(), requestHeader('User-Agent'));
    } catch (InvalidArgumentException $exception) {
        respond(['error' => 'registration_rejected', 'message' => $exception->getMessage()], 422);
    } catch (Throwable) {
        respond(['error' => 'registration_failed'], 500);
    }
    respond([
        'authenticated' => true,
        'user' => $user,
        'session' => $created['session'],
        'csrf_token' => $created['csrf_token'],
        'cash_mode' => false,
    ], 201, [], authCookies($created['token'], $created['csrf_token'], (int) $created['session']['expires_at']));
}

if ($path === '/api/v1/auth/login') {
    requireOperationalStorage($runtime);
    rateLimit($runtime, 'auth-login', 10, 900);
    $user = $runtime['identity']->authenticate(
        (string) ($body['email'] ?? ''),
        (string) ($body['password'] ?? ''),
    );
    if ($user === null) {
        respond(['error' => 'invalid_credentials'], 401);
    }
    try {
        $created = $runtime['auth']->create($user['id'], requestIp(), requestHeader('User-Agent'));
    } catch (Throwable) {
        respond(['error' => 'login_failed'], 500);
    }
    respond([
        'authenticated' => true,
        'user' => $user,
        'session' => $created['session'],
        'csrf_token' => $created['csrf_token'],
        'cash_mode' => false,
    ], 200, [], authCookies($created['token'], $created['csrf_token'], (int) $created['session']['expires_at']));
}

if ($path === '/api/v1/auth/logout') {
    $auth = requireAuth($runtime);
    requireCsrf($runtime, $auth);
    $runtime['auth']->revoke($auth['session']);
    respond(['authenticated' => false], 200, [], clearAuthCookies());
}

if ($path === '/api/v1/admin/kill-switches') {
    requireOperationalStorage($runtime);
    rateLimit($runtime, 'admin-write', 10, 60);
    $auth = requireAuth($runtime);
    requireCsrf($runtime, $auth);
    $eventId = requestHeader('Idempotency-Key');
    if ($eventId === '') {
        respond(['error' => 'idempotency_key_required'], 400);
    }
    if (!is_bool($body['enabled'] ?? null)) {
        respond(['error' => 'enabled_boolean_required'], 400);
    }
    try {
        $result = $runtime['admin']->setKillSwitch(
            (string) $auth['user']['id'],
            (string) ($auth['user']['role'] ?? ''),
            (string) ($body['flag'] ?? ''),
            $body['enabled'],
            (string) ($body['reason'] ?? ''),
            $eventId,
        );
    } catch (InvalidArgumentException $exception) {
        $message = $exception->getMessage();
        respond([
            'error' => str_contains($message, 'permission') ? 'admin_permission_denied' : 'admin_request_rejected',
            'message' => $message,
        ], str_contains($message, 'permission') ? 403 : 422);
    } catch (Throwable) {
        respond(['error' => 'admin_write_failed'], 503);
    }
    respond($result);
}

if ($path === '/api/v1/practice/sessions') {
    requireOperationalStorage($runtime);
    rateLimit($runtime, 'practice-issue', 20, 60);
    requireVirtualFeature($runtime, 'virtual_gameplay');
    $auth = currentAuth($runtime);
    if ($auth !== null) {
        requireCsrf($runtime, $auth);
    }
    $secret = (string) $runtime['session_secret'];
    if (strlen($secret) < 32) {
        respond(['error' => 'session_secret_not_configured'], 503);
    }
    try {
        $playerId = $auth === null ? null : (string) $auth['user']['id'];
        $session = (new SignedPracticeSessionService($secret))->issue(null, null, $playerId);
        /** @var GameSessionStore $gameSessions */
        $gameSessions = $runtime['game_sessions'];
        $gameSessions->create($session['claims'], hash('sha256', $session['token']), $playerId);
    } catch (Throwable) {
        respond(['error' => 'session_issue_failed'], 500);
    }
    respond([
        'session_token' => $session['token'],
        'session' => $session['claims'],
        'cash_mode' => false,
    ], 201);
}

if ($path === '/api/v1/practice/sessions/verify') {
    requireOperationalStorage($runtime);
    rateLimit($runtime, 'practice-verify', 20, 60);
    requireVirtualFeature($runtime, 'virtual_gameplay');
    $auth = currentAuth($runtime);
    if ($auth !== null) {
        requireCsrf($runtime, $auth);
    }
    $verified = inspectPracticeReplay($runtime, $body, $auth);
    if ($verified['final_result'] !== null) {
        respond($verified['final_result']);
    }
    $inspection = $runtime['anti_cheat']->inspect(
        $auth === null ? 'anonymous:' . $verified['claims']['session_id'] : (string) $auth['user']['id'],
        $verified['result'],
        [],
        'replay:' . $verified['claims']['session_id'],
    );
    if ($inspection['allowed'] !== true) {
        respond(['error' => 'anti_cheat_blocked', 'risk_score' => $inspection['risk_score'], 'cash_mode' => false], 422);
    }
    $progression = applyProgressionIfAuthenticated(
        $runtime,
        $auth,
        $verified['result'],
        (string) $verified['claims']['session_id'],
    );
    $payload = [
        'verified' => true,
        'verification' => $verified['result'],
        'progression' => $progression,
        'cash_mode' => false,
        'idempotent' => false,
    ];
    respond(finalizePracticeResponse($runtime, (string) $verified['claims']['session_id'], $payload));
}

if (preg_match('#^/api/v1/tournaments/([^/]+)/enter$#', $path, $matches) === 1) {
    requireOperationalStorage($runtime);
    rateLimit($runtime, 'tournament-enter', 10, 60);
    requireVirtualFeature($runtime, 'virtual_tournaments');
    $auth = requireAuth($runtime);
    requireCsrf($runtime, $auth);
    $verified = inspectPracticeReplay($runtime, $body, $auth);
    if ($verified['final_result'] !== null) {
        respond($verified['final_result']);
    }
    $inspection = $runtime['anti_cheat']->inspect(
        (string) $auth['user']['id'],
        $verified['result'],
        [],
        'replay:' . $verified['claims']['session_id'],
    );
    if ($inspection['allowed'] !== true) {
        respond(['error' => 'anti_cheat_blocked', 'risk_score' => $inspection['risk_score'], 'cash_mode' => false], 422);
    }
    try {
        $entry = $runtime['tournaments']->enter(
            (string) $auth['user']['id'],
            rawurldecode($matches[1]),
            $verified['result'],
        );
        $progression = applyProgressionIfAuthenticated(
            $runtime,
            $auth,
            $verified['result'],
            (string) $verified['claims']['session_id'],
        );
    } catch (InvalidArgumentException $exception) {
        respond(['error' => 'tournament_entry_rejected', 'message' => $exception->getMessage()], 422);
    } catch (Throwable) {
        respond(['error' => 'tournament_entry_failed'], 503);
    }
    $payload = [
        'value_type' => 'virtual',
        'cash_mode' => false,
        'verification' => $verified['result'],
        'entry' => publicTournamentEntry($entry['entry']),
        'lobby' => publicTournamentLobby($entry['lobby']),
        'progression' => $progression,
        'idempotent' => $entry['idempotent'],
    ];
    $final = finalizePracticeResponse($runtime, (string) $verified['claims']['session_id'], $payload);
    respond($final, $entry['idempotent'] ? 200 : 201);
}

respond(['error' => 'not_found'], 404);
