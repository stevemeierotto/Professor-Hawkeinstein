#!/bin/bash
#
# Analytics Privacy Static Analysis
#
# This script performs static analysis of analytics endpoints to ensure
# all required privacy guards are invoked.
#
# Usage:
#   ./scripts/validate_analytics_privacy.sh
#
# Exit Codes:
#   0 = All validations passed
#   1 = Privacy violations detected
#

set -euo pipefail

# Color codes
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color
BOLD='\033[1m'

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_ROOT"

FAILURES=0
CHECKS=0

echo -e "${BOLD}════════════════════════════════════════════════════════════════${NC}"
echo -e "${BOLD}  Analytics Privacy Static Analysis (Phase 5)${NC}"
echo -e "${BOLD}════════════════════════════════════════════════════════════════${NC}\n"

# Define analytics endpoints to check
ANALYTICS_ENDPOINTS=(
    "app/api/public/metrics.php"
    "app/api/admin/analytics/overview.php"
    "app/api/admin/analytics/course.php"
    "app/api/admin/analytics/timeseries.php"
    "app/api/admin/analytics/export.php"
)

# ============================================================================
# CHECK 1: Verify all endpoints require privacy guards
# ============================================================================

echo -e "${BLUE}[CHECK 1]${NC} Verifying privacy guard imports...\n"

for endpoint in "${ANALYTICS_ENDPOINTS[@]}"; do
    if [[ ! -f "$endpoint" ]]; then
        echo -e "${YELLOW}  ⚠ Skipping missing endpoint: $endpoint${NC}"
        continue
    fi
    
    CHECKS=$((CHECKS + 1))
    
    # Check for Phase 2 guard
    if ! grep -q "require_once.*analytics_response_guard\.php" "$endpoint"; then
        echo -e "${RED}  ✗ $endpoint missing analytics_response_guard.php import${NC}"
        FAILURES=$((FAILURES + 1))
    fi
    
    # Check for Phase 3 guard
    if ! grep -q "require_once.*analytics_cohort_guard\.php" "$endpoint"; then
        echo -e "${RED}  ✗ $endpoint missing analytics_cohort_guard.php import${NC}"
        FAILURES=$((FAILURES + 1))
    fi
    
    # Check for Phase 4 rate limiter
    if ! grep -q "require_once.*analytics_rate_limiter\.php" "$endpoint"; then
        echo -e "${RED}  ✗ $endpoint missing analytics_rate_limiter.php import${NC}"
        FAILURES=$((FAILURES + 1))
    fi
    
    # Check for Phase 4 audit log
    if ! grep -q "require_once.*analytics_audit_log\.php" "$endpoint"; then
        echo -e "${RED}  ✗ $endpoint missing analytics_audit_log.php import${NC}"
        FAILURES=$((FAILURES + 1))
    fi
done

if [[ $FAILURES -eq 0 ]]; then
    echo -e "${GREEN}  ✓ All endpoints have required guard imports${NC}\n"
fi

# ============================================================================
# CHECK 2: Verify sendProtectedAnalyticsJSON usage
# ============================================================================

echo -e "${BLUE}[CHECK 2]${NC} Checking for sendProtectedAnalyticsJSON() usage...\n"

for endpoint in "${ANALYTICS_ENDPOINTS[@]}"; do
    if [[ ! -f "$endpoint" ]]; then
        continue
    fi
    
    CHECKS=$((CHECKS + 1))
    
    if ! grep -q "sendProtectedAnalyticsJSON" "$endpoint"; then
        echo -e "${RED}  ✗ $endpoint does not use sendProtectedAnalyticsJSON()${NC}"
        FAILURES=$((FAILURES + 1))
    fi
done

if [[ $FAILURES -eq 0 ]]; then
    echo -e "${GREEN}  ✓ All endpoints use sendProtectedAnalyticsJSON()${NC}\n"
fi

# ============================================================================
# CHECK 3: Verify rate limiting enforcement
# ============================================================================

echo -e "${BLUE}[CHECK 3]${NC} Checking for enforceRateLimit() calls...\n"

for endpoint in "${ANALYTICS_ENDPOINTS[@]}"; do
    if [[ ! -f "$endpoint" ]]; then
        continue
    fi
    
    CHECKS=$((CHECKS + 1))
    
    if ! grep -q "enforceRateLimit" "$endpoint"; then
        echo -e "${RED}  ✗ $endpoint does not call enforceRateLimit()${NC}"
        FAILURES=$((FAILURES + 1))
    fi
done

if [[ $FAILURES -eq 0 ]]; then
    echo -e "${GREEN}  ✓ All endpoints enforce rate limiting${NC}\n"
fi

# ============================================================================
# CHECK 4: Verify audit logging
# ============================================================================

echo -e "${BLUE}[CHECK 4]${NC} Checking for audit logging calls...\n"

for endpoint in "${ANALYTICS_ENDPOINTS[@]}"; do
    if [[ ! -f "$endpoint" ]]; then
        continue
    fi
    
    CHECKS=$((CHECKS + 1))
    
    if ! grep -Eq "logAnalyticsAccess|logAnalyticsExport" "$endpoint"; then
        echo -e "${RED}  ✗ $endpoint missing audit logging (logAnalyticsAccess/logAnalyticsExport)${NC}"
        FAILURES=$((FAILURES + 1))
    fi
done

if [[ $FAILURES -eq 0 ]]; then
    echo -e "${GREEN}  ✓ All endpoints implement audit logging${NC}\n"
fi

# ============================================================================
# CHECK 5: Verify no direct PII exposure patterns
# ============================================================================

echo -e "${BLUE}[CHECK 5]${NC} Scanning for potential PII exposure patterns...\n"

FORBIDDEN_PATTERNS=(
    '\$row\[.email.\]'
    '\$row\[.username.\]'
    '\$data\[.first_name.\]'
    '\$data\[.last_name.\]'
    "SELECT.*email.*FROM"
    "SELECT.*username.*FROM"
)

for endpoint in "${ANALYTICS_ENDPOINTS[@]}"; do
    if [[ ! -f "$endpoint" ]]; then
        continue
    fi
    
    CHECKS=$((CHECKS + 1))
    
    for pattern in "${FORBIDDEN_PATTERNS[@]}"; do
        if grep -Eq "$pattern" "$endpoint"; then
            echo -e "${RED}  ✗ $endpoint may expose PII (pattern: $pattern)${NC}"
            FAILURES=$((FAILURES + 1))
        fi
    done
done

if [[ $FAILURES -eq 0 ]]; then
    echo -e "${GREEN}  ✓ No obvious PII exposure patterns detected${NC}\n"
fi

# ============================================================================
# CHECK 6: Verify privacy helper modules exist
# ============================================================================

echo -e "${BLUE}[CHECK 6]${NC} Verifying privacy helper modules...\n"

REQUIRED_HELPERS=(
    "app/api/helpers/analytics_response_guard.php"
    "app/api/helpers/analytics_cohort_guard.php"
    "app/api/helpers/analytics_rate_limiter.php"
    "app/api/helpers/analytics_audit_log.php"
    "app/api/helpers/analytics_export_guard.php"
)

for helper in "${REQUIRED_HELPERS[@]}"; do
    CHECKS=$((CHECKS + 1))
    
    if [[ ! -f "$helper" ]]; then
        echo -e "${RED}  ✗ Missing required helper: $helper${NC}"
        FAILURES=$((FAILURES + 1))
    fi
done

if [[ $FAILURES -eq 0 ]]; then
    echo -e "${GREEN}  ✓ All privacy helper modules present${NC}\n"
fi

# ============================================================================
# CHECK 7: Verify security headers
# ============================================================================

echo -e "${BLUE}[CHECK 7]${NC} Checking for security headers...\n"

REQUIRED_HEADERS=(
    "X-Content-Type-Options"
    "Cache-Control"
)

for endpoint in "${ANALYTICS_ENDPOINTS[@]}"; do
    if [[ ! -f "$endpoint" ]]; then
        continue
    fi
    
    CHECKS=$((CHECKS + 1))
    
    for header in "${REQUIRED_HEADERS[@]}"; do
        if ! grep -iq "$header" "$endpoint"; then
            echo -e "${RED}  ✗ $endpoint missing security header: $header${NC}"
            FAILURES=$((FAILURES + 1))
        fi
    done
done

if [[ $FAILURES -eq 0 ]]; then
    echo -e "${GREEN}  ✓ All endpoints include required security headers${NC}\n"
fi

# ============================================================================
# RESULTS SUMMARY
# ============================================================================

echo -e "${BOLD}════════════════════════════════════════════════════════════════${NC}"
echo -e "${BOLD}  Results Summary${NC}"
echo -e "${BOLD}════════════════════════════════════════════════════════════════${NC}\n"

echo -e "Total checks performed: ${CHECKS}"

if [[ $FAILURES -gt 0 ]]; then
    echo -e "${RED}${BOLD}✗ FAILED: $FAILURES privacy violations detected${NC}\n"
    echo -e "${YELLOW}Required actions:${NC}"
    echo -e "  1. Review failures listed above"
    echo -e "  2. Ensure all analytics endpoints invoke required guards"
    echo -e "  3. Consult docs/ANALYTICS_PRIVACY_VALIDATION.md"
    echo -e "  4. Re-run this script after fixes\n"
    exit 1
else
    echo -e "${GREEN}${BOLD}✓ PASSED: All privacy validations successful${NC}\n"
    echo -e "Analytics privacy enforcement verified:"
    echo -e "  ✓ Phase 2: PII response validation"
    echo -e "  ✓ Phase 3: Minimum cohort enforcement"
    echo -e "  ✓ Phase 4: Operational safeguards"
    echo -e "  ✓ Phase 5: CI regression prevention\n"
    exit 0
fi
