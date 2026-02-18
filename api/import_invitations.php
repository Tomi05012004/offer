<?php
/**
 * API: Import Invitations from JSON
 * Bulk import invitations from uploaded JSON file
 * Required permissions: board
 */

require_once __DIR__ . '/../includes/handlers/AuthHandler.php';
require_once __DIR__ . '/../includes/handlers/CSRFHandler.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../src/MailService.php';
require_once __DIR__ . '/../src/Auth.php';

AuthHandler::startSession();

// Set JSON response header
header('Content-Type: application/json');

// Check authentication and permission
if (!AuthHandler::isAuthenticated() || !AuthHandler::hasPermission('board')) {
    echo json_encode([
        'success' => false,
        'message' => 'Nicht autorisiert. Nur Vorstände können Einladungen importieren.'
    ]);
    exit;
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Ungültige Anfrage'
    ]);
    exit;
}

// Verify CSRF token
CSRFHandler::verifyToken($_POST['csrf_token'] ?? '');

// Check if file was uploaded
if (!isset($_FILES['json_file']) || $_FILES['json_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode([
        'success' => false,
        'message' => 'Keine JSON-Datei hochgeladen oder Fehler beim Upload'
    ]);
    exit;
}

// Validate file extension (don't trust client-provided MIME type)
$fileExtension = strtolower(pathinfo($_FILES['json_file']['name'], PATHINFO_EXTENSION));

if ($fileExtension !== 'json') {
    echo json_encode([
        'success' => false,
        'message' => 'Ungültiger Dateityp. Bitte laden Sie eine JSON-Datei hoch.'
    ]);
    exit;
}

// Read and parse JSON file
$jsonContent = file_get_contents($_FILES['json_file']['tmp_name']);
$invitations = json_decode($jsonContent, true);

// Check for JSON parsing errors
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode([
        'success' => false,
        'message' => 'Fehler beim Parsen der JSON-Datei: ' . json_last_error_msg()
    ]);
    exit;
}

// Validate that invitations is an array
if (!is_array($invitations)) {
    echo json_encode([
        'success' => false,
        'message' => 'Die JSON-Datei muss ein Array von Einladungen enthalten'
    ]);
    exit;
}

// Set time limit to 0 for large imports
set_time_limit(0);

// Get database connection
$db = Database::getUserDB();

// Track results
$successCount = 0;
$failedCount = 0;
$errors = [];

// Get current user ID for created_by field
$createdBy = $_SESSION['user_id'];

// Build protocol and host for invitation links
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];

// Process each invitation
foreach ($invitations as $index => $invitation) {
    // Validate required fields
    if (!isset($invitation['email']) || !isset($invitation['role'])) {
        $failedCount++;
        $errors[] = "Zeile " . ($index + 1) . ": Fehlende Felder (email oder role erforderlich)";
        continue;
    }
    
    $email = trim($invitation['email']);
    $role = $invitation['role'];
    
    // Validate email
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $failedCount++;
        $errors[] = "Zeile " . ($index + 1) . ": Ungültige E-Mail-Adresse: " . htmlspecialchars($email);
        continue;
    }
    
    // Validate role - use Auth::VALID_ROLES as single source of truth
    if (!in_array($role, Auth::VALID_ROLES)) {
        $failedCount++;
        $errors[] = "Zeile " . ($index + 1) . ": Ungültige Rolle: " . htmlspecialchars($role);
        continue;
    }
    
    // Check if user already exists
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $failedCount++;
        $errors[] = "Zeile " . ($index + 1) . ": Benutzer existiert bereits: " . htmlspecialchars($email);
        continue;
    }
    
    // Check if there's already an open invitation for this email
    $stmt = $db->prepare("SELECT id FROM invitation_tokens WHERE email = ? AND used_at IS NULL AND expires_at > NOW()");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $failedCount++;
        $errors[] = "Zeile " . ($index + 1) . ": Offene Einladung existiert bereits: " . htmlspecialchars($email);
        continue;
    }
    
    try {
        // Generate invitation token
        $token = AuthHandler::generateInvitationToken($email, $role, $createdBy);
        
        // Build invitation link
        $invitationLink = $protocol . '://' . $host . '/pages/auth/register.php?token=' . $token;
        
        // Send invitation email
        $mailSent = MailService::sendInvitation($email, $token, $role);
        
        // Count as success if token was created (even if email failed)
        $successCount++;
        
    } catch (Exception $e) {
        $failedCount++;
        $errors[] = "Zeile " . ($index + 1) . ": Fehler beim Erstellen der Einladung: " . htmlspecialchars($e->getMessage());
    }
}

// Build success message
$message = "$successCount Einladungen erfolgreich erstellt";
if ($failedCount > 0) {
    $message .= ", $failedCount fehlgeschlagen";
}

// Return results
echo json_encode([
    'success' => true,
    'message' => $message,
    'total' => count($invitations),
    'success_count' => $successCount,
    'failed_count' => $failedCount,
    'errors' => $errors
]);
