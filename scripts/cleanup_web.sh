#!/bin/bash
# Cleanup sensitive and development files from web directory
# Run this once to prepare for automated sync system

set -euo pipefail

WEB_DIR="/var/www/html/basic_educational"

echo "=== Cleaning Web Directory ==="
echo "Target: $WEB_DIR"
echo ""

# List of patterns to remove
PATTERNS_TO_REMOVE=(
    ".env*"
    "*.md"
    "setup*.sh"
    "setup*.sql"
    "test_*.php"
    "test_*.html"
    "README.md"
    "*.test"
    "migrations/"
)

echo "Removing development and sensitive files..."
echo ""

REMOVED=0
ERRORS=0

for pattern in "${PATTERNS_TO_REMOVE[@]}"; do
    echo "Checking: $pattern"
    
    # Find and remove matching files/dirs
    if [ "$pattern" == "migrations/" ]; then
        # Remove directory
        if [ -d "$WEB_DIR/migrations" ]; then
            rm -rf "$WEB_DIR/migrations" && echo "  ✓ Removed migrations/" && REMOVED=$((REMOVED + 1))
        fi
    else
        # Remove files matching pattern - handle no matches gracefully
        shopt -s nullglob
        for file in $WEB_DIR/$pattern; do
            if [ -e "$file" ]; then
                rm -f "$file" && echo "  ✓ Removed: $(basename $file)" && REMOVED=$((REMOVED + 1))
            fi
        done
        shopt -u nullglob
    fi
done

echo ""
echo "========================================="
echo "Cleanup complete!"
echo "Files removed: $REMOVED"
echo ""
echo "Next steps:"
echo "  1. Verify: make test-sync"
echo "  2. Deploy: make sync-web"
echo ""
