<?php
/**
 * Helper Functions
 */

/**
 * Get base URL path
 */
function getBasePath() {
    return rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
}

/**
 * Generate URL relative to document root using BASE_URL
 * Uses BASE_URL constant for robust URL generation regardless of subdirectory depth
 */
function url($path) {
    // Remove trailing slashes from BASE_URL
    $baseUrl = rtrim(BASE_URL, '/');
    
    // Remove leading slashes from path
    $path = ltrim($path, '/');
    
    // Combine with exactly one slash
    return $baseUrl . '/' . $path;
}

/**
 * Redirect helper
 */
function redirect($path, $absolute = false) {
    if ($absolute) {
        header('Location: ' . $path);
    } else {
        header('Location: ' . url($path));
    }
    exit;
}

/**
 * Format currency
 */
function formatCurrency($amount) {
    return number_format($amount, 2, ',', '.') . ' €';
}

/**
 * Format date
 */
function formatDate($date, $format = 'd.m.Y') {
    if (empty($date)) return '-';
    return date($format, is_numeric($date) ? $date : strtotime($date));
}

/**
 * Format datetime
 */
function formatDateTime($date, $format = 'd.m.Y H:i') {
    if (empty($date)) return '-';
    return date($format, is_numeric($date) ? $date : strtotime($date));
}

/**
 * Format name from Entra ID (e.g., "tom.lehmann" -> "Tom Lehmann")
 * Replaces dots with spaces and capitalizes first letters of each word
 * 
 * Note: This function is idempotent and safe to apply to any name for display purposes.
 * It's designed for Entra ID names that may use lowercase with dots (e.g., "tom.lehmann"),
 * but can be safely applied to names already in proper format.
 * 
 * Limitation: Special name patterns like "McDonald" will become "Mcdonald" and 
 * "O'Brien" will become "O'brien". This is acceptable for Entra ID names which 
 * typically use simple lowercase format.
 * 
 * @param string $name The name to format
 * @return string The formatted name
 */
function formatEntraName($name) {
    if (empty($name)) {
        return '';
    }
    
    // Replace dots with spaces
    $name = str_replace('.', ' ', $name);
    
    // Convert to lowercase first, then capitalize first letter of each word
    $name = mb_strtolower($name, 'UTF-8');
    return ucwords($name);
}

/**
 * Escape HTML
 */
function e($text) {
    return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Check if current page is active
 */
function isActive($page) {
    return strpos($_SERVER['REQUEST_URI'], $page) !== false ? 'active' : '';
}

/**
 * Generate asset URL with BASE_URL
 * Ensures exactly one slash between BASE_URL and path
 */
function asset_url($path) {
    // Remove trailing slashes from BASE_URL
    $baseUrl = rtrim(BASE_URL, '/');
    
    // Remove leading slashes from path
    $path = ltrim($path, '/');
    
    // Combine with exactly one slash
    return $baseUrl . '/' . $path;
}

/**
 * Generate asset path with BASE_URL
 * Ensures no double slash by using rtrim on BASE_URL
 * This is an alias for asset_url() for convenience
 */
function asset($path) {
    return asset_url($path);
}

/**
 * Translate role from English to German
 * All board sub-roles (vorstand_*) are displayed as 'Vorstand'
 * 
 * @param string $role Role identifier
 * @return string German translation of the role
 */
function translateRole($role) {
    $roleTranslations = [
        'admin' => 'Administrator',
        'board_finance' => 'Vorstand Finanzen & Recht',
        'board_internal' => 'Vorstand Intern',
        'board_external' => 'Vorstand Extern',
        'head' => 'Ressortleiter',
        'member' => 'Mitglied',
        'alumni' => 'Alumni',
        'candidate' => 'Anwärter',
        'alumni_board' => 'Alumni-Vorstand',
        'alumni_auditor' => 'Alumni-Finanzprüfer',
        'honorary_member' => 'Ehrenmitglied',
        'manager' => 'Ressortleiter'
    ];
    
    return $roleTranslations[$role] ?? ucfirst($role);
}

/**
 * Translate Azure/Entra ID role to German display name
 * Maps the original Azure role names to their German equivalents
 * 
 * @param string $azureRole Azure role identifier (e.g., 'anwaerter', 'mitglied')
 * @return string German display name
 */
function translateAzureRole($azureRole) {
    $azureRoleTranslations = [
        'anwaerter' => 'Anwärter',
        'mitglied' => 'Mitglied',
        'ressortleiter' => 'Ressortleiter',
        'vorstand_finanzen' => 'Vorstand Finanzen',
        'vorstand_intern' => 'Vorstand Intern',
        'vorstand_extern' => 'Vorstand Extern',
        'alumni' => 'Alumni',
        'alumni_vorstand' => 'Alumni-Vorstand',
        'alumni_finanz' => 'Alumni-Finanzprüfer',
        'ehrenmitglied' => 'Ehrenmitglied'
    ];
    
    // If role not found in mapping, log it for manual addition and return formatted version
    if (!isset($azureRoleTranslations[$azureRole])) {
        error_log("Unknown Azure role encountered: '$azureRole'. Consider adding translation to translateAzureRole()");
        return ucfirst(str_replace('_', ' ', $azureRole));
    }
    
    return $azureRoleTranslations[$azureRole];
}

/**
 * Check if role is an active member role
 * Active member roles: candidate, member, head, board (and board variants)
 * 
 * Note: This matches Auth::BOARD_ROLES plus candidate, member, head.
 * Keep this in sync with Member::ACTIVE_ROLES constant.
 * 
 * @param string $role Role identifier
 * @return bool True if role is an active member role
 */
function isMemberRole($role) {
    // Active roles: board roles + candidate, member, head
    // Matches Member::ACTIVE_ROLES constant
    return in_array($role, ['candidate', 'member', 'head', 'board_finance', 'board_internal', 'board_external']);
}

/**
 * Check if role is an alumni role
 * Alumni roles: alumni, alumni_board, honorary_member
 * 
 * @param string $role Role identifier
 * @return bool True if role is an alumni role
 */
function isAlumniRole($role) {
    return in_array($role, ['alumni', 'alumni_board', 'honorary_member']);
}

