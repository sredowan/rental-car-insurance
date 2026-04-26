<?php
// Run the Vehicle Type migration
require_once __DIR__ . '/api/config/config.php';
require_once __DIR__ . '/api/config/database.php';
require_once __DIR__ . '/api/helpers/response.php';

$db = Database::get();
$sql = file_get_contents(__DIR__ . '/database/update_vehicle_types.sql');

// Split and execute each statement
$statements = array_filter(array_map('trim', explode(';', $sql)), function($s) { return strlen($s) > 5; });

echo "Starting database update...\n";

foreach ($statements as $stmt) {
    try {
        $db->exec($stmt);
        echo "OK: " . substr(str_replace("\n", " ", $stmt), 0, 80) . "...\n";
    } catch (Exception $e) {
        // Ignore "Duplicate column name" error if already run
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "SKIP: Column already exists.\n";
        } else {
            echo "ERR: " . $e->getMessage() . "\n";
        }
    }
}

echo "\nUpdate complete.\n";
