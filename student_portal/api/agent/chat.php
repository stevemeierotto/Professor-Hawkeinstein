<?php
/**
 * Student Portal Agent API Proxy - chat.php
 * Forwards to /api/agent/chat.php
 */
require_once __DIR__ . '/../../../api/helpers/security_headers.php';
set_api_security_headers();
require_once __DIR__ . '/../../../api/agent/chat.php';
