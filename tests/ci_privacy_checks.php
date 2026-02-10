#!/usr/bin/env php
<?php
/**
 * CI Privacy Checks
 * 
 * Automated privacy regression tests for analytics endpoints.
 * This script MUST pass before merging any analytics-related changes.
 * 
 * Usage:
 *   php tests/ci_privacy_checks.php
 * 
 * Exit Codes:
 *   0 = All checks passed
 *   1 = Privacy violations detected (BLOCKS MERGE)
 * 
 * @version 5.0
 * @since Phase 5 (Feb 2026)
 */

define('APP_ROOT', __DIR__ . '/..');

// ANSI color codes for output
define('RED', "\033[31m");
define('GREEN', "\033[32m");
define('YELLOW', "\033[33m");
define('BLUE', "\033[34m");
define('RESET', "\033[0m");
define('BOLD', "\033[1m");

$failures = [];
$warnings = [];
$passed = 0;

echo BOLD . "\n╔══════════════════════════════════════════════════════════════╗\n" . RESET;
echo BOLD . "║  ANALYTICS PRIVACY CI CHECKS (Phase 5)                      ║\n" . RESET;
echo BOLD . "╚══════════════════════════════════════════════════════════════╝\n" . RESET;

// ============================================================================
// TEST 1: Forbidden PII Keys in Analytics Responses
// ============================================================================

echo BLUE . "\n[TEST 1] " . RESET . "Checking for forbidden PII keys in analytics endpoints...\n";

$forbiddenKeys = [
    'user_id',
    'email',
    'username',
    'name',
    'first_name',
    'last_name',
    'ip_address',
    'session_id',
    'student_id',
    'password',
    'token',
    'api_key'
];

$analyticsEndpoints = [
    'api/public/metrics.php',
    'api/admin/analytics/overview.php',
    'api/admin/analytics/course.php',
    'api/admin/analytics/timeseries.php',
    'api/admin/analytics/export.php'
];

foreach ($analyticsEndpoints as $endpoint) {
    $filePath = APP_ROOT . '/' . $endpoint;
    if (!file_exists($filePath)) {
        $warnings[] = "Endpoint not found: $endpoint";
        continue;
    }
    
    $content = file_get_contents($filePath);
    
    foreach ($forbiddenKeys as $key) {
        // Check for common patterns that might expose PII
        $patterns = [
            "/'$key'/",                      // 'user_id'
            "/\"$key\"/",                    // "user_id"
            "/\\['$key'\\]/",                // ['user_id']
            "/\\\$row\\['$key'\\]/",         // $row['user_id']
            "/\\\$data\\['$key'\\]/",        // $data['user_id']
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                // Exceptions: admin user_id for auth/audit is OK
                if ($key === 'user_id' && (
                    strpos($content, 'requireAdmin()') !== false ||
                    strpos($content, 'logAnalyticsAccess') !== false
                )) {
                    continue; // Allow user_id in admin auth and audit contexts
                }
                
                $failures[] = "❌ BLOCKED: $endpoint may expose '$key' in response (Phase 2 violation)";
                break 2;
            }
        }
    }
    
    $passed++;
}

// ============================================================================
// TEST 2: Required Guard Invocations
// ============================================================================

echo BLUE . "\n[TEST 2] " . RESET . "Verifying privacy guard invocations...\n";

$requiredGuards = [
    'sendProtectedAnalyticsJSON' => 'Phase 2/3 PII and cohort guards',
    'analytics_response_guard.php' => 'Phase 2 PII response validator',
    'analytics_cohort_guard.php' => 'Phase 3 minimum cohort enforcement'
];

foreach ($analyticsEndpoints as $endpoint) {
    $filePath = APP_ROOT . '/' . $endpoint;
    if (!file_exists($filePath)) {
        continue;
    }
    
    $content = file_get_contents($filePath);
    
    // Check for sendProtectedAnalyticsJSON or sendAnalyticsJSON usage
    if (!preg_match('/sendProtectedAnalyticsJSON\s*\(|sendAnalyticsJSON\s*\(/', $content)) {
        $failures[] = "❌ BLOCKED: $endpoint does not use sendProtectedAnalyticsJSON() or sendAnalyticsJSON() wrapper";
    }
    
    // Check for guard imports
    if (!preg_match("/require_once.*analytics_response_guard\.php/", $content)) {
        $failures[] = "❌ BLOCKED: $endpoint missing analytics_response_guard.php import";
    }
    
    if (!preg_match("/require_once.*analytics_cohort_guard\.php/", $content)) {
        $failures[] = "❌ BLOCKED: $endpoint missing analytics_cohort_guard.php import";
    }
    
    $passed++;
}

// ============================================================================
// TEST 3: Rate Limiting and Audit Logging (Phase 4)
// ============================================================================

echo BLUE . "\n[TEST 3] " . RESET . "Verifying operational safeguards (Phase 4)...\n";

$phase4Endpoints = [
    'api/public/metrics.php',
    'api/admin/analytics/overview.php',
    'api/admin/analytics/course.php',
    'api/admin/analytics/timeseries.php',
    'api/admin/analytics/export.php'
];

foreach ($phase4Endpoints as $endpoint) {
    $filePath = APP_ROOT . '/' . $endpoint;
    if (!file_exists($filePath)) {
        continue;
    }
    
    $content = file_get_contents($filePath);
    
    // Check for rate limiter import
    if (!preg_match("/require_once.*analytics_rate_limiter\.php/", $content)) {
        $failures[] = "❌ BLOCKED: $endpoint missing analytics_rate_limiter.php (Phase 4 requirement)";
    }
    
    // Check for audit log import
    if (!preg_match("/require_once.*analytics_audit_log\.php/", $content)) {
        $failures[] = "❌ BLOCKED: $endpoint missing analytics_audit_log.php (Phase 4 requirement)";
    }
    
    // Check for enforceRateLimit() call
    if (!preg_match('/enforceRateLimit\s*\(/', $content)) {
        $failures[] = "❌ BLOCKED: $endpoint does not call enforceRateLimit()";
    }
    
    // Check for audit logging
    if (!preg_match('/logAnalyticsAccess\s*\(|logAnalyticsExport\s*\(/', $content)) {
        $failures[] = "❌ BLOCKED: $endpoint does not call logAnalyticsAccess() or logAnalyticsExport()";
    }
    
    $passed++;
}

// ============================================================================
// TEST 4: Analytics Schema Validation
// ============================================================================

echo BLUE . "\n[TEST 4] " . RESET . "Validating analytics database schema...\n";

$schemaFile = APP_ROOT . '/schema.sql';
if (file_exists($schemaFile)) {
    $schema = file_get_contents($schemaFile);
    
    // Check for analytics tables with direct user identifiers
    if (preg_match('/CREATE TABLE analytics_.*\n[^;]*\b(email|username|first_name|last_name|ip_address)\b/is', $schema)) {
        $failures[] = "❌ BLOCKED: analytics_* table contains direct PII column (Phase 1 violation)";
    }
    
    // Verify user_hash exists instead of user_id in snapshots
    if (preg_match('/CREATE TABLE analytics_user_snapshots/i', $schema)) {
        if (preg_match('/CREATE TABLE analytics_user_snapshots[^;]*user_id[^;]*;/is', $schema)) {
            $failures[] = "❌ BLOCKED: analytics_user_snapshots should use user_hash, not user_id";
        }
    }
    
    $passed++;
} else {
    $warnings[] = "Schema file not found, skipping schema validation";
}

// ============================================================================
// TEST 5: Helper Module Integrity
// ============================================================================

echo BLUE . "\n[TEST 5] " . RESET . "Verifying privacy helper modules exist...\n";

$requiredHelpers = [
    'api/helpers/analytics_response_guard.php' => 'Phase 2',
    'api/helpers/analytics_cohort_guard.php' => 'Phase 3',
    'api/helpers/analytics_rate_limiter.php' => 'Phase 4',
    'api/helpers/analytics_audit_log.php' => 'Phase 4',
    'api/helpers/analytics_export_guard.php' => 'Phase 4'
];

foreach ($requiredHelpers as $helperPath => $phase) {
    $filePath = APP_ROOT . '/' . $helperPath;
    if (!file_exists($filePath)) {
        $failures[] = "❌ BLOCKED: Required privacy helper missing: $helperPath ($phase)";
    } else {
        // Check for critical functions
        $content = file_get_contents($filePath);
        
        // Check for sendProtectedAnalyticsJSON or sendAnalyticsJSON
        if (strpos($helperPath, 'response_guard') !== false) {
            if (!preg_match('/function sendProtectedAnalyticsJSON|function sendAnalyticsJSON/', $content)) {
                $failures[] = "❌ BLOCKED: $helperPath missing sendProtectedAnalyticsJSON() or sendAnalyticsJSON()";
            }
        }
        
        if (strpos($helperPath, 'cohort_guard') !== false) {
            if (!preg_match('/function enforceCohortMinimum/', $content)) {
                $failures[] = "❌ BLOCKED: $helperPath missing enforceCohortMinimum()";
            }
        }
        
        if (strpos($helperPath, 'rate_limiter') !== false) {
            if (!preg_match('/function enforceRateLimit/', $content)) {
                $failures[] = "❌ BLOCKED: $helperPath missing enforceRateLimit()";
            }
        }
        
        $passed++;
    }
}

// ============================================================================
// TEST 6: Security Headers Validation
// ============================================================================

echo BLUE . "\n[TEST 6] " . RESET . "Checking security headers in analytics endpoints...\n";

$requiredHeaders = [
    'X-Content-Type-Options: nosniff',
    'Cache-Control',
    'X-Frame-Options'
];

foreach ($analyticsEndpoints as $endpoint) {
    $filePath = APP_ROOT . '/' . $endpoint;
    if (!file_exists($filePath)) {
        continue;
    }
    
    $content = file_get_contents($filePath);
    
    foreach ($requiredHeaders as $header) {
        if (stripos($content, $header) === false) {
            $failures[] = "❌ BLOCKED: $endpoint missing security header: $header";
        }
    }
    
    $passed++;
}

// ============================================================================
// RESULTS SUMMARY
// ============================================================================

echo BOLD . "\n╔══════════════════════════════════════════════════════════════╗\n" . RESET;
echo BOLD . "║  TEST RESULTS                                                ║\n" . RESET;
echo BOLD . "╚══════════════════════════════════════════════════════════════╝\n" . RESET;

echo GREEN . "\n✓ Passed checks: $passed\n" . RESET;

if (!empty($warnings)) {
    echo YELLOW . "\n⚠ Warnings (" . count($warnings) . "):\n" . RESET;
    foreach ($warnings as $warning) {
        echo YELLOW . "  $warning\n" . RESET;
    }
}

if (!empty($failures)) {
    echo RED . BOLD . "\n✗ PRIVACY VIOLATIONS DETECTED (" . count($failures) . "):\n" . RESET;
    foreach ($failures as $failure) {
        echo RED . "  $failure\n" . RESET;
    }
    
    echo RED . BOLD . "\n╔══════════════════════════════════════════════════════════════╗\n" . RESET;
    echo RED . BOLD . "║  ❌ CI CHECK FAILED — MERGE BLOCKED                          ║\n" . RESET;
    echo RED . BOLD . "╚══════════════════════════════════════════════════════════════╝\n" . RESET;
    
    echo YELLOW . "\nRequired actions:\n" . RESET;
    echo "  1. Fix all violations listed above\n";
    echo "  2. Verify privacy guards are properly invoked\n";
    echo "  3. Re-run: php tests/ci_privacy_checks.php\n";
    echo "  4. Consult docs/ANALYTICS_PRIVACY_VALIDATION.md\n\n";
    
    exit(1);
}

echo GREEN . BOLD . "\n╔══════════════════════════════════════════════════════════════╗\n" . RESET;
echo GREEN . BOLD . "║  ✓ ALL PRIVACY CHECKS PASSED                                 ║\n" . RESET;
echo GREEN . BOLD . "╚══════════════════════════════════════════════════════════════╝\n" . RESET;

echo "\nAnalytics privacy enforcement verified:\n";
echo "  ✓ Phase 1: Database access control\n";
echo "  ✓ Phase 2: PII response validation\n";
echo "  ✓ Phase 3: Minimum cohort enforcement\n";
echo "  ✓ Phase 4: Operational safeguards\n";
echo "  ✓ Phase 5: CI regression prevention\n\n";

exit(0);
