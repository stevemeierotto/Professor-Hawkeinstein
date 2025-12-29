<?php
/**
 * Course Factory Auth Proxy - Login
 * 
 * Forwards login requests to the shared auth API.
 * This allows the Course Factory to work from /course_factory/ path.
 * 
 * See: docs/ARCHITECTURE.md
 */

// Forward to the actual auth login
require_once __DIR__ . '/../../../api/auth/login.php';
