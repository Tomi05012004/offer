<?php
/**
 * API: Update User Role
 * Updates the local role of a user
 * Required permissions: canManageUsers (board or higher)
 */

require_once __DIR__ . '/../includes/handlers/AuthHandler.php';
require_once __DIR__ . '/../includes/handlers/CSRFHandler.php';
require_once __DIR__ . '/../includes/models/User.php';
require_once __DIR__ . '/../src/Auth.php';

AuthHandler::startSession();

header('Content-Type: application/json');

if (!AuthHandler::isAuthenticated() || !AuthHandler::canManageUsers()) {
    echo json_encode(['success' => false, 'message' => 'Nicht autorisiert']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Ungültige Anfrage']);
    exit;
}

CSRFHandler::verifyToken($_POST['csrf_token'] ?? '');

$userId  = intval($_POST['user_id'] ?? 0);
$newRole = $_POST['new_role'] ?? '';

if ($userId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Ungültige Benutzer-ID']);
    exit;
}

if (!in_array($newRole, Auth::VALID_ROLES, true)) {
    echo json_encode(['success' => false, 'message' => 'Ungültige Rolle']);
    exit;
}

if ($userId === intval($_SESSION['user_id'] ?? 0)) {
    echo json_encode(['success' => false, 'message' => 'Du kannst Deine eigene Rolle nicht ändern']);
    exit;
}

if (User::update($userId, ['role' => $newRole])) {
    echo json_encode(['success' => true, 'message' => 'Rolle erfolgreich geändert']);
} else {
    echo json_encode(['success' => false, 'message' => 'Fehler beim Ändern der Rolle']);
}
