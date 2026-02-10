<?php
/**
 * Student Portal Auth API Proxy - register.php
 * Forwards to /api/auth/register.php
 */
require_once __DIR__ . '/../../../api/helpers/security_headers.php';
set_api_security_headers();
require_once __DIR__ . '/../../../api/auth/register.php';
