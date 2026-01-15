# Analytics System Setup Guide

This document provides instructions for deploying and activating the analytics system for Professor Hawkeinstein.

---

## Prerequisites

- MariaDB 10.7+ with existing Professor Hawkeinstein database
- PHP 7.4+ with PDO extension
- Cron access for scheduled aggregation
- Admin credentials for database migrations

---

## Step 1: Database Migration

Run the analytics schema migration:

```bash
cd /home/steve/Professor_Hawkeinstein
mysql -u <DB_USERNAME> -p<DB_PASSWORD> professor_hawkeinstein < migrations/004_analytics_tables.sql
```

This creates:
- `analytics_daily_rollup`
- `analytics_course_metrics`
- `analytics_agent_metrics`
- `analytics_user_snapshots`
- `analytics_timeseries`
- `analytics_public_metrics`

Verify tables were created:
```sql
USE professor_hawkeinstein;
SHOW TABLES LIKE 'analytics_%';
```

Expected output: 6 tables

---

## Step 2: Initial Data Population

Run the aggregation script manually to populate initial data:

```bash
php /home/steve/Professor_Hawkeinstein/scripts/aggregate_analytics.php
```

Check the log:
```bash
cat /tmp/analytics_aggregation.log
```

Expected: Daily rollup, course metrics, agent metrics, and public metrics updated

---

## Step 3: Schedule Daily Aggregation

Add cron job to run analytics aggregation daily at 1 AM:

```bash
crontab -e
```

Add this line:
```cron
0 1 * * * /usr/bin/php /home/steve/Professor_Hawkeinstein/scripts/aggregate_analytics.php >> /tmp/analytics_aggregation.log 2>&1
```

Verify cron was added:
```bash
crontab -l | grep aggregate_analytics
```

---

## Step 4: Deploy to Production

Sync analytics files to production:

```bash
cd /home/steve/Professor_Hawkeinstein
make sync-web
```

Verify files were synced:
```bash
ls -la /var/www/html/basic_educational/api/admin/analytics/
ls -la /var/www/html/basic_educational/api/public/
ls -la /var/www/html/basic_educational/course_factory/admin_analytics.*
ls -la /var/www/html/basic_educational/student_portal/metrics.html
```

---

## Step 5: Verify API Endpoints

### Test Public Metrics (No Auth Required)
```bash
curl http://localhost/api/public/metrics.php | jq
```

Expected: JSON with metrics array, recentActivity, weeklyTrend

### Test Admin Analytics (Auth Required)
First, get admin JWT token:
```bash
TOKEN=$(curl -X POST http://localhost/api/auth/login.php \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"<ADMIN_PASSWORD>"}' | jq -r '.token')
```

Then test overview endpoint:
```bash
curl http://localhost/api/admin/analytics/overview.php \
  -H "Authorization: Bearer $TOKEN" | jq
```

Expected: JSON with platformHealth, engagement, masteryDistribution, topCourses, topAgents

---

## Step 6: Access Dashboards

### Admin Analytics Dashboard
1. Navigate to: `http://localhost/course_factory/admin_analytics.html`
2. Login with admin credentials
3. Verify charts render and data loads

### Public Metrics Page
1. Navigate to: `http://localhost/student_portal/metrics.html`
2. No login required
3. Verify metrics display and auto-refresh

---

## Troubleshooting

### Analytics Tables Not Created
```bash
# Check MySQL error log
sudo tail -f /var/log/mysql/error.log

# Verify user has CREATE TABLE permission
mysql -u root -p
GRANT CREATE ON professor_hawkeinstein.* TO '<DB_USERNAME>'@'localhost';
FLUSH PRIVILEGES;
```

### Aggregation Script Fails
```bash
# Check PHP errors
php -l /home/steve/Professor_Hawkeinstein/scripts/aggregate_analytics.php

# Test database connection
php /home/steve/Professor_Hawkeinstein/test_db_connection.php

# Check log for specific errors
tail -50 /tmp/analytics_aggregation.log
```

### API Returns 500 Error
```bash
# Check PHP error log
sudo tail -f /var/log/apache2/error.log

# Verify file permissions
ls -la /var/www/html/basic_educational/api/admin/analytics/
chmod 644 /var/www/html/basic_educational/api/admin/analytics/*.php
```

### Charts Not Rendering
- Verify Chart.js CDN is accessible: `https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js`
- Check browser console for JavaScript errors
- Clear browser cache and reload

---

## Maintenance

### Manual Aggregation Run
```bash
php /home/steve/Professor_Hawkeinstein/scripts/aggregate_analytics.php
```

### View Aggregation Logs
```bash
tail -f /tmp/analytics_aggregation.log
```

### Backfill Historical Data
To populate analytics for past dates, modify the aggregation script temporarily:
```php
// Change this line in aggregate_analytics.php
$targetDate = date('Y-m-d', strtotime('-30 days')); // Backfill 30 days

// Run in loop
for i in {1..30}; do
    php /home/steve/Professor_Hawkeinstein/scripts/aggregate_analytics.php
done
```

### Clear Analytics Data (Testing Only)
```sql
TRUNCATE TABLE analytics_daily_rollup;
TRUNCATE TABLE analytics_course_metrics;
TRUNCATE TABLE analytics_agent_metrics;
TRUNCATE TABLE analytics_user_snapshots;
UPDATE analytics_public_metrics SET metric_value = '0' WHERE metric_type = 'number';
```

---

## Security Notes

1. **Admin Endpoints**: Always require JWT authentication via `requireAdmin()`
2. **Public Endpoint**: Rate limiting recommended (not yet implemented)
3. **Database Credentials**: Stored in `/home/steve/Professor_Hawkeinstein/config/database.php` (not synced to web)
4. **Export Logs**: All data exports logged to `admin_activity_log` table

---

## Performance Optimization

### Query Optimization
Analytics queries use:
- Indexed columns (`rollup_date`, `calculation_date`, `course_id`, `agent_id`)
- Pre-aggregated rollup tables
- Views for common queries

### Caching
Public metrics are cached in `analytics_public_metrics` table:
- Updated daily by aggregation script
- Served directly without computation
- Last update timestamp included in response

### Future Improvements
- Add Redis caching for frequently accessed endpoints
- Implement query result pagination
- Add GraphQL API for flexible analytics queries

---

## Privacy Compliance

All analytics follow privacy-first design:
- ✅ No PII in public metrics
- ✅ Hashed user identifiers in research exports
- ✅ Admin access logged
- ✅ FERPA/COPPA compliant

See full validation: `docs/ANALYTICS_PRIVACY_VALIDATION.md`

---

## Support

For issues or questions:
- Check logs: `/tmp/analytics_aggregation.log`
- Review schema: `migrations/004_analytics_tables.sql`
- Privacy validation: `docs/ANALYTICS_PRIVACY_VALIDATION.md`
