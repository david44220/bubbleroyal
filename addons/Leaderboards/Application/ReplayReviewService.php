<?php

declare(strict_types=1);

namespace Addons\Leaderboards\Application;

use InvalidArgumentException;

final class ReplayReviewService
{
    /** @var list<string> */
    private const DECISIONS = ['approved', 'rejected', 'manual_review'];

    /**
     * Attach an explicit review decision to a verified virtual entry.
     * A rejected or manually reviewed replay can never enter a published board.
     *
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    public function decide(
        array $entry,
        string $decision,
        string $reviewer,
        ?int $reviewedAt = null,
    ): array {
        $decision = trim($decision);
        $reviewer = trim($reviewer);
        if (!in_array($decision, self::DECISIONS, true)) {
            throw new InvalidArgumentException('Unsupported replay review decision.');
        }
        if ($reviewer === '' || strlen($reviewer) > 128) {
            throw new InvalidArgumentException('Reviewer ID must contain 1 to 128 characters.');
        }
        if (($entry['verified'] ?? false) !== true || ($entry['value_type'] ?? null) !== 'virtual') {
            throw new InvalidArgumentException('Only verified virtual entries can be reviewed.');
        }

        $reviewedAt ??= time();
        $entry['replay_review'] = $decision;
        $entry['reviewed_by'] = $reviewer;
        $entry['reviewed_at'] = $reviewedAt;
        $entry['review_id'] = hash(
            'sha256',
            (string) ($entry['entry_id'] ?? '') . '|' . $decision . '|' . $reviewer . '|' . $reviewedAt,
        );

        return $entry;
    }
}
