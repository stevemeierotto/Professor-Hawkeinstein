<?php
/**
 * OAuth States Invitation Token Migration Runner
 * 
 * Purpose: Add invite_token column to oauth_states table
 * Date: February 6, 2026
 * 
 * Usage: php migrations/run_oauth_states_migration.php
 */

require_once __DIR__ . '/../config/database.php';

echo "========================================\n";
echo "OAuth States Invitation Token Migration\n";
echo "========================================\n\n";

try {
    $db = getDB();
    
    echo "[1/3] Checking database connection...\n";
    $result = $db->query("SELECT DATABASE() as db_name");
    $dbName = $result->fetch(PDO::FETCH_ASSOC)['db_name'];
    echo "      ✓ Connected to: $dbName\n\n";
    
    echo "[2/3] Checking for existing column...\n";
    $columnCheck = $db->query("SHOW COLUMNS FROM oauth_states LIKE 'invite_token'");
    if ($columnCheck->rowCount() > 0) {
        echo "      ⚠ WARNING: invite_token column already exists\n";
        echo "      Migration may have already been applied.\n";
        echo "      Continue anyway? (y/N): ";
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        if (trim($line) !== 'y') {
            echo "      Migration aborted.\n";
            exit(0);
        }
    } else {
        echo "      ✓ Column does not exist, proceeding...\n\n";
    }
    
    echo "[3/3] Applying migration...\n";
    
    // Add column
    $db->exec("
        ALTER TABLE oauth_states 
        ADD COLUMN invite_token VARCHAR(64) NULL 
        COMMENT 'Optional admin invitation token to process after OAuth'
        AFTER state_token
    ");
    echo "      ✓ Column added\n";
    
    // Add index
    $db->exec("
        ALTER TABLE oauth_states 
        ADD INDEX idx_invite_token (invite_token)
    ");
    echo "      ✓ Index created\n\n";
    
    // Verify
    $verifyCheck = $db->query("SHOW COLUMNS FROM oauth_states LIKE 'invite_token'");
    if ($verifyCheck->rowCount() === 0) {
        throw new Exception("Column was not created");
    }
    
    echo "========================================\n";
    echo "✓ MIGRATION SUCCESSFUL\n";
    echo "========================================\n\n";
    
    echo "The oauth_states table can now store invitation tokens.\n";
    echo "This enables secure passing of invitations through OAuth flow.\n\n";
    
} catch (Exception $e) {
    echo "\n========================================\n";
    echo "✗ MIGRATION FAILED\n";
    echo "========================================\n";
    echo "Error: " . $e->getMessage() . "\n\n";
    exit(1);
}
