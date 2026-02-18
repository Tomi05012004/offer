<?php
/**
 * API: Save Event Financial Stats
 * Only accessible to board and alumni_board members
 */

require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../includes/models/EventFinancialStats.php';

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
$category = $data['category'] ?? null;
$itemName = $data['item_name'] ?? null;
$quantity = $data['quantity'] ?? null;
$revenue = $data['revenue'] ?? null;
$recordYear = $data['record_year'] ?? date('Y');

// Validation
if (!$eventId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Event-ID fehlt']);
    exit;
}

if (!$category || !in_array($category, ['Verkauf', 'Kalkulation'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Ungültige Kategorie']);
    exit;
}

if (!$itemName || trim($itemName) === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Artikelname fehlt']);
    exit;
}

if ($quantity === null || !is_numeric($quantity) || $quantity < 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Ungültige Menge (muss >= 0 sein)']);
    exit;
}

if ($revenue !== null && (!is_numeric($revenue) || $revenue < 0)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Ungültiger Umsatz (muss >= 0 sein)']);
    exit;
}

// Convert empty string to null for revenue
if ($revenue === '') {
    $revenue = null;
}

// Save financial stat
try {
    $success = EventFinancialStats::create(
        $eventId,
        $category,
        trim($itemName),
        intval($quantity),
        $revenue !== null ? floatval($revenue) : null,
        intval($recordYear),
        $user['id']
    );
    
    if ($success) {
        echo json_encode([
            'success' => true,
            'message' => 'Eintrag erfolgreich gespeichert'
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Fehler beim Speichern'
        ]);
    }
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Validierungsfehler: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Error saving event financial stats: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Serverfehler: ' . $e->getMessage()
    ]);
}
