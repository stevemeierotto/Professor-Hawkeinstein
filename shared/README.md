# Shared Infrastructure

This directory contains utilities shared by both subsystems:
- **Student Portal** (`/student_portal/`)
- **Course Factory** (`/course_factory/`)

## Directory Structure

```
/shared/
├── auth/
│   ├── jwt.php          # JWT token generation and verification
│   └── middleware.php   # Authentication middleware functions
├── db/
│   └── connection.php   # Database connection wrapper
└── README.md            # This file
```

## Usage

### Authentication (shared/auth/)

```php
// In subsystem code:
require_once __DIR__ . '/../../shared/auth/jwt.php';
require_once __DIR__ . '/../../shared/auth/middleware.php';

// Generate a token
$token = jwt_generate($userId, $username, $role);

// Verify a token
$userData = jwt_verify($token);

// Require authentication (sends 401 if invalid)
$userData = require_valid_token();

// Check role (sends 403 if not allowed)
require_role($userData, ['admin', 'root']);
```

### Database (shared/db/)

```php
require_once __DIR__ . '/../../shared/db/connection.php';

// Get PDO connection
$pdo = db_connect();

// Query helpers
$rows = db_query("SELECT * FROM users WHERE role = ?", ['student']);
$user = db_query_one("SELECT * FROM users WHERE id = ?", [$id]);

// Execute statements
$affected = db_execute("UPDATE users SET active = ? WHERE id = ?", [1, $id]);
$newId = db_insert("INSERT INTO users (name) VALUES (?)", ['John']);

// Transactions
db_begin_transaction();
try {
    db_execute(...);
    db_commit();
} catch (Exception $e) {
    db_rollback();
}
```

## Constraints (per ARCHITECTURE.md)

This directory **MUST NOT** contain:
- UI components or HTML
- Subsystem-specific business logic
- Role-based authorization beyond basic checks
- Direct table queries (use subsystem data layers)

## Relationship to Existing Code

The shared utilities **wrap** existing functionality:
- `shared/auth/jwt.php` wraps `config/database.php` JWT functions
- `shared/db/connection.php` wraps `config/database.php` getDB()

Original files in `/config/` and `/api/helpers/` remain unchanged for backward compatibility.
Subsystems should gradually migrate to using shared utilities.
