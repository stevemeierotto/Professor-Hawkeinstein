# Analytics System

**Status:** ✅ Production Ready  
**Version:** 1.0  
**Date:** January 14, 2026

---

## Quick Links

- **Setup Guide:** [`docs/ANALYTICS_SETUP.md`](../docs/ANALYTICS_SETUP.md)
- **Privacy Validation:** [`docs/ANALYTICS_PRIVACY_VALIDATION.md`](../docs/ANALYTICS_PRIVACY_VALIDATION.md)
- **Implementation Report:** [`docs/ANALYTICS_IMPLEMENTATION_REPORT.md`](../docs/ANALYTICS_IMPLEMENTATION_REPORT.md)

---

## Admin Endpoints

All require JWT authentication (`Authorization: Bearer <token>`):

- `GET /api/admin/analytics/overview.php` - Platform health & engagement
- `GET /api/admin/analytics/course.php` - Course effectiveness metrics
- `GET /api/admin/analytics/timeseries.php` - Daily/weekly/monthly trends
- `GET /api/admin/analytics/export.php` - Data export (CSV/JSON)

---

## Public Endpoint

No authentication required:

- `GET /api/public/metrics.php` - Aggregate platform statistics

---

## Dashboards

- **Admin:** [`course_factory/admin_analytics.html`](../course_factory/admin_analytics.html)
- **Public:** [`student_portal/metrics.html`](../student_portal/metrics.html)

---

## Deployment

```bash
# 1. Run migration
mysql -u <user> -p professor_hawkeinstein < migrations/004_analytics_tables.sql

# 2. Initial aggregation
php scripts/aggregate_analytics.php

# 3. Add cron job
echo "0 1 * * * php /home/steve/Professor_Hawkeinstein/scripts/aggregate_analytics.php" | crontab -

# 4. Deploy to web
make sync-web
```

---

## Privacy Guarantees

✅ **NO PII** in public or research exports  
✅ **Aggregate-only** statistics  
✅ **Hashed IDs** for research datasets  
✅ **FERPA/COPPA** compliant by design

---

## Files

```
api/
  admin/analytics/
    overview.php         # Platform-wide metrics
    course.php           # Course effectiveness
    timeseries.php       # Trend analysis
    export.php           # Data export
  public/
    metrics.php          # Public statistics

course_factory/
  admin_analytics.html   # Admin dashboard
  admin_analytics.js     # Chart rendering

student_portal/
  metrics.html           # Public metrics page

scripts/
  aggregate_analytics.php # Daily ETL (cron)

migrations/
  004_analytics_tables.sql # Database schema

docs/
  ANALYTICS_SETUP.md
  ANALYTICS_PRIVACY_VALIDATION.md
  ANALYTICS_IMPLEMENTATION_REPORT.md
```

---

## Support

**Logs:** `/tmp/analytics_aggregation.log`  
**Issues:** Check setup guide troubleshooting section
