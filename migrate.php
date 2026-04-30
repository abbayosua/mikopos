<?php

require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

use Miko\Database;

echo "Running migrations...\n";

try {
    $pdo = Database::getInstance()->getConnection();

    $migrationsDir = __DIR__ . '/migrations';
    $migrationFiles = glob($migrationsDir . '/*.sql');
    sort($migrationFiles);

    $pdo->exec("CREATE TABLE IF NOT EXISTS migrations (
        id SERIAL PRIMARY KEY,
        filename VARCHAR(255) NOT NULL UNIQUE,
        executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $executed = $pdo->query("SELECT filename FROM migrations")->fetchAll(\PDO::FETCH_COLUMN);

    foreach ($migrationFiles as $file) {
        $filename = basename($file);

        if (in_array($filename, $executed)) {
            echo "  [SKIP] {$filename} (already executed)\n";
            continue;
        }

        $sql = file_get_contents($file);

        try {
            $pdo->beginTransaction();
            $pdo->exec($sql);
            $pdo->exec("INSERT INTO migrations (filename) VALUES ('{$filename}')");
            $pdo->commit();
            echo "  [OK]   {$filename}\n";
        } catch (\Exception $e) {
            $pdo->rollBack();
            echo "  [FAIL] {$filename}: {$e->getMessage()}\n";
            exit(1);
        }
    }

    echo "\nAll migrations completed!\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
