#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Apply pending SQL migrations from database/migrations/.
 * Safe to run repeatedly; tracks applied files in schema_migrations.
 */

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$pdo = db();

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS schema_migrations (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        migration VARCHAR(255) NOT NULL UNIQUE,
        applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB'
);

$dir = dirname(__DIR__) . '/database/migrations';
$files = glob($dir . '/*.sql') ?: [];
sort($files);

$appliedStmt = $pdo->query('SELECT migration FROM schema_migrations');
$applied = [];
foreach ($appliedStmt->fetchAll(PDO::FETCH_COLUMN) as $name) {
    $applied[$name] = true;
}

$ignoreErrors = [
    'Duplicate column name',
    'Duplicate key name',
    'already exists',
    'Duplicate foreign key constraint name',
];

/**
 * Remove leading SQL line comments so ALTER/UPDATE blocks are not skipped.
 */
function migrate_sql_executable(string $statement): string
{
    $lines = preg_split('/\r?\n/', $statement) ?: [];
    $kept = [];
    foreach ($lines as $line) {
        $trimmed = ltrim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '--')) {
            continue;
        }
        $kept[] = $line;
    }

    return trim(implode("\n", $kept));
}

foreach ($files as $file) {
    $name = basename($file);
    if (isset($applied[$name])) {
        echo "Skip {$name} (already applied)\n";
        continue;
    }

    $sql = (string) file_get_contents($file);
    $sql = preg_replace('/^\s*USE\s+[^;]+;\s*/mi', '', $sql) ?? $sql;
    $statements = preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [];

    echo "Applying {$name}...\n";
    foreach ($statements as $statement) {
        $statement = migrate_sql_executable(trim($statement));
        if ($statement === '') {
            continue;
        }

        try {
            $pdo->exec($statement);
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            $ignored = false;
            foreach ($ignoreErrors as $needle) {
                if (str_contains($msg, $needle)) {
                    $ignored = true;
                    echo "  note: {$msg}\n";
                    break;
                }
            }
            if (!$ignored) {
                fwrite(STDERR, "Migration {$name} failed: {$msg}\n");
                exit(1);
            }
        }
    }

    $pdo->prepare('INSERT INTO schema_migrations (migration) VALUES (?)')->execute([$name]);
    echo "Applied {$name}\n";
}

echo "All migrations up to date.\n";
