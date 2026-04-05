<?php
// Run the Phase 5 migration
require_once __DIR__ . '/api/config/config.php';
require_once __DIR__ . '/api/config/database.php';

$db = Database::get();
$sql = file_get_contents(__DIR__ . '/database/migration_phase5.sql');

// Split and execute each statement
$statements = array_filter(array_map('trim', explode(';', $sql)), fn($s) => strlen($s) > 5);

foreach ($statements as $stmt) {
    try {
        $db->exec($stmt);
        echo "OK: " . substr($stmt, 0, 70) . "...\n";
    } catch (Exception $e) {
        echo "ERR: " . $e->getMessage() . "\n";
    }
}

echo "\nMigration complete.\n";
