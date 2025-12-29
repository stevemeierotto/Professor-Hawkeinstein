# Course Factory API

This directory will contain the Course Factory's API endpoints.

## Purpose

Provide API access for:
- Course creation and editing
- Lesson generation
- Standards alignment
- Question bank generation
- Agent configuration for authoring

## Current Status

🚧 **Migration in progress**

Currently, factory APIs are served from `/api/admin/`.
This directory will eventually contain proxies or relocated endpoints.

## Boundaries (per ARCHITECTURE.md)

This API layer **MUST NOT**:
- Access individual student identities
- Read or modify student progress
- Authenticate students or observers
- Act as a learning environment

## Structure (Planned)

```
/course_factory/api/
├── auth/           # Factory admin authentication
├── course/         # Course CRUD operations
├── generation/     # LLM-based content generation
├── standards/      # Standards management
└── agents/         # Authoring agent configuration
```
