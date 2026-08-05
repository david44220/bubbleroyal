<?php

declare(strict_types=1);

namespace Addons\AntiFraud\Domain;

use InvalidArgumentException;

final class RiskCaseService
{
    public function __construct(
        private readonly RiskCaseStore $store,
        private readonly RiskAssessmentService $assessment,
    ) {
    }

    /** @return array<string,mixed> */
    public function open(
        string $subjectId,
        array $signals,
        string $source,
        string $eventId,
        ?int $occurredAt = null,
    ): array {
        $subjectId = $this->assertIdentifier($subjectId, 'Subject ID');
        $source = $this->assertIdentifier($source, 'Risk source');
        $eventId = $this->assertIdentifier($eventId, 'Risk event ID');
        $occurredAt ??= time();
        $caseId = hash('sha256', 'br-risk-case|' . $subjectId . '|' . $eventId);
        $existing = $this->store->find($caseId);
        if ($existing !== null) {
            return ['idempotent' => true, 'case' => $existing];
        }

        $result = $this->assessment->assess($signals);
        $case = [
            'case_id' => $caseId,
            'subject_id' => $subjectId,
            'source' => $source,
            'event_id' => $eventId,
            'status' => $result['risk_score'] >= 500 ? 'open' : 'monitoring',
            'review_status' => 'pending',
            'risk_score' => $result['risk_score'],
            'severity' => $result['severity'],
            'factors' => $result['factors'],
            'first_seen_at' => $occurredAt,
            'last_seen_at' => $occurredAt,
            'review_history' => [],
            'policy_version' => $result['policy_version'],
        ];
        $this->store->save($case);

        return ['idempotent' => false, 'case' => $case];
    }

    /** @return array<string,mixed> */
    public function review(
        string $caseId,
        string $decision,
        string $reviewerId,
        string $reason,
        ?int $reviewedAt = null,
    ): array {
        $caseId = $this->assertIdentifier($caseId, 'Risk case ID');
        $reviewerId = $this->assertIdentifier($reviewerId, 'Reviewer ID');
        $reason = trim($reason);
        if ($reason === '' || strlen($reason) > 1000) {
            throw new InvalidArgumentException('Risk review reason must contain 1 to 1000 characters.');
        }
        if (!in_array($decision, ['approve', 'block', 'escalate', 'release'], true)) {
            throw new InvalidArgumentException('Unsupported risk case decision.');
        }
        $case = $this->store->find($caseId);
        if ($case === null) {
            throw new InvalidArgumentException('Risk case not found.');
        }

        $reviewedAt ??= time();
        $case['review_status'] = match ($decision) {
            'approve' => 'approved',
            'block' => 'blocked',
            'release' => 'released',
            default => 'manual_review',
        };
        $case['status'] = match ($decision) {
            'block' => 'blocked',
            'release', 'approve' => 'released',
            default => 'open',
        };
        $case['review_history'][] = [
            'decision' => $decision,
            'reviewer_id' => $reviewerId,
            'reason' => $reason,
            'reviewed_at' => $reviewedAt,
        ];
        $case['last_seen_at'] = $reviewedAt;
        $this->store->save($case);

        return ['idempotent' => false, 'case' => $case];
    }

    /** @return list<array<string,mixed>> */
    public function all(): array
    {
        return $this->store->all();
    }

    private function assertIdentifier(string $value, string $label): string
    {
        $value = trim($value);
        if ($value === '' || strlen($value) > 191) {
            throw new InvalidArgumentException($label . ' must contain 1 to 191 characters.');
        }
        return $value;
    }
}
