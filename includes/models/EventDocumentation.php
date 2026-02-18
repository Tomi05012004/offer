<?php
/**
 * EventDocumentation Model
 * Manages event documentation for board and alumni_board members
 */

require_once __DIR__ . '/../../src/Database.php';

class EventDocumentation {
    
    /**
     * Get documentation for an event
     * 
     * @param int $eventId Event ID
     * @return array|null Documentation data or null if not found
     */
    public static function getByEventId($eventId) {
        $db = Database::getContentDB();
        
        $stmt = $db->prepare("
            SELECT * FROM event_documentation 
            WHERE event_id = ?
        ");
        $stmt->execute([$eventId]);
        
        $doc = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($doc) {
            // Decode JSON sales data
            if (!empty($doc['sales_data'])) {
                $doc['sales_data'] = json_decode($doc['sales_data'], true);
            }
            // Decode JSON sellers data
            if (!empty($doc['sellers_data'])) {
                $doc['sellers_data'] = json_decode($doc['sellers_data'], true);
            }
        }
        
        return $doc ?: null;
    }
    
    /**
     * Save or update documentation for an event
     * 
     * @param int $eventId Event ID
     * @param string $calculations Calculations text
     * @param array $salesData Array of sales entries
     * @param array $sellersData Array of seller entries
     * @param int $userId User ID making the update
     * @return bool Success status
     */
    public static function save($eventId, $calculations, $salesData, $sellersData, $userId) {
        $db = Database::getContentDB();
        
        // Encode sales data and sellers data as JSON
        $salesDataJson = json_encode($salesData);
        $sellersDataJson = json_encode($sellersData);
        
        // Check if documentation exists
        $existing = self::getByEventId($eventId);
        
        if ($existing) {
            // Update existing
            $stmt = $db->prepare("
                UPDATE event_documentation 
                SET calculations = ?, sales_data = ?, sellers_data = ?, updated_by = ?
                WHERE event_id = ?
            ");
            return $stmt->execute([$calculations, $salesDataJson, $sellersDataJson, $userId, $eventId]);
        } else {
            // Insert new
            $stmt = $db->prepare("
                INSERT INTO event_documentation (event_id, calculations, sales_data, sellers_data, created_by, updated_by)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            return $stmt->execute([$eventId, $calculations, $salesDataJson, $sellersDataJson, $userId, $userId]);
        }
    }
    
    /**
     * Get all event documentation for history view
     * 
     * @return array Array of documentation entries with event titles
     */
    public static function getAllWithEvents() {
        $db = Database::getContentDB();
        
        $stmt = $db->query("
            SELECT ed.*, e.title as event_title, e.start_time, e.end_time
            FROM event_documentation ed
            INNER JOIN events e ON ed.event_id = e.id
            ORDER BY e.start_time DESC
        ");
        
        $docs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($docs as &$doc) {
            // Decode JSON data
            if (!empty($doc['sales_data'])) {
                $doc['sales_data'] = json_decode($doc['sales_data'], true);
            }
            if (!empty($doc['sellers_data'])) {
                $doc['sellers_data'] = json_decode($doc['sellers_data'], true);
            }
        }
        
        return $docs;
    }
}
