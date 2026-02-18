<?php
ob_start();

// Load security headers early to harden HTTP responses
require_once __DIR__ . '/../includes/security_headers.php';

// Manually parse .env file
$envFile = __DIR__ . '/../.env';
$env = [];

if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        // Skip comments (lines starting with #)
        if (preg_match('/^\s*#/', $line)) {
            continue;
        }
        
        // Parse key=value pairs with robust regex
        // Pattern matches: KEY = VALUE or KEY=VALUE
        // Where VALUE can be:
        // - Quoted with double quotes: "value with # and spaces"
        // - Quoted with single quotes: 'value with # and spaces'
        // - Unquoted: value (comments after # are removed)
        // Note: Escaped quotes within quoted strings are not supported (e.g., "value with \"quote\"")
        if (preg_match('/^\s*([A-Z_][A-Z0-9_]*)\s*=\s*(.*)$/i', $line, $matches)) {
            $key = $matches[1];
            $value = $matches[2];
            
            // Handle quoted values (both single and double quotes)
            if (preg_match('/^"(.*)"(\s*#.*)?$/', $value, $valueMatches)) {
                // Double-quoted value - preserve everything inside quotes
                $value = $valueMatches[1];
            } elseif (preg_match("/^'(.*)'(\s*#.*)?$/", $value, $valueMatches)) {
                // Single-quoted value - preserve everything inside quotes
                $value = $valueMatches[1];
            } else {
                // Unquoted value - remove inline comments
                // Remove everything after # (and any preceding whitespace)
                $value = preg_replace('/\s*#.*$/', '', $value);
                $value = trim($value);
            }
            
            $env[$key] = $value;
        }
    }
}

// User Database (Authentication, Logins, Passwords, Alumni Profiles)
define('DB_USER_HOST', $env['DB_USER_HOST'] ?? 'localhost');
define('DB_USER_NAME', $env['DB_USER_NAME'] ?? '');
define('DB_USER_USER', $env['DB_USER_USER'] ?? '');
define('DB_USER_PASS', $env['DB_USER_PASS'] ?? '');

// Content Database (Projects, Inventory, Events, News, System Logs)
define('DB_CONTENT_HOST', $env['DB_CONTENT_HOST'] ?? 'localhost');
define('DB_CONTENT_NAME', $env['DB_CONTENT_NAME'] ?? '');
define('DB_CONTENT_USER', $env['DB_CONTENT_USER'] ?? '');
define('DB_CONTENT_PASS', $env['DB_CONTENT_PASS'] ?? '');

// Invoice Database (Dedicated database for invoices)
define('DB_RECH_HOST', $env['DB_RECH_HOST'] ?? 'localhost');
define('DB_RECH_PORT', $env['DB_RECH_PORT'] ?? 3306);
define('DB_RECH_NAME', $env['DB_RECH_NAME'] ?? '');
define('DB_RECH_USER', $env['DB_RECH_USER'] ?? '');
define('DB_RECH_PASS', $env['DB_RECH_PASS'] ?? '');

// Generic Database Constants (from .env if available, defaults to user DB)
define('DB_HOST', $env['DB_HOST'] ?? $env['DB_USER_HOST'] ?? 'localhost');
define('DB_NAME', $env['DB_NAME'] ?? $env['DB_USER_NAME'] ?? '');
define('DB_USER', $env['DB_USER'] ?? $env['DB_USER_USER'] ?? '');
define('DB_PASS', $env['DB_PASS'] ?? $env['DB_USER_PASS'] ?? '');

// SMTP Configuration
define('SMTP_HOST', $env['SMTP_HOST'] ?? 'localhost');
define('SMTP_PORT', $env['SMTP_PORT'] ?? 587);
define('SMTP_USER', $env['SMTP_USER'] ?? '');
define('SMTP_PASS', $env['SMTP_PASS'] ?? '');
define('SMTP_FROM', $env['SMTP_FROM'] ?? $env['SMTP_USER'] ?? '');
define('SMTP_FROM_EMAIL', $env['SMTP_FROM_EMAIL'] ?? $env['SMTP_FROM'] ?? $env['SMTP_USER'] ?? '');
define('SMTP_FROM_NAME', $env['SMTP_FROM_NAME'] ?? 'IBC Intranet');

// Invoice notification email
define('INVOICE_NOTIFICATION_EMAIL', $env['INVOICE_NOTIFICATION_EMAIL'] ?? 'tlehmann630@gmail.com');

// EasyVerein API Configuration
define('EASYVEREIN_API_TOKEN', $env['EASYVEREIN_API_TOKEN'] ?? '');

// Azure/Microsoft Entra ID OAuth Configuration
define('AZURE_CLIENT_ID', $env['AZURE_CLIENT_ID'] ?? '');
define('AZURE_CLIENT_SECRET', $env['AZURE_CLIENT_SECRET'] ?? '');
define('AZURE_REDIRECT_URI', $env['AZURE_REDIRECT_URI'] ?? '');
define('AZURE_TENANT_ID', $env['AZURE_TENANT_ID'] ?? '');

/**
 * Sanitize HTTP_HOST to prevent injection attacks
 * Only allows alphanumeric characters, dots, colons, and hyphens
 * 
 * @param string $host The host string to sanitize
 * @return string|null Sanitized host or null if invalid
 */
function sanitize_http_host($host) {
    if (empty($host)) {
        return null;
    }
    
    // Only allow alphanumeric, dots, colons, and hyphens (for valid hostnames and ports)
    // This prevents XSS and injection via malicious headers
    if (!preg_match('/^[a-zA-Z0-9.\-:]+$/', $host)) {
        return null;
    }
    
    // Additional validation: Prevent consecutive dots
    if (strpos($host, '..') !== false) {
        return null;
    }
    
    // Prevent dots at start or end (invalid hostname format)
    if (strlen($host) > 0 && ($host[0] === '.' || $host[strlen($host) - 1] === '.')) {
        return null;
    }
    
    // If there's a port, validate the port is at the end and numeric
    if (strpos($host, ':') !== false) {
        $parts = explode(':', $host);
        // Should only have one colon (host:port)
        // Note: IPv6 addresses in brackets are not supported
        if (count($parts) !== 2) {
            return null;
        }
        // Port should be numeric and in valid range (1-65535)
        if (!ctype_digit($parts[1])) {
            return null;
        }
        $port = (int)$parts[1];
        if ($port < 1 || $port > 65535) {
            return null;
        }
    }
    
    return $host;
}

// Define BASE_URL with security considerations
if (isset($env['BASE_URL'])) {
    define('BASE_URL', $env['BASE_URL']);
} else {
    $environment = $env['ENVIRONMENT'] ?? 'development';
    
    if ($environment === 'production') {
        // SECURITY: In production, BASE_URL MUST be defined in .env
        // Never fall back to HTTP_HOST to prevent Host Header Injection attacks
        throw new RuntimeException('BASE_URL must be defined in .env for production environment');
    } else {
        // Development environment: Allow fallback but sanitize HTTP_HOST
        $protocol = (isset($_SERVER['HTTPS']) ? 'https' : 'http');
        $host = sanitize_http_host($_SERVER['HTTP_HOST'] ?? '');
        
        if ($host === null) {
            // If HTTP_HOST is invalid or missing, use localhost as safe fallback
            $host = 'localhost';
        }
        
        define('BASE_URL', $protocol . '://' . $host . '/intra');
    }
}

// Application Settings
define('APP_NAME', 'IBC Intranet');
define('ENVIRONMENT', $env['ENVIRONMENT'] ?? 'development');
define('SESSION_LIFETIME', 3600);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900);
define('UPLOAD_MAX_SIZE', 5242880);
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);

// Security
define('HASH_ALGO', PASSWORD_ARGON2ID);
define('SESSION_NAME', 'IBC_SESSION');

// Rate Limiting - Exponential Backoff Configuration
// Progressive lockout durations (in seconds) for failed login attempts
define('RATE_LIMIT_BACKOFF', [
    3 => 60,      // 1 minute after 3 failed attempts
    4 => 120,     // 2 minutes after 4 failed attempts
    5 => 300,     // 5 minutes after 5 failed attempts
    6 => 900,     // 15 minutes after 6 failed attempts
    7 => 1800,    // 30 minutes after 7 failed attempts
]);
// Maximum backoff duration for 8+ failed attempts
// Note: Different behaviors exist for security policy flexibility:
//       - AuthHandler.php: Uses temporary 60-minute lock (allows recovery)
//       - src/Auth.php: Permanently locks accounts (requires admin intervention)
//       This allows different security policies for different authentication flows.
define('RATE_LIMIT_MAX_BACKOFF', 3600);  // 60 minutes

// Set error display for production (0) or debugging (1)
$isProduction = ($env['ENVIRONMENT'] ?? '') === 'production';
ini_set('display_errors', $isProduction ? '0' : '1');
error_reporting($isProduction ? 0 : E_ALL);

// Set timezone
date_default_timezone_set('Europe/Berlin');

/**
 * Initialize session with secure parameters
 * This function MUST be called before any session access to ensure
 * all sessions are created with Secure, HttpOnly and SameSite=Strict flags
 */
function init_session() {
    if (session_status() === PHP_SESSION_NONE) {
        // Get session lifetime from config, default to 3600 seconds (1 hour)
        $lifetime = defined('SESSION_LIFETIME') ? SESSION_LIFETIME : 3600;
        
        // Get domain from BASE_URL
        // Leave domain empty for single-domain deployments (most secure and reliable)
        // Setting explicit domain can cause cookie issues with OAuth redirects
        $domain = '';
        
        // Set secure cookie parameters BEFORE starting session
        // Only require secure flag over HTTPS to prevent session issues over HTTP
        // Check for HTTPS considering proxy/load balancer scenarios
        // Note: X-Forwarded-* headers are checked assuming trusted infrastructure (ionos.de hosting)
        // In environments with untrusted proxies, consider validating proxy IPs before trusting these headers
        $isSecure = false;
        if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') {
            $isSecure = true;
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
            $isSecure = true;
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) === 'on') {
            $isSecure = true;
        }
        
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path' => '/',
            'domain' => $domain,
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        
        // Set session name if defined
        if (defined('SESSION_NAME')) {
            session_name(SESSION_NAME);
        }
        
        // Start the session with secure parameters
        session_start();
    }
}

// Role mapping from Azure groups/roles to internal role names
// This mapping is used for Microsoft Entra ID authentication
// Supports both App Roles (from JWT token) and Group display names (from Microsoft Graph API)
define('ROLE_MAPPING', [
    // Lowercase versions (for App Roles)
    'anwaerter' => 'candidate',
    'mitglied' => 'member',
    'ressortleiter' => 'head',
    'vorstand_finanzen' => 'board_finance',
    'vorstand_intern' => 'board_internal',
    'vorstand_extern' => 'board_external',
    'vorstand' => 'board_internal',
    'alumni' => 'alumni',
    'alumni_vorstand' => 'alumni_board',
    'alumni_finanz' => 'alumni_auditor',
    'ehrenmitglied' => 'honorary_member',
    // Capitalized versions with underscore (for Group display names)
    'Anwaerter' => 'candidate',
    'Mitglied' => 'member',
    'Ressortleiter' => 'head',
    'Vorstand_Finanzen' => 'board_finance',
    'Vorstand_Intern' => 'board_internal',
    'Vorstand_Extern' => 'board_external',
    'Vorstand' => 'board_internal',
    'Alumni' => 'alumni',
    'Alumni_Vorstand' => 'alumni_board',
    'Alumni_Finanz' => 'alumni_auditor',
    'Ehrenmitglied' => 'honorary_member',
    // Capitalized versions with space (alternative Group display names)
    'Vorstand Finanzen' => 'board_finance',
    'Vorstand Intern' => 'board_internal',
    'Vorstand Extern' => 'board_external',
    'Alumni Vorstand' => 'alumni_board',
    'Alumni Finanz' => 'alumni_auditor'
]);

// Load helper functions globally
require_once __DIR__ . '/../includes/helpers.php';