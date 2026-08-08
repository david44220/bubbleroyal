<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Bootstrap.php';

use Addons\GameSessions\Application\SignedPracticeSessionService;
use Addons\GameSessions\Domain\InMemoryGameSessionStore;
use Addons\Identity\Application\AuthSessionService;
use Addons\Identity\Domain\IdentityService;
use Addons\Identity\Domain\InMemorySessionRepository;
use Addons\Identity\Domain\InMemoryUserRepository;

function identityTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$users = new InMemoryUserRepository();
$sessions = new InMemorySessionRepository();
$identity = new IdentityService($users);
$auth = new AuthSessionService($users, $sessions);
$user = $identity->register('Player@example.com', 'correct horse battery staple', 'Player One', 1700000000);
identityTrue($user['email'] === 'player@example.com', 'Identity must normalize email addresses.');
identityTrue($identity->authenticate('player@example.com', 'wrong password') === null, 'Invalid passwords must be rejected.');
$authenticated = $identity->authenticate('PLAYER@EXAMPLE.COM', 'correct horse battery staple', 1700000001);
identityTrue(is_array($authenticated), 'Valid credentials must authenticate.');

$created = $auth->create($user['id'], '127.0.0.1', 'test-agent', 1700000002);
$current = $auth->authenticate($created['token'], 1700000003);
identityTrue(is_array($current) && $current['user']['id'] === $user['id'], 'Session tokens must resolve to their user.');
$auth->assertCsrf($current['session'], $created['csrf_token']);

$failedCsrf = false;
try {
    $auth->assertCsrf($current['session'], str_repeat('a', 64));
} catch (Throwable) {
    $failedCsrf = true;
}
identityTrue($failedCsrf, 'CSRF tokens must be bound to the session.');

$sessionService = new SignedPracticeSessionService('identity-test-secret-change-me-0123456789');
$issued = $sessionService->issue(424242, 1700000010, $user['id']);
$gameSessions = new InMemoryGameSessionStore();
$gameSessions->create($issued['claims'], hash('sha256', $issued['token']), $user['id']);
$stored = $gameSessions->find($issued['claims']['session_id']);
identityTrue(is_array($stored) && $stored['player_id'] === $user['id'], 'Game sessions must retain player binding.');
identityTrue($gameSessions->consume($issued['claims']['session_id'], 1700000011), 'A game session must be consumable once.');
identityTrue(!$gameSessions->consume($issued['claims']['session_id'], 1700000012), 'A consumed game session must reject reuse.');

$auth->revoke($current['session'], 1700000013);
identityTrue($auth->authenticate($created['token'], 1700000014) === null, 'Revoked sessions must no longer authenticate.');

echo "PHP identity tests passed.\n";
