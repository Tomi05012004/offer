<?php
/**
 * Microsoft Entra ID OAuth Callback Handler
 * This file handles the redirect callback from Azure AD after user authentication.
 */

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables and configuration
require_once __DIR__ . '/../config/config.php';

// Load AuthHandler
require_once __DIR__ . '/../includes/handlers/AuthHandler.php';

// Start session
AuthHandler::startSession();

// Handle the Microsoft callback
try {
    AuthHandler::handleMicrosoftCallback();
} catch (Exception $e) {
    // Log the full error details server-side
    error_log("Microsoft callback error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Redirect to login page with a generic error message
    $loginUrl = (defined('BASE_URL') && BASE_URL) ? BASE_URL . '/pages/auth/login.php' : '/pages/auth/login.php';
    $errorMessage = urlencode('Authentifizierung fehlgeschlagen. Bitte versuchen Sie es erneut.');
    header('Location: ' . $loginUrl . '?error=' . $errorMessage);
    exit;
}
