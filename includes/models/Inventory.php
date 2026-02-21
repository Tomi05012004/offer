<?php
/**
 * Inventory Model
 * Manages inventory items and operations
 */

class Inventory {
    
    /**
     * Master Data fields that are synced with EasyVerein
     * These fields are protected from manual editing for synced items
     */
    const MASTER_DATA_FIELDS = ['name', 'description', 'quantity', 'unit_price'];
    
    /**
     * Get item by ID
     */
    public static function getById($id) {
        $db = Database::getContentDB();
        $stmt = $db->prepare("
            SELECT i.id, i.easyverein_id, i.name, i.description, i.serial_number, 
                   i.category_id, i.location_id, i.quantity, i.min_stock, i.unit, 
                   i.unit_price, i.image_path, i.notes, i.created_at, i.updated_at, i.last_synced_at,
                   i.is_archived_in_easyverein,
                   c.name as category_name, c.color as category_color, 
                   l.name as location_name,
                   (i.quantity - COALESCE(i.quantity_borrowed, 0) - COALESCE(i.quantity_rented, 0)) as available_quantity
            FROM inventory_items i
            LEFT JOIN categories c ON i.category_id = c.id
            LEFT JOIN locations l ON i.location_id = l.id
            WHERE i.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /**
     * Get available stock for an item
     * 
     * Calculates available stock as: (Synced Total Stock from DB) - (Count of Active/Reserved Local Loans)
     * 
     * @param int $id Item ID
     * @return int Available stock quantity
     */
    public static function getAvailableStock($id) {
        $db = Database::getContentDB();
        
        // Available stock = total quantity - quantity_borrowed - quantity_rented
        $stmt = $db->prepare("
            SELECT 
                i.quantity,
                COALESCE(i.quantity_borrowed, 0) as quantity_borrowed,
                COALESCE(i.quantity_rented, 0) as quantity_rented
            FROM inventory_items i
            WHERE i.id = ?
        ");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        
        if (!$result) {
            return 0;
        }
        
        // Formula: Total Stock - Borrowed - Rented
        $availableStock = $result['quantity'] - $result['quantity_borrowed'] - $result['quantity_rented'];
        
        // Ensure non-negative
        return max(0, $availableStock);
    }

    /**
     * Get all items with filters
     */
    public static function getAll($filters = []) {
        $db = Database::getContentDB();
        
        // Build WHERE clauses based on filters
        $whereClauses = [];
        $params = [];
        
        if (!empty($filters['category_id'])) {
            $whereClauses[] = "i.category_id = ?";
            $params[] = $filters['category_id'];
        }
        
        // Support filtering by location_id (for programmatic use) OR by location name (for UI filters)
        if (!empty($filters['location_id'])) {
            $whereClauses[] = "i.location_id = ?";
            $params[] = $filters['location_id'];
        }
        
        if (!empty($filters['location'])) {
            $whereClauses[] = "l.name = ?";
            $params[] = $filters['location'];
        }
        
        if (!empty($filters['search'])) {
            $whereClauses[] = "(i.name LIKE ? OR i.description LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if (!empty($filters['low_stock'])) {
            $whereClauses[] = "i.quantity <= i.min_stock AND i.min_stock > 0";
        }
        
        $whereSQL = '';
        if (!empty($whereClauses)) {
            $whereSQL = ' WHERE ' . implode(' AND ', $whereClauses);
        }
        
        // Build ORDER BY clause based on sort parameter
        // Using a whitelist switch statement to prevent SQL injection
        // Only predefined column/direction combinations are allowed
        $orderBy = 'i.name ASC';
        if (!empty($filters['sort'])) {
            switch ($filters['sort']) {
                case 'name_asc':
                    $orderBy = 'i.name ASC';
                    break;
                case 'name_desc':
                    $orderBy = 'i.name DESC';
                    break;
                case 'quantity_asc':
                    $orderBy = 'i.quantity ASC';
                    break;
                case 'quantity_desc':
                    $orderBy = 'i.quantity DESC';
                    break;
                case 'price_asc':
                    $orderBy = 'i.unit_price ASC';
                    break;
                case 'price_desc':
                    $orderBy = 'i.unit_price DESC';
                    break;
                default:
                    $orderBy = 'i.name ASC';
            }
        }
        
        // SQL query with correct table and column names.
        // available_quantity = quantity - loaned_count (loaned_count tracks currently active loans)
        $sql = "SELECT i.id, i.easyverein_id, i.name, i.description, i.serial_number, 
                       i.category_id, i.location_id, i.quantity, i.min_stock, i.unit, 
                       i.unit_price, i.image_path, i.notes, i.created_at, i.updated_at, i.last_synced_at,
                       i.is_archived_in_easyverein,
                       COALESCE(i.loaned_count, 0) as loaned_count,
                       c.name as category_name, 
                       c.color as category_color,
                       l.name as location_name,
                       (i.quantity - COALESCE(i.loaned_count, 0)) as available_quantity
                FROM inventory_items i
                LEFT JOIN categories c ON i.category_id = c.id
                LEFT JOIN locations l ON i.location_id = l.id" 
                . $whereSQL . "
                ORDER BY " . $orderBy;
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Create new item
     */
    public static function create($data, $userId) {
        $db = Database::getContentDB();
        
        $stmt = $db->prepare("
            INSERT INTO inventory_items (name, description, category_id, location_id, quantity, min_stock, unit, unit_price, image_path, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['name'],
            $data['description'] ?? null,
            $data['category_id'] ?? null,
            $data['location_id'] ?? null,
            $data['quantity'] ?? 0,
            $data['min_stock'] ?? 0,
            $data['unit'] ?? 'Stück',
            $data['unit_price'] ?? 0,
            $data['image_path'] ?? null,
            $data['notes'] ?? null
        ]);
        
        $itemId = $db->lastInsertId();
        
        // Log creation
        self::logHistory($itemId, $userId, 'create', null, $data['quantity'] ?? 0, null, 'Item created', null);
        
        return $itemId;
    }

    /**
     * Update item
     * 
     * Updates item fields locally in the database.
     * 
     * @param int $id Item ID
     * @param array $data Fields to update
     * @param int $userId User ID performing the update
     * @return bool Success status
     */
    public static function update($id, $data, $userId) {
        $db = Database::getContentDB();
        
        $fields = [];
        $values = [];
        
        foreach ($data as $key => $value) {
            if ($key !== 'id') {
                $fields[] = "$key = ?";
                $values[] = $value;
            }
        }
        
        $values[] = $id;
        $sql = "UPDATE inventory_items SET " . implode(', ', $fields) . " WHERE id = ?";
        
        $stmt = $db->prepare($sql);
        $result = $stmt->execute($values);
        
        // Log update
        self::logHistory($id, $userId, 'update', null, null, null, 'Item updated', json_encode($data));
        
        return $result;
    }

    /**
     * Delete item
     */
    public static function delete($id, $userId) {
        $db = Database::getContentDB();
        
        // Log deletion
        self::logHistory($id, $userId, 'delete', null, null, null, 'Item deleted', null);
        
        $stmt = $db->prepare("DELETE FROM inventory_items WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Adjust stock
     */
    public static function adjustStock($id, $amount, $reason, $comment, $userId) {
        $db = Database::getContentDB();
        
        // Get current stock
        $stmt = $db->prepare("SELECT quantity FROM inventory_items WHERE id = ?");
        $stmt->execute([$id]);
        $item = $stmt->fetch();
        
        if (!$item) {
            return false;
        }
        
        $oldStock = $item['quantity'];
        $newStock = $oldStock + $amount;
        
        // Prevent negative stock
        if ($newStock < 0) {
            $newStock = 0;
        }
        
        // Update stock
        $stmt = $db->prepare("UPDATE inventory_items SET quantity = ? WHERE id = ?");
        $stmt->execute([$newStock, $id]);
        
        // Log adjustment
        self::logHistory($id, $userId, 'adjustment', $oldStock, $newStock, $amount, $reason, $comment);
        
        return true;
    }

    /**
     * Get item history
     */
    public static function getHistory($itemId, $limit = 50) {
        $db = Database::getContentDB();
        $stmt = $db->prepare("
            SELECT * FROM inventory_history
            WHERE item_id = ?
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$itemId, $limit]);
        return $stmt->fetchAll();
    }

    /**
     * Log history entry
     */
    public static function logHistory($itemId, $userId, $changeType, $oldStock, $newStock, $changeAmount, $reason, $comment) {
        $db = Database::getContentDB();
        $stmt = $db->prepare("
            INSERT INTO inventory_history (item_id, user_id, change_type, old_stock, new_stock, change_amount, reason, comment)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$itemId, $userId, $changeType, $oldStock, $newStock, $changeAmount, $reason, $comment]);
    }

    /**
     * Get dashboard statistics
     */
    public static function getDashboardStats() {
        $db = Database::getContentDB();
        
        $stats = [];
        
        // Total items
        $stmt = $db->query("SELECT COUNT(*) as total FROM inventory_items");
        $stats['total_items'] = (int)($stmt->fetch()['total'] ?? 0);
        
        // Total value
        $stmt = $db->query("SELECT SUM(quantity * unit_price) as total_value FROM inventory_items");
        $stats['total_value'] = (float)($stmt->fetch()['total_value'] ?? 0);
        
        // Low stock items
        $stmt = $db->query("SELECT COUNT(*) as low_stock FROM inventory_items WHERE quantity <= min_stock AND min_stock > 0");
        $stats['low_stock'] = (int)($stmt->fetch()['low_stock'] ?? 0);
        
        // Recently moved items
        $stmt = $db->query("
            SELECT COUNT(DISTINCT item_id) as recent_moves 
            FROM inventory_history 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        $stats['recent_moves'] = $stmt->fetch()['recent_moves'];
        
        return $stats;
    }

    /**
     * Get categories
     */
    public static function getCategories() {
        $db = Database::getContentDB();
        // Use EXISTS for better performance - only show categories that are used in inventory_items
        $stmt = $db->query("
            SELECT c.* 
            FROM categories c
            WHERE EXISTS (SELECT 1 FROM inventory_items i WHERE i.category_id = c.id)
            ORDER BY c.name ASC
        ");
        return $stmt->fetchAll();
    }

    /**
     * Get locations
     */
    public static function getLocations() {
        $db = Database::getContentDB();
        // Use EXISTS for better performance - only show locations that are used in inventory_items
        $stmt = $db->query("
            SELECT l.* 
            FROM locations l
            WHERE EXISTS (SELECT 1 FROM inventory_items i WHERE i.location_id = l.id)
            ORDER BY l.name ASC
        ");
        return $stmt->fetchAll();
    }

    /**
     * Get ALL locations from database (not just those in use)
     * Used for dropdown population in Add/Edit forms
     */
    public static function getAllLocations() {
        $db = Database::getContentDB();
        $stmt = $db->query("
            SELECT * 
            FROM locations 
            ORDER BY name ASC
        ");
        return $stmt->fetchAll();
    }

    /**
     * Create category
     */
    public static function createCategory($name, $description = null, $color = '#3B82F6') {
        $db = Database::getContentDB();
        $stmt = $db->prepare("INSERT INTO categories (name, description, color) VALUES (?, ?, ?)");
        $stmt->execute([$name, $description, $color]);
        return $db->lastInsertId();
    }

    /**
     * Create location
     */
    public static function createLocation($name, $description = null, $address = null) {
        $db = Database::getContentDB();
        $stmt = $db->prepare("INSERT INTO locations (name, description, address) VALUES (?, ?, ?)");
        $stmt->execute([$name, $description, $address]);
        return $db->lastInsertId();
    }

    /**
     * Checkout item (Borrow/Remove from inventory)
     */
    public static function checkoutItem($itemId, $userId, $quantity, $purpose, $destination = null, $expectedReturnDate = null) {
        $db = Database::getContentDB();
        
        // Special case: if quantity is 0, prevent checkout
        if ($quantity <= 0) {
            return ['success' => false, 'message' => 'Ungültige Menge'];
        }
        
        // Begin transaction
        $db->beginTransaction();
        
        try {
            // Lock the row to prevent race conditions
            $stmt = $db->prepare("SELECT quantity, COALESCE(quantity_borrowed, 0) AS quantity_borrowed, COALESCE(quantity_rented, 0) AS quantity_rented, name FROM inventory_items WHERE id = ? FOR UPDATE");
            $stmt->execute([$itemId]);
            $item = $stmt->fetch();
            
            if (!$item) {
                $db->rollBack();
                return ['success' => false, 'message' => 'Artikel nicht gefunden'];
            }
            
            $newBorrowed = $item['quantity_borrowed'] + $quantity;
            
            // Check if enough stock available (accounting for both borrowed and rented)
            if ($newBorrowed + $item['quantity_rented'] > $item['quantity']) {
                $db->rollBack();
                $available = $item['quantity'] - $item['quantity_borrowed'] - $item['quantity_rented'];
                return ['success' => false, 'message' => 'Nicht genügend Artikel verfügbar. Verfügbar: ' . $available];
            }
            
            // Update quantity_borrowed
            $stmt = $db->prepare("UPDATE inventory_items SET quantity_borrowed = ? WHERE id = ?");
            $stmt->execute([$newBorrowed, $itemId]);
            
            // Create rental record
            $stmt = $db->prepare("
                INSERT INTO rentals (item_id, user_id, amount, purpose, destination, checkout_date, expected_return, status)
                VALUES (?, ?, ?, ?, ?, NOW(), ?, 'active')
            ");
            $stmt->execute([$itemId, $userId, $quantity, $purpose, $destination, $expectedReturnDate]);
            
            // Log checkout in history
            $oldAvailable = $item['quantity'] - $item['quantity_borrowed'];
            $newAvailable = $item['quantity'] - $newBorrowed;
            self::logHistory($itemId, $userId, 'checkout', $oldAvailable, $newAvailable, -$quantity, 'Ausgeliehen', $purpose . ($destination ? ' - ' . $destination : ''));
            
            $db->commit();
            return ['success' => true, 'message' => 'Artikel erfolgreich ausgeliehen'];
        } catch (Exception $e) {
            $db->rollBack();
            return ['success' => false, 'message' => 'Fehler beim Ausleihen: ' . $e->getMessage()];
        }
    }

    /**
     * Check-in item (Return to inventory)
     */
    public static function checkinItem($rentalId, $returnedQuantity, $isDefective, $defectiveQuantity = 0, $defectiveReason = null) {
        $db = Database::getContentDB();
        
        // Get rental record (using rentals table)
        $stmt = $db->prepare("
            SELECT r.*, i.quantity, COALESCE(i.quantity_borrowed, 0) AS quantity_borrowed, i.name 
            FROM rentals r
            JOIN inventory_items i ON r.item_id = i.id
            WHERE r.id = ? AND r.actual_return IS NULL
        ");
        $stmt->execute([$rentalId]);
        $rental = $stmt->fetch();
        
        if (!$rental) {
            return ['success' => false, 'message' => 'Ausleihe nicht gefunden oder bereits zurückgegeben'];
        }
        
        // Validate quantities
        if ($returnedQuantity > $rental['amount']) {
            return ['success' => false, 'message' => 'Rückgabemenge kann nicht größer als ausgeliehene Menge sein'];
        }
        
        if ($isDefective && $defectiveQuantity > $returnedQuantity) {
            return ['success' => false, 'message' => 'Defekte Menge kann nicht größer als Rückgabemenge sein'];
        }
        
        // Begin transaction
        $db->beginTransaction();
        
        try {
            // Update rental record - set status to 'returned'
            $status = $isDefective && $defectiveQuantity > 0 ? 'defective' : 'returned';
            $stmt = $db->prepare("
                UPDATE rentals 
                SET actual_return = NOW(), defect_notes = ?, status = ?
                WHERE id = ?
            ");
            $stmt->execute([$defectiveReason, $status, $rentalId]);
            
            // Reduce quantity_borrowed in inventory
            $newBorrowed = max(0, $rental['quantity_borrowed'] - $returnedQuantity);
            $stmt = $db->prepare("UPDATE inventory_items SET quantity_borrowed = ? WHERE id = ?");
            $stmt->execute([$newBorrowed, $rental['item_id']]);
            
            // If items are defective, also reduce total quantity
            if ($defectiveQuantity > 0) {
                $newTotalQuantity = $rental['quantity'] - $defectiveQuantity;
                $stmt = $db->prepare("UPDATE inventory_items SET quantity = ? WHERE id = ?");
                $stmt->execute([max(0, $newTotalQuantity), $rental['item_id']]);
            }
            
            // Log check-in
            $goodQuantity = $returnedQuantity - $defectiveQuantity;
            $oldAvailable = $rental['quantity'] - $rental['quantity_borrowed'];
            $newAvailable = $rental['quantity'] - $defectiveQuantity - $newBorrowed;
            $comment = "Rückgabe: {$returnedQuantity} Stück";
            if ($defectiveQuantity > 0) {
                $comment .= " (davon {$defectiveQuantity} defekt: {$defectiveReason})";
            }
            self::logHistory($rental['item_id'], $rental['user_id'], 'checkin', $oldAvailable, $newAvailable, $goodQuantity, 'Zurückgegeben', $comment);
            
            // If items are defective, log write-off
            if ($defectiveQuantity > 0) {
                self::logHistory($rental['item_id'], $rental['user_id'], 'writeoff', $newAvailable, $newAvailable, -$defectiveQuantity, 'Ausschuss', $defectiveReason);
            }
            
            $db->commit();
            return ['success' => true, 'message' => 'Artikel erfolgreich zurückgegeben'];
        } catch (Exception $e) {
            $db->rollBack();
            return ['success' => false, 'message' => 'Fehler bei der Rückgabe: ' . $e->getMessage()];
        }
    }

    /**
     * Get active checkouts for an item
     */
    public static function getItemCheckouts($itemId) {
        $db = Database::getContentDB();
        $stmt = $db->prepare("
            SELECT r.*
            FROM rentals r
            WHERE r.item_id = ? AND r.actual_return IS NULL
            ORDER BY r.created_at DESC
        ");
        $stmt->execute([$itemId]);
        $rentals = $stmt->fetchAll();
        
        // Fetch user information from user database
        if (!empty($rentals)) {
            $userDb = Database::getUserDB();
            $userIds = array_column($rentals, 'user_id');
            $placeholders = str_repeat('?,', count($userIds) - 1) . '?';
            $userStmt = $userDb->prepare("SELECT id, email FROM users WHERE id IN ($placeholders)");
            $userStmt->execute($userIds);
            $users = [];
            foreach ($userStmt->fetchAll() as $user) {
                $users[$user['id']] = $user;
            }
            
            // Add user info to rentals
            foreach ($rentals as &$rental) {
                $rental['user_email'] = $users[$rental['user_id']]['email'] ?? 'Unknown';
            }
        }
        
        return $rentals;
    }

    /**
     * Get all checkouts for a user
     */
    public static function getUserCheckouts($userId, $includeReturned = false) {
        $db = Database::getContentDB();
        $sql = "
            SELECT r.*, i.name as item_name, i.unit
            FROM rentals r
            JOIN inventory_items i ON r.item_id = i.id
            WHERE r.user_id = ?
        ";
        
        if (!$includeReturned) {
            $sql .= " AND r.actual_return IS NULL";
        }
        
        $sql .= " ORDER BY r.created_at DESC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    /**
     * Get all rentals for a user
     * This method returns rentals from the rentals table with proper column names
     */
    public static function getRentalsByUser($userId, $includeReturned = false) {
        // Validate userId
        if (!is_numeric($userId) || $userId <= 0) {
            throw new InvalidArgumentException('Invalid user ID provided');
        }
        
        $db = Database::getContentDB();
        $sql = "
            SELECT 
                r.id,
                r.item_id,
                r.user_id,
                r.amount as quantity,
                r.created_at as rented_at,
                r.expected_return,
                r.actual_return as returned_at,
                i.name as item_name,
                i.unit
            FROM rentals r
            JOIN inventory_items i ON r.item_id = i.id
            WHERE r.user_id = ?
        ";
        
        if (!$includeReturned) {
            $sql .= " AND r.actual_return IS NULL";
        }
        
        $sql .= " ORDER BY r.created_at DESC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    /**
     * Get checkout by ID
     */
    public static function getCheckoutById($rentalId) {
        $db = Database::getContentDB();
        $stmt = $db->prepare("
            SELECT r.*, i.name as item_name, i.unit, i.quantity
            FROM rentals r
            JOIN inventory_items i ON r.item_id = i.id
            WHERE r.id = ?
        ");
        $stmt->execute([$rentalId]);
        return $stmt->fetch();
    }

    /**
     * Get in-stock statistics for dashboard
     * Returns total items currently in stock (available inventory)
     */
    public static function getInStockStats() {
        $db = Database::getContentDB();
        
        $stats = [];
        
        // Total items in stock (sum of all quantity)
        $stmt = $db->query("SELECT SUM(quantity) as total_in_stock FROM inventory_items");
        $stats['total_in_stock'] = (float)($stmt->fetch()['total_in_stock'] ?? 0);
        
        // Total unique items in stock
        $stmt = $db->query("SELECT COUNT(*) as unique_items FROM inventory_items WHERE quantity > 0");
        $stats['unique_items_in_stock'] = (int)($stmt->fetch()['unique_items'] ?? 0);
        
        // Total value in stock
        $stmt = $db->query("SELECT SUM(quantity * unit_price) as total_value FROM inventory_items");
        $stats['total_value_in_stock'] = (float)($stmt->fetch()['total_value'] ?? 0);
        
        return $stats;
    }

    /**
     * Get checked-out statistics for dashboard
     * Returns: ['total_items_out' => (int), 'unique_users' => (int), 'overdue' => (int), 'checkouts' => array]
     */
    public static function getCheckedOutStats() {
        $db = Database::getContentDB();
        
        // Calculate total items out (sum of all amounts from active and pending rentals)
        $stmt = $db->query("
            SELECT 
                COALESCE(SUM(r.amount), 0) as total_items_out,
                COUNT(DISTINCT r.user_id) as unique_users
            FROM rentals r
            WHERE r.actual_return IS NULL
            AND r.status IN ('active', 'pending_confirmation')
        ");
        $stats = $stmt->fetch();
        
        // Calculate overdue items (expected_return < current date and not yet returned)
        $stmt = $db->query("
            SELECT COUNT(*) as overdue
            FROM rentals r
            WHERE r.actual_return IS NULL
            AND r.status = 'active'
            AND r.expected_return IS NOT NULL
            AND r.expected_return < CURDATE()
        ");
        $overdueResult = $stmt->fetch();
        
        // Get detailed checkout information
        $stmt = $db->query("
            SELECT 
                r.id,
                r.item_id,
                r.user_id,
                r.amount,
                r.created_at as rented_at,
                r.expected_return,
                r.status,
                i.name as item_name,
                i.unit
            FROM rentals r
            JOIN inventory_items i ON r.item_id = i.id
            WHERE r.actual_return IS NULL
            AND r.status IN ('active', 'pending_confirmation')
            ORDER BY r.created_at DESC
        ");
        $checkouts = $stmt->fetchAll();
        
        // Fetch user information from user database
        if (!empty($checkouts)) {
            $userDb = Database::getUserDB();
            $userIds = array_unique(array_column($checkouts, 'user_id'));
            
            if (!empty($userIds)) {
                $placeholders = str_repeat('?,', count($userIds) - 1) . '?';
                $userStmt = $userDb->prepare("SELECT id, email FROM users WHERE id IN ($placeholders)");
                $userStmt->execute($userIds);
                $users = [];
                foreach ($userStmt->fetchAll() as $user) {
                    $users[$user['id']] = $user;
                }
                
                // Add user info to checkouts
                foreach ($checkouts as &$checkout) {
                    $checkout['borrower_email'] = isset($users[$checkout['user_id']]) 
                        ? $users[$checkout['user_id']]['email'] 
                        : 'Unbekannt';
                }
            }
        }
        
        return [
            'total_items_out' => (int)$stats['total_items_out'],
            'unique_users' => (int)$stats['unique_users'],
            'overdue' => (int)$overdueResult['overdue'],
            'checkouts' => $checkouts
        ];
    }

    /**
     * Get write-off statistics for this month
     * Returns items reported as write-off (loss/defect) this month
     */
    public static function getWriteOffStatsThisMonth() {
        $db = Database::getContentDB();
        
        // Get all write-offs this month
        $stmt = $db->query("
            SELECT 
                ih.id, ih.item_id, ih.user_id, ih.change_amount, ih.reason, ih.comment, ih.created_at,
                i.name as item_name, i.unit
            FROM inventory_history ih
            JOIN inventory_items i ON ih.item_id = i.id
            WHERE ih.change_type = 'writeoff'
            AND MONTH(ih.created_at) = MONTH(CURRENT_DATE())
            AND YEAR(ih.created_at) = YEAR(CURRENT_DATE())
            ORDER BY ih.created_at DESC
        ");
        $writeoffs = $stmt->fetchAll();
        
        // Fetch user information from user database
        if (!empty($writeoffs)) {
            $userDb = Database::getUserDB();
            $userIds = array_unique(array_column($writeoffs, 'user_id'));
            
            if (!empty($userIds)) {
                $placeholders = str_repeat('?,', count($userIds) - 1) . '?';
                $userStmt = $userDb->prepare("SELECT id, email FROM users WHERE id IN ($placeholders)");
                $userStmt->execute($userIds);
                $users = [];
                foreach ($userStmt->fetchAll() as $user) {
                    $users[$user['id']] = $user;
                }
                
                // Add user info to writeoffs
                foreach ($writeoffs as &$writeoff) {
                    $writeoff['reported_by_email'] = $users[$writeoff['user_id']]['email'] ?? 'Unbekannt';
                }
            }
        }
        
        // Calculate statistics
        $stats = [
            'total_writeoffs' => count($writeoffs),
            'total_quantity_lost' => abs(array_sum(array_column($writeoffs, 'change_amount'))),
            'writeoffs' => $writeoffs
        ];
        
        return $stats;
    }

    /**
     * Import inventory items from JSON data
     * 
     * @param array $data Array of inventory items from JSON
     * @param int $userId User ID performing the import
     * @return array Result with 'success', 'imported', 'skipped', and 'errors' keys
     */
    public static function importFromJson($data, $userId) {
        $db = Database::getContentDB();
        $imported = 0;
        $skipped = 0;
        $errors = [];
        
        // Validate that data is an array
        if (!is_array($data)) {
            return [
                'success' => false,
                'imported' => 0,
                'skipped' => 0,
                'errors' => ['Invalid JSON format: expected array of items']
            ];
        }
        
        // Process each item
        foreach ($data as $index => $item) {
            // Validate required fields
            if (empty($item['name'])) {
                $errors[] = "Item at index $index: 'name' is required";
                $skipped++;
                continue;
            }
            
            if (empty($item['category'])) {
                $errors[] = "Item at index $index: 'category' is required";
                $skipped++;
                continue;
            }
            
            // Check if serial_number exists and is duplicate
            if (!empty($item['serial_number'])) {
                $stmt = $db->prepare("SELECT id, name FROM inventory_items WHERE serial_number = ?");
                $stmt->execute([$item['serial_number']]);
                $existing = $stmt->fetch();
                
                if ($existing) {
                    $errors[] = "Item at index $index ('{$item['name']}'): Serial number '{$item['serial_number']}' already exists for item '{$existing['name']}' (ID: {$existing['id']})";
                    $skipped++;
                    continue;
                }
            }
            
            try {
                // Get or create category
                $categoryId = null;
                $stmt = $db->prepare("SELECT id FROM categories WHERE name = ?");
                $stmt->execute([$item['category']]);
                $category = $stmt->fetch();
                
                if ($category) {
                    $categoryId = $category['id'];
                } else {
                    // Create new category
                    $categoryId = self::createCategory($item['category']);
                }
                
                // Get or create location if provided
                $locationId = null;
                if (!empty($item['location'])) {
                    $stmt = $db->prepare("SELECT id FROM locations WHERE name = ?");
                    $stmt->execute([$item['location']]);
                    $location = $stmt->fetch();
                    
                    if ($location) {
                        $locationId = $location['id'];
                    } else {
                        // Create new location
                        $locationId = self::createLocation($item['location']);
                    }
                }
                
                // Insert item
                $stmt = $db->prepare("
                    INSERT INTO inventory_items (
                        name, description, serial_number, category_id, location_id, quantity
                    ) VALUES (?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $item['name'],
                    $item['description'] ?? null,
                    $item['serial_number'] ?? null,
                    $categoryId,
                    $locationId,
                    1 // Default stock of 1 for imported items
                ]);
                
                $itemId = $db->lastInsertId();
                
                // Log creation
                self::logHistory(
                    $itemId, 
                    $userId, 
                    'create', 
                    null, 
                    1, 
                    null, 
                    'Imported from JSON', 
                    json_encode(['original_data' => $item])
                );
                
                $imported++;
            } catch (Exception $e) {
                $errors[] = "Item at index $index ('{$item['name']}'): " . $e->getMessage();
                $skipped++;
            }
        }
        
        return [
            'success' => $imported > 0,
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors
        ];
    }
    
    /**
     * Confirm a pending rental return (board/head only)
     * 
     * Sets rental status to 'returned', sets actual_return, and decrements quantity_rented.
     * 
     * @param int $rentalId Rental ID to confirm
     * @param int $confirmingUserId User ID of the board member confirming
     * @return array ['success' => bool, 'message' => string]
     */
    public static function confirmReturn($rentalId, $confirmingUserId) {
        $db = Database::getContentDB();
        
        // Get rental record
        $stmt = $db->prepare("
            SELECT r.*, COALESCE(i.quantity_rented, 0) AS quantity_rented, i.name
            FROM rentals r
            JOIN inventory_items i ON r.item_id = i.id
            WHERE r.id = ? AND r.status = 'pending_confirmation'
        ");
        $stmt->execute([$rentalId]);
        $rental = $stmt->fetch();
        
        if (!$rental) {
            return ['success' => false, 'message' => 'Ausleihe nicht gefunden oder nicht im Status "Rückgabe ausstehend"'];
        }
        
        $db->beginTransaction();
        
        try {
            // Mark rental as returned
            $stmt = $db->prepare("
                UPDATE rentals 
                SET actual_return = NOW(), status = 'returned'
                WHERE id = ?
            ");
            $stmt->execute([$rentalId]);
            
            // Decrement quantity_rented
            $newRented = max(0, $rental['quantity_rented'] - $rental['amount']);
            $stmt = $db->prepare("UPDATE inventory_items SET quantity_rented = ? WHERE id = ?");
            $stmt->execute([$newRented, $rental['item_id']]);
            
            // Log confirmation
            self::logHistory($rental['item_id'], $confirmingUserId, 'checkin', $rental['quantity_rented'], $newRented, $rental['amount'], 'Rückgabe bestätigt', 'Rückgabe durch Vorstand bestätigt');
            
            $db->commit();
            return ['success' => true, 'message' => 'Rückgabe erfolgreich bestätigt'];
        } catch (Exception $e) {
            $db->rollBack();
            return ['success' => false, 'message' => 'Fehler bei der Bestätigung: ' . $e->getMessage()];
        }
    }

    /**
     * Sync inventory from EasyVerein
     * 
     * Wrapper method to easily call EasyVereinSync::sync()
     * 
     * @param int $userId User ID performing the sync (for audit trail)
     * @return array Result with statistics (created, updated, archived, errors)
     */
    public static function syncFromEasyVerein($userId) {
        require_once __DIR__ . '/../services/EasyVereinSync.php';
        
        $sync = new EasyVereinSync();
        return $sync->sync($userId);
    }
}
