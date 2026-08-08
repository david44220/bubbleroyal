<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Bootstrap.php';

use Addons\Admin\Domain\AuditLogService;
use Addons\Admin\Domain\KillSwitchService;
use Addons\Admin\Infrastructure\PdoAuditLogStore;
use Addons\Admin\Infrastructure\PdoKillSwitchStore;
use Addons\GameSessions\Infrastructure\PdoGameSessionStore;
use Addons\GeoFeature\Infrastructure\PdoComplianceProfileRepository;
use Addons\Identity\Application\AuthSessionService;
use Addons\Identity\Domain\IdentityService;
use Addons\Identity\Infrastructure\PdoSessionRepository;
use Addons\Identity\Infrastructure\PdoUserRepository;
use Addons\Ledger\Domain\VirtualLedgerService;
use Addons\Ledger\Infrastructure\PdoVirtualLedgerStore;
use Addons\Rewards\Domain\ProgressionService;
use Addons\Rewards\Infrastructure\PdoProgressionStore;
use Addons\Tournaments\Domain\VirtualTournamentService;
use Addons\Tournaments\Infrastructure\PdoVirtualTournamentStore;
use App\Core\Database\Connection;

function persistenceTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$connection = Connection::fromEnvironment();
$suffix = bin2hex(random_bytes(8));
$userId = bin2hex(random_bytes(16));
$email = 'ci-' . $suffix . '@bubble-royale.test';

$users = new PdoUserRepository($connection);
$identity = new IdentityService($users);
$user = [
    'id' => $userId,
    'email' => $email,
    'email_normalized' => $email,
    'display_name' => 'Persistence Test',
    'password_hash' => password_hash('ci-password-012345', PASSWORD_BCRYPT),
    'role' => 'player',
    'status' => 'active',
    'locale' => 'en',
    'created_at' => time(),
    'updated_at' => time(),
    'last_login_at' => null,
];
$users->create($user);
persistenceTrue($users->findById($userId) !== null, 'PDO user repository must round-trip a user.');
persistenceTrue($identity->authenticate($email, 'ci-password-012345') !== null, 'PDO identity must verify a password.');

$sessions = new PdoSessionRepository($connection);
$auth = new AuthSessionService($users, $sessions);
$created = $auth->create($userId, '127.0.0.1', 'ci', time());
persistenceTrue($auth->authenticate($created['token']) !== null, 'PDO auth sessions must authenticate.');

$game = new PdoGameSessionStore($connection);
$claims = [
    'session_id' => bin2hex(random_bytes(16)),
    'mode' => 'practice',
    'value_type' => 'virtual',
    'rules_version' => 'br-practice-1',
    'seed' => 123,
    'rows' => 12,
    'cols' => 12,
    'issued_at' => time(),
    'expires_at' => time() + 900,
];
$game->create($claims, hash('sha256', 'persistence-token-' . $suffix), $userId);
persistenceTrue($game->find($claims['session_id']) !== null, 'PDO game sessions must round-trip.');
persistenceTrue($game->consume($claims['session_id'], time()), 'PDO game sessions must be consumed once.');
persistenceTrue(!$game->consume($claims['session_id'], time()), 'PDO game sessions must reject a second consume.');

$retryClaims = $claims;
$retryClaims['session_id'] = bin2hex(random_bytes(16));
$retryClaims['seed']++;
$game->create($retryClaims, hash('sha256', 'persistence-retry-token-' . $suffix), $userId);
$finalPayload = ['verified' => true, 'verification' => ['valid' => true, 'score' => 42], 'cash_mode' => false];
persistenceTrue($game->finalize($retryClaims['session_id'], $finalPayload, time()), 'PDO game sessions must finalize a response once.');
persistenceTrue(!$game->finalize($retryClaims['session_id'], $finalPayload, time()), 'PDO final responses must be idempotent.');
persistenceTrue($game->result($retryClaims['session_id']) === $finalPayload, 'PDO final responses must be recoverable.');

$progression = new ProgressionService(new PdoProgressionStore($connection));
$verification = [
    'valid' => true,
    'mode' => 'practice',
    'value_type' => 'virtual',
    'session_id' => $claims['session_id'],
    'best_combo' => 3,
    'shots_verified' => 5,
    'status' => 'out',
];
$progressionResult = $progression->applyVerifiedResult($userId, $verification, 'progression-' . $suffix, time());
persistenceTrue($progressionResult['progression']['xp'] > 0, 'PDO progression must persist a verified reward state.');
$progressionAgain = $progression->applyVerifiedResult($userId, $verification, 'progression-' . $suffix, time());
persistenceTrue($progressionAgain['idempotent'] === true, 'PDO progression must be idempotent.');

$ledger = new VirtualLedgerService(new PdoVirtualLedgerStore($connection));
$ledger->issue('player:' . $userId, 'virtual_ticket', 4, 'verified_progression', 'progression-' . $suffix, 'ledger-issue-' . $suffix);
$ledger->transfer('player:' . $userId, 'virtual_sink:test', 'virtual_ticket', 2, 'virtual_entry', 'entry-' . $suffix, 'ledger-transfer-' . $suffix);
persistenceTrue($ledger->balance('player:' . $userId, 'virtual_ticket') === 2, 'PDO ledger must preserve double-entry balances.');

$tournaments = new VirtualTournamentService(null, new PdoVirtualTournamentStore($connection));
$open = $tournaments->listOpen();
persistenceTrue($open !== [], 'PDO tournament catalog must expose an open period.');
$tournament = $open[0];
$entry = $tournaments->enter($userId, (string) $tournament['id'], $verification);
persistenceTrue($entry['entry']['cash_mode'] === false, 'PDO tournament entries must remain virtual-only.');

$audit = new AuditLogService(new PdoAuditLogStore($connection));
$auditEvent = $audit->record('ci', 'persistence.check', 'ci', ['safe' => true], 'audit-' . $suffix);
persistenceTrue($auditEvent['idempotent'] === false, 'PDO audit events must be appendable.');
$kill = new KillSwitchService(new PdoKillSwitchStore($connection));
$kill->set('virtual_gameplay', false, 'CI check', 'ci');
persistenceTrue($kill->isEnabled('virtual_gameplay') === false, 'PDO kill switches must persist disables.');
$kill->set('virtual_gameplay', true, 'CI restore', 'ci');
persistenceTrue((new PdoComplianceProfileRepository($connection))->find($userId) === null, 'Compliance must fail closed without a verified profile.');

echo "PHP persistence tests passed.\n";
