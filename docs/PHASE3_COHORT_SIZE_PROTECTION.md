# Phase 3: Minimum Cohort Size Protection (k-Anonymity)

**Status:** ✅ COMPLETE  
**Date:** 2026-02-08  
**Compliance:** FERPA, COPPA, k-anonymity principles

---

## Overview

Phase 3 implements **k-anonymity enforcement** (k=5) to prevent re-identification attacks when cohort sizes are too small. This ensures that analytics cannot expose individual-level insights through small sample sizes.

This is a **mandatory privacy safeguard** required by FERPA principles and statistical disclosure control best practices.

---

## The Re-identification Problem

### Example Attack Scenario

Without cohort size protection:

```json
{
  "course_name": "Advanced Quantum Physics",
  "total_enrolled": 2,
  "avg_mastery_score": 87.5,
  "completion_rate": 50.0
}
```

**Attack:** If an observer knows one student in this course scored 92%, they can infer the other student scored 83%.

### Phase 3 Solution

With cohort size protection:

```json
{
  "course_name": "Advanced Quantum Physics",
  "total_enrolled": 2,
  "avg_mastery_score": null,
  "completion_rate": null,
  "insufficient_data": true,
  "insufficient_data_reason": "Cohort size below minimum threshold for privacy protection"
}
```

**Result:** No individual inference possible. Course identity preserved, but metrics suppressed.

---

## Implementation

### Core Module

**File:** `/api/helpers/analytics_cohort_guard.php`

**Global Constant:**
```php
define('MIN_ANALYTICS_COHORT_SIZE', 5);
```

**Functions:**
- `enforceCohortMinimum($payload, $contextLabel)` - Main enforcement entry point
- `applyCohortEnforcement($data, $path, &$suppressions)` - Recursive processor
- `extractCohortSize($data)` - Cohort size detection
- `suppressMetrics($data, $cohortSize, $path, &$suppressions)` - Metric suppression
- `sendProtectedAnalyticsJSON($data, $statusCode, $contextLabel)` - Combined Phase 2+3 wrapper

---

## Cohort Size Detection

The guard automatically detects cohort size from these fields (in priority order):

1. `total_enrolled`
2. `total_students`
3. `unique_students`
4. `student_count`
5. `total`
6. `active_students`
7. `unique_users`
8. `unique_users_served`
9. `studentSummary.total` (nested)

If no cohort size field is found, data passes through unchanged (assumes non-cohort data).

---

## Suppressed Metrics

When cohort < 5, these fields are set to `null`:

### Mastery & Performance
- `avg_mastery_score`
- `avg_student_mastery`

### Completion & Progress
- `completion_rate`
- `avg_completion_time_days`

### Time & Engagement
- `avg_study_time_hours`
- `avg_session_duration_minutes`

### Agent Metrics
- `avg_response_time_ms`
- `avg_response_length_chars`
- `avg_interactions_per_user`
- `students_improved_count`

### Course Metrics
- `retry_rate`
- `avg_lessons_per_student`
- `avg_quiz_attempts`

**Total:** 14 sensitive metrics suppressed

---

## Preserved Data

Even when metrics are suppressed, these fields remain:

- Identifiers: `course_id`, `course_name`, `agent_name`
- Cohort size itself: `total_enrolled`, `total_students`
- Descriptive fields: `subject_area`, `difficulty_level`
- Status flags: `is_active`

**Rationale:** Knowing a course exists with 2 students is not a privacy violation. Knowing their average score IS.

---

## Enforcement Behavior

### Single Metric Groups

```php
// INPUT
[
  'course_name' => 'Small Course',
  'total_enrolled' => 3,
  'avg_mastery_score' => 92.0
]

// OUTPUT
[
  'course_name' => 'Small Course',
  'total_enrolled' => 3,
  'avg_mastery_score' => null,
  'insufficient_data' => true,
  'insufficient_data_reason' => '...'
]
```

### Array of Courses (Selective Suppression)

```php
// INPUT
['courses' => [
  ['name' => 'Large', 'total_enrolled' => 50, 'avg_score' => 82.0],
  ['name' => 'Small', 'total_enrolled' => 2, 'avg_score' => 95.0],
  ['name' => 'Medium', 'total_enrolled' => 8, 'avg_score' => 77.5]
]]

// OUTPUT
['courses' => [
  ['name' => 'Large', 'total_enrolled' => 50, 'avg_score' => 82.0],  // ✅ Preserved
  ['name' => 'Small', 'total_enrolled' => 2, 'avg_score' => null, 'insufficient_data' => true],  // ❌ Suppressed
  ['name' => 'Medium', 'total_enrolled' => 8, 'avg_score' => 77.5]  // ✅ Preserved
]]
```

### Nested Structures

```php
// INPUT
[
  'course' => ['course_name' => 'Test'],
  'studentSummary' => ['total' => 4],
  'currentMetrics' => ['avg_mastery_score' => 88.0]
]

// OUTPUT
[
  'course' => ['course_name' => 'Test'],
  'studentSummary' => ['total' => 4],
  'currentMetrics' => [
    'avg_mastery_score' => null,
    'insufficient_data' => true
  ]
]
```

---

## Integration Points

All analytics endpoints now use `sendProtectedAnalyticsJSON()` which enforces **both Phase 2 and Phase 3**:

### Admin Analytics
- `/api/admin/analytics/overview.php`
- `/api/admin/analytics/course.php` (2 routes)
- `/api/admin/analytics/timeseries.php` (3 routes)
- `/api/admin/analytics/export.php` (4 datasets)

### Public Metrics
- `/api/public/metrics.php`

**Total protected endpoints:** 9

---

## Logging

### Non-Production
```log
[COHORT SUPPRESSION] Endpoint: admin_analytics_course_detail | Events: 1 | Time: 2026-02-08 19:05:12
[COHORT SUPPRESSION DETAIL] Path: root | Cohort: 3/5 | Suppressed: avg_mastery_score, completion_rate
```

### Production
```log
[COHORT SUPPRESSION] Endpoint: admin_analytics_course_detail | Events: 1 | Time: 2026-02-08 19:05:12
```

**Note:** In production, suppression details are NOT logged to prevent exposing cohort sizes via log analysis.

---

## Testing

**Test suite:** `/tests/test_cohort_enforcement.php`

**Test coverage:**
- ✅ Cohort size 10 (above threshold) → metrics preserved
- ✅ Cohort size 3 (below threshold) → metrics suppressed
- ✅ Cohort size 5 (exactly at threshold) → metrics preserved
- ✅ Mixed array (50, 2, 8 students) → selective suppression
- ✅ No cohort field → pass through unchanged
- ✅ Nested cohort (studentSummary.total = 4) → nested suppression
- ✅ Agent metrics (unique_users_served = 2) → suppression
- ✅ Zero students → suppression

**Result:** 8/8 tests passing

---

## k-Anonymity Rationale

**k-anonymity principle:** Each record must be indistinguishable from at least k-1 other records.

**Our implementation:** k=5

| Cohort Size | Status | Rationale |
|-------------|--------|-----------|
| 0-4 students | ❌ SUPPRESSED | High re-identification risk |
| 5+ students | ✅ DISCLOSED | Acceptable anonymity threshold |

**Why k=5 (not k=3 or k=10)?**
- **FERPA guidance:** Small numbers rule (suppress < 5)
- **NCES standards:** Minimum cell size = 5 for education data
- **Balance:** Usable analytics vs. privacy protection

---

## Compliance Alignment

### FERPA (34 CFR § 99.3)
> "Personally identifiable information includes... information that, alone or in combination, is linked to a specific student that would allow a reasonable person to identify the student."

**Phase 3 compliance:** Suppressing metrics for cohorts < 5 prevents identification even when combined with external information.

### COPPA (16 CFR § 312.2)
> "Personal information... includes... information sufficient to contact a specific individual."

**Phase 3 compliance:** Small cohort metrics could enable contact identification when combined with directory information. Suppression prevents this.

### GDPR (Article 4(1))
> "Personal data means any information relating to an identified or identifiable natural person."

**Phase 3 compliance:** Metrics from small cohorts constitute identifiable data. Suppression ensures data minimization (Article 5(1)(c)).

---

## Attack Scenarios Prevented

### 1. Single-Student Inference
**Attack:** Course with 1 student → avg_score reveals that student's score  
**Defense:** ✅ Suppressed (cohort < 5)

### 2. Two-Student Differencing
**Attack:** Course with 2 students → if one score is known, derive the other  
**Defense:** ✅ Suppressed (cohort < 5)

### 3. Temporal Correlation
**Attack:** Track cohort size changes over time to infer individual progress  
**Defense:** ✅ Suppressed (all time periods with cohort < 5)

### 4. Cross-Course Triangulation
**Attack:** Student enrolled in 3 small courses → combine metrics to identify  
**Defense:** ✅ All small courses suppressed independently

### 5. Agent Interaction Profiling
**Attack:** Agent with 2 unique users → interaction patterns reveal identities  
**Defense:** ✅ Suppressed (unique_users_served < 5)

---

## Migration Path

### Before Phase 3
```php
sendAnalyticsJSON([
    'total_enrolled' => 3,
    'avg_mastery_score' => 88.0
], 200, 'endpoint');
```

### After Phase 3
```php
sendProtectedAnalyticsJSON([
    'total_enrolled' => 3,
    'avg_mastery_score' => 88.0  // Will be suppressed automatically
], 200, 'endpoint');
```

**Required changes:** Replace function name only. Suppression is automatic.

---

## Maintenance

### Adjusting Threshold

Edit `analytics_cohort_guard.php`:

```php
// Change from 5 to new value
define('MIN_ANALYTICS_COHORT_SIZE', 10);  // More conservative
```

### Adding New Sensitive Metrics

Edit `suppressMetrics()` function:

```php
$sensitiveMetrics = [
    'avg_mastery_score',
    // ... existing metrics ...
    'new_metric_name'  // Add here
];
```

---

## Next Steps

**Phase 4:** Admin & Public Endpoint Operational Safeguards (rate limiting, audit logs)  
**Phase 5:** Ongoing Privacy Regression Prevention (CI checks, quarterly audits)

---

## Summary

Phase 3 creates a **structural privacy safeguard** that prevents individual-level inference through small sample sizes. Combined with Phase 1 (database access control) and Phase 2 (PII field blocking), the platform now enforces defense-in-depth privacy protection.

**Key Achievement:** Analytics cannot expose individual student data through small cohorts, even if developers accidentally query small groups.
