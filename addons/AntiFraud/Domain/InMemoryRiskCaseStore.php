<?php

declare(strict_types=1);

namespace Addons\AntiFraud\Domain;

final class InMemoryRiskCaseStore implements RiskCaseStore
{
    /** @var array<string,array<string,mixed>> */
    private array $cases = [];

    public function find(string $caseId): ?array
    {
        return $this->cases[$caseId] ?? null;
    }

    public function save(array $case): void
    {
        $this->cases[(string) $case['case_id']] = $case;
    }

    public function all(): array
    {
        return array_values($this->cases);
    }
}
