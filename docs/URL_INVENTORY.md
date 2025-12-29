# URL Inventory

**Generated:** December 28, 2025  
**Purpose:** Document all existing URLs for architecture migration  
**Source of Truth:** [ARCHITECTURE.md](ARCHITECTURE.md)

---

## Summary

| Category | Count | Target Subsystem |
|----------|-------|------------------|
| Course Factory UI | 13 | `/course_factory/` |
| Student Portal UI | 6 | `/student_portal/` |
| Shared/Test UI | 6 | Root (keep or remove) |
| Course Factory API | 56 | `/course_factory/api/` |
| Student Portal API | 3 | `/student_portal/api/` |
| Shared API | 16 | `/api/` (keep shared) |

---

## HTML Entry Points

### Course Factory UI (admin_*.html)

These files belong to the **Course Factory** subsystem.

| Current URL | Description | Target Location |
|-------------|-------------|-----------------|
| `/admin_login.html` | Factory authentication | `/course_factory/admin_login.html` |
| `/admin_dashboard.html` | Main factory dashboard | `/course_factory/admin_dashboard.html` |
| `/admin_courses.html` | Course listing | `/course_factory/admin_courses.html` |
| `/admin_course_wizard.html` | Course creation wizard | `/course_factory/admin_course_wizard.html` |
| `/admin_course_create_step1.html` | Course creation step 1 | `/course_factory/admin_course_create_step1.html` |
| `/admin_course_editor.html` | Course editor | `/course_factory/admin_course_editor.html` |
| `/admin_course_outline.html` | Outline editor | `/course_factory/admin_course_outline.html` |
| `/admin_question_generator.html` | Question generation | `/course_factory/admin_question_generator.html` |
| `/admin_agents.html` | Agent management | `/course_factory/admin_agents.html` |
| `/admin_agent_factory.html` | Agent factory | `/course_factory/admin_agent_factory.html` |
| `/admin_system_agents.html` | System agents config | `/course_factory/admin_system_agents.html` |
| `/admin_manage_users.html` | User management | `/course_factory/admin_manage_users.html` |
| `/admin_finetuning.html` | Model finetuning | `/course_factory/admin_finetuning.html` |

### Student Portal UI

These files belong to the **Student Portal** subsystem.

| Current URL | Description | Target Location |
|-------------|-------------|-----------------|
| `/index.html` | Landing page | `/student_portal/index.html` |
| `/login.html` | Student login | `/student_portal/login.html` |
| `/register.html` | Student registration | `/student_portal/register.html` |
| `/student_dashboard.html` | Student main dashboard | `/student_portal/student_dashboard.html` |
| `/workbook.html` | Learning workbook | `/student_portal/workbook.html` |
| `/course_viewer.html` | Course viewing | `/student_portal/course_viewer.html` |
| `/quiz.html` | Quiz/assessment | `/student_portal/quiz.html` |

### Shared/Test Files (Keep at Root or Remove)

| Current URL | Description | Disposition |
|-------------|-------------|-------------|
| `/system_status.php` | System health check | Keep at root (ops) |
| `/test_chat_flow.html` | Testing | Move to `/tests/` or remove |
| `/test_login_flow.html` | Testing | Move to `/tests/` or remove |
| `/test_db_setup.php` | Testing | Move to `/tests/` or remove |
| `/test_ensure_advisor.php` | Testing | Move to `/tests/` or remove |
| `/test_token.php` | Testing | Move to `/tests/` or remove |
| `/update_root_password.php` | One-time setup | Remove after use |

---

## API Endpoints

### Course Factory API (`/api/admin/`)

All endpoints in `/api/admin/` belong to **Course Factory**.  
Target: `/course_factory/api/`

#### Authentication & Authorization
| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/admin/auth_check.php` | - | Middleware (not directly called) |

#### Course Management
| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/admin/create_course.php` | POST | Create new course |
| `/api/admin/create_course_draft.php` | POST | Create course draft |
| `/api/admin/delete_course.php` | POST | Delete course |
| `/api/admin/list_courses.php` | GET | List all courses |
| `/api/admin/list_course_drafts.php` | GET | List course drafts |
| `/api/admin/list_published_courses.php` | GET | List published courses |
| `/api/admin/get_course_draft.php` | GET | Get draft details |
| `/api/admin/get_course_outline.php` | GET | Get course outline |
| `/api/admin/save_course_outline.php` | POST | Save course outline |
| `/api/admin/publish_course.php` | POST | Publish course |

#### Content Generation (LLM)
| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/admin/generate_course_outline.php` | POST | Generate outline via LLM |
| `/api/admin/generate_draft_outline.php` | POST | Generate draft outline |
| `/api/admin/generate_outline_from_skills.php` | POST | Generate from skills |
| `/api/admin/generate_lesson.php` | POST | Generate lesson |
| `/api/admin/generate_lesson_content.php` | POST | Generate lesson content |
| `/api/admin/generate_lesson_questions.php` | POST | Generate questions |
| `/api/admin/generate_unit.php` | POST | Generate unit |
| `/api/admin/generate_full_course.php` | POST | Generate entire course |
| `/api/admin/generate_assessment.php` | POST | Generate assessment |
| `/api/admin/generate_standards.php` | POST | Generate standards |
| `/api/admin/summarize_content.php` | POST | Summarize content |

#### Lesson Management
| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/admin/get_lesson_content.php` | GET | Get lesson content |
| `/api/admin/save_lesson.php` | POST | Save lesson |
| `/api/admin/save_lesson_questions.php` | POST | Save questions |
| `/api/admin/update_lesson_video.php` | POST | Update video |

#### Approval Workflow
| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/admin/approve_outline.php` | POST | Approve outline |
| `/api/admin/approve_lesson_content.php` | POST | Approve lesson |
| `/api/admin/approve_standards.php` | POST | Approve standards |
| `/api/admin/get_approved_standards.php` | GET | Get approved standards |
| `/api/admin/get_draft_outline.php` | GET | Get draft outline |
| `/api/admin/save_simplified_skills.php` | POST | Save skills |

#### Agent Configuration
| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/admin/create_agent.php` | POST | Create agent |
| `/api/admin/create_agent_instance.php` | POST | Create agent instance |
| `/api/admin/create_system_agent.php` | POST | Create system agent |
| `/api/admin/update_agent.php` | POST | Update agent |
| `/api/admin/update_system_agent.php` | POST | Update system agent |
| `/api/admin/delete_agent.php` | POST | Delete agent |
| `/api/admin/list_agents.php` | GET | List agents |
| `/api/admin/list_system_agents.php` | GET | List system agents |
| `/api/admin/get_agent_instance.php` | GET | Get agent instance |
| `/api/admin/chat_instance.php` | POST | Chat with agent |
| `/api/admin/course_agent.php` | POST | Course agent interaction |

#### User Management
| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/admin/create_user.php` | POST | Create user |
| `/api/admin/delete_user.php` | POST | Delete user |
| `/api/admin/list_users.php` | GET | List users |
| `/api/admin/toggle_user_status.php` | POST | Toggle user status |

#### Student Advisor Management
| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/admin/assign_student_advisor.php` | POST | Assign advisor |
| `/api/admin/list_student_advisors.php` | GET | List advisors |

#### Content & Export
| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/admin/get_content.php` | GET | Get content |
| `/api/admin/delete_content.php` | POST | Delete content |
| `/api/admin/embed_content.php` | POST | Generate embeddings |
| `/api/admin/review_content.php` | GET/POST | Review content |
| `/api/admin/export_training_data.php` | GET | Export for training |
| `/api/admin/list_exports.php` | GET | List exports |

#### Statistics & Activity
| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/admin/statistics.php` | GET | Dashboard stats |
| `/api/admin/activity.php` | GET | Recent activity |

#### Helper (Internal)
| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/admin/assessment_helpers.php` | - | Helper functions |

---

### Student Portal API (`/api/student/`)

All endpoints in `/api/student/` belong to **Student Portal**.  
Target: `/student_portal/api/student/`

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/student/ensure_advisor.php` | POST | Ensure student has advisor |
| `/api/student/get_advisor.php` | GET | Get student's advisor |
| `/api/student/update_advisor_data.php` | POST | Update advisor data |

---

### Student Portal API (`/api/progress/`)

All endpoints in `/api/progress/` belong to **Student Portal**.  
Target: `/student_portal/api/progress/`

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/progress/course.php` | GET | Course progress |
| `/api/progress/overview.php` | GET | Progress overview |
| `/api/progress/submit_quiz.php` | POST | Submit quiz answers |
| `/api/progress/update.php` | POST | Update progress |

---

### Shared API (`/api/auth/`)

Authentication endpoints - **remain shared** but may need subsystem-specific wrappers.

| Endpoint | Method | Description | Notes |
|----------|--------|-------------|-------|
| `/api/auth/login.php` | POST | User login | Used by both subsystems |
| `/api/auth/logout.php` | POST | User logout | Used by both subsystems |
| `/api/auth/register.php` | POST | User registration | Student Portal only |
| `/api/auth/validate.php` | GET | Validate token | Used by both subsystems |

---

### Shared API (`/api/agent/`)

Agent interaction - **shared infrastructure** (C++ agent service proxy).

| Endpoint | Method | Description | Notes |
|----------|--------|-------------|-------|
| `/api/agent/chat.php` | POST | Chat with agent | Both subsystems |
| `/api/agent/history.php` | GET | Chat history | Both subsystems |
| `/api/agent/list.php` | GET | List agents | Both subsystems |
| `/api/agent/retrieve_context.php` | POST | RAG context | Both subsystems |
| `/api/agent/set_active.php` | POST | Set active agent | Both subsystems |

---

### Shared/Ambiguous API (`/api/course/`)

Course access - **needs evaluation**. Read endpoints go to Student Portal, write endpoints to Course Factory.

| Endpoint | Method | Description | Target Subsystem |
|----------|--------|-------------|------------------|
| `/api/course/available.php` | GET | List available courses | Student Portal (read) |
| `/api/course/detail.php` | GET | Course details | Student Portal (read) |
| `/api/course/enrolled.php` | GET | Enrolled courses | Student Portal (read) |
| `/api/course/get_available_courses.php` | GET | Available courses | Student Portal (read) |
| `/api/course/get_course_draft.php` | GET | Course draft | ⚠️ Ambiguous - both? |
| `/api/course/get_lesson_content.php` | GET | Lesson content | Student Portal (read) |
| `/api/course/CourseMetadata.php` | - | Helper class | Shared |

---

### Helper Files (`/api/helpers/`)

Internal helpers - **shared infrastructure**.

| File | Description | Notes |
|------|-------------|-------|
| `/api/helpers/auth_headers.php` | Auth header utilities | Shared |
| `/api/helpers/embedding_generator.php` | Embedding generation | Shared |
| `/api/helpers/model_validation.php` | Model validation | Shared |
| `/api/helpers/system_agent_helper.php` | System agent helpers | Course Factory |

---

## Migration Notes

### Phase 2: Course Factory Migration
1. Copy `admin_*.html` to `/course_factory/`
2. Create proxy files in `/course_factory/api/` pointing to `/api/admin/`
3. Update internal links in copied files

### Phase 3: Student Portal Migration
1. Copy student-facing HTML to `/student_portal/`
2. Create proxy files in `/student_portal/api/` pointing to `/api/student/`, `/api/progress/`
3. Update internal links in copied files

### Shared Infrastructure
- `/api/auth/` - Keep shared, wrap in subsystem auth layers
- `/api/agent/` - Keep shared (C++ service proxy)
- `/api/helpers/` - Keep shared
- `/api/course/` - May need splitting; evaluate read vs write

### Test Files
Consider moving all `test_*.php` and `test_*.html` files to `/tests/` directory for cleaner root.
