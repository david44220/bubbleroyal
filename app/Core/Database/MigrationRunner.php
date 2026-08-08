<?php

declare(strict_types=1);

namespace App\Core\Database;

use PDO;
use RuntimeException;

final class MigrationRunner
{
    public function __construct(
        private readonly PDO $connection,
        private readonly string $directory,
    ) {
    }

    /** @return list<string> */
    public function pending(): array
    {
        $this->ensureTrackingTable();
        $applied = $this->appliedVersions();
        $files = glob(rtrim($this->directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.sql') ?: [];
        sort($files, SORT_STRING);

        $pending = [];
        foreach ($files as $file) {
            $version = basename($file, '.sql');
            if (!isset($applied[$version])) {
                $pending[] = $version;
            }
        }

        return $pending;
    }

    public function migrate(): int
    {
        $this->ensureTrackingTable();
        $applied = $this->appliedVersions();
        $files = glob(rtrim($this->directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.sql') ?: [];
        sort($files, SORT_STRING);
        $count = 0;

        foreach ($files as $file) {
            $version = basename($file, '.sql');
            if (isset($applied[$version])) {
                continue;
            }

            $sql = file_get_contents($file);
            if ($sql === false || trim($sql) === '') {
                throw new RuntimeException('Migration is empty or unreadable: ' . $version);
            }

            $this->connection->exec($sql);
            $statement = $this->connection->prepare(
                'INSERT INTO br_schema_migrations (version, applied_at) VALUES (:version, UTC_TIMESTAMP(6))',
            );
            $statement->execute(['version' => $version]);
            $count++;
        }

        return $count;
    }

    private function ensureTrackingTable(): void
    {
        $this->connection->exec(
            'CREATE TABLE IF NOT EXISTS br_schema_migrations ('
            . 'version VARCHAR(128) NOT NULL PRIMARY KEY, '
            . 'applied_at DATETIME(6) NOT NULL'
            . ') ENGINE=InnoDB',
        );
    }

    /** @return array<string,true> */
    private function appliedVersions(): array
    {
        $rows = $this->connection->query('SELECT version FROM br_schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
        $versions = [];
        foreach ($rows as $version) {
            $versions[(string) $version] = true;
        }
        return $versions;
    }
}
