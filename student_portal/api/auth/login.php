<?php
/**
 * Student Portal Auth API Proxy - login.php
 * Forwards to /api/auth/login.php
 */
	require_once __DIR__ . '/../../../api/helpers/security_headers.php';
	set_api_security_headers();
	require_once __DIR__ . '/../../../api/auth/login.php';
