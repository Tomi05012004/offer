<?php
/**
 * API: Send Invitation
 * Generates invitation link for new users
 * Required permissions: admin, board, or alumni_board
 */

// Set JSON response header
header('Content-Type: application/json');

// Disable error output in body
ini_set('display_errors', 0);

require_once __DIR__ . '/../includes/handlers/AuthHandler.php';
require_once __DIR__ . '/../includes/handlers/CSRFHandler.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../src/MailService.php';
require_once __DIR__ . '/../src/Auth.php';

try {
    AuthHandler::startSession();

// Check authentication and permission
if (!AuthHandler::isAuthenticated() || !AuthHandler::hasPermission('board')) {
    echo json_encode([
        'success' => false,
        'message' => 'Nicht autorisiert. Nur Vorstände können Einladungen erstellen.'
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

// Get POST data
$email = trim($_POST['email'] ?? '');
$role = $_POST['role'] ?? 'member';
$sendMail = isset($_POST['send_mail']) && $_POST['send_mail'] == '1';
$validityHours = isset($_POST['validity_hours']) ? intval($_POST['validity_hours']) : 168; // Default 7 days (168 hours)

// Validate validity_hours (must be a positive integer)
if ($validityHours <= 0) {
    $validityHours = 168; // Default to 7 days if invalid
}

// Validate input
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        'success' => false,
        'message' => 'Ungültige E-Mail-Adresse'
    ]);
    exit;
}

// Validate role - use Auth::VALID_ROLES as single source of truth
if (!in_array($role, Auth::VALID_ROLES)) {
    echo json_encode([
        'success' => false,
        'message' => 'Ungültige Rolle'
    ]);
    exit;
}

// Check if user already exists
$db = Database::getUserDB();
$stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->fetch()) {
    echo json_encode([
        'success' => false,
        'message' => 'Ein Benutzer mit dieser E-Mail-Adresse existiert bereits'
    ]);
    exit;
}

// Check if there's already an open invitation for this email
$stmt = $db->prepare("SELECT id FROM invitation_tokens WHERE email = ? AND used_at IS NULL AND expires_at > NOW()");
$stmt->execute([$email]);
if ($stmt->fetch()) {
    echo json_encode([
        'success' => false,
        'message' => 'Es existiert bereits eine offene Einladung für diese E-Mail-Adresse'
    ]);
    exit;
}

// Generate invitation token
$token = AuthHandler::generateInvitationToken($email, $role, $_SESSION['user_id'], $validityHours);

// Build invitation link
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$invitationLink = $protocol . '://' . $host . '/pages/auth/register.php?token=' . $token;

    // Check if we should send email
    if ($sendMail) {
        // Check if vendor is available
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            error_log('PHPMailer class not found. Composer vendor may be missing.');
            echo json_encode([
                'success' => true,
                'message' => 'Link generiert, aber E-Mail konnte nicht versendet werden (PHPMailer nicht verfügbar).',
                'link' => $invitationLink,
                'email' => $email,
                'role' => $role,
                'mail_error' => 'PHPMailer not available'
            ]);
            exit;
        }
        
        // Send invitation email
        $mailSent = MailService::sendInvitation($email, $token, $role);
        
        if ($mailSent === true) {
            // Return success response with message about sent email
            echo json_encode([
                'success' => true,
                'message' => 'Einladung per E-Mail versendet.',
                'email' => $email,
                'role' => $role,
                'link' => $invitationLink
            ]);
        } else {
            // Email failed, get error details from error log
            $errorDetails = 'Unknown error';
            
            // Check common issues
            if (!defined('SMTP_HOST') || empty(SMTP_HOST)) {
                $errorDetails = 'SMTP configuration missing (SMTP_HOST not defined)';
            } elseif (!defined('SMTP_USERNAME') || empty(SMTP_USERNAME)) {
                $errorDetails = 'SMTP configuration missing (SMTP_USERNAME not defined)';
            } elseif (!defined('SMTP_PASSWORD') || empty(SMTP_PASSWORD)) {
                $errorDetails = 'SMTP configuration missing (SMTP_PASSWORD not defined)';
            }
            
            error_log("Mail sending failed for invitation to $email. Error: $errorDetails");
            
            // Email failed, but still return link
            echo json_encode([
                'success' => true,
                'message' => 'Link generiert, aber E-Mail konnte nicht versendet werden. Bitte überprüfen Sie die SMTP-Konfiguration.',
                'link' => $invitationLink,
                'email' => $email,
                'role' => $role,
                'mail_error' => $errorDetails
            ]);
        }
    } else {
        // Return success response with just the link
        echo json_encode([
            'success' => true,
            'link' => $invitationLink,
            'message' => 'Link generiert.',
            'email' => $email,
            'role' => $role
        ]);
    }
} catch (Exception $e) {
    // Log the full error details
    error_log('Error in send_invitation.php: ' . $e->getMessage() . ' | File: ' . $e->getFile() . ' | Line: ' . $e->getLine());
    
    // Return generic JSON error response (don't expose internal details)
    echo json_encode([
        'success' => false,
        'message' => 'Server Fehler: Es ist ein interner Fehler aufgetreten. Bitte versuche es später erneut.'
    ]);
}
