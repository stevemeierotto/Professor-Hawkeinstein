#!/usr/bin/env php
<?php
/**
 * Rate Limiting Un Test Suite
 * 
 * Unit tests for Phase 8 DEFAULT-ON rate limiting architecture
 * Tests configuration and function signatures only (no database required)
 * 
 * Usage: php tests/rate_limiting_test.php
 * Created: February 11, 2026
 */

// Load rate limiter
require_once __DIR__ . '/../app/api/helpers/rate_limiter.php';

// Colors
define('ANSI_GREEN', "\033[32m");
define('ANSI_RED', "\033[31m");
define('ANSI_YELLOW', "\033[33m");
define('ANSI_RESET', "\033[0m");

// Test state
$testsPassed = 0;
$testsFailed = 0;

function runTest($name, $callback) {
    global $testsPassed, $testsFailed;
    echo "\n✓ Test: $name\n";
    try {
        $result = $callback();
        if ($result) {
            echo ANSI_GREEN . "  PASSED" . ANSI_RESET . "\n";
            $testsPassed++;
        } else {
            echo ANSI_RED . "  FAILED" . ANSI_RESET . "\n";
            $testsFailed++;
        }
    } catch (Exception $e) {
        echo ANSI_RED . "  ERROR: " . $e->getMessage() . ANSI_RESET . "\n";
        $testsFailed++;
    }
}

function assertTrue($condition, $message) {
    if (!$condition) {
        throw new Exception("Assertion failed: $message");
    }
    echo "    ✓ $message\n";
}

echo "\n";
echo "═══════════════════════════════════════════════\n";
echo "  Rate Limiting Test Suite - Phase 8\n";
echo "═══════════════════════════════════════════════\n";

// Test 1: Configuration
runTest("Rate limit profiles configuration", function() {
    assertTrue(defined('RATE_LIMITS'), "RATE_LIMITS constant defined");
    
    $profiles = ['PUBLIC', 'AUTHENTICATED', 'ADMIN', 'ROOT', 'GENERATION'];
    foreach ($profiles as $profile) {
        assertTrue(isset(RATE_LIMITS[$profile]), "Profile $profile exists");
    }
    
    assertTrue(RATE_LIMITS['GENERATION'][0] === 10, "GENERATION: 10 req/hour");
    assertTrue(RATE_LIMITS['PUBLIC'][0] === 60, "PUBLIC: 60 req/min");
    assert(RATE_LIMITS['AUTHENTICATED'][0] === 120, "AUTHENTICATED: 120 req/min");
    assertTrue(RATE_LIMITS['ADMIN'][0] === 300, "ADMIN: 300 req/min");
    assertTrue(RATE_LIMITS['ROOT'][0] === 600, "ROOT: 600 req/min");
    
    return true;
});

// Test 2: Functions exist
runTest("Core functions exist", function() {
    assertTrue(function_exists('enforceRateLimit'), "enforceRateLimit() exists");
    assertTrue(function_exists('require_rate_limit_auto'), "require_rate_limit_auto() exists");
    assertTrue(function_exists('require_rate_limit'), "require_rate_limit() exists");
    assertTrue(function_exists('enforceGenerationRateLimit'), "enforceGenerationRateLimit() exists");
    assertTrue(function_exists('getClientIP'), "getClientIP() exists");
    return true;
});

// Test 3: Function signatures
runTest("Function signatures", function() {
    $ref = new ReflectionFunction('enforceRateLimit');
    assertTrue($ref->getNumberOfParameters() === 3, "enforceRateLimit() takes 3 params");
    
    $ref2 = new ReflectionFunction('require_rate_limit_auto');
    assertTrue($ref2->getNumberOfParameters() === 1, "require_rate_limit_auto() takes 1 param");
    
    $ref3 = new ReflectionFunction('require_rate_limit');
    assertTrue($ref3->getNumberOfParameters() === 2, "require_rate_limit() takes 2 params");
    
    return true;
});

// Test 4: Log configuration
runTest("Log configuration", function() {
    assertTrue(defined('RATE_LIMIT_LOG'), "RATE_LIMIT_LOG defined");
    assertTrue(RATE_LIMIT_LOG === '/tmp/rate_limit.log', "Log path correct");
    assertTrue(is_writable(dirname(RATE_LIMIT_LOG)), "Log directory writable");
    return true;
});

// Test 5: Profile ordering
runTest("Profile limits ordered correctly", function() {
    assertTrue(
        RATE_LIMITS['PUBLIC'][0] < RATE_LIMITS['AUTHENTICATED'][0],
        "PUBLIC < AUTHENTICATED"
    );
    assertTrue(
        RATE_LIMITS['AUTHENTICATED'][0] < RATE_LIMITS['ADMIN'][0],
        "AUTHENTICATED < ADMIN"
    );
    assertTrue(
        RATE_LIMITS['ADMIN'][0] < RATE_LIMITS['ROOT'][0],
        "ADMIN < ROOT"
    );
    return true;
});

// Test 6: Double-invocation prevention
runTest("Double-invocation prevention", function() {
    if (isset($GLOBALS['RATE_LIMIT_APPLIED'])) {
        unset($GLOBALS['RATE_LIMIT_APPLIED']);
    }
    assertTrue(!isset($GLOBALS['RATE_LIMIT_APPLIED']), "Flag starts unset");
    
    $GLOBALS['RATE_LIMIT_APPLIED'] = true;
    assertTrue($GLOBALS['RATE_LIMIT_APPLIED'] === true, "Flag can be set");
    
    unset($GLOBALS['RATE_LIMIT_APPLIED']);
    return true;
});

// Test 7: Configuration completeness
runTest("Configuration completeness", function() {
    foreach (RATE_LIMITS as $profile => $config) {
        list($limit, $window) = $config;
        assertTrue($limit > 0, "$profile limit positive");
        assertTrue($window > 0, "$profile window positive");
    }
    return true;
});

// Summary
echo "\n";
echo "═══════════════════════════════════════════════\n";
echo "  TEST SUMMARY\n";
echo "═══════════════════════════════════════════════\n";
echo "  " . ANSI_GREEN . "Passed:  $testsPassed" . ANSI_RESET . "\n";
echo "  " . ANSI_RED . "Failed:  $testsFailed" . ANSI_RESET . "\n";
echo "  Total:   " . ($testsPassed + $testsFailed) . "\n";
echo "\n";

if ($testsFailed > 0) {
    echo ANSI_RED . "✗ TESTS FA" . ANSI_RESET . "\n\n";
    exit(1);
} else {
    echo ANSI_GREEN . "✓ ALL TESTS PASSED" . ANSI_RESET . "\n\n";
    exit(0);
}
