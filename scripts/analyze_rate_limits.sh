#!/bin/bash
#
# Rate Limit Log Analyzer
# 
# Analyzes rate limit log file and generates detailed reports
# Shows trends, patterns, and suspicious activity
#
# Usage:
#   ./scripts/analyze_rate_limits.sh
#   ./scripts/analyze_rate_limits.sh --hours 24
#   ./scripts/analyze_rate_limits.sh --top 10
#
# Created: February 11, 2026

set -euo pipefail

LOG_FILE="/tmp/rate_limit.log"
HOURS="${2:-24}"
TOP_N="${2:-10}"

# Parse command line
if [[ "${1:-}" == "--hours" ]]; then
    HOURS="${2:-24}"
elif [[ "${1:-}" == "--top" ]]; then
    TOP_N="${2:-10}"
fi

# Colors
RED='\033[0;31m'
YELLOW='\033[1;33m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

if [[ ! -f "$LOG_FILE" ]]; then
    echo -e "${RED}Error: Log file not found: $LOG_FILE${NC}"
    exit 1
fi

echo -e "${BLUE}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║          Rate Limit Log Analysis                          ║${NC}"
echo -e "${BLUE}║          Last $HOURS hours                                      ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════════════════════╝${NC}"
echo ""

# Calculate cutoff time
CUTOFF=$(date -d "$HOURS hours ago" '+%Y-%m-%d %H:%M:%S' 2>/dev/null || date -v -"${HOURS}H" '+%Y-%m-%d %H:%M:%S')

# Filter log to time window
TEMP_LOG=$(mktemp)
awk -v cutoff="$CUTOFF" '{
    timestamp = $1 " " $2
    if (timestamp >= cutoff) print
}' "$LOG_FILE" > "$TEMP_LOG"

# Total violations
TOTAL=$(grep -c "RATE_LIMIT_EXCEEDED" "$TEMP_LOG" || echo 0)
echo -e "${YELLOW}Total Violations: $TOTAL${NC}"
echo ""

# Violations by profile
echo -e "${GREEN}Violations by Profile:${NC}"
grep "RATE_LIMIT_EXCEEDED" "$TEMP_LOG" | \
    grep -oP 'Profile: \K[A-Z]+' | \
    sort | uniq -c | sort -rn | \
    awk '{printf "  %-20s %s\n", $2":", $1}'
echo ""

# Top violators by identifier
echo -e "${GREEN}Top $TOP_N Violators (by identifier):${NC}"
grep "RATE_LIMIT_EXCEEDED" "$TEMP_LOG" | \
    grep -oP 'Identifier: \K[^\s]+' | \
    sort | uniq -c | sort -rn | head -n "$TOP_N" | \
    awk '{printf "  %-40s %s requests\n", $2, $1}'
echo ""

# Top endpoints hit
echo -e "${GREEN}Top $TOP_N Endpoints Hit:${NC}"
grep "RATE_LIMIT_EXCEEDED" "$TEMP_LOG" | \
    grep -oP 'Endpoint: \K[^\s]+' | \
    sort | uniq -c | sort -rn | head -n "$TOP_N" | \
    awk '{printf "  %-40s %s violations\n", $2, $1}'
echo ""

# Hourly breakdown
echo -e "${GREEN}Violations by Hour:${NC}"
grep "RATE_LIMIT_EXCEEDED" "$TEMP_LOG" | \
    awk '{print $1 " " substr($2, 1, 2)}' | \
    uniq -c | \
    awk '{printf "  %s %s:00 - %s violations\n", $2, $3, $1}'
echo ""

# Suspicious patterns
echo -e "${CYAN}Suspicious Activity Detection:${NC}"

# Check for rapid-fire from single IP
RAPID_FIRE=$(grep "RATE_LIMIT_EXCEEDED" "$TEMP_LOG" | \
    grep -oP 'Identifier: \K[^\s]+' | \
    sort | uniq -c | \
    awk '$1 > 50 {print $0}' | wc -l)

if [[ "$RAPID_FIRE" -gt 0 ]]; then
    echo -e "  ${RED}⚠ $RAPID_FIRE identifier(s) with >50 violations (possible attack)${NC}"
else
    echo -e "  ${GREEN}✓ No rapid-fire patterns detected${NC}"
fi

# Check for generation endpoint abuse
GEN_ABUSE=$(grep "RATE_LIMIT_EXCEEDED.*Profile: GENERATION" "$TEMP_LOG" | wc -l)
if [[ "$GEN_ABUSE" -gt 5 ]]; then
    echo -e "  ${RED}⚠ $GEN_ABUSE generation endpoint violations (LLM abuse)${NC}"
else
    echo -e "  ${GREEN}✓ Generation endpoints within limits${NC}"
fi

# Check for distributed attack pattern (many unique IPs, low per-IP count)
UNIQUE_IPS=$(grep "RATE_LIMIT_EXCEEDED" "$TEMP_LOG" | \
    grep -oP 'Identifier: \K[^\s]+' | \
    sort -u | wc -l)
AVG_PER_IP=$(echo "scale=2; $TOTAL / $UNIQUE_IPS" | bc 2>/dev/null || echo "0")

if [[ "$UNIQUE_IPS" -gt 20 ]] && [[ $(echo "$AVG_PER_IP < 5" | bc) -eq 1 ]]; then
    echo -e "  ${YELLOW}⚠ Possible distributed attack: $UNIQUE_IPS unique IPs, avg $AVG_PER_IP violations/IP${NC}"
else
    echo -e "  ${GREEN}✓ No distributed attack pattern detected${NC}"
fi

echo ""

# Cleanup
rm "$TEMP_LOG"

# Recommendations
if [[ "$TOTAL" -gt 100 ]]; then
    echo -e "${YELLOW}Recommendations:${NC}"
    echo "  • Review top violators for potential blocks"
    echo "  • Consider tightening rate limits for abused endpoints"
    echo "  • Check application logs for errors causing retries"
    echo ""
fi

echo -e "${BLUE}Analysis complete. Log file: $LOG_FILE${NC}"
echo ""
