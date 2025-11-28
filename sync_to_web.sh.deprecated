#!/bin/bash
# Sync development files to web directory
# Enhanced with logging and error checking

set -e  # Exit on error

DEV_DIR="/home/steve/Professor_Hawkeinstein"
WEB_DIR="/var/www/html/Professor_Hawkeinstein"
LOG_FILE="/tmp/sync_to_web.log"
TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')

echo "=== Sync to Web - $TIMESTAMP ===" | tee -a "$LOG_FILE"
echo "Dev: $DEV_DIR" | tee -a "$LOG_FILE"
echo "Web: $WEB_DIR" | tee -a "$LOG_FILE"
echo "" | tee -a "$LOG_FILE"

# Function to sync files with verification
sync_files() {
    local pattern=$1
    local description=$2
    local count=0
    local failed=0
    
    echo "Syncing $description..." | tee -a "$LOG_FILE"
    
    for file in $DEV_DIR/$pattern; do
        if [ -f "$file" ]; then
            filename=$(basename "$file")
            if cp "$file" "$WEB_DIR/" 2>>"$LOG_FILE"; then
                count=$((count + 1))
                echo "  ✓ $filename" >> "$LOG_FILE"
            else
                failed=$((failed + 1))
                echo "  ✗ $filename (FAILED)" | tee -a "$LOG_FILE"
            fi
        fi
    done
    
    if [ $failed -eq 0 ]; then
        echo "✓ $description: $count files copied" | tee -a "$LOG_FILE"
    else
        echo "⚠ $description: $count copied, $failed FAILED" | tee -a "$LOG_FILE"
    fi
    
    return $failed
}

# Function to sync directories
sync_directory() {
    local dir=$1
    local description=$2
    
    echo "Syncing $description directory..." | tee -a "$LOG_FILE"
    
    if [ ! -d "$DEV_DIR/$dir" ]; then
        echo "  ⚠ Source directory $dir not found, skipping" | tee -a "$LOG_FILE"
        return 0
    fi
    
    mkdir -p "$WEB_DIR/$dir" 2>>"$LOG_FILE"
    
    if cp -r "$DEV_DIR/$dir/"* "$WEB_DIR/$dir/" 2>>"$LOG_FILE"; then
        local count=$(find "$DEV_DIR/$dir" -type f | wc -l)
        echo "✓ $description: $count files copied" | tee -a "$LOG_FILE"
        return 0
    else
        echo "✗ $description: FAILED" | tee -a "$LOG_FILE"
        return 1
    fi
}

# Track failures
TOTAL_FAILURES=0

# Sync HTML files
sync_files "*.html" "HTML files" || TOTAL_FAILURES=$((TOTAL_FAILURES + $?))

# Sync CSS files
sync_files "*.css" "CSS files" || TOTAL_FAILURES=$((TOTAL_FAILURES + $?))

# Sync JS files
sync_files "*.js" "JS files" || TOTAL_FAILURES=$((TOTAL_FAILURES + $?))

# Sync API directory
sync_directory "api" "API" || TOTAL_FAILURES=$((TOTAL_FAILURES + $?))

# Sync config directory
sync_directory "config" "Config" || TOTAL_FAILURES=$((TOTAL_FAILURES + $?))

# Sync tests directory (optional)
if [ -d "$DEV_DIR/tests" ]; then
    sync_directory "tests" "Tests" || TOTAL_FAILURES=$((TOTAL_FAILURES + $?))
fi

# Set proper permissions
echo "" | tee -a "$LOG_FILE"
echo "Setting permissions..." | tee -a "$LOG_FILE"
chmod -R 755 "$WEB_DIR" 2>>"$LOG_FILE" && echo "✓ Permissions set" | tee -a "$LOG_FILE" || echo "✗ Permission setting failed" | tee -a "$LOG_FILE"

# Verify critical files
echo "" | tee -a "$LOG_FILE"
echo "Verifying critical files..." | tee -a "$LOG_FILE"

CRITICAL_FILES=(
    "index.html"
    "admin_dashboard.html"
    "admin_agent_factory.html"
    "student_dashboard.html"
    "api/admin/create_agent_instance.php"
    "api/admin/chat_instance.php"
    "api/admin/get_agent_instance.php"
    "config/database.php"
)

MISSING_FILES=0
for file in "${CRITICAL_FILES[@]}"; do
    if [ -f "$WEB_DIR/$file" ]; then
        echo "  ✓ $file" >> "$LOG_FILE"
    else
        echo "  ✗ $file (MISSING!)" | tee -a "$LOG_FILE"
        MISSING_FILES=$((MISSING_FILES + 1))
    fi
done

if [ $MISSING_FILES -eq 0 ]; then
    echo "✓ All critical files present" | tee -a "$LOG_FILE"
else
    echo "⚠ $MISSING_FILES critical files missing!" | tee -a "$LOG_FILE"
    TOTAL_FAILURES=$((TOTAL_FAILURES + MISSING_FILES))
fi

# Summary
echo "" | tee -a "$LOG_FILE"
echo "========================================" | tee -a "$LOG_FILE"
if [ $TOTAL_FAILURES -eq 0 ]; then
    echo "✅ Sync completed successfully!" | tee -a "$LOG_FILE"
    echo "Web directory updated: $WEB_DIR" | tee -a "$LOG_FILE"
    exit 0
else
    echo "⚠️  Sync completed with $TOTAL_FAILURES failures" | tee -a "$LOG_FILE"
    echo "Check log: $LOG_FILE" | tee -a "$LOG_FILE"
    exit 1
fi
