<?php

declare(strict_types=1);

namespace Addons\AntiFraud\Domain;

interface RiskCaseStore
{
    /** @return array<string,mixed>|null */
    public function find(string $caseId): ?array;

    /** @param array<string,mixed> $case */
    public function save(array $case): void;

    /** @return list<array<string,mixed>> */
    public function all(): array;
}
