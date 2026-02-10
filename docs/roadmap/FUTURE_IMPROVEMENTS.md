# Engineering Roadmap

**Project:** Professor Hawkeinstein Educational Platform  
**Architecture:** PHP + MariaDB backend, C++ agent service (llama.cpp), local LLM inference  
**Status:** Pre-production, core features operational  
**Last updated:** February 10, 2026

This document defines work required before production release and prioritized future enhancements. Items are categorized by engineering necessity based on authoritative implementation reports in `/docs`.

---

## 1. Critical – Required Before Production

These items block a credible public release due to legal compliance, accessibility, or operational maturity gaps.

### Legal Compliance
- **Student/parent data access portal (GDPR Article 15 / CCPA compliance)**  
  *Status:* No mechanism to fulfill right-to-access requests from EU or California users.  
  *Justification:* Legal requirement for public educational platforms. Must provide downloadable personal data exports.

- **WCAG 2.1 Level AA accessibility compliance audit and remediation**  
  *Status:* No formal accessibility testing performed.  
  *Justification:* Section 508 and ADA Title II requirements apply to educational platforms. Current UI has no screen reader compatibility verification.

### Operational Maturity
- **Two-factor authentication for admin accounts**  
  *Status:* Password and OAuth authentication implemented; TOTP/SMS 2FA not present.  
  *Justification:* Admin accounts control curriculum and student data. Password-only or OAuth-only is insufficient for production institutional deployments.  
  *Implementation note:* OAuth callback comments indicate "MFA not implemented" (see `api/auth/google/callback.php:23`).

- **Comprehensive API documentation (OpenAPI 3.0 specification)**  
  *Status:* 50+ admin endpoints undocumented. No formal API contracts.  
  *Justification:* API changes break clients silently. Required for third-party integrations and institutional IT review.

- **Docker deployment health checks and automated restart policies**  
  *Status:* Services fail silently with no recovery.  
  *Justification:* Not production-grade. Requires health check endpoints and orchestration configuration.

---

## 2. High Priority – Next Engineering Phase

These items harden existing implementations or complete partially deployed systems.

### Security Hardening
- **Expand rate limiting to non-analytics endpoints**  
  *Status:* Public analytics endpoints (`/api/public/metrics.php`) have rate limiting via `analytics_rate_limiter.php`. Admin invitation endpoints (`api/admin/invite_admin.php`) note "rate limiting recommended (not implemented in v1)".  
  *Justification:* Prevents abuse of admin provisioning, course generation, and agent endpoints.

- **HSTS and CORS allowlist for production**  
  *Status:* Headers implemented in `api/helpers/security_headers.php`, but CORS currently set to `*` (permissive in development).  
  *Justification:* Production deployments require restricted origin policies.

### Privacy & Compliance
- **Automated privacy audit report generation**  
  *Status:* Manual compliance checks documented in `docs/ANALYTICS_PRIVACY_VALIDATION.md`.  
  *Justification:* Ongoing FERPA/COPPA adherence requires quarterly audit automation. Current process does not scale.

### Data Integrity
- **Course versioning and audit trail**  
  *Status:* Curriculum edits overwrite previous versions. No rollback mechanism.  
  *Justification:* Collaborative authoring requires version history. Accidental lesson deletion is unrecoverable.

### Agent Quality Improvements
- **Improve JSON output consistency from small LLMs**  
  *Status:* Standards Generator and Outline Generator occasionally produce malformed JSON with <4B parameter models (noted in `COURSE_GENERATION_ARCHITECTURE.md`).  
  *Justification:* Causes silent failures. Retry logic masks reliability issues. Consider constrained decoding or grammar-guided generation.

- **Age-appropriate content validation enforcer**  
  *Status:* Content Generator does not enforce readability constraints. Manual review required.  
  *Justification:* Outputs can be too advanced or simplistic for target age. Flesch-Kincaid or Lexile scoring needed.

- **Automated explanation generation for incorrect quiz answers**  
  *Status:* Students receive "Incorrect" feedback with no corrective guidance.  
  *Justification:* Reduces pedagogical value. LLM can generate targeted explanations referencing lesson content.

---

## 3. Medium Priority – Strong Value Add

These features assume a stable core system and improve UX, performance, or expand applicability.

### Performance
- **Redis caching layer for database-heavy operations**  
  *Targets:* Aggregate analytics queries, course outlines, agent prompt templates.  
  *Justification:* Public metrics and admin dashboard queries are expensive. Expected cache hit rate >80%. Single Redis instance serves multiple features.

### Agent & Content Capabilities
- **Support for multiple standards frameworks**  
  *Current:* Standards Generator assumes U.S. K-12 model.  
  *Expansion:* Common Core, NGSS, state-specific standards (Texas TEKS, California frameworks).  
  *Justification:* Required for geographic expansion but not core functionality.

- **Multimedia content suggestions in lessons**  
  *Current:* Text-only lesson generation.  
  *Enhancement:* Include relevant images, diagrams, video references (e.g., Khan Academy, YouTube embeds).  
  *Justification:* Improves engagement and retention. Requires citation and copyright handling.

- **Additional question types (matching, ordering, diagram labeling)**  
  *Current:* Multiple choice, short answer, essay questions implemented (see `ASSESSMENT_GENERATION_API.md`).  
  *Justification:* Expands assessment variety. Not strictly required but improves learning outcomes.

### Admin Productivity
- **Course templates for rapid creation**  
  *Justification:* Reduces time-to-first-course for new authors. High ROI for onboarding.

- **Bulk import/export of courses (JSON/ZIP format)**  
  *Justification:* Manual migration between environments is error-prone. Required for multi-school deployments.

### Student/Parent Features
- **Parent/guardian dashboard (read-only child progress view)**  
  *Justification:* Viewing child progress increases stakeholder buy-in. Requires separate authentication flow or invitation system.

- **Student course recommendations based on mastery metrics**  
  *Status:* Requires stable analytics and mastery tracking (already implemented per `ANALYTICS_IMPLEMENTATION_REPORT.md`).  
  *Justification:* Improves engagement but optional.

### Integrations
- **LTI 1.3 integration for LMS platforms (Canvas, Moodle, Blackboard)**  
  *Justification:* Enables adoption by existing schools using institutional LMS. Significant engineering lift (OAuth, grade passback, roster sync).

- **Multi-language support for UI and content**  
  *Justification:* Expands addressable market. Complicates agent prompts (requires multilingual models or translation layer) and content validation.

### Infrastructure
- **Upgrade to larger LLM models (e.g., 7B → 13B or 30B parameters)**  
  *Justification:* Improves agent output quality and JSON formatting reliability. Requires more VRAM (16GB → 24GB+). Hardware-dependent tradeoff.

---

## 4. Low Priority – Would Be Nice to Have

Optional enhancements that do not materially affect credibility or safety.

- **Real-time WebSocket updates for analytics dashboard**  
  *Justification:* Current polling works. WebSockets add complexity for marginal UX gain.

- **Achievement badges and gamification elements**  
  *Justification:* Engagement booster but not pedagogically required. Risk of extrinsic motivation crowding out intrinsic learning.

- **Course sharing marketplace between educators**  
  *Justification:* Introduces multi-tenancy complexity. Single-school deployments do not need this.

- **Offline mode for content access (PWA with service workers)**  
  *Justification:* Local-first architecture already minimizes internet dependency for inference. Full offline requires extensive caching and sync logic.

- **Public-facing API for third-party integrations**  
  *Justification:* Requires stable, documented API first (see Critical section). No external demand yet.

- **Optional demographic data collection for research**  
  *Justification:* Adds value for longitudinal studies but privacy risk. Low priority until core analytics stable and IRB approval obtained.

---

## 5. Explicitly Out of Scope (For Now)

These items do not align with current architecture, resource constraints, or project goals.

- **Native mobile apps (iOS/Android)**  
  *Rationale:* Web UI is responsive. Native apps require dedicated teams, TestFlight/Play Store compliance, and separate build pipelines. Unjustified effort for current user base.

- **White-label/multi-tenancy for school district branding**  
  *Rationale:* Business model complexity. Single-deployment architecture is intentional. Premature commercialization.

- **Cloud-based LLM inference (OpenAI, Anthropic, Google Gemini)**  
  *Rationale:* Violates local-first architecture principle. Introduces recurring API costs, vendor lock-in, and data privacy concerns (student data leaving premises).

- **Blockchain-based credential verification**  
  *Rationale:* Unnecessary complexity. No demand from target users. Traditional transcripts suffice.

- **Federated learning across school deployments**  
  *Rationale:* Research-grade feature. Requires privacy-preserving ML infrastructure (differential privacy, secure aggregation). Beyond current scope.

---

## Completed Work (For Reference)

**Analytics System (January 2026):**  
- ✅ Aggregate metrics tables (`analytics_daily_rollup`, `analytics_course_metrics`, `analytics_agent_metrics`)  
- ✅ Daily aggregation script (`scripts/aggregate_analytics.php`) with cron scheduling  
- ✅ Admin analytics dashboard with Chart.js (`course_factory/admin_analytics.html`)  
- ✅ Public metrics page at `/student_portal/metrics.html` (no authentication)  
- ✅ Time-series analysis (daily/weekly/monthly trends via `api/admin/analytics/timeseries.php`)  
- ✅ Course effectiveness tracking and agent performance monitoring  
- ✅ FERPA/COPPA-compliant anonymized exports (hashed user IDs, no PII)  
- ✅ Privacy validation documented in `docs/ANALYTICS_PRIVACY_VALIDATION.md`  
- ✅ Rate limiting on public analytics endpoints (`api/helpers/analytics_rate_limiter.php`)

**Course Generation System (December 2025 - January 2026):**  
- ✅ 5-agent pipeline: Standards → Outline → Lessons → Questions → Assessments  
- ✅ Standards Generation Agent (`api/admin/generate_standards.php`)  
- ✅ Outline Generation Agent (`api/admin/generate_draft_outline.php`)  
- ✅ Lesson Content Generator (`api/admin/generate_lesson_content.php`) - LLM-generated, no web scraping  
- ✅ Question Bank Generator (`api/admin/generate_lesson_questions.php`) - fill-in-blank, multiple choice, essay  
- ✅ Assessment Generator (`api/admin/generate_assessment.php`) - unit tests, midterms, finals  
- ✅ Complete documentation in `docs/COURSE_GENERATION_ARCHITECTURE.md`, `docs/COURSE_GENERATION_API.md`, `docs/ASSESSMENT_GENERATION_API.md`

**Security & Authentication (January 2026):**  
- ✅ Google OAuth 2.0 Authorization Code flow (`api/auth/google/login.php`, `callback.php`)  
- ✅ Invitation-only admin onboarding (`admin_invitations` table, 7-day token expiry)  
- ✅ Secure httpOnly cookies with SameSite=Lax (`SECURE_COOKIE_IMPLEMENTATION.md`)  
- ✅ HTTPS support via mkcert for localhost development (`HTTPS_AUDIT_REPORT.md`)  
- ✅ Centralized security headers (CSP, HSTS, X-Frame-Options) in `api/helpers/security_headers.php`  
- ✅ JWT-based authorization with `requireAuth()`, `requireAdmin()`, `requireRoot()`  
- ✅ OAuth audit logging to `auth_events` table  
- ✅ CORS configuration (currently permissive `*` for dev; production requires allowlist)

**Audit & Compliance (February 2026):**  
- ✅ Role-based audit access (admin: summary stats, root: full logs) - `docs/PHASE6_AUDIT_ACCESS.md`  
- ✅ Audit endpoints: `/api/admin/audit/summary`, `/api/root/audit/logs`  
- ✅ Audit access logging to `/tmp/audit_access.log`  
- ✅ Privacy enforcement audit tracking (PII blocks, cohort suppressions)

---

## Dependency Notes

- **Critical section items are legal/operational gates.** GDPR portal and WCAG audit must complete before public launch to institutions.  
- **2FA implementation** should use TOTP (RFC 6238) with QR code enrollment. Consider integration with existing OAuth flow.  
- **API documentation** required before LTI integration or third-party API access.  
- **Redis caching** applicable to multiple features (analytics, course data, agent prompts). Implement once, reuse across subsystems.  
- **Multi-language support** requires stable content validation and agent prompt templates first. Consider using multilingual models (Qwen2.5 supports 29 languages) vs. translation pipeline.  
- **Rate limiting expansion** should use same `analytics_rate_limiter.php` pattern with configurable limits per endpoint class (public: 60/min, admin: 300/min, course generation: 10/hr).

---

*This is an engineering roadmap based on implemented features documented in `/docs`. Priorities subject to change based on resource availability, user feedback, and regulatory requirements.*
