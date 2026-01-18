<?php
/**
 * OAuth Migration Runner
 * Applies Google OAuth 2.0 database schema changes
 */

require_once __DIR__ . '/../config/database.php';

echo "=== Google OAuth 2.0 Migration ===\n\n";

try {
    $db = getDB();
    
    // Read migration SQL file
    $sqlFile = __DIR__ . '/add_oauth_support.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("Migration file not found: $sqlFile");
    }
    
    $sql = file_get_contents($sqlFile);
    
    // Split by semicolons and execute each statement
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            // Ignore comments and empty statements
            return !empty($stmt) && 
                   strpos($stmt, '--') !== 0 && 
                   strpos($stmt, '/*') !== 0;
        }
    );
    
    $db->beginTransaction();
    
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            echo "Executing: " . substr($statement, 0, 80) . "...\n";
            $db->exec($statement);
        }
    }
    
    $db->commit();
    
    echo "\n✓ Migration completed successfully!\n";
    echo "\nTables created:\n";
    echo "  - auth_providers (OAuth provider linking)\n";
    echo "  - auth_events (authentication audit log)\n";
    echo "  - oauth_states (CSRF state tokens)\n";
    echo "\nUsers table modified:\n";
    echo "  - password_hash now nullable (OAuth-only accounts)\n";
    echo "  - Added email_verified, email_verification_token columns\n";
    echo "\nExisting users backfilled to auth_providers table.\n";
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    echo "\n✗ Migration failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
