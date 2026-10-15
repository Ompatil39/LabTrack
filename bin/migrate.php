<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../config/config.php';

$direction = (isset($argv[1]) && strtolower($argv[1]) === 'down') ? 'down' : 'up';

$pdo = db_pdo();
$pdo->exec(
    'CREATE TABLE IF NOT EXISTS schema_migrations (
        migration_name VARCHAR(255) PRIMARY KEY,
        applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )'
);

$checkStatement = $pdo->prepare('SELECT migration_name FROM schema_migrations WHERE migration_name = ? LIMIT 1');
$insertStatement = $pdo->prepare('INSERT IGNORE INTO schema_migrations (migration_name) VALUES (?)');
$deleteStatement = $pdo->prepare('DELETE FROM schema_migrations WHERE migration_name = ?');

if ($direction === 'up') {
    $migrationFiles = glob(__DIR__ . '/../database/migrations/*.sql') ?: [];
    // Exclude .down.sql files
    $migrationFiles = array_filter($migrationFiles, fn($f) => !str_ends_with($f, '.down.sql'));
    sort($migrationFiles, SORT_NATURAL);

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
            // Execute each statement separately (PDO doesn't support multi-statement by default)
            $stmtIndex = 0;
            foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
                if ($statement !== '') {
                    $stmtIndex++;
                    $pdo->exec($statement . ';');
                }
            }
            $insertStatement->execute([$migrationName]);
            // MySQL implicitly commits DDL, so this transaction may already be closed.
            if ($pdo->inTransaction()) {
                $pdo->commit();
            }
            echo "Applied {$migrationName}" . PHP_EOL;
        } catch (Throwable $throwable) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $executedBeforeFailure = $stmtIndex - 1;
            $ddlNote = ($executedBeforeFailure > 0)
                ? " NOTE: {$executedBeforeFailure} statement(s) executed before this failure; MySQL DDL is auto-committed and cannot be rolled back."
                : '';
            echo sprintf(
                "ERROR [file=%s direction=up stmt=%d]: %s.%s",
                $migrationName,
                $stmtIndex,
                $throwable->getMessage(),
                $ddlNote
            ) . PHP_EOL;
            throw $throwable;
        }
    }

    echo 'Migration (up) complete.' . PHP_EOL;
} else {
    // DOWN: find applied migrations in reverse order
    $allApplied = $pdo->query('SELECT migration_name FROM schema_migrations ORDER BY applied_at DESC, migration_name DESC')->fetchAll();

    if (empty($allApplied)) {
        echo 'No migrations to roll back.' . PHP_EOL;
        exit(0);
    }

    // Only roll back the most recently applied migration
    $last = $allApplied[0]['migration_name'];
    $downFile = __DIR__ . '/../database/migrations/' . preg_replace('/\.sql$/', '.down.sql', $last);

    if (!file_exists($downFile)) {
        echo "No down migration found for {$last} (looked for: " . basename($downFile) . ")" . PHP_EOL;
        exit(1);
    }

    $sql = file_get_contents($downFile);
    if ($sql === false) {
        throw new RuntimeException("Unable to read down migration file: " . basename($downFile));
    }

    $pdo->beginTransaction();

    try {
        $stmtIndex = 0;
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            if ($statement !== '') {
                $stmtIndex++;
                $pdo->exec($statement . ';');
            }
        }
        $deleteStatement->execute([$last]);
        // MySQL implicitly commits DDL, so this transaction may already be closed.
        if ($pdo->inTransaction()) {
            $pdo->commit();
        }
        echo "Rolled back {$last}" . PHP_EOL;
    } catch (Throwable $throwable) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $executedBeforeFailure = $stmtIndex - 1;
        $ddlNote = ($executedBeforeFailure > 0)
            ? " NOTE: {$executedBeforeFailure} statement(s) executed before this failure; MySQL DDL is auto-committed and cannot be rolled back."
            : '';
        echo sprintf(
            "ERROR [file=%s direction=down stmt=%d]: %s.%s",
            $last,
            $stmtIndex,
            $throwable->getMessage(),
            $ddlNote
        ) . PHP_EOL;
        throw $throwable;
    }

    echo 'Migration (down) complete.' . PHP_EOL;
}
