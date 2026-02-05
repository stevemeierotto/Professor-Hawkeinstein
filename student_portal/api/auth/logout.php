<?php
/**
 * Student Portal Auth API Proxy - logout.php
 * Forwards to /api/auth/logout.php
 */
require_once __DIR__ . '/../../../api/helpers/security_headers.php';
set_api_security_headers();
require_once __DIR__ . '/../../../api/auth/logout.php';
