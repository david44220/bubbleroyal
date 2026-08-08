<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Bootstrap.php';

use App\Core\Database\Connection;
use App\Core\Database\MigrationRunner;

$connection = Connection::fromEnvironment();
$runner = new MigrationRunner($connection, dirname(__DIR__) . '/database/migrations');
$pending = $runner->pending();
$applied = $runner->migrate();

echo json_encode([
    'status' => 'ok',
    'pending_before_run' => $pending,
    'applied' => $applied,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
