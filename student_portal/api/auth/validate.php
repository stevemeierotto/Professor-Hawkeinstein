<?php
/**
 * Student Portal Auth API Proxy - validate.php
 * Forwards to /api/auth/validate.php
 */
require_once __DIR__ . '/../../../api/helpers/security_headers.php';
set_api_security_headers();
require_once __DIR__ . '/../../../api/auth/validate.php';
