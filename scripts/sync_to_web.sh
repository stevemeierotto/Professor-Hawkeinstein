#!/bin/bash
# Automated rsync-based deployment from dev to web directory
# Clean, minimal, predictable deployment without ownership changes
# Usage: ./scripts/sync_to_web.sh [--dry-run]

set -euo pipefail  # Exit on error, undefined vars, pipe failures

# ==============================================================================
# CONFIGURATION
# ==============================================================================
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
SOURCE="$PROJECT_ROOT"
TARGET="/var/www/html/basic_educational"
EXCLUSION_FILE="$PROJECT_ROOT/.rsyncignore"
TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')

# ==============================================================================
# ARGUMENT PARSING
# ==============================================================================
DRY_RUN=false

while [[ $# -gt 0 ]]; do
    case "$1" in
        --dry-run)
            DRY_RUN=true
            shift
            ;;
        --help|-h)
            cat << EOF
Usage: $0 [--dry-run]

Sync development files to production web directory.

OPTIONS:
    --dry-run       Preview changes without applying them
    --help, -h      Show this help message

PATHS:
    Source:      $SOURCE
    Target:      $TARGET
    Exclusions:  $EXCLUSION_FILE

EXAMPLES:
    # Preview what will be synced
    $0 --dry-run

    # Deploy to production
    $0

EOF
            exit 0
            ;;
        *)
            echo "❌ Unknown option: $1"
            echo "Use --help for usage information"
            exit 1
            ;;
    esac
done

# ==============================================================================
# SAFETY CHECKS
# ==============================================================================
echo "=== Web Deployment Sync - $TIMESTAMP ==="
echo "Source: $SOURCE"
echo "Target: $TARGET"
echo ""

# Check source directory exists
if [ ! -d "$SOURCE" ]; then
    echo "❌ ERROR: Source directory not found: $SOURCE"
    exit 1
fi

# Check exclusion file exists
if [ ! -f "$EXCLUSION_FILE" ]; then
    echo "❌ ERROR: Exclusion file not found: $EXCLUSION_FILE"
    echo "Cannot proceed without exclusion rules (would sync sensitive files)"
    exit 1
fi

# Check target directory exists
if [ ! -d "$TARGET" ]; then
    echo "❌ ERROR: Target directory not found: $TARGET"
    echo "Create it first or check permissions"
    exit 1
fi

# Check write permissions (skip in dry-run mode)
if [ "$DRY_RUN" = false ]; then
    TEST_FILE="$TARGET/.sync_test_$$"
    if ! touch "$TEST_FILE" 2>/dev/null; then
        echo "❌ ERROR: No write permission to $TARGET"
        echo "Check ownership and permissions before deploying"
        exit 1
    fi
    rm -f "$TEST_FILE"
fi

# ==============================================================================
# EXECUTE RSYNC
# ==============================================================================
RSYNC_OPTS=(
    -a                          # Archive mode (recursive, preserve perms, times, etc)
    --delete-after              # Delete files after transfer (safer)
    --exclude-from="$EXCLUSION_FILE"
)

if [ "$DRY_RUN" = true ]; then
    RSYNC_OPTS+=(--dry-run --itemize-changes)
    echo "🔍 DRY RUN MODE - No files will be modified"
    echo ""
fi

echo "🚀 Starting sync..."
echo ""

if rsync "${RSYNC_OPTS[@]}" "$SOURCE/" "$TARGET/"; then
    RSYNC_EXIT=0
else
    RSYNC_EXIT=$?
fi

echo ""

# ==============================================================================
# SUMMARY
# ==============================================================================
if [ "$DRY_RUN" = true ]; then
    echo "========================================="
    echo "🔍 DRY RUN COMPLETE"
    echo "No changes were made. Review output above."
    echo "Run without --dry-run to apply changes."
    exit 0
fi

if [ $RSYNC_EXIT -eq 0 ]; then
    echo "========================================="
    echo "✅ SYNC COMPLETED SUCCESSFULLY"
    echo "Production directory: $TARGET"
    exit 0
else
    echo "========================================="
    echo "❌ SYNC FAILED (exit code: $RSYNC_EXIT)"
    echo "Review errors above and fix before retrying"
    exit $RSYNC_EXIT
fi
