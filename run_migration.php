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

function column_exists(PDO $db, string $table, string $column): bool {
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

$customerOtpColumns = [
    'otp_code'       => 'ALTER TABLE customers ADD COLUMN otp_code VARCHAR(64) NULL AFTER email_verified_at',
    'otp_expires_at' => 'ALTER TABLE customers ADD COLUMN otp_expires_at TIMESTAMP NULL AFTER otp_code',
    'otp_attempts'   => 'ALTER TABLE customers ADD COLUMN otp_attempts TINYINT DEFAULT 0 AFTER otp_expires_at',
];

foreach ($customerOtpColumns as $column => $statement) {
    try {
        if (column_exists($db, 'customers', $column)) {
            echo "SKIP: customers.$column already exists\n";
            continue;
        }
        $db->exec($statement);
        echo "OK: added customers.$column\n";
    } catch (Exception $e) {
        echo "ERR: customers.$column - " . $e->getMessage() . "\n";
    }
}
