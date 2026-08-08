<?php

declare(strict_types=1);

namespace Addons\Analytics\Domain;

final class OperationsReportService
{
    /** @param list<array<string,mixed>> $events @return array<string,mixed> */
    public function summarize(array $events): array
    {
        $summary = [
            'event_count' => count($events),
            'by_type' => [],
            'by_status' => [],
            'virtual_events' => 0,
            'cash_events' => 0,
        ];
        foreach ($events as $event) {
            $type = (string) ($event['type'] ?? 'unknown');
            $status = (string) ($event['status'] ?? 'unknown');
            $summary['by_type'][$type] = ($summary['by_type'][$type] ?? 0) + 1;
            $summary['by_status'][$status] = ($summary['by_status'][$status] ?? 0) + 1;
            if (($event['value_type'] ?? null) === 'virtual') {
                $summary['virtual_events']++;
            }
            if (($event['cash_mode'] ?? false) === true) {
                $summary['cash_events']++;
            }
        }
        return $summary;
    }
}
