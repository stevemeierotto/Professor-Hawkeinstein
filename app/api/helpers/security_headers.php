<?php
// Centralized CORS and Security Headers for all API endpoints
// Phase 6: CORS & Security Headers

function set_security_headers() {
    // X-Content-Type-Options
    header('X-Content-Type-Options: nosniff');
    // X-Frame-Options
    header('X-Frame-Options: DENY');
    // Referrer-Policy
    header('Referrer-Policy: no-referrer');
    // Permissions-Policy (disable camera, microphone, geolocation, etc.)
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    // Content Security Policy (CSP) - baseline, restricts scripts/styles/connect
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; connect-src 'self'; object-src 'none'; frame-ancestors 'none';");
    // HSTS (Strict-Transport-Security) - only in production
    $env = getenv('ENV') ?: (getenv('APP_ENV') ?: 'production');
    if ($env === 'production' && (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')) {
        header('Strict-Transport-Security: max-age=63072000; includeSubDomains; preload');
    }
}

function set_cors_headers() {
    // Centralized CORS logic
    $env = getenv('ENV') ?: (getenv('APP_ENV') ?: 'production');
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowedOrigins = [
        // Production domains (edit as needed)
        'https://professorhawkeinstein.com',
        'https://www.professorhawkeinstein.com',
    ];
    if ($env !== 'production') {
        // Allow localhost for dev
        $allowedOrigins[] = 'http://localhost:3000';
        $allowedOrigins[] = 'http://localhost:8080';
        $allowedOrigins[] = 'http://localhost';
        $allowedOrigins[] = 'http://127.0.0.1';
    }
    if (in_array($origin, $allowedOrigins, true)) {
        header("Access-Control-Allow-Origin: $origin");
        header('Vary: Origin');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Authorization, Content-Type');
        // Only allow credentials if absolutely required (not by default)
        // header('Access-Control-Allow-Credentials: true');
    }
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

// Call both in all API entrypoints
function set_api_security_headers() {
    set_security_headers();
    set_cors_headers();
}
