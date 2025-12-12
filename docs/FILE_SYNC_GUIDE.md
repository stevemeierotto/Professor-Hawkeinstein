# File Sync System - Automated Deployment

## Overview

The Professor Hawkeinstein project uses **rsync-based automated deployment** instead of manual file copying. This eliminates fragile `cp` commands and provides idempotent, safe synchronization from the development directory to the web directory.

## Quick Start

### Deploy Changes
```bash
# Preview what will be synced (dry run)
make sync-web-dry

# Deploy to web directory
make sync-web

# Deploy with verbose output
make sync-web-verbose
```

### Verify Deployment
```bash
# Run sync validation tests
make test-sync

# Check sync log
tail -f /tmp/sync_to_web.log
```

## Architecture

### Directory Structure
```
Development:  /home/steve/Professor_Hawkeinstein
Production:   /var/www/html/basic_educational
Exclusions:   .rsyncignore
Logs:         /tmp/sync_to_web.log
```

### Sync Flow
```
1. Read .rsyncignore exclusion patterns
2. rsync dev → web with exclusions
3. Set proper file permissions (644 files, 755 dirs)
4. Verify critical files present
5. Verify sensitive files excluded
6. Log results to /tmp/sync_to_web.log
```

## Security - Excluded Files

The following files/directories are **NEVER** synced to production:

### Sensitive Configuration
- `.env` and `.env.*`
- `*.key`, `*.pem` (private keys)
- `config/database.php` (contains DB credentials)
- `config/secrets.php`

### Development Files
- `tests/` (test suite)
- `migrations/` (SQL migrations)
- `setup*.sh` (setup scripts)
- `*.md` (documentation)
- `.git/` (version control)
- `README.md`, `*.json`

### Build Artifacts
- `llama.cpp/` (LLM source code)
- `models/` (large model files)
- `cpp_agent/build/`, `cpp_agent/bin/`
- `*.o`, `*.so`, `*.a` (compiled objects)

### Logs and Temporary Files
- `logs/` directory
- `*.log`, `*.tmp`, `*.bak`
- `nohup.out`

**Full exclusion list:** See `.rsyncignore` in project root.

## Usage

### Command Line Script

```bash
# Basic usage
./scripts/sync_to_web.sh

# Preview changes without applying
./scripts/sync_to_web.sh --dry-run

# Verbose output
./scripts/sync_to_web.sh --verbose

# Keep extra files in target (don't delete)
./scripts/sync_to_web.sh --no-delete

# Help
./scripts/sync_to_web.sh --help
```

### Makefile Targets

```bash
# Deployment
make sync-web           # Full deployment
make sync-web-dry       # Preview (dry run)
make sync-web-verbose   # Deploy with detailed output
make sync-web-preserve  # Deploy without deleting extra files

# Testing
make test-sync          # Run sync validation tests

# Maintenance
make clean-logs         # Clean log files
make show-logs          # Show log file locations
```

## Testing

### Run Validation Tests
```bash
make test-sync
```

### Test Coverage
1. ✅ Sync script exists and is executable
2. ✅ Exclusion file has patterns
3. ✅ Dry run executes successfully
4. ✅ Web directory accessible
5. ✅ Sensitive files in exclusion list
6. ✅ Sensitive files NOT in web directory
7. ✅ Required web files ARE synced
8. ✅ rsync command available
9. ✅ Log file writable
10. ✅ Makefile target exists

## Verification

### After Deployment

**Check sync status:**
```bash
tail -20 /tmp/sync_to_web.log
```

**Verify critical files:**
```bash
ls /var/www/html/basic_educational/index.html
ls /var/www/html/basic_educational/api/agent/chat.php
```

**Verify sensitive files excluded:**
```bash
# These should NOT exist:
ls /var/www/html/basic_educational/.env                  # Should fail
ls /var/www/html/basic_educational/config/database.php  # Should fail
ls /var/www/html/basic_educational/tests/               # Should fail
```

## Troubleshooting

### Issue: Permission Denied

**Symptom:** Rsync fails with permission errors

**Solution:** Ensure write access to `/var/www/html/basic_educational`
```bash
sudo chown -R $USER:www-data /var/www/html/basic_educational
sudo chmod -R 775 /var/www/html/basic_educational
```

### Issue: Sensitive Files Leaked

**Symptom:** `.env` or `config/database.php` in web directory

**Solution:** Remove manually and update `.rsyncignore`
```bash
sudo rm /var/www/html/basic_educational/.env
sudo rm /var/www/html/basic_educational/config/database.php

# Verify exclusion pattern
grep -E "^\.env$|^config/database\.php$" .rsyncignore

# Re-sync
make sync-web
```

### Issue: Files Not Syncing

**Symptom:** New files don't appear in web directory

**Check if excluded:**
```bash
# Dry run shows what will be synced
make sync-web-dry | grep "your-file.php"
```

**Check exclusion patterns:**
```bash
cat .rsyncignore | grep -v "^$"
```

**Force sync:**
```bash
# Remove file from exclusions if needed
# Then re-sync
make sync-web-verbose
```

### Issue: Old Files Remain

**Symptom:** Deleted files still in web directory

**Solution:** Use `--delete` flag (enabled by default)
```bash
# Verify delete mode enabled
grep "Delete mode enabled" /tmp/sync_to_web.log

# If disabled, re-sync with delete
./scripts/sync_to_web.sh  # delete is default
```

## Comparison: Old vs New System

### ❌ Old System (Manual Copying)
```bash
# Fragile, error-prone
cp *.html /var/www/html/basic_educational/
cp *.css /var/www/html/basic_educational/
cp -r api /var/www/html/basic_educational/
cp -r config /var/www/html/basic_educational/  # ⚠️ Copies sensitive files!
chmod -R 755 /var/www/html/basic_educational/
```

**Problems:**
- 🔴 No exclusion mechanism - copies sensitive files
- 🔴 Requires multiple commands
- 🔴 No verification of success/failure
- 🔴 No logging
- 🔴 Doesn't handle deletions
- 🔴 Overwrites newer files

### ✅ New System (Automated rsync)
```bash
# Single command, safe and idempotent
make sync-web
```

**Benefits:**
- ✅ Automatic exclusion of sensitive files
- ✅ Single command deployment
- ✅ Dry-run preview mode
- ✅ Comprehensive logging
- ✅ Handles file deletions
- ✅ Only updates changed files
- ✅ Verification and validation
- ✅ Proper permission handling

## Migration from Old System

### Step 1: Remove Sensitive Files from Web
```bash
cd /var/www/html/basic_educational
sudo rm -f .env .env.* config/database.php
sudo rm -rf tests/ migrations/ setup*.sh
sudo rm -f README.md *.md
```

### Step 2: Run Initial Sync
```bash
cd /home/steve/Professor_Hawkeinstein
make sync-web-dry   # Preview
make sync-web       # Apply
```

### Step 3: Verify Exclusions
```bash
make test-sync
```

### Step 4: Update Workflows
Replace all manual `cp` commands with:
```bash
make sync-web
```

## Advanced Configuration

### Custom Exclusions

Edit `.rsyncignore` to add patterns:
```bash
# Add your patterns (one per line, no comments inline)
my_sensitive_file.php
custom_config/
*.backup
```

### Custom Web Directory

Edit `scripts/sync_to_web.sh`:
```bash
# Change this line:
WEB_DIR="/var/www/html/basic_educational"

# To your directory:
WEB_DIR="/var/www/html/your_directory"
```

### Automation with Git Hooks

Add to `.git/hooks/post-commit`:
```bash
#!/bin/bash
# Auto-sync after commit
make sync-web-dry
echo "Run 'make sync-web' to deploy"
```

## Performance

### Benchmarks
- **First sync:** ~2-3 seconds (300+ files)
- **Incremental sync:** ~0.5 seconds (5-10 changed files)
- **Dry run:** ~0.3 seconds

### Optimization
- Only changed files are transferred
- System prompt caching reduces LLM overhead
- Parallel rsync for large directories (if needed)

## Maintenance

### Log Rotation
```bash
# Logs are in /tmp/ and auto-cleared on reboot
# Manual cleanup:
make clean-logs
```

### Regular Verification
```bash
# Run weekly
make test-sync

# Check for leaked sensitive files
ls /var/www/html/basic_educational/.env 2>&1 | grep "No such file" && echo "✅ Good"
```

## References

- **Script:** `scripts/sync_to_web.sh`
- **Exclusions:** `.rsyncignore`
- **Tests:** `tests/sync.test`
- **Makefile:** `Makefile` (sync-web targets)
- **Logs:** `/tmp/sync_to_web.log`

## Support

### Questions?
1. Check logs: `tail -f /tmp/sync_to_web.log`
2. Run tests: `make test-sync`
3. Dry run: `make sync-web-dry`
4. Help: `./scripts/sync_to_web.sh --help`
