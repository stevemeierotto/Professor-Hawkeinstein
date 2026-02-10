# Phase 6: Human-Visible Audit Access

**Status:** ✅ COMPLETE  
**Completion Date:** February 9, 2026  
**Version:** 6.0

---

## Overview

Phase 6 establishes **human-visible audit access** with strict role-based controls for analytics privacy enforcement transparency. This phase ensures that privacy enforcement (Phases 1-5) is auditable and reviewable by authorized humans without weakening safeguards.

**Core Principle:** Privacy enforcement is transparent and auditable, but audit access itself is controlled and logged.

---

## Problem Statement

Without audit visibility:
- Privacy enforcement operates as "black box"
- No human oversight of automated decisions
- Compliance reviews require server access
- No accountability trail for audit access itself
- Admins cannot verify privacy protections are working

**Risk:** Lack of transparency undermines trust in privacy enforcement, regulatory audits are difficult.

---

## Solution Architecture

### Role Hierarchy

Two privileged roles with distinct capabilities:

**Admin Role:**
- Standard administrative access
- View aggregate audit statistics
- Monitor privacy enforcement health
- **Cannot** export logs or view raw entries
- **Cannot** bypass privacy safeguards

**Root Role:**
- Superset of admin capabilities
- Full audit log access with filtering
- Audit export capabilities (CSV/JSON)
- Compliance review tools
- **Cannot** bypass privacy safeguards
- **Cannot** modify audit logs

**Critical:** NO role can override Phases 1-5 privacy enforcement.

---

## Implementation Details

### 1. Role-Based Access Control

**File:** `api/helpers/role_check.php`

**Functions:**
```php
requireRoot()         // Enforces root-only access (HTTP 403 if not root)
hasRootRole($user)    // Non-blocking check for root role
hasAdminRole($user)   // Checks admin or root role
logAuditAccess()      // Logs privileged audit access
```

**Security:**
- JWT validation required (via `requireAdmin()`)
- Explicit role verification from database
- All audit access logged to `/tmp/audit_access.log`
- No bypass mechanisms exist

---

### 2. Admin Audit Summary (Read-Only)

**Endpoint:** `/api/admin/audit/summary`  
**Role Required:** admin (or root)  
**Method:** GET  
**Rate Limit:** 300 req/min (admin tier)

**Query Parameters:**
- `window` - Time window (1d, 7d, 30d, 90d) - default: 7d

**Response Structure:**
```json
{
  "success": true,
  "message": "Privacy enforcement audit summary",
  "viewer_role": "admin",
  "access_level": "aggregate_only",
  "statistics": {
    "time_window": "7d",
    "start_time": "2026-02-02T00:00:00-05:00",
    "end_time": "2026-02-09T23:59:59-05:00",
    "pii_blocks": 0,
    "cohort_suppressions": 0,
    "rate_limit_violations": 12,
    "analytics_access_count": 487,
    "analytics_export_count": 3,
    "access_failures": 15,
    "privileged_audit_access": 8,
    "total_enforcement_events": 12,
    "total_analytics_requests": 490,
    "enforcement_rate": 2.45,
    "failure_rate": 3.06,
    "top_endpoints": {
      "admin_analytics_overview": 215,
      "admin_analytics_course": 143,
      "public_metrics": 89,
      "admin_analytics_timeseries": 40
    }
  },
  "phases": {
    "phase_1": {
      "name": "Database Lock-Down",
      "status": "active",
      "enforcement": "SELECT-only analytics_reader user"
    },
    "phase_2": {
      "name": "PII Response Validation",
      "status": "active",
      "enforcement": "Blocks: 0"
    },
    "phase_3": {
      "name": "Cohort Minimum (k=5)",
      "status": "active",
      "enforcement": "Suppressions: 0"
    },
    "phase_4": {
      "name": "Operational Safeguards",
      "status": "active",
      "enforcement": "Rate limit blocks: 12"
    },
    "phase_5": {
      "name": "CI Regression Prevention",
      "status": "active",
      "enforcement": "Automated checks on every PR"
    },
    "phase_6": {
      "name": "Audit Access",
      "status": "active",
      "enforcement": "Role-based audit visibility"
    }
  },
  "notice": "Aggregate statistics only. For full audit logs, root access required.",
  "export_capability": "not_available"
}
```

**What Admins See:**
- ✅ Aggregate counts (suppressions, blocks, violations)
- ✅ Enforcement rates and trends
- ✅ Top accessed endpoints
- ✅ Phase status overview
- ❌ Individual log entries (root only)
- ❌ Export capability (root only)

---

### 3. Root Audit Logs Viewer

**Endpoint:** `/api/root/audit/logs`  
**Role Required:** root ONLY  
**Method:** GET

**Query Parameters:**
- `startDate` - Start date (YYYY-MM-DD) - default: 7 days ago
- `endDate` - End date (YYYY-MM-DD) - default: today
- `endpoint` - Filter by endpoint (optional)
- `action` - Filter by action (optional)
- `success` - Filter by success (true/false) (optional)
- `limit` - Max entries to return (1-1000) - default: 100
- `offset` - Pagination offset - default: 0

**Response Structure:**
```json
{
  "success": true,
  "message": "Audit logs retrieved",
  "viewer_role": "root",
  "access_level": "full_compliance",
  "logs": [
    {
      "timestamp": 1707516234,
      "iso_timestamp": "2026-02-09T18:30:34-05:00",
      "endpoint": "admin_analytics_overview",
      "action": "view_dashboard",
      "user_role": "admin",
      "client_ip": "192.168.1.100",
      "request_method": "GET",
      "success": true,
      "parameters": {"startDate": "2026-01-01", "endDate": "2026-02-09"},
      "metadata": {"response_time_ms": 142}
    }
  ],
  "pagination": {
    "total_matched": 487,
    "returned": 100,
    "offset": 0,
    "limit": 100,
    "has_more": true
  },
  "filters_applied": {
    "startDate": "2026-02-02",
    "endDate": "2026-02-09",
    "endpoint": null,
    "action": null,
    "success": null,
    "limit": 100,
    "offset": 0
  },
  "available_filters": {
    "endpoints": ["admin_analytics_overview", "admin_analytics_course", ...],
    "actions": ["view_dashboard", "view_course_detail", "export", ...]
  },
  "privacy_notice": "Audit logs do not contain student PII or raw analytics payloads",
  "export_available": true,
  "export_endpoint": "/api/root/audit/export"
}
```

**What Root Sees:**
- ✅ Full audit log entries with context
- ✅ Filtering and pagination
- ✅ Available filter suggestions
- ✅ Export capability
- ❌ Student PII (never logged)
- ❌ Raw analytics payloads (never logged)

**Privacy Safeguards:**
- User IDs are hashed in display (`user_12ab34cd`)
- No PII fields from analytics responses
- Metadata sanitized before storage

---

### 4. Root Audit Export

**Endpoint:** `/api/root/audit/export`  
**Role Required:** root ONLY  
**Method:** GET

**Query Parameters:**
- `format` - Export format (json or csv) - required
- `startDate` - Start date (YYYY-MM-DD) - required
- `endDate` - End date (YYYY-MM-DD) - required
- `reason` - Export justification (min 5 chars) - required
- `confirmed` - Confirmation flag for large exports (0 or 1) - optional

**Export Limits:**
- **Maximum entries:** 50,000 per export
- **Maximum date range:** 365 days
- **Warning threshold:** 10,000 entries (requires confirmation)

**Confirmation Flow:**

**Step 1: Initial Request (Large Export)**
```bash
GET /api/root/audit/export?format=csv&startDate=2026-01-01&endDate=2026-02-09&reason=quarterly_compliance_review
```

**Response:**
```json
{
  "success": false,
  "confirmation_required": true,
  "message": "Large export requires confirmation",
  "warnings": [
    "Export contains 12,483 entries (>= 10000 threshold)",
    "Explicit confirmation required"
  ],
  "export_details": {
    "entry_count": 12483,
    "date_range": {"start": "2026-01-01", "end": "2026-02-09"},
    "days": 39,
    "format": "csv",
    "reason": "quarterly_compliance_review"
  },
  "to_confirm": "Add parameter: confirmed=1"
}
```

**Step 2: Confirmed Request**
```bash
GET /api/root/audit/export?format=csv&startDate=2026-01-01&endDate=2026-02-09&reason=quarterly_compliance_review&confirmed=1
```

**Response:** CSV file download with filename `audit_export_2026-01-01_to_2026-02-09.csv`

**CSV Structure:**
```
Timestamp,ISO Timestamp,Endpoint,Action,User Role,Client IP,User Agent,Request Method,Success,Parameters,Metadata
1707516234,2026-02-09T18:30:34-05:00,admin_analytics_overview,view_dashboard,admin,192.168.1.100,Mozilla/5.0...,GET,true,"{""startDate"":""2026-01-01""}","{""response_time_ms"":142}"
...
```

**JSON Export Structure:**
```json
{
  "export_metadata": {
    "generated_at": "2026-02-09T19:00:00-05:00",
    "generated_by": 1,
    "reason": "quarterly_compliance_review",
    "date_range": {"start": "2026-01-01", "end": "2026-02-09"},
    "entry_count": 12483,
    "format": "json"
  },
  "privacy_notice": "This export contains audit logs only. No student PII or raw analytics payloads included.",
  "compliance_certification": "FERPA-compliant audit trail export",
  "events": [...]
}
```

**Export Logging:**

Every export creates entries in TWO log files:

**1. Audit Access Log** (`/tmp/audit_access.log`):
```json
{
  "timestamp": 1707519600,
  "iso_timestamp": "2026-02-09T19:00:00-05:00",
  "action": "export_audit",
  "user_id": 1,
  "user_role": "root",
  "client_ip": "192.168.1.100",
  "user_agent": "Mozilla/5.0...",
  "parameters": {
    "format": "csv",
    "startDate": "2026-01-01",
    "endDate": "2026-02-09",
    "entry_count": 12483,
    "reason": "quarterly_compliance_review",
    "confirmed": true
  },
  "request_uri": "/api/root/audit/export?..."
}
```

**2. Audit Exports Log** (`/tmp/audit_exports.log`):
```json
{
  "timestamp": 1707519600,
  "iso_timestamp": "2026-02-09T19:00:00-05:00",
  "user_id": 1,
  "username": "root_admin",
  "action": "audit_export",
  "format": "csv",
  "date_range": {"start": "2026-01-01", "end": "2026-02-09"},
  "entry_count": 12483,
  "reason": "quarterly_compliance_review",
  "client_ip": "192.168.1.100",
  "user_agent": "Mozilla/5.0..."
}
```

**Export Safeguards:**
- ✅ Root-only access enforced
- ✅ Explicit confirmation for large exports
- ✅ Reason required (justification)
- ✅ All exports logged (high-visibility)
- ✅ Size and date range limits
- ✅ No PII in exported data
- ❌ No analytics payloads included
- ❌ No modification capability

---

## Audit Data Sources

Phase 6 provides visibility into enforcement from Phases 2-5:

| Phase | Enforcement | Audit Data Source | Logged Events |
|-------|-------------|-------------------|---------------|
| Phase 2 | PII Response Validation | `analytics_audit.log` | PII blocks (HTTP 403) |
| Phase 3 | Cohort Minimum (k=5) | `analytics_audit.log` | Cohort suppressions (metadata) |
| Phase 4 | Rate Limiting | `analytics_audit.log` | Rate limit violations (HTTP 429) |
| Phase 4 | Audit Logging | `analytics_audit.log` | All analytics access (success/failure) |
| Phase 4 | Export Controls | `analytics_audit.log` | Export attempts with validation |
| Phase 5 | CI Checks | CI pipeline logs | Test failures (not in audit log) |
| Phase 6 | Audit Access | `audit_access.log` | Privileged audit viewing/exports |

**Log Locations:**
- `/tmp/analytics_audit.log` - All analytics access and enforcement events
- `/tmp/audit_access.log` - Privileged audit access (Phase 6)
- `/tmp/audit_exports.log` - High-risk export operations (Phase 6)

---

## What Audit Records Include

**Always Included:**
- ✅ Timestamp (Unix + ISO 8601)
- ✅ Endpoint or subsystem
- ✅ Action performed
- ✅ User role (admin/root)
- ✅ Client IP address
- ✅ Request method (GET/POST)
- ✅ Success/failure status
- ✅ Request parameters
- ✅ Metadata (response times, enforcement details)

**NEVER Included:**
- ❌ Student user IDs
- ❌ Student email addresses
- ❌ Student names
- ❌ Raw analytics payloads
- ❌ PII from any source
- ❌ Session tokens or credentials

---

## Security & Privacy Guarantees

### Role Enforcement

**Admin Cannot:**
- Export audit logs
- View raw log files
- Access root-only endpoints
- Bypass privacy safeguards
- Modify audit data

**Root Cannot:**
- Bypass PII validation (Phase 2)
- Override cohort minimums (Phase 3)
- Skip rate limiting (Phase 4)
- Disable CI checks (Phase 5)
- Modify audit logs
- Delete enforcement events

**System Guarantees:**
- All audit access is logged
- Exports create audit trail
- No silent audit access possible
- JWT validation always enforced
- Default deny on role checks

---

## Verification

### Test Admin Access
```bash
# Get admin JWT
curl -X POST http://localhost/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin_user","password":"admin_pass"}'

# View audit summary (admin can access)
curl -H "Authorization: Bearer $ADMIN_JWT" \
  "http://localhost/api/admin/audit/summary?window=7d"

# Expected: HTTP 200, aggregate statistics

# Attempt root endpoint (should fail)
curl -H "Authorization: Bearer $ADMIN_JWT" \
  "http://localhost/api/root/audit/logs"

# Expected: HTTP 403, insufficient_privileges error
```

### Test Root Access
```bash
# Get root JWT
curl -X POST http://localhost/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"root_admin","password":"root_pass"}'

# View audit logs (root can access)
curl -H "Authorization: Bearer $ROOT_JWT" \
  "http://localhost/api/root/audit/logs?startDate=2026-02-01&endDate=2026-02-09&limit=10"

# Expected: HTTP 200, log entries with pagination

# Export audit logs (root can access)
curl -H "Authorization: Bearer $ROOT_JWT" \
  "http://localhost/api/root/audit/export?format=json&startDate=2026-02-01&endDate=2026-02-09&reason=testing" \
  -o audit_export.json

# Expected: JSON file with export metadata and events
```

### Verify Audit Logging
```bash
# Check audit access was logged
tail -n 10 /tmp/audit_access.log | jq '.'

# Expected: JSON entries with action=view_summary, view_logs, export_audit

# Check export was logged separately
tail -n 5 /tmp/audit_exports.log | jq '.'

# Expected: High-visibility export logs with reason and metadata
```

---

## Compliance Impact

### FERPA Compliance
- ✅ Audit trail of all analytics access (§99.32)
- ✅ Record of disclosures maintained
- ✅ Authorized officials only (role-based)
- ✅ Purpose specification (export reason required)

### GDPR Compliance
- ✅ Transparency principle (Article 5)
- ✅ Accountability principle (Article 5)
- ✅ Right to information (audit visibility)
- ✅ Data protection by design (role hierarchy)

### COPPA Compliance
- ✅ Parental oversight capability (via admin dashboard)
- ✅ No child PII in audit logs
- ✅ Access logging for accountability

### Organizational Accountability
- ✅ Privacy Officer can review enforcement
- ✅ Legal team can export for investigations
- ✅ IT Security can monitor access patterns
- ✅ Auditors can verify compliance

---

## Integration with Phases 1-5

Phase 6 provides visibility **WITHOUT weakening** previous phases:

**Phase 1 (Database):** Root cannot query raw student data, only analytics_* tables via analytics_reader  
**Phase 2 (PII Validation):** Root sees "PII block" events but not the blocked payload  
**Phase 3 (Cohort Minimum):** Root sees suppression counts but not individual student data  
**Phase 4 (Rate Limiting):** Root sees rate limit violations logged, cannot bypass limits  
**Phase 5 (CI Checks):** Root cannot disable CI checks, sees test results in pipeline logs  

**Key Principle:** Audit access is orthogonal to privacy enforcement. Viewing enforcement events does not grant ability to bypass enforcement.

---

## Operational Procedures

### Daily Operations
- Admins monitor dashboard for anomalies
- Root reviews logs weekly for patterns
- Export logs monthly for archival

### Quarterly Audits (Phase 5 Checklist)
- Root exports audit logs for quarter
- Review suppression and block counts
- Validate enforcement operating correctly
- Archive export with compliance docs

### Incident Response
- Root exports targeted date range
- Analyze access patterns
- Identify suspicious activity
- Generate incident report

### Compliance Reviews
- Root exports full year logs
- Submit to regulatory auditor
- Demonstrate FERPA/COPPA compliance
- Archive with signed attestation

---

## Future Enhancements

**Potential improvements:**
1. **Real-time alerting** - Slack/email on high-risk events
2. **Dashboard UI** - Web interface for admin/root users
3. **Automated reports** - Weekly/monthly summaries
4. **Anomaly detection** - ML-based pattern analysis
5. **Retention policies** - Automated log rotation/archival
6. **Differential privacy** - Privacy budget tracking in audit logs

---

## Maintenance

### Log Rotation
```bash
# Rotate analytics audit log (monthly)
mv /tmp/analytics_audit.log /tmp/archives/analytics_audit_$(date +%Y%m).log

# Rotate audit access log (quarterly)
mv /tmp/audit_access.log /tmp/archives/audit_access_Q$(date +%q)_$(date +%Y).log

# Rotate audit exports log (annually)
mv /tmp/audit_exports.log /tmp/archives/audit_exports_$(date +%Y).log
```

### Archival
- Compress old logs: `gzip /tmp/archives/*.log`
- Store for 7 years (FERPA requirement)
- Encrypt at rest
- Backup to secure storage

---

## Success Metrics

Phase 6 success measured by:
1. ✅ Admin can view enforcement health (aggregate stats)
2. ✅ Root can export full audit trails (compliance)
3. ✅ All audit access is logged (accountability)
4. ✅ No PII in audit logs (privacy preserved)
5. ✅ No role can bypass enforcement (safeguards intact)

---

## Final Confirmation

### Privacy Enforcement is Human-Auditable ✅
- Admins see aggregate enforcement statistics
- Root sees full audit log details
- Enforcement decisions are transparent
- Compliance reviews are feasible

### Only Root Can Export Audit Evidence ✅
- Admin cannot export logs
- Root exports require confirmation
- All exports logged as high-risk actions
- Export justification required

### No Role Can Override Privacy Safeguards ✅
- Admin cannot bypass Phases 1-5
- Root cannot bypass Phases 1-5
- Audit access is read-only
- Enforcement remains mandatory

**Analytics privacy enforcement is transparent, auditable, and accountable—without weakening safeguards.**

---

## References

- **Role-Based Access:** `api/helpers/role_check.php`
- **Admin Summary:** `api/admin/audit/summary.php`
- **Root Logs Viewer:** `api/root/audit/logs.php`
- **Root Export:** `api/root/audit/export.php`
- **Audit Access Log:** `/tmp/audit_access.log`
- **Audit Exports Log:** `/tmp/audit_exports.log`
- **Main Audit Log:** `/tmp/analytics_audit.log`

**Previous Phases:**
- Phase 1: `docs/PHASE1_DATABASE_LOCKDOWN.md`
- Phase 2: `docs/PHASE2_PII_RESPONSE_VALIDATION.md`
- Phase 3: `docs/PHASE3_COHORT_MINIMUM.md`
- Phase 4: `docs/PHASE4_OPERATIONAL_SAFEGUARDS.md`
- Phase 5: `docs/PHASE5_CI_AND_AUDITS.md`

---

**Document Version:** 6.0  
**Implementation Date:** February 9, 2026  
**Next Review:** May 9, 2026 (Q2 audit)
