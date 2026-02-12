#!/bin/bash
#
# Rate Limit Monitoring Script
# 
# Monitors rate limit violations and system health
# Outputs metrics suitable for alerting and dashboards
#
# Usage:
#   ./scripts/monitor_rate_limits.sh
#   ./scripts/monitor_rate_limits.sh --json
#   ./scripts/monitor_rate_limits.sh --alert
#
# Modes:
#   default: Human-readable console output
#   --json:  JSON output for ingestion by monitoring tools
#   --alert: Check thresholds and exit non-zero if alerts triggered
#
# Created: February 11, 2026
# Part of Phase 8 Rate Limiting Initiative

set -euo pipefail

# Configuration
LOG_FILE="/tmp/rate_limit.log"
DB_HOST="127.0.0.1"
DB_PORT="3307"
DB_NAME="phef"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"

# Alert thresholds
ALERT_VIOLATIONS_PER_HOUR=100
ALERT_UNIQUE_IPS_BLOCKED=20
ALERT_GENERATION_LIMIT_HIT=5

# Colors
RED='\033[0;31m'
YELLOW='\033[1;33m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Parse arguments
OUTPUT_MODE="console"
if [[ "${1:-}" == "--json" ]]; then
    OUTPUT_MODE="json"
elif [[ "${1:-}" == "--alert" ]]; then
    OUTPUT_MODE="alert"
fi

# Helper: Execute MySQL query
query_db() {
    local query="$1"
    mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" ${DB_PASS:+-p"$DB_PASS"} "$DB_NAME" -N -B -e "$query" 2>/dev/null || echo ""
}

# Helper: Parse log file
parse_log() {
    if [[ ! -f "$LOG_FILE" ]]; then
        return
    fi
    
    local time_window="${1:-60}" # minutes
    local cutoff_time=$(date -d "$time_window minutes ago" '+%Y-%m-%d %H:%M:%S' 2>/dev/null || date -v -"${time_window}M" '+%Y-%m-%d %H:%M:%S' 2>/dev/null)
    
    # Count violations in last N minutes
    grep "RATE_LIMIT_EXCEEDED" "$LOG_FILE" 2>/dev/null | \
        awk -v cutoff="$cutoff_time" '{
            timestamp = $1 " " $2
            if (timestamp >= cutoff) print
        }' | wc -l | tr -d ' '
}

# Collect metrics
collect_metrics() {
    # Total rate limit entries
    local total_entries=$(query_db "SELECT COUNT(*) FROM rate_limits")
    
    # Active rate limit entries (last hour)
    local active_entries=$(query_db "
        SELECT COUNT(*) FROM rate_limits 
        WHERE window_start >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ")
    
    # Violations from log (last hour)
    local violations_1h=$(parse_log 60)
    
    # Violations from log (last 24 hours)
    local violations_24h=$(parse_log 1440)
    
    # Top violators (last hour)
    local top_violators=$(query_db "
        SELECT identifier, endpoint_class, COUNT(*) as count
        FROM rate_limits
        WHERE window_start >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        GROUP BY identifier, endpoint_class
        HAVING count >= (
            SELECT CASE endpoint_class
                WHEN 'PUBLIC' THEN 60
                WHEN 'AUTHENTICATED' THEN 120
                WHEN 'ADMIN' THEN 300
                WHEN 'ROOT' THEN 600
                WHEN 'GENERATION' THEN 10
                ELSE 60
            END
        )
        ORDER BY count DESC
        LIMIT 5
    ")
    
    # Generation endpoint usage (critical)
    local generation_usage=$(query_db "
        SELECT COUNT(*) FROM rate_limits
        WHERE endpoint_class = 'GENERATION'
        AND window_start >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ")
    
    # Unique IPs blocked
    local unique_blocked=$(query_db "
        SELECT COUNT(DISTINCT identifier) FROM rate_limits
        WHERE endpoint_class = 'PUBLIC'
        AND window_start >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        AND request_count >= 60
    ")
    
    # Export metrics
    echo "$total_entries|$active_entries|$violations_1h|$violations_24h|$generation_usage|$unique_blocked|$top_violators"
}

# Output: Console
output_console() {
    local metrics="$1"
    IFS='|' read -r total active viol_1h viol_24h gen_usage blocked violators <<< "$metrics"
    
    echo -e "${BLUE}╔════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${BLUE}║          Rate Limit Monitoring ($(date '+%Y-%m-%d %H:%M:%S'))          ║${NC}"
    echo -e "${BLUE}╚════════════════════════════════════════════════════════════╝${NC}"
    echo ""
    
    echo -e "${GREEN}Database Metrics:${NC}"
    echo "  Total rate limit entries:     $total"
    echo "  Active entries (last hour):   $active"
    echo ""
    
    echo -e "${YELLOW}Violations:${NC}"
    echo "  Last hour:                    $viol_1h"
    echo "  Last 24 hours:                $viol_24h"
    echo ""
    
    echo -e "${RED}Critical Endpoints:${NC}"
    echo "  Generation usage (last hour): $gen_usage"
    echo "  Unique IPs blocked:           $blocked"
    echo ""
    
    if [[ -n "$violators" ]]; then
        echo -e "${RED}Top Violators (Last Hour):${NC}"
        echo "$violators" | while IFS=$'\t' read -r identifier endpoint count; do
            echo "  $identifier ($endpoint): $count requests"
        done
    else
        echo -e "${GREEN}No rate limit violations detected${NC}"
    fi
    echo ""
}

# Output: JSON
output_json() {
    local metrics="$1"
    IFS='|' read -r total active viol_1h viol_24h gen_usage blocked violators <<< "$metrics"
    
    # Parse violators into JSON array
    local violators_json="[]"
    if [[ -n "$violators" ]]; then
        violators_json=$(echo "$violators" | awk -F'\t' '{
            printf "{\"identifier\":\"%s\",\"endpoint\":\"%s\",\"count\":%d},", $1, $2, $3
        }' | sed 's/,$//')
        violators_json="[$violators_json]"
    fi
    
    cat <<EOF
{
  "timestamp": "$(date -u '+%Y-%m-%dT%H:%M:%SZ')",
  "database": {
    "total_entries": $total,
    "active_entries_1h": $active
  },
  "violations": {
    "last_hour": $viol_1h,
    "last_24h": $viol_24h
  },
  "critical": {
    "generation_usage_1h": $gen_usage,
    "unique_ips_blocked": $blocked
  },
  "top_violators": $violators_json
}
EOF
}

# Output: Alert mode
output_alert() {
    local metrics="$1"
    IFS='|' read -r total active viol_1h viol_24h gen_usage blocked violators <<< "$metrics"
    
    local alert_triggered=0
    
    if [[ "$viol_1h" -ge "$ALERT_VIOLATIONS_PER_HOUR" ]]; then
        echo "ALERT: High violation rate - $viol_1h violations in last hour (threshold: $ALERT_VIOLATIONS_PER_HOUR)"
        alert_triggered=1
    fi
    
    if [[ "$blocked" -ge "$ALERT_UNIQUE_IPS_BLOCKED" ]]; then
        echo "ALERT: Many IPs blocked - $blocked unique IPs (threshold: $ALERT_UNIQUE_IPS_BLOCKED)"
        alert_triggered=1
    fi
    
    if [[ "$gen_usage" -ge "$ALERT_GENERATION_LIMIT_HIT" ]]; then
        echo "WARNING: High generation endpoint usage - $gen_usage requests in last hour"
        # Don't trigger alert, just warn
    fi
    
    if [[ "$alert_triggered" -eq 0 ]]; then
        echo "OK: No alerts triggered"
    fi
    
    return $alert_triggered
}

# Main execution
main() {
    # Check dependencies
    if ! command -v mysql &> /dev/null; then
        echo "ERROR: mysql client not found" >&2
        exit 1
    fi
    
    # Collect metrics
    local metrics=$(collect_metrics)
    
    # Output based on mode
    case "$OUTPUT_MODE" in
        console)
            output_console "$metrics"
            ;;
        json)
            output_json "$metrics"
            ;;
        alert)
            output_alert "$metrics"
            exit $?
            ;;
    esac
}

main "$@"
