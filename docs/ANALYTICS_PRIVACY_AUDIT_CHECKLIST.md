# Analytics Privacy Audit Checklist

**Purpose:** Ensure ongoing compliance with FERPA, COPPA, GDPR, and institutional privacy standards.

**Frequency:** Quarterly (Q1, Q2, Q3, Q4 annually) + ad-hoc for major changes

**Owner:** Privacy Compliance Lead / Senior Platform Engineer

**Version:** 5.0 (Phase 5)

**Last Updated:** February 8, 2026

---

## Audit Schedule

| Quarter | Review Window | Completed By | Next Audit Due |
|---------|---------------|--------------|----------------|
| Q1 2026 | Jan 1 - Mar 31 | ___________ | April 15, 2026 |
| Q2 2026 | Apr 1 - Jun 30 | ___________ | July 15, 2026 |
| Q3 2026 | Jul 1 - Sep 30 | ___________ | Oct 15, 2026 |
| Q4 2026 | Oct 1 - Dec 31 | ___________ | Jan 15, 2027 |

---

## Pre-Audit Setup

- [ ] Ensure database access (analytics_reader credentials)
- [ ] Collect audit log files: `/tmp/analytics_audit.log`
- [ ] Check rate limit state: `/tmp/analytics_rate_limits.json`
- [ ] Review git commits since last audit

**Tools:**
```bash
# Run automated CI checks first
php tests/ci_privacy_checks.php
./scripts/validate_analytics_privacy.sh

# Review recent analytics access
tail -n 500 /tmp/analytics_audit.log | jq '.'

# Check for schema changes
git log --since="3 months ago" --grep="analytics\|schema" --oneline
```

---

## Audit Checklist

### 1. Database Layer (Phase 1)

**Objective:** Verify analytics tables contain only anonymized/aggregated data

- [ ] Confirm `analytics_reader` user has SELECT-only permissions
- [ ] Verify `analytics_user_snapshots` uses `user_hash`, not `user_id`
- [ ] Check no analytics tables contain: `email`, `username`, `first_name`, `last_name`, `ip_address`
- [ ] Validate geographic data is state/province level only (no city/postal code)
- [ ] Confirm age groups are categorical: `under_13`, `13_17`, `18_plus`, `not_provided`

**Validation Commands:**
```sql
-- Connect as analytics_reader
-- Attempt INSERT (should fail)
INSERT INTO analytics_user_snapshots VALUES (...);  -- Expected: Permission denied

-- Check column names
SHOW COLUMNS FROM analytics_user_snapshots;
SHOW COLUMNS FROM analytics_course_metrics;
SHOW COLUMNS FROM analytics_agent_metrics;
SHOW COLUMNS FROM analytics_daily_rollup;
```

**Pass Criteria:**
- ✅ No direct PII columns in any `analytics_*` table
- ✅ No write permissions for `analytics_reader`
- ✅ All geographic data limited to region/state precision

---

### 2. API Response Validation (Phase 2)

**Objective:** Confirm PII fields never appear in analytics responses

- [ ] Test all 9 analytics endpoints with audit/test accounts
- [ ] Verify responses contain NO forbidden keys:
  - `user_id`, `email`, `username`, `name`, `first_name`, `last_name`
  - `ip_address`, `session_id`, `password`, `token`
- [ ] Check nested objects blocked deeper than 3 levels
- [ ] Confirm `sendProtectedAnalyticsJSON()` used in all responses

**Test Commands:**
```bash
# Test public metrics (no auth)
curl -s http://localhost/api/public/metrics.php | jq 'keys'

# Test admin endpoints (requires JWT)
curl -s -H "Authorization: Bearer $ADMIN_JWT" \
  http://localhost/api/admin/analytics/overview.php | jq 'keys'

# Check for forbidden keys
curl -s http://localhost/api/public/metrics.php | grep -E 'email|username|user_id'
```

**Pass Criteria:**
- ✅ No forbidden PII keys in any response
- ✅ All responses validated by `sendProtectedAnalyticsJSON()`
- ✅ Deep nesting blocked (max 3 levels)

---

### 3. Cohort Size Protection (Phase 3)

**Objective:** Verify k-anonymity enforcement (k=5 minimum)

- [ ] Test analytics queries with small cohorts (< 5 users)
- [ ] Confirm cohort suppression messages returned:
  - `"suppressed": true`
  - `"reason": "Cohort size below minimum threshold"`
- [ ] Validate `enforceCohortMinimum()` invoked in all endpoints
- [ ] Check no individual-level data exposed when cohort < 5

**Test Scenarios:**
```bash
# Create test course with 3 students
# Query analytics for that course
curl -s -H "Authorization: Bearer $ADMIN_JWT" \
  "http://localhost/api/admin/analytics/course.php?courseId=TEST_COURSE"

# Expected response should suppress small cohort data
```

**Pass Criteria:**
- ✅ Cohorts < 5 automatically suppressed
- ✅ Clear suppression messages provided
- ✅ No inference attacks possible via aggregation

---

### 4. Operational Safeguards (Phase 4)

**Objective:** Confirm rate limiting, audit logging, and export controls

#### Rate Limiting
- [ ] Test public metrics rate limit (60 requests/minute)
- [ ] Test admin analytics rate limit (300 requests/minute)
- [ ] Verify HTTP 429 responses when limits exceeded
- [ ] Check `Retry-After` header present

**Test:**
```bash
# Hammer public endpoint (should rate limit after 60 req/min)
for i in {1..70}; do
  curl -s -o /dev/null -w "%{http_code}\n" http://localhost/api/public/metrics.php
done
```

#### Audit Logging
- [ ] Verify all analytics access logged to `/tmp/analytics_audit.log`
- [ ] Check log structure includes:
  - `timestamp`, `endpoint`, `action`, `user_id`, `user_role`
  - `client_ip`, `parameters`, `success`, `metadata`
- [ ] Confirm no gaps in audit trail
- [ ] Validate both success and failure events logged

**Test:**
```bash
# Trigger analytics access
curl -s http://localhost/api/public/metrics.php

# Check audit log
tail -n 1 /tmp/analytics_audit.log | jq '.'
```

#### Export Controls
- [ ] Test export row limit (50,000 max)
- [ ] Test date range limit (365 days max)
- [ ] Verify confirmation required for large exports (> 10,000 rows)
- [ ] Check export metadata logged (row count, date range, format)

**Pass Criteria:**
- ✅ Rate limits enforced correctly
- ✅ All access logged with full context
- ✅ Export limits prevent bulk data extraction

---

### 5. CI Regression Prevention (Phase 5)

**Objective:** Verify automated checks prevent privacy regressions

- [ ] Run CI privacy checks: `php tests/ci_privacy_checks.php`
- [ ] Run static analysis: `./scripts/validate_analytics_privacy.sh`
- [ ] Confirm both scripts exit 0 (pass)
- [ ] Review any warnings or edge cases
- [ ] Check CI integration (GitHub Actions, GitLab CI, etc.)

**Pass Criteria:**
- ✅ CI checks pass without errors
- ✅ Static analysis detects all required guards
- ✅ Automated tests run on every pull request

---

### 6. Code Review

**Objective:** Manual inspection of analytics-related changes

- [ ] Review git commits since last audit
- [ ] Check for new analytics endpoints (require full Phase 1-5 compliance)
- [ ] Validate privacy banners present in all analytics files
- [ ] Confirm no bypass mechanisms added (e.g., `skip_privacy_check` flags)

**Commands:**
```bash
# Find all analytics-related commits
git log --since="3 months ago" --grep="analytics" --oneline

# Check for new analytics endpoints
find api -name "*analytics*" -type f -newer .last_audit

# Verify privacy banners
grep -r "PRIVACY REGRESSION PROTECTED" api/
```

**Pass Criteria:**
- ✅ All new endpoints comply with Phases 1-5
- ✅ No privacy bypasses introduced
- ✅ Privacy banners present in all files

---

### 7. Access Pattern Analysis

**Objective:** Identify unusual or suspicious analytics access

- [ ] Review audit log for high-volume users
- [ ] Check for repeated rate limit violations
- [ ] Identify any admin accounts with unusual export patterns
- [ ] Look for timing attack indicators (rapid sequential queries)

**Analysis:**
```bash
# Top 10 users by analytics access volume
jq -r '.user_id' /tmp/analytics_audit.log | sort | uniq -c | sort -rn | head -10

# Rate limit violations
grep "Rate limit exceeded" /tmp/analytics_audit.log | wc -l

# Large export attempts
jq 'select(.action == "export" and .metadata.row_count > 10000)' /tmp/analytics_audit.log
```

**Pass Criteria:**
- ✅ No anomalous access patterns detected
- ✅ Rate limit violations are rare and legitimate
- ✅ Export volumes align with expected research use

---

### 8. Documentation Review

**Objective:** Ensure privacy documentation is current

- [ ] Review `ANALYTICS_PRIVACY_VALIDATION.md` for accuracy
- [ ] Check phase-specific docs (PHASE1-5) are up to date
- [ ] Verify API documentation reflects privacy constraints
- [ ] Confirm onboarding materials include privacy training

**Pass Criteria:**
- ✅ All documentation current and accurate
- ✅ Privacy requirements clearly communicated to developers

---

## Post-Audit Actions

### If All Checks Pass:
1. **Record audit completion:**
   - Date: _______________
   - Auditor: _______________
   - Status: ✅ COMPLIANT
   - Next audit due: _______________

2. **Archive audit logs:**
   ```bash
   cp /tmp/analytics_audit.log "logs/analytics_audit_Q{n}_{year}.log"
   ```

3. **Update audit schedule** (top of this document)

4. **No further action required**

### If Issues Found:
1. **Document findings:**
   - Issue description
   - Severity (Critical / High / Medium / Low)
   - Affected endpoints/tables
   - Remediation plan

2. **Create remediation tickets** (Jira, GitHub Issues, etc.)

3. **Assign owner and deadline** for each issue

4. **Re-audit after fixes** to confirm compliance

5. **Escalate critical issues** to Privacy Officer / Legal

---

## Issue Tracking Template

```markdown
### Audit Finding #{N}

**Date Found:** YYYY-MM-DD
**Auditor:** Name
**Severity:** [Critical / High / Medium / Low]

**Description:**
Brief description of the privacy violation or concern.

**Affected Components:**
- Endpoint: `api/...`
- Table: `analytics_...`
- Function: `functionName()`

**Risk:**
What privacy harm could result if not fixed?

**Remediation:**
1. Step 1
2. Step 2
3. Verification

**Owner:** @username
**Due Date:** YYYY-MM-DD
**Status:** [Open / In Progress / Fixed / Verified]

**Follow-up:**
Any additional context or related issues.
```

---

## Contact Information

**Privacy Compliance Lead:**
- Name: _______________
- Email: _______________

**Platform Engineering Lead:**
- Name: _______________
- Email: _______________

**Database Administrator:**
- Name: _______________
- Email: _______________

---

## Revision History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 5.0 | Feb 8, 2026 | Platform Team | Phase 5: CI and audit mechanisms |
| 4.0 | Feb 8, 2026 | Platform Team | Phase 4: Operational safeguards |
| 3.0 | Feb 8, 2026 | Platform Team | Phase 3: Cohort size protection |
| 2.0 | Feb 8, 2026 | Platform Team | Phase 2: PII response validation |
| 1.0 | Feb 8, 2026 | Platform Team | Phase 1: Database lock-down |

---

## Appendix: Compliance Standards

**FERPA (Family Educational Rights and Privacy Act):**
- Student education records protected
- No PII disclosure without consent

**COPPA (Children's Online Privacy Protection Act):**
- Under-13 users require parental consent
- No behavioral tracking without notice

**GDPR (General Data Protection Regulation):**
- Data minimization principle
- Right to erasure (anonymization)
- Purpose limitation for analytics

**NCES Guidelines (National Center for Education Statistics):**
- K-anonymity with k=5 minimum
- Cell suppression for small cohorts
- Geographic precision limited to region/state
