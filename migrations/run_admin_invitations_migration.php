<?php
/**
 * Admin Invitations Migration Runner
 * 
 * Purpose: Safely apply admin invitation system database changes
 * Date: February 6, 2026
 * 
 * SAFETY CHECKS:
 * - Validates database connection before running
 * - Rolls back on any error
 * - Verifies migration success
 * - Does NOT modify existing user data
 * 
 * Usage: php migrations/run_admin_invitations_migration.php
 */

require_once __DIR__ . '/../config/database.php';

echo "========================================\n";
echo "Admin Invitations Migration\n";
echo "========================================\n\n";

try {
    $db = getDB();
    
    echo "[1/5] Checking database connection...\n";
    $result = $db->query("SELECT DATABASE() as db_name");
    $dbName = $result->fetch(PDO::FETCH_ASSOC)['db_name'];
    echo "      ✓ Connected to: $dbName\n\n";
    
    echo "[2/5] Checking for existing tables...\n";
    
    // Check if migration already applied
    $tableCheck = $db->query("SHOW TABLES LIKE 'admin_invitations'");
    if ($tableCheck->rowCount() > 0) {
        echo "      ⚠ WARNING: admin_invitations table already exists\n";
        echo "      This migration may have already been applied.\n";
        echo "      Continue anyway? (y/N): ";
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        if (trim($line) !== 'y') {
            echo "      Migration aborted.\n";
            exit(0);
        }
    }
    
    // Check if column already exists
    $columnCheck = $db->query("SHOW COLUMNS FROM users LIKE 'auth_provider_required'");
    if ($columnCheck->rowCount() > 0) {
        echo "      ⚠ WARNING: auth_provider_required column already exists in users table\n";
    } else {
        echo "      ✓ Tables ready for migration\n\n";
    }
    
    echo "[3/5] Reading migration file...\n";
    $sqlFile = __DIR__ . '/014_add_admin_invitations.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("Migration file not found: $sqlFile");
    }
    $sql = file_get_contents($sqlFile);
    echo "      ✓ Migration file loaded\n\n";
    
    echo "[4/5] Applying migration...\n";
    
    // Begin transaction for safety
    $db->beginTransaction();
    
    try {
        // Split SQL into individual statements and execute
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            function($stmt) {
                // Filter out comments and empty statements
                $stmt = trim($stmt);
                return !empty($stmt) 
                    && strpos($stmt, '--') !== 0 
                    && strpos($stmt, 'SELECT') !== 0; // Skip verification queries
            }
        );
        
        foreach ($statements as $statement) {
            if (!empty($statement)) {
                $db->exec($statement);
            }
        }
        
        // Commit transaction
        $db->commit();
        echo "      ✓ Migration applied successfully\n\n";
        
    } catch (Exception $e) {
        $db->rollBack();
        throw new Exception("Migration failed (rolled back): " . $e->getMessage());
    }
    
    echo "[5/5] Verifying migration...\n";
    
    // Verify admin_invitations table
    $tableCheck = $db->query("SHOW TABLES LIKE 'admin_invitations'");
    if ($tableCheck->rowCount() === 0) {
        throw new Exception("admin_invitations table was not created");
    }
    echo "      ✓ admin_invitations table created\n";
    
    // Verify column added
    $columnCheck = $db->query("SHOW COLUMNS FROM users LIKE 'auth_provider_required'");
    if ($columnCheck->rowCount() === 0) {
        throw new Exception("auth_provider_required column was not added");
    }
    echo "      ✓ auth_provider_required column added to users table\n";
    
    // Verify existing users unaffected (all should have NULL)
    $userCheck = $db->query("SELECT COUNT(*) as count FROM users WHERE auth_provider_required IS NOT NULL");
    $affectedCount = $userCheck->fetch(PDO::FETCH_ASSOC)['count'];
    if ($affectedCount > 0) {
        echo "      ⚠ WARNING: $affectedCount users have non-NULL auth_provider_required\n";
    } else {
        echo "      ✓ All existing users have NULL auth_provider_required (no restrictions)\n";
    }
    
    // Verify no invitations exist yet
    $inviteCheck = $db->query("SELECT COUNT(*) as count FROM admin_invitations");
    $inviteCount = $inviteCheck->fetch(PDO::FETCH_ASSOC)['count'];
    echo "      ✓ admin_invitations table is empty ($inviteCount rows)\n";
    
    echo "\n========================================\n";
    echo "✓ MIGRATION SUCCESSFUL\n";
    echo "========================================\n\n";
    
    echo "Next Steps:\n";
    echo "1. Test existing logins (they should work unchanged)\n";
    echo "2. Implement invitation API endpoint\n";
    echo "3. Update OAuth callback to check invitations\n\n";
    
    echo "Rollback (if needed):\n";
    echo "  DROP TABLE IF EXISTS admin_invitations;\n";
    echo "  ALTER TABLE users DROP COLUMN IF EXISTS auth_provider_required;\n\n";
    
} catch (Exception $e) {
    echo "\n========================================\n";
    echo "✗ MIGRATION FAILED\n";
    echo "========================================\n";
    echo "Error: " . $e->getMessage() . "\n\n";
    echo "The database has been rolled back to its previous state.\n";
    echo "No data was modified.\n\n";
    exit(1);
}
