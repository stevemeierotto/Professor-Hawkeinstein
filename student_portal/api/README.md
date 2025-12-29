# Student Portal API

This directory will contain the Student Portal's API endpoints.

## Purpose

Provide API access for:
- Student authentication
- Course consumption (read-only)
- Progress tracking
- Observer dashboards

## Current Status

🚧 **Migration in progress**

Currently, student APIs are served from `/api/student/` and `/api/progress/`.
This directory will eventually contain proxies or relocated endpoints.

## Boundaries (per ARCHITECTURE.md)

This API layer **MUST NOT**:
- Create or modify courses
- Generate curriculum or questions
- Edit standards or pacing
- Access Course Factory functionality

## Structure (Planned)

```
/student_portal/api/
├── auth/           # Student authentication
├── course/         # Course viewing (read-only)
├── progress/       # Progress tracking
└── observer/       # Observer dashboards
```
