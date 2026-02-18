<?php
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../includes/models/Inventory.php';
require_once __DIR__ . '/../../includes/database.php';

if (!Auth::check()) {
    header('Location: ../auth/login.php');
    exit;
}

$message = '';
$error = '';

// Handle rental creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_rental'])) {
    $itemId = intval($_POST['item_id'] ?? 0);
    $amount = intval($_POST['amount'] ?? 0);
    $expectedReturn = $_POST['expected_return'] ?? '';
    $purpose = trim($_POST['purpose'] ?? '');
    
    if ($itemId <= 0 || $amount <= 0) {
        $_SESSION['rental_error'] = 'Ungültige Artikel-ID oder Menge';
        header('Location: view.php?id=' . $itemId);
        exit;
    }
    
    if (empty($expectedReturn)) {
        $_SESSION['rental_error'] = 'Bitte geben Sie ein voraussichtliches Rückgabedatum an';
        header('Location: view.php?id=' . $itemId);
        exit;
    }
    
    if (empty($purpose)) {
        $_SESSION['rental_error'] = 'Bitte geben Sie einen Verwendungszweck an';
        header('Location: view.php?id=' . $itemId);
        exit;
    }
    
    // Get item to check stock
    $item = Inventory::getById($itemId);
    if (!$item) {
        $_SESSION['rental_error'] = 'Artikel nicht gefunden';
        header('Location: index.php');
        exit;
    }
    
    if ($item['quantity'] < $amount) {
        $_SESSION['rental_error'] = 'Nicht genügend Bestand verfügbar';
        header('Location: view.php?id=' . $itemId);
        exit;
    }
    
    try {
        $db = Database::getContentDB();
        $db->beginTransaction();
        
        // Create rental record
        $stmt = $db->prepare("
            INSERT INTO rentals (user_id, item_id, amount, expected_return)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $itemId,
            $amount,
            $expectedReturn
        ]);
        
        // Update inventory stock
        $newStock = $item['quantity'] - $amount;
        $stmt = $db->prepare("UPDATE inventory_items SET quantity = ? WHERE id = ?");
        $stmt->execute([$newStock, $itemId]);
        
        // Log the change
        Inventory::logHistory(
            $itemId,
            $_SESSION['user_id'],
            'checkout',
            $item['quantity'],
            $newStock,
            -$amount,
            'Ausgeliehen',
            $purpose
        );
        
        $db->commit();
        
        $_SESSION['rental_success'] = 'Artikel erfolgreich ausgeliehen! Bitte geben Sie ihn bis zum ' . date('d.m.Y', strtotime($expectedReturn)) . ' zurück.';
        header('Location: view.php?id=' . $itemId);
        exit;
        
    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['rental_error'] = 'Fehler beim Ausleihen: ' . $e->getMessage();
        header('Location: view.php?id=' . $itemId);
        exit;
    }
}

// Handle rental return
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['return_rental'])) {
    $rentalId = intval($_POST['rental_id'] ?? 0);
    $isDefective = isset($_POST['is_defective']) && $_POST['is_defective'] === 'yes';
    $defectNotes = $isDefective ? trim($_POST['defect_notes'] ?? '') : null;
    
    if ($rentalId <= 0) {
        $_SESSION['rental_error'] = 'Ungültige Ausleihe-ID';
        header('Location: my_rentals.php');
        exit;
    }
    
    try {
        $db = Database::getContentDB();
        
        // Get rental details
        $stmt = $db->prepare("
            SELECT r.*, i.quantity, i.name as item_name
            FROM rentals r
            JOIN inventory_items i ON r.item_id = i.id
            WHERE r.id = ? AND r.user_id = ? AND r.actual_return IS NULL
        ");
        $stmt->execute([$rentalId, $_SESSION['user_id']]);
        $rental = $stmt->fetch();
        
        if (!$rental) {
            $_SESSION['rental_error'] = 'Ausleihe nicht gefunden oder bereits zurückgegeben';
            header('Location: my_rentals.php');
            exit;
        }
        
        $db->beginTransaction();
        
        // Determine new status
        $newStatus = $isDefective ? 'defective' : 'returned';
        
        // Update rental record
        $stmt = $db->prepare("
            UPDATE rentals 
            SET actual_return = NOW(), 
                status = ?, 
                defect_notes = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$newStatus, $defectNotes, $rentalId]);
        
        // Update inventory stock (only add back if not defective)
        $returnAmount = $isDefective ? 0 : $rental['amount'];
        $newStock = $rental['quantity'] + $returnAmount;
        
        $stmt = $db->prepare("UPDATE inventory_items SET quantity = ? WHERE id = ?");
        $stmt->execute([$newStock, $rental['item_id']]);
        
        // Log the change
        $changeType = $isDefective ? 'writeoff' : 'checkin';
        $reason = $isDefective ? 'Defekt zurückgegeben' : 'Zurückgegeben';
        $comment = $isDefective ? "Defekt: " . $defectNotes : 'Artikel zurückgegeben';
        
        Inventory::logHistory(
            $rental['item_id'],
            $_SESSION['user_id'],
            $changeType,
            $rental['quantity'],
            $newStock,
            $returnAmount,
            $reason,
            $comment
        );
        
        $db->commit();
        
        if ($isDefective) {
            $_SESSION['rental_success'] = 'Artikel als defekt gemeldet. Vielen Dank für Deine Rückmeldung.';
        } else {
            $_SESSION['rental_success'] = 'Artikel erfolgreich zurückgegeben!';
        }
        
        header('Location: my_rentals.php');
        exit;
        
    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['rental_error'] = 'Fehler beim Zurückgeben: ' . $e->getMessage();
        header('Location: my_rentals.php');
        exit;
    }
}

// If direct access, redirect to inventory
header('Location: index.php');
exit;
