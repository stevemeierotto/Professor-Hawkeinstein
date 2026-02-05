# Analytics System - Quick Reference

## 🚀 Quick Start

### Check Analytics Status
```bash
php /home/steve/Professor_Hawkeinstein/test_analytics_direct.php
```

### Manual Data Refresh
```bash
php /home/steve/Professor_Hawkeinstein/scripts/aggregate_analytics.php
```

### View Logs
```bash
tail -f /tmp/analytics_aggregation.log
```

---

## 📊 Endpoints

### Admin APIs (Require JWT Auth)
- `GET /api/admin/analytics/overview.php` - Platform overview
- `GET /api/admin/analytics/course.php` - Course metrics
- `GET /api/admin/analytics/timeseries.php` - Time-series data
- `GET /api/admin/analytics/export.php` - Data export

### Query Parameters
```
?startDate=2026-01-01&endDate=2026-01-31
?period=daily|weekly|monthly
?courseId=1
```

---

## 🔧 Maintenance

### Daily Aggregation (Recommended)
Add to crontab:
```bash
0 1 * * * /usr/bin/php /home/steve/Professor_Hawkeinstein/scripts/aggregate_analytics.php
```

### Manual Table Reset (Caution!)
```bash
sudo mysql professorhawkeinstein_platform -e "TRUNCATE analytics_daily_rollup;"
sudo mysql professorhawkeinstein_platform -e "TRUNCATE analytics_course_metrics;"
sudo mysql professorhawkeinstein_platform -e "TRUNCATE analytics_agent_metrics;"
```

### Rebuild Analytics
```bash
# Reset tables
sudo mysql professorhawkeinstein_platform < /home/steve/Professor_Hawkeinstein/migrations/004_analytics_tables.sql

# Backfill data
php /home/steve/Professor_Hawkeinstein/scripts/aggregate_analytics.php
```

---

## 🐛 Troubleshooting

### "Failed to load analytics overview"
**Cause:** Empty rollup tables or API error

**Fix:**
```bash
# Check if tables exist
sudo mysql -e "SHOW TABLES LIKE 'analytics_%';" professorhawkeinstein_platform

# Run aggregation
php scripts/aggregate_analytics.php

# Check API directly
curl http://localhost/api/admin/analytics/overview.php \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

### "No data available" (expected)
This is **normal** if:
- No student activity in date range
- New system with no historical data
- Analytics aggregation hasn't run yet

**Not an error** - system working correctly!

### Agent Tab Error
**Cause:** JavaScript expecting topAgents array

**Fix:** Already implemented - now shows "No data available" gracefully

---

## 📋 Database Tables

### Core Analytics Tables
```sql
analytics_daily_rollup       -- Daily platform metrics
analytics_course_metrics     -- Course effectiveness
analytics_agent_metrics      -- Agent performance
analytics_timeseries         -- Hourly/weekly/monthly rollups
analytics_user_snapshots     -- Anonymized user progress
analytics_public_metrics     -- Public-facing stats
```

### Query Examples
```sql
-- Platform activity summary
SELECT * FROM analytics_daily_rollup ORDER BY rollup_date DESC LIMIT 7;

-- Top performing courses
SELECT * FROM analytics_course_metrics 
ORDER BY avg_mastery_score DESC LIMIT 5;

-- Agent usage stats
SELECT * FROM analytics_agent_metrics 
ORDER BY total_interactions DESC LIMIT 10;
```

---

## 🔐 Security Notes

- All endpoints require admin JWT authentication
- Queries return aggregate data only (no PII)
- Student names/emails never exposed
- Uses `COUNT(DISTINCT user_id)` for anonymization

---

## 📈 Data Sources

### Raw Tables (Source)
- `progress_tracking` → Activity, mastery, time spent
- `agent_memories` → Agent interactions
- `course_assignments` → Enrollment, completion
- `users` → User counts by role

### Aggregated Tables (Computed)
- `analytics_*` tables updated by aggregation script

---

## 🎯 Common Tasks

### Add New Metric
1. Modify `scripts/aggregate_analytics.php`
2. Add column to relevant `analytics_*` table
3. Update frontend to display new metric

### Export Data
Via UI:
- Click "Export Data" button in admin_analytics.html

Via Command Line:
```bash
curl "http://localhost/api/admin/analytics/export.php?dataset=user_progress&format=csv" \
  -H "Authorization: Bearer TOKEN" > export.csv
```

### Check Last Aggregation
```bash
sudo mysql professorhawkeinstein_platform -e \
  "SELECT MAX(updated_at) FROM analytics_daily_rollup;"
```

---

## ✅ Health Check Script
```bash
#!/bin/bash
# Save as check_analytics_health.sh

echo "Analytics System Health Check"
echo "=============================="

# Check tables exist
TABLE_COUNT=$(sudo mysql professorhawkeinstein_platform -sN -e \
  "SELECT COUNT(*) FROM information_schema.tables 
   WHERE table_schema='professorhawkeinstein_platform' 
   AND table_name LIKE 'analytics_%'")

echo "Analytics tables: $TABLE_COUNT / 9"

# Check rollup data
ROLLUP_ROWS=$(sudo mysql professorhawkeinstein_platform -sN -e \
  "SELECT COUNT(*) FROM analytics_daily_rollup")

echo "Daily rollup records: $ROLLUP_ROWS"

# Check last update
LAST_UPDATE=$(sudo mysql professorhawkeinstein_platform -sN -e \
  "SELECT MAX(updated_at) FROM analytics_daily_rollup")

echo "Last aggregation: $LAST_UPDATE"

# Check API accessibility
API_RESPONSE=$(curl -s -o /dev/null -w "%{http_code}" \
  http://localhost/api/admin/analytics/overview.php)

echo "API response code: $API_RESPONSE (401=auth required, expected)"

echo ""
echo "Status: $([ $TABLE_COUNT -eq 9 ] && echo "✅ Healthy" || echo "⚠️ Check tables")"
```

---

**Last Updated:** January 18, 2026  
**See Also:** [ANALYTICS_DEBUG_REPORT.md](ANALYTICS_DEBUG_REPORT.md)
