<?php
/**
 * API: Get Invitations
 * Lists all open invitations
 * Required permissions: admin, board, or alumni_board
 */

require_once __DIR__ . '/../includes/handlers/AuthHandler.php';
require_once __DIR__ . '/../includes/database.php';

AuthHandler::startSession();

// Set JSON response header
header('Content-Type: application/json');

// Check authentication and permission
if (!AuthHandler::isAuthenticated() || !AuthHandler::hasPermission('board')) {
    echo json_encode([
        'success' => false,
        'message' => 'Nicht autorisiert'
    ]);
    exit;
}

try {
    // Get all open invitations from user DB
    $db = Database::getConnection('user');
    $stmt = $db->prepare("
        SELECT 
            id,
            token,
            email,
            role,
            created_by,
            created_at,
            expires_at
        FROM invitation_tokens
        WHERE used_at IS NULL AND expires_at > NOW()
        ORDER BY created_at DESC
    ");
    $stmt->execute();
    $invitations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch creator emails in a separate query (no JOIN)
    $creatorIds = array_unique(array_column($invitations, 'created_by'));
    $creatorEmailMap = [];
    
    if (!empty($creatorIds)) {
        $placeholders = implode(',', array_fill(0, count($creatorIds), '?'));
        $userStmt = $db->prepare("SELECT id, email FROM users WHERE id IN ($placeholders)");
        $userStmt->execute($creatorIds);
        $creators = $userStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($creators as $creator) {
            $creatorEmailMap[$creator['id']] = $creator['email'];
        }
    }

    // Build invitation links and add creator emails
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];

    foreach ($invitations as &$invitation) {
        $invitation['link'] = $protocol . '://' . $host . '/pages/auth/register.php?token=' . $invitation['token'];
        // Use 'Deleted User' as fallback when creator no longer exists
        $invitation['created_by_email'] = $creatorEmailMap[$invitation['created_by']] ?? 'Deleted User';
    }

    echo json_encode([
        'success' => true,
        'invitations' => $invitations
    ]);
} catch (Exception $e) {
    // Log the full error for debugging
    error_log('Error in get_invitations.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.']);
}
