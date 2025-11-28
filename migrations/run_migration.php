<?php
/**
 * Run Database Migration: Add last_active column to agents table
 * Usage: php run_migration.php
 */

require_once __DIR__ . '/../config/database.php';

try {
    $db = getDB();
    
    echo "=== Running Migration: Add last_active Column ===\n\n";
    
    // Check if column already exists
    $checkStmt = $db->query("
        SELECT COUNT(*) as col_count
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = 'professorhawkeinstein_db'
        AND TABLE_NAME = 'agents'
        AND COLUMN_NAME = 'last_active'
    ");
    $result = $checkStmt->fetch();
    
    if ($result['col_count'] > 0) {
        echo "✓ last_active column already exists\n";
    } else {
        echo "Adding last_active column to agents table...\n";
        $db->exec("ALTER TABLE agents ADD COLUMN last_active TIMESTAMP NULL DEFAULT NULL AFTER is_active");
        echo "✓ last_active column added\n";
    }
    
    // Check if index exists
    $indexStmt = $db->query("
        SELECT COUNT(*) as idx_count
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = 'professorhawkeinstein_db'
        AND TABLE_NAME = 'agents'
        AND INDEX_NAME = 'idx_last_active'
    ");
    $result = $indexStmt->fetch();
    
    if ($result['idx_count'] > 0) {
        echo "✓ idx_last_active index already exists\n";
    } else {
        echo "Adding index on last_active...\n";
        $db->exec("ALTER TABLE agents ADD INDEX idx_last_active (last_active)");
        echo "✓ idx_last_active index added\n";
    }
    
    // Show final schema
    echo "\nFinal agents table schema:\n";
    $schemaStmt = $db->query("DESCRIBE agents");
    while ($row = $schemaStmt->fetch()) {
        if (strpos($row['Field'], 'active') !== false) {
            echo "  - {$row['Field']}: {$row['Type']} (Null: {$row['Null']}, Default: {$row['Default']})\n";
        }
    }
    
    echo "\n✅ Migration completed successfully\n";
    
} catch (Exception $e) {
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
