# Rate Limiting Monitoring & Testing Documentation

**Created:** February 11, 2026  
**Part of:** Phase 8 DEFAULT-ON Rate Limiting Architecture

## Overview

This document describes the automated testing and monitoring infrastructure for the rate limiting system.

## Components

### 1. Automated Test Suite

**File:** `tests/rate_limiting_test.php`

Comprehensive test suite that validates:
- Database connectivity and schema
- Rate limit profile configuration
- Core functions (enforceRateLimit, require_rate_limit_auto, require_rate_limit)
- Double-invocation prevention
- Cleanup and expiration logic
- Log file accessibility

**Run tests:**
```bash
php tests/rate_limiting_test.php
```

**Expected output:**
- 10 tests executed
- All tests should pass
- Summary shows pass/fail/skip counts

**CI/CD Integration:**
```bash
# Add to your CI pipeline
php tests/rate_limiting_test.php || exit 1
```

### 2. Real-Time Monitoring Script

**File:** `scripts/monitor_rate_limits.sh`

Real-time monitoring with multiple output modes:

**Console mode (human-readable):**
```bash
./scripts/monitor_rate_limits.sh
```

Shows:
- Total and active rate limit entries
- Violations (last hour, last 24 hours)
- Critical metrics (generation usage, blocked IPs)
- Top violators

**JSON mode (for ingestion by monitoring tools):**
```bash
./scripts/monitor_rate_limits.sh --json
```

Output format:
```json
{
  "timestamp": "2026-02-11T15:30:00Z",
  "database": {
    "total_entries": 1523,
    "active_entries_1h": 247
  },
  "violations": {
    "last_hour": 3,
    "last_24h": 45
  },
  "critical": {
    "generation_usage_1h": 8,
    "unique_ips_blocked": 2
  },
  "top_violators": []
}
```

**Alert mode (exit non-zero if thresholds exceeded):**
```bash
./scripts/monitor_rate_limits.sh --alert
```

Alert thresholds (configurable in script):
- Violations per hour: 100
- Unique IPs blocked: 20
- Generation limit hits: 5

### 3. Log Analysis Tool

**File:** `scripts/analyze_rate_limits.sh`

Detailed analysis of rate limit log file:

**Default (last 24 hours):**
```bash
./scripts/analyze_rate_limits.sh
```

**Custom time window:**
```bash
./scripts/analyze_rate_limits.sh --hours 48
```

**Top N analysis:**
```bash
./scripts/analyze_rate_limits.sh --top 20
```

**Reports include:**
- Total violations
- Violations by profile (PUBLIC, AUTHENTICATED, ADMIN, etc.)
- Top violators by identifier
- Top endpoints hit
- Hourly breakdown
- Suspicious activity detection:
  - Rapid-fire attacks (>50 violations from single IP)
  - Generation endpoint abuse
  - Distributed attack patterns

### 4. Cron Jobs

**File:** `infra/cron/rate_limiting.cron`

Automated scheduling for production:

```bash
# Install cron jobs
crontab infra/cron/rate_limiting.cron
```

**Jobs included:**
1. **Every 5 minutes:** JSON monitoring output to log
2. **Every 10 minutes:** Alert check with email notification
3. **Daily at 9 AM:** Summary report generation
4. **Weekly (Sunday 3 AM):** Database cleanup (7-day retention)
5. **Monthly (1st at 2 AM):** Log archival

**Customize email alerts:**
Edit `rate_limiting.cron` and change `admin@example.com` to your email.

## Monitoring Dashboards

### Grafana Integration

**Ingest JSON output:**
```bash
# Send to Grafana Loki
./scripts/monitor_rate_limits.sh --json | \
  curl -X POST http://localhost:3100/loki/api/v1/push \
  -H "Content-Type: application/json" \
  --data-binary @-
```

**Recommended panels:**
1. Violations over time (line chart)
2. Active entries gauge
3. Top violators table
4. Generation usage (single stat)
5. Alert status (status panel)

### Prometheus Metrics

Export metrics in Prometheus format:
```bash
# Parse JSON output to Prometheus format
./scripts/monitor_rate_limits.sh --json | jq -r '
  "rate_limit_violations_1h \(.violations.last_hour)",
  "rate_limit_violations_24h \(.violations.last_24h)",
  "rate_limit_generation_usage \(.critical.generation_usage_1h)",
  "rate_limit_unique_blocked \(.critical.unique_ips_blocked)"
'
```

## Log Files

### Rate Limit Log

**Location:** `/tmp/rate_limit.log`

**Format:**
```
2026-02-11 15:45:23 [RATE_LIMIT_EXCEEDED] Profile: PUBLIC, Identifier: 192.168.1.100, Endpoint: auth_login, Count: 61/60, Reset: 2026-02-11 15:46:23
```

**Rotation:**
- Manual: `> /tmp/rate_limit.log`
- Automated: Monthly via cron (compressed to `/var/log/`)

**Retention:** 12 months recommended for compliance

### Monitoring Log

**Location:** `/tmp/rate_limit_monitoring.log`

**Format:** JSON output from monitoring script (every 5 minutes)

**Usage:**
```bash
# Last 10 entries
tail -n 10 /tmp/rate_limit_monitoring.log | jq

# Count violations today
grep "$(date +%Y-%m-%d)" /tmp/rate_limit_monitoring.log | \
  jq -s 'map(.violations.last_hour) | add'
```

## Alert Configuration

### Email Alerts

**Requirements:**
- `mail` command configured (`sudo apt-get install mailutils`)
- SMTP server configured

**Test email:**
```bash
echo "Test alert" | mail -s "Rate Limit Test" your-email@example.com
```

### Slack Integration

**Add to monitoring script:**
```bash
# In monitor_rate_limits.sh, add after alert detection:
if [[ "$alert_triggered" -eq 1 ]]; then
    curl -X POST https://hooks.slack.com/services/YOUR/WEBHOOK/URL \
      -H 'Content-Type: application/json' \
      -d "{\"text\":\"Rate limit alert: $viol_1h violations in last hour\"}"
fi
```

### PagerDuty Integration

```bash
# Trigger incident
curl -X POST https://api.pagerduty.com/incidents \
  -H 'Authorization: Token YOUR_API_TOKEN' \
  -H 'Content-Type: application/json' \
  -d '{
    "incident": {
      "type": "incident",
      "title": "Rate Limit Alert",
      "service": {"id": "YOUR_SERVICE_ID", "type": "service_reference"}
    }
  }'
```

## Troubleshooting

### Tests Failing

**Database connection error:**
```bash
# Check database is running
mysql -h 127.0.0.1 -P 3307 -u root -e "SELECT 1"

# Check rate_limits table exists
mysql -h 127.0.0.1 -P 3307 -u root phef -e "SHOW TABLES LIKE 'rate_limits'"
```

**Permission errors:**
```bash
# Ensure scripts are executable
chmod +x scripts/*.sh tests/*.php
```

### Monitoring Script Issues

**No data returned:**
- Check database credentials in script
- Verify rate_limits table has data
- Check log file exists: `ls -la /tmp/rate_limit.log`

**MySQL connection refused:**
- Ensure database container is running
- Verify port 3307 is accessible
- Check firewall rules

### Log Analysis Issues

**Log file not found:**
```bash
# Create log file
sudo touch /tmp/rate_limit.log
sudo chmod 666 /tmp/rate_limit.log
```

**Empty results:**
- Check time window (may need longer window)
- Verify log format matches expected pattern
- Try: `grep "RATE_LIMIT" /tmp/rate_limit.log`

## Performance Considerations

### Database Cleanup

Rate limiting creates many database rows. Regular cleanup is essential:

**Manual cleanup (immediate):**
```bash
mysql -h 127.0.0.1 -P 3307 -u root phef <<EOF
DELETE FROM rate_limits WHERE window_start < DATE_SUB(NOW(), INTERVAL 7 DAY);
OPTIMIZE TABLE rate_limits;
EOF
```

**Monitor table size:**
```bash
mysql -h 127.0.0.1 -P 3307 -u root phef -e "
  SELECT 
    COUNT(*) as rows,
    ROUND(SUM(LENGTH(identifier) + LENGTH(endpoint_class)) / 1024, 2) as size_kb
  FROM rate_limits"
```

### Log File Size

Monitor log file growth:
```bash
du -h /tmp/rate_limit.log
```

Rotate if >10 MB:
```bash
gzip -c /tmp/rate_limit.log > /var/log/rate_limit_$(date +%Y%m%d).log.gz
> /tmp/rate_limit.log
```

## Security Considerations

### Log Sanitization

Logs may contain sensitive data (IPs, user IDs). Redact before sharing:
```bash
# Redact IPs
sed 's/[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}/XXX.XXX.XXX.XXX/g' \
  /tmp/rate_limit.log
```

### Access Control

Restrict access to monitoring tools:
```bash
chmod 750 scripts/*.sh
chown root:monitoring scripts/*.sh
```

### Database Security

Rate limit data is not sensitive, but access should be controlled:
- Use read-only database user for monitoring
- No student PII in rate_limits table
- Identifier is IP (public) or user_id (UUID)

## Next Steps

1. **Deploy to Production:**
   - Copy scripts to production server
   - Configure cron jobs
   - Set up alert email/Slack

2. **Integrate with Monitoring Stack:**
   - Add to Grafana dashboards
   - Export Prometheus metrics
   - Configure PagerDuty incidents

3. **Tune Thresholds:**
   - Adjust alert thresholds based on baseline
   - Add custom rate limits for specific endpoints
   - Implement adaptive rate limiting

4. **Compliance:**
   - Document log retention policy
   - Add to security audit procedures
   - Include in incident response playbook

---

**Maintained by:** System Security Team  
**Last updated:** February 11, 2026  
**Review schedule:** Monthly
