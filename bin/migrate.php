<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../config/config.php';

$pdo = db_pdo();
$pdo->exec(
    'CREATE TABLE IF NOT EXISTS schema_migrations (
        migration_name VARCHAR(255) PRIMARY KEY,
        applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )'
);

$migrationFiles = glob(__DIR__ . '/../database/migrations/*.sql') ?: [];
sort($migrationFiles, SORT_NATURAL);

$checkStatement = $pdo->prepare('SELECT migration_name FROM schema_migrations WHERE migration_name = ? LIMIT 1');
$insertStatement = $pdo->prepare('INSERT INTO schema_migrations (migration_name) VALUES (?)');

foreach ($migrationFiles as $filePath) {
    $migrationName = basename($filePath);
    $checkStatement->execute([$migrationName]);

    if ($checkStatement->fetch()) {
        echo "Skipping {$migrationName} (already applied)" . PHP_EOL;
        continue;
    }

    $sql = file_get_contents($filePath);
    if ($sql === false) {
        throw new RuntimeException("Unable to read migration file: {$migrationName}");
    }

    $pdo->beginTransaction();

    try {
        $pdo->exec($sql);
        $insertStatement->execute([$migrationName]);
        $pdo->commit();
        echo "Applied {$migrationName}" . PHP_EOL;
    } catch (Throwable $throwable) {
        $pdo->rollBack();
        throw $throwable;
    }
}

echo 'Migration run complete.' . PHP_EOL;
