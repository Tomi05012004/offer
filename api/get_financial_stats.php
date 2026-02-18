<?php
/**
 * API: Get Event Financial Stats
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

// Get query parameters
$eventId = $_GET['event_id'] ?? null;
$category = $_GET['category'] ?? null;
$year = $_GET['year'] ?? null;

if (!$eventId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Event-ID fehlt']);
    exit;
}

try {
    // Get yearly comparison data
    $comparison = EventFinancialStats::getYearlyComparison($eventId, $category);
    
    // Get available years
    $availableYears = EventFinancialStats::getAvailableYears($eventId);
    
    // Get all stats for the event
    $allStats = EventFinancialStats::getByEventId($eventId, $category, $year);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'comparison' => $comparison,
            'available_years' => $availableYears,
            'all_stats' => $allStats
        ]
    ]);
} catch (Exception $e) {
    error_log("Error getting event financial stats: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Serverfehler: ' . $e->getMessage()
    ]);
}
