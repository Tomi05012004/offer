<?php
/**
 * API: Delete Invitation
 * Deletes an open invitation
 * Required permissions: admin, board, or alumni_board
 */

require_once __DIR__ . '/../includes/handlers/AuthHandler.php';
require_once __DIR__ . '/../includes/handlers/CSRFHandler.php';
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
$invitationId = intval($_POST['invitation_id'] ?? 0);

// Validate input
if ($invitationId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Ungültige Einladungs-ID'
    ]);
    exit;
}

// Delete the invitation
$db = Database::getUserDB();
$stmt = $db->prepare("DELETE FROM invitation_tokens WHERE id = ? AND used_at IS NULL");
$stmt->execute([$invitationId]);

if ($stmt->rowCount() > 0) {
    echo json_encode([
        'success' => true,
        'message' => 'Einladung erfolgreich gelöscht'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Einladung nicht gefunden oder bereits verwendet'
    ]);
}
