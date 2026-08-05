<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Bootstrap.php';

use Addons\Leaderboards\Application\ReplayReviewService;
use Addons\Leaderboards\Application\ResultPublicationService;
use Addons\Leaderboards\Domain\LeaderboardService;
use Addons\Rewards\Domain\InMemoryProgressionStore;
use Addons\Rewards\Domain\ProgressionService;
use Addons\Tournaments\Domain\VirtualTournamentService;

function phaseExpectTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function phaseExpectSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true));
    }
}

/** @param Closure():void $callback */
function phaseExpectThrows(Closure $callback, string $message): void
{
    try {
        $callback();
    } catch (Throwable) {
        return;
    }

    throw new RuntimeException($message);
}

$verification = [
    'valid' => true,
    'mode' => 'practice',
    'value_type' => 'virtual',
    'session_id' => 'verified-session-a',
    'score' => 1200,
    'status' => 'cleared',
    'best_combo' => 3,
    'shots_verified' => 5,
];

$progression = new ProgressionService(new InMemoryProgressionStore());
$firstGrant = $progression->applyVerifiedResult('player-a', $verification, 'replay-a', 1700000100);
phaseExpectSame(950, $firstGrant['rewards']['xp_delta'], 'All virtual challenge XP rewards must be granted once.');
phaseExpectSame(7, $firstGrant['rewards']['tickets_delta'], 'All virtual ticket rewards must be granted once.');
phaseExpectSame(3, $firstGrant['progression']['level'], 'XP thresholds must produce a deterministic level.');
phaseExpectSame(4, count($firstGrant['rewards']['completed_challenges']), 'All matching challenges must complete.');
phaseExpectSame(3, count($firstGrant['rewards']['unlocked_achievements']), 'Achievement unlocks must be unique.');

$duplicateGrant = $progression->applyVerifiedResult('player-a', $verification, 'replay-a', 1700000101);
phaseExpectTrue($duplicateGrant['idempotent'] === true, 'The same verified replay event must be idempotent.');
phaseExpectSame(0, $duplicateGrant['rewards']['xp_delta'], 'Duplicate replay events must not grant XP twice.');
phaseExpectSame(950, $duplicateGrant['progression']['xp'], 'Duplicate replay events must preserve progression totals.');
phaseExpectSame(false, $firstGrant['progression']['value_type'] === 'cash', 'Progression must remain virtual-only.');

$now = 1700000100;
$tournaments = new VirtualTournamentService(static fn (): int => $now);
phaseExpectSame(2, count($tournaments->listOpen($now)), 'The virtual catalog must expose open free tournaments.');

$entryA = $tournaments->enter('player-a', 'daily-precision', $verification, $now);
$entryBVerification = $verification;
$entryBVerification['session_id'] = 'verified-session-b';
$entryB = $tournaments->enter('player-b', 'daily-precision', $entryBVerification, $now);
$entryCVerification = $verification;
$entryCVerification['session_id'] = 'verified-session-c';
$entryCVerification['score'] = 800;
$entryCVerification['best_combo'] = 1;
$entryCVerification['status'] = 'out';
$entryC = $tournaments->enter('player-c', 'daily-precision', $entryCVerification, $now);
$entryDVerification = $verification;
$entryDVerification['session_id'] = 'verified-session-d';
$entryD = $tournaments->enter('player-d', 'daily-precision', $entryDVerification, $now);

phaseExpectSame(0, $entryA['entry']['ticket_cost'], 'Virtual tournament entries must not consume cash or paid value.');
phaseExpectSame(false, $entryA['entry']['cash_mode'], 'Cash mode must remain disabled for tournament entries.');
phaseExpectTrue($tournaments->enter('player-a', 'daily-precision', $verification, $now)['idempotent'] === true, 'A player may not create a duplicate tournament entry.');

$review = new ReplayReviewService();
$approvedA = $review->decide($entryA['entry'], 'approved', 'reviewer-1', $now + 10);
$approvedB = $review->decide($entryB['entry'], 'approved', 'reviewer-1', $now + 11);
$rejectedC = $review->decide($entryC['entry'], 'rejected', 'reviewer-1', $now + 12);
$manualD = $review->decide($entryD['entry'], 'manual_review', 'reviewer-1', $now + 13);

$pendingEntries = [$approvedA, $approvedB, $rejectedC, $manualD];
$publication = new ResultPublicationService(new LeaderboardService());
phaseExpectThrows(
    static fn (): array => $publication->publish('daily-precision', $pendingEntries, $now + 20),
    'Result publication must wait for manual replay reviews.',
);

$approvedEntries = [$approvedA, $approvedB, $rejectedC, $review->decide($entryD['entry'], 'rejected', 'reviewer-2', $now + 14)];
$leaderboard = (new LeaderboardService())->rank($approvedEntries);
phaseExpectSame(2, count($leaderboard), 'Only approved replays may appear on a leaderboard.');
phaseExpectSame('player-a', $leaderboard[0]['player_id'], 'The final player ID tie-break must be deterministic.');
phaseExpectSame('player-b', $leaderboard[1]['player_id'], 'The second deterministic tie-break result must be stable.');
phaseExpectSame(1, $leaderboard[0]['rank'], 'Leaderboard ranks must start at one.');

$published = $publication->publish('daily-precision', $approvedEntries, $now + 30);
phaseExpectSame(false, $published['idempotent'], 'The first result publication must be new.');
phaseExpectSame(2, $published['publication']['approved_entry_count'], 'Publication must contain only approved results.');
phaseExpectSame(2, $published['publication']['rejected_entry_count'], 'Rejected replay entries must be excluded and counted.');
phaseExpectSame(false, $published['publication']['cash_mode'], 'Published results must remain virtual-only.');
phaseExpectSame([], $published['publication']['rewards'], 'This phase must not create a payout or wallet flow.');

$publishedAgain = $publication->publish('daily-precision', $approvedEntries, $now + 31);
phaseExpectTrue($publishedAgain['idempotent'] === true, 'Publishing the same immutable snapshot must be idempotent.');
phaseExpectSame(
    $published['publication']['publication_id'],
    $publishedAgain['publication']['publication_id'],
    'Idempotent publication must preserve its immutable publication ID.',
);

echo "PHP phases 08-10 tests passed.\n";
