# Professor Hawkeinstein – System Architecture Overview

## Purpose of This Document

This document defines the **intentional architectural split** of the Professor Hawkeinstein platform into two independent but related subsystems:

1. **Student Portal** – learning, progress, and observation
2. **Course Factory** – curriculum authoring and generation

This file is intended to be a **stable reference point** for humans and AI copilots during refactors, migrations, and feature development. Any architectural decision should be checked against this document.

---

## High-Level Architecture

The system is a **single codebase** that hosts **multiple applications**, separated by responsibility and deployed via **subdomains**.

```text
Student Portal   → https://app.professorhawkeinstein.org
Course Factory   → https://factory.professorhawkeinstein.org
```

Both applications:

* Share infrastructure (database, models, agent runtime)
* Do NOT share UI, authentication, or authority

---

## Subsystem 1: Student Portal

**Primary Purpose**

* Deliver learning experiences to students
* Track progress and mastery
* Provide read-only insight to parents and teachers

**Who Uses It**

* Students
* Observers (parents, teachers)

**Responsibilities**

* Authentication for students and observers
* Course consumption (view lessons, activities)
* Tutor-style AI agent interaction
* Progress tracking (mastery, completion, time)
* Observer dashboards (read-only)

**Explicitly Must NOT**

* Create or modify courses
* Generate curriculum or questions
* Edit standards or pacing
* Access Course Factory UI

**Directory Anchor**

```text
/student_portal/
```

Observers are implemented as a **role within the Student Portal**, not as system administrators.

---

## Subsystem 2: Course Factory

**Primary Purpose**

* Author and generate educational content
* Manage curriculum pipelines
* Configure system and authoring agents

**Who Uses It**

* Platform owner
* Curriculum designers
* Authorized content contributors

**Responsibilities**

* Course creation and editing
* Lesson and outline generation
* Standards alignment
* Question bank generation
* Agent configuration for authoring

**Explicitly Must NOT**

* Access individual student identities
* Read or modify student progress
* Authenticate students or observers
* Act as a learning environment

**Directory Anchor**

```text
/course_factory/
```

Files currently prefixed with `admin_*` are considered **Course Factory UI**, even if they are temporarily located at the repository root.

---

## Shared Infrastructure

Some components are intentionally shared but must remain **neutral**.

**Shared Components**

* Database schema and migrations
* Agent runtime and models
* API utilities
* Configuration and secrets

**Directory Anchors**

```text
/api/
/shared/
/config/
/cpp_agent/
/models/
```

Shared code must not contain business logic specific to either subsystem.

---

## Authentication & Authority Boundaries

| Area        | Student Portal         | Course Factory     |
| ----------- | ---------------------- | ------------------ |
| Login       | Students & Observers   | Factory Admins     |
| Tokens      | Separate scope         | Separate scope     |
| Cookies     | Subdomain-isolated     | Subdomain-isolated |
| Permissions | Role-based, read/write | Authoring-only     |

There is **no global admin role**.

Observers are **visibility-only** and cannot perform actions.

---

## Deployment Model

* Single repository
* Single server (initially)
* Multiple subdomains
* Separate document roots per subdomain

This allows future separation into multiple servers or repositories **without architectural changes**.

---

## Migration & Refactor Rules (Critical)

1. **Boundaries first, movement second**
2. Do not break existing URLs without redirects
3. Do not merge observer and admin concepts
4. Course Factory must never depend on student data
5. Student Portal must never modify curriculum

---

## Checkpoints for Copilot / Human Review

During refactors, periodically verify:

* [ ] Is this change clearly inside one subsystem?
* [ ] Does this violate a stated MUST NOT rule?
* [ ] Does this introduce cross-authority access?
* [ ] Can this be explained using this document?

If the answer is unclear, STOP and reassess before continuing.

---

## Source of Truth

This document is the **architectural source of truth** for Professor Hawkeinstein.

If code, prompts, or tooling conflict with this file, the conflict must be resolved explicitly — not ignored.

