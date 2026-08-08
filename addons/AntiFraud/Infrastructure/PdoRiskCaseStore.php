<?php

declare(strict_types=1);

namespace Addons\AntiFraud\Infrastructure;

use Addons\AntiFraud\Domain\RiskCaseStore;
use PDO;

final class PdoRiskCaseStore implements RiskCaseStore
{
    public function __construct(
        private readonly PDO $connection,
    ) {
    }

    public function find(string $caseId): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT case_id, subject_id, source, event_id, status, review_status, risk_score, severity, factors, '
            . 'UNIX_TIMESTAMP(first_seen_at) AS first_seen_at, UNIX_TIMESTAMP(last_seen_at) AS last_seen_at, policy_version '
            . 'FROM br_risk_cases WHERE case_id = :case_id LIMIT 1',
        );
        $statement->execute(['case_id' => $caseId]);
        $row = $statement->fetch();
        return is_array($row) ? $this->normalise($row) : null;
    }

    public function save(array $case): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO br_risk_cases '
            . '(case_id, subject_id, source, event_id, status, review_status, risk_score, severity, factors, '
            . 'first_seen_at, last_seen_at, policy_version) VALUES (:case_id, :subject_id, :source, :event_id, '
            . ':status, :review_status, :risk_score, :severity, :factors, FROM_UNIXTIME(:first_seen_at), '
            . 'FROM_UNIXTIME(:last_seen_at), :policy_version) ON DUPLICATE KEY UPDATE status = VALUES(status), '
            . 'review_status = VALUES(review_status), risk_score = VALUES(risk_score), severity = VALUES(severity), '
            . 'factors = VALUES(factors), last_seen_at = VALUES(last_seen_at)',
        );
        $statement->execute([
            'case_id' => $case['case_id'],
            'subject_id' => $case['subject_id'],
            'source' => $case['source'],
            'event_id' => $case['event_id'],
            'status' => $case['status'],
            'review_status' => $case['review_status'],
            'risk_score' => $case['risk_score'],
            'severity' => $case['severity'],
            'factors' => json_encode($case['factors'] ?? [], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            'first_seen_at' => $case['first_seen_at'],
            'last_seen_at' => $case['last_seen_at'],
            'policy_version' => $case['policy_version'],
        ]);

        foreach (($case['review_history'] ?? []) as $review) {
            if (!is_array($review)) {
                continue;
            }
            $reviewId = hash('sha256', implode('|', [
                $case['case_id'],
                (string) ($review['decision'] ?? ''),
                (string) ($review['reviewer_id'] ?? ''),
                (string) ($review['reviewed_at'] ?? ''),
            ]));
            $insertReview = $this->connection->prepare(
                'INSERT IGNORE INTO br_risk_case_reviews '
                . '(review_id, case_id, decision, reviewer_id, reason, reviewed_at) VALUES '
                . '(:review_id, :case_id, :decision, :reviewer_id, :reason, FROM_UNIXTIME(:reviewed_at))',
            );
            $insertReview->execute([
                'review_id' => $reviewId,
                'case_id' => $case['case_id'],
                'decision' => $review['decision'],
                'reviewer_id' => $review['reviewer_id'],
                'reason' => $review['reason'],
                'reviewed_at' => $review['reviewed_at'],
            ]);
        }
    }

    public function all(): array
    {
        $rows = $this->connection->query(
            'SELECT case_id, subject_id, source, event_id, status, review_status, risk_score, severity, factors, '
            . 'UNIX_TIMESTAMP(first_seen_at) AS first_seen_at, UNIX_TIMESTAMP(last_seen_at) AS last_seen_at, policy_version '
            . 'FROM br_risk_cases ORDER BY last_seen_at DESC, case_id ASC',
        )->fetchAll();
        return array_map(fn (array $row): array => $this->normalise($row), $rows);
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function normalise(array $row): array
    {
        $row['factors'] = is_string($row['factors']) && trim($row['factors']) !== ''
            ? json_decode($row['factors'], true, 32, JSON_THROW_ON_ERROR)
            : [];
        $row['review_history'] = $this->reviews((string) $row['case_id']);
        foreach (['risk_score', 'first_seen_at', 'last_seen_at'] as $key) {
            $row[$key] = (int) $row[$key];
        }
        return $row;
    }

    /** @return list<array<string,mixed>> */
    private function reviews(string $caseId): array
    {
        $statement = $this->connection->prepare(
            'SELECT decision, reviewer_id, reason, UNIX_TIMESTAMP(reviewed_at) AS reviewed_at '
            . 'FROM br_risk_case_reviews WHERE case_id = :case_id ORDER BY reviewed_at ASC, id ASC',
        );
        $statement->execute(['case_id' => $caseId]);
        $rows = $statement->fetchAll();
        foreach ($rows as &$row) {
            $row['reviewed_at'] = (int) $row['reviewed_at'];
        }
        unset($row);
        return $rows;
    }
}
