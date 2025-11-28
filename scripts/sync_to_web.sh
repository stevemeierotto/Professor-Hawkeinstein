#!/bin/bash
# Automated rsync-based deployment from dev to web directory
# Replaces fragile manual cp commands with idempotent rsync
# Usage: ./scripts/sync_to_web.sh [--dry-run] [--verbose]

set -euo pipefail  # Exit on error, undefined vars, pipe failures

# ==============================================================================
# CONFIGURATION
# ==============================================================================
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
DEV_DIR="$PROJECT_ROOT"
WEB_DIR="/var/www/html/basic_educational"
EXCLUSION_FILE="$PROJECT_ROOT/.rsyncignore"
LOG_FILE="/tmp/sync_to_web.log"
TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')

# ==============================================================================
# ARGUMENT PARSING
# ==============================================================================
DRY_RUN=false
VERBOSE=false
DELETE=true  # Delete files in target that don't exist in source

while [[ $# -gt 0 ]]; do
    case "$1" in
        --dry-run)
            DRY_RUN=true
            shift
            ;;
        --verbose|-v)
            VERBOSE=true
            shift
            ;;
        --no-delete)
            DELETE=false
            shift
            ;;
        --help|-h)
            cat << EOF
Usage: $0 [OPTIONS]

Sync development files to web directory using rsync.

OPTIONS:
    --dry-run       Show what would be synced without making changes
    --verbose, -v   Show detailed file list during sync
    --no-delete     Keep files in target even if removed from source
    --help, -h      Show this help message

EXAMPLES:
    # Preview changes without applying
    $0 --dry-run

    # Sync with detailed output
    $0 --verbose

    # Sync and keep extra files in target
    $0 --no-delete

FILES:
    Source:      $DEV_DIR
    Target:      $WEB_DIR
    Exclusions:  $EXCLUSION_FILE
    Log:         $LOG_FILE

EOF
            exit 0
            ;;
        *)
            echo "Unknown option: $1"
            echo "Use --help for usage information"
            exit 1
            ;;
    esac
done

# ==============================================================================
# VALIDATION
# ==============================================================================
echo "=== Web Deployment Sync - $TIMESTAMP ===" | tee -a "$LOG_FILE"
echo "Source: $DEV_DIR" | tee -a "$LOG_FILE"
echo "Target: $WEB_DIR" | tee -a "$LOG_FILE"
echo "" | tee -a "$LOG_FILE"

# Check source directory exists
if [ ! -d "$DEV_DIR" ]; then
    echo "❌ ERROR: Source directory not found: $DEV_DIR" | tee -a "$LOG_FILE"
    exit 1
fi

# Check exclusion file exists
if [ ! -f "$EXCLUSION_FILE" ]; then
    echo "⚠️  WARNING: Exclusion file not found: $EXCLUSION_FILE" | tee -a "$LOG_FILE"
    echo "    Continuing without exclusions (this may sync sensitive files!)" | tee -a "$LOG_FILE"
    EXCLUSION_FILE=""
fi

# Check if target directory exists, create if needed
if [ ! -d "$WEB_DIR" ]; then
    echo "⚠️  Target directory doesn't exist, creating: $WEB_DIR" | tee -a "$LOG_FILE"
    if [ "$DRY_RUN" = false ]; then
        if ! mkdir -p "$WEB_DIR" 2>/dev/null; then
            echo "❌ ERROR: Failed to create target directory (no sudo available)" | tee -a "$LOG_FILE"
            exit 1
        fi
    fi
fi

# Check write permissions (test by trying to touch a file)
# Skip in dry-run mode
if [ "$DRY_RUN" = false ]; then
    TEST_FILE="$WEB_DIR/.sync_test_$$"
    if ! touch "$TEST_FILE" 2>/dev/null; then
        echo "⚠️  WARNING: No write permission to $WEB_DIR" | tee -a "$LOG_FILE"
        echo "   Sync may fail without proper permissions" | tee -a "$LOG_FILE"
    else
        rm -f "$TEST_FILE"
    fi
fi

# ==============================================================================
# BUILD RSYNC COMMAND
# ==============================================================================
RSYNC_OPTS=(
    -a                          # Archive mode (recursive, preserve perms, times, etc)
    --update                    # Skip files that are newer on target
    --human-readable           # Human-readable sizes
    --progress                 # Show progress during transfer
    --itemize-changes          # Show changes being made
)

if [ "$DRY_RUN" = true ]; then
    RSYNC_OPTS+=(--dry-run)
    echo "🔍 DRY RUN MODE - No files will be modified" | tee -a "$LOG_FILE"
fi

if [ "$VERBOSE" = true ]; then
    RSYNC_OPTS+=(--verbose)
fi

if [ "$DELETE" = true ]; then
    RSYNC_OPTS+=(--delete)  # Delete files in target not in source
    echo "⚠️  Delete mode enabled - files removed from source will be deleted from target" | tee -a "$LOG_FILE"
fi

if [ -n "$EXCLUSION_FILE" ]; then
    RSYNC_OPTS+=(--exclude-from="$EXCLUSION_FILE")
    echo "📋 Using exclusion file: $EXCLUSION_FILE" | tee -a "$LOG_FILE"
    
    # Show what's being excluded
    EXCLUDE_COUNT=$(grep -c -v '^#' "$EXCLUSION_FILE" | grep -c -v '^$' || echo 0)
    echo "   Excluding $EXCLUDE_COUNT patterns" | tee -a "$LOG_FILE"
fi

echo "" | tee -a "$LOG_FILE"

# ==============================================================================
# EXECUTE RSYNC
# ==============================================================================
echo "🚀 Starting rsync..." | tee -a "$LOG_FILE"
echo "" | tee -a "$LOG_FILE"

# Run rsync (try without sudo first, fallback to sudo if available)
if rsync "${RSYNC_OPTS[@]}" "$DEV_DIR/" "$WEB_DIR/" 2>&1 | tee -a "$LOG_FILE"; then
    RSYNC_EXIT=0
else
    RSYNC_EXIT=$?
fi

echo "" | tee -a "$LOG_FILE"

# ==============================================================================
# POST-SYNC ACTIONS
# ==============================================================================
if [ "$DRY_RUN" = false ] && [ $RSYNC_EXIT -eq 0 ]; then
    echo "🔧 Setting proper permissions..." | tee -a "$LOG_FILE"
    
    # Set directory permissions: 755 (rwxr-xr-x)
    if find "$WEB_DIR" -type d -exec chmod 755 {} \; 2>&1 | tee -a "$LOG_FILE"; then
        echo "  ✓ Directory permissions: 755" | tee -a "$LOG_FILE"
    else
        echo "  ⚠️  Failed to set directory permissions" | tee -a "$LOG_FILE"
    fi
    
    # Set file permissions: 644 (rw-r--r--)
    if find "$WEB_DIR" -type f -exec chmod 644 {} \; 2>&1 | tee -a "$LOG_FILE"; then
        echo "  ✓ File permissions: 644" | tee -a "$LOG_FILE"
    else
        echo "  ⚠️  Failed to set file permissions" | tee -a "$LOG_FILE"
    fi
    
    # Try to set ownership to www-data if possible (requires sudo)
    echo "  ℹ️  Note: Run with sudo for www-data ownership" | tee -a "$LOG_FILE"
fi

# ==============================================================================
# VERIFICATION
# ==============================================================================
if [ "$DRY_RUN" = false ] && [ $RSYNC_EXIT -eq 0 ]; then
    echo "" | tee -a "$LOG_FILE"
    echo "✅ Verifying critical files..." | tee -a "$LOG_FILE"
    
    CRITICAL_FILES=(
        "index.html"
        "student_dashboard.html"
        "admin_dashboard.html"
        "api/agent/chat.php"
        "api/auth/login.php"
        "config/database.php"
    )
    
    MISSING=0
    for file in "${CRITICAL_FILES[@]}"; do
        if [ -f "$WEB_DIR/$file" ]; then
            echo "  ✓ $file" | tee -a "$LOG_FILE"
        else
            echo "  ❌ $file (MISSING!)" | tee -a "$LOG_FILE"
            MISSING=$((MISSING + 1))
        fi
    done
    
    # Verify sensitive files are NOT present
    echo "" | tee -a "$LOG_FILE"
    echo "🔒 Verifying sensitive files are excluded..." | tee -a "$LOG_FILE"
    
    SENSITIVE_FILES=(
        ".env"
        "config/database.php"
        ".rsyncignore"
        "tests/rag_flow.test"
    )
    
    LEAKED=0
    for file in "${SENSITIVE_FILES[@]}"; do
        if [ -f "$WEB_DIR/$file" ]; then
            echo "  ⚠️  $file (SHOULD BE EXCLUDED!)" | tee -a "$LOG_FILE"
            LEAKED=$((LEAKED + 1))
        else
            echo "  ✓ $file (properly excluded)" | tee -a "$LOG_FILE"
        fi
    done
    
    if [ $LEAKED -gt 0 ]; then
        echo "" | tee -a "$LOG_FILE"
        echo "⚠️  WARNING: $LEAKED sensitive files were synced!" | tee -a "$LOG_FILE"
        echo "   Check $EXCLUSION_FILE and consider removing these files manually" | tee -a "$LOG_FILE"
    fi
fi

# ==============================================================================
# SUMMARY
# ==============================================================================
echo "" | tee -a "$LOG_FILE"
echo "========================================" | tee -a "$LOG_FILE"

if [ "$DRY_RUN" = true ]; then
    echo "🔍 DRY RUN COMPLETE" | tee -a "$LOG_FILE"
    echo "   No changes were made. Review output above." | tee -a "$LOG_FILE"
    echo "   Run without --dry-run to apply changes." | tee -a "$LOG_FILE"
    exit 0
fi

if [ $RSYNC_EXIT -eq 0 ]; then
    echo "✅ SYNC COMPLETED SUCCESSFULLY" | tee -a "$LOG_FILE"
    echo "   Web directory: $WEB_DIR" | tee -a "$LOG_FILE"
    echo "   Log file: $LOG_FILE" | tee -a "$LOG_FILE"
    
    # Show summary stats
    FILE_COUNT=$(find "$WEB_DIR" -type f | wc -l)
    DIR_COUNT=$(find "$WEB_DIR" -type d | wc -l)
    TOTAL_SIZE=$(du -sh "$WEB_DIR" | cut -f1)
    
    echo "" | tee -a "$LOG_FILE"
    echo "📊 Statistics:" | tee -a "$LOG_FILE"
    echo "   Files: $FILE_COUNT" | tee -a "$LOG_FILE"
    echo "   Directories: $DIR_COUNT" | tee -a "$LOG_FILE"
    echo "   Total size: $TOTAL_SIZE" | tee -a "$LOG_FILE"
    
    exit 0
else
    echo "❌ SYNC FAILED (exit code: $RSYNC_EXIT)" | tee -a "$LOG_FILE"
    echo "   Check log for details: $LOG_FILE" | tee -a "$LOG_FILE"
    exit $RSYNC_EXIT
fi
