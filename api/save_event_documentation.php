<?php
/**
 * API: Save Event Documentation
 * Only accessible to board and alumni_board members
 */

require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../includes/models/EventDocumentation.php';

header('Content-Type: application/json');

// Check authentication
if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Nicht authentifiziert']);
    exit;
}

$user = Auth::user();
$userRole = $user['role'] ?? '';

// Check if user has permission (board roles or alumni_board only)
// This includes all board role variants: board_finance, board_internal, board_external
$allowedRoles = array_merge(Auth::BOARD_ROLES, ['alumni_board']);
if (!in_array($userRole, $allowedRoles)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Keine Berechtigung']);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Ungültige Daten']);
    exit;
}

$eventId = $data['event_id'] ?? null;
$calculations = $data['calculations'] ?? '';
$salesData = $data['sales_data'] ?? [];
$sellersData = $data['sellers_data'] ?? [];

if (!$eventId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Event-ID fehlt']);
    exit;
}

// Validate sales data structure
if (!is_array($salesData)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Ungültige Verkaufsdaten']);
    exit;
}

// Validate sellers data structure
if (!is_array($sellersData)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Ungültige Verkäuferdaten']);
    exit;
}

// Save documentation
try {
    $success = EventDocumentation::save($eventId, $calculations, $salesData, $sellersData, $user['id']);
    
    if ($success) {
        echo json_encode([
            'success' => true,
            'message' => 'Dokumentation erfolgreich gespeichert'
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Fehler beim Speichern'
        ]);
    }
} catch (Exception $e) {
    error_log("Error saving event documentation: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Serverfehler: ' . $e->getMessage()
    ]);
}
