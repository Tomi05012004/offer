<?php
/**
 * Microsoft Entra ID OAuth Callback Handler
 * This file handles the redirect callback from Azure AD after user authentication.
 */

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables and configuration
require_once __DIR__ . '/../config/config.php';

// Load AuthHandler and Database
require_once __DIR__ . '/../includes/handlers/AuthHandler.php';
require_once __DIR__ . '/../includes/database.php';

// Start session
AuthHandler::startSession();

// Capture diagnostic information before the try-block so it is available in the catch
$stateGet     = $_GET['state'] ?? null;
$stateSession = $_SESSION['oauth2state'] ?? null;
$stateMatch   = ($stateGet !== null && $stateSession !== null && $stateGet === $stateSession);
$tokenError   = null;
$azureOid     = null;
$userEmail    = null;

// Handle the Microsoft callback
try {
    // Validate state for CSRF protection
    if (!isset($_GET['state']) || !isset($_SESSION['oauth2state']) || $_GET['state'] !== $_SESSION['oauth2state']) {
        $stateMsg = sprintf(
            "[OAuth] State validation failed. GET state: %s | SESSION state: %s | Match: %s",
            $stateGet    ?? 'not set',
            $stateSession ?? 'not set',
            $stateMatch ? 'yes' : 'no'
        );
        error_log($stateMsg);
        unset($_SESSION['oauth2state']);
        throw new Exception('Invalid state parameter');
    }
    unset($_SESSION['oauth2state']);

    // Check for OAuth errors returned by Azure
    if (isset($_GET['error'])) {
        throw new Exception('OAuth error: ' . ($_GET['error_description'] ?? $_GET['error']));
    }

    // Check for authorization code
    if (!isset($_GET['code'])) {
        throw new Exception('No authorization code received');
    }

    // Initialize GenericProvider with Azure endpoints using config constants
    $clientId     = defined('CLIENT_ID') ? CLIENT_ID : '';
    $clientSecret = defined('CLIENT_SECRET') ? CLIENT_SECRET : '';
    $redirectUri  = defined('REDIRECT_URI') ? REDIRECT_URI : '';
    $tenantId     = defined('TENANT_ID') ? TENANT_ID : '';

    if (empty($clientId) || empty($clientSecret) || empty($redirectUri) || empty($tenantId)) {
        throw new Exception('Missing Azure OAuth configuration');
    }

    $provider = new \League\OAuth2\Client\Provider\GenericProvider([
        'clientId'                => $clientId,
        'clientSecret'            => $clientSecret,
        'redirectUri'             => $redirectUri,
        'urlAuthorize'            => 'https://login.microsoftonline.com/' . $tenantId . '/oauth2/v2.0/authorize',
        'urlAccessToken'          => 'https://login.microsoftonline.com/' . $tenantId . '/oauth2/v2.0/token',
        'urlResourceOwnerDetails' => 'https://graph.microsoft.com/v1.0/me',
    ]);

    // Exchange authorization code for access token
    try {
        $accessToken = $provider->getAccessToken('authorization_code', [
            'code' => $_GET['code'],
        ]);
    } catch (Exception $tokenEx) {
        $tokenError = $tokenEx->getMessage();
        error_log("[OAuth] getAccessToken() failed: " . $tokenError);
        throw $tokenEx;
    }

    // Get resource owner (user) details and claims
    $resourceOwner = $provider->getResourceOwner($accessToken);
    $claims = $resourceOwner->toArray();

    // Extract azure_oid from the oid or sub claim
    $azureOid  = $claims['oid'] ?? $claims['sub'] ?? null;
    $userEmail = $claims['mail'] ?? $claims['userPrincipalName'] ?? $claims['email'] ?? null;
    error_log(sprintf("[OAuth] Claims received. azure_oid: %s | email: %s", $azureOid ?? 'null', $userEmail ?? 'null'));

    // Look up user in local database by azure_oid
    $db = Database::getUserDB();
    $existingUser = null;
    if ($azureOid) {
        $stmt = $db->prepare("SELECT * FROM users WHERE azure_oid = ?");
        $stmt->execute([$azureOid]);
        $existingUser = $stmt->fetch() ?: null;
    }

    // Complete the login process (role mapping, user create/update, session setup)
    AuthHandler::completeMicrosoftLogin($claims, $existingUser);

} catch (Exception $e) {
    // Log full diagnostic details server-side (visible in IONOS server logs)
    error_log(sprintf(
        "[OAuth Callback] Authentifizierung fehlgeschlagen: Fehler: %s | State (GET) gesetzt: %s | State (SESSION) gesetzt: %s | States identisch: %s | Token-Fehler: %s | azure_oid: %s | E-Mail: %s",
        $e->getMessage(),
        $stateGet     !== null ? 'ja' : 'nein',
        $stateSession !== null ? 'ja' : 'nein',
        $stateMatch ? 'ja' : 'nein',
        $tokenError   ?? 'keiner',
        $azureOid     ?? 'nicht verfügbar',
        $userEmail    ?? 'nicht verfügbar'
    ));
    error_log("[OAuth Callback] Stack Trace: " . $e->getTraceAsString());

    // Redirect to login page with a generic user-facing message (details are in server logs)
    $loginUrl = (defined('BASE_URL') && BASE_URL) ? BASE_URL . '/pages/auth/login.php' : '/pages/auth/login.php';
    $errorMessage = urlencode('Authentifizierung fehlgeschlagen. Bitte versuchen Sie es erneut.');
    header('Location: ' . $loginUrl . '?error=' . $errorMessage);
    exit;
}
