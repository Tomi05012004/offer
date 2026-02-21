<?php
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../includes/models/Inventory.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../src/MailService.php';

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
    
    $available = ($item['quantity'] ?? 0) - ($item['quantity_borrowed'] ?? 0) - ($item['quantity_rented'] ?? 0);
    if ($available < $amount) {
        $_SESSION['rental_error'] = 'Nicht genügend Bestand verfügbar. Verfügbar: ' . max(0, $available);
        header('Location: view.php?id=' . $itemId);
        exit;
    }
    
    try {
        $db = Database::getContentDB();
        $db->beginTransaction();
        
        // Create rental record
        $stmt = $db->prepare("
            INSERT INTO rentals (user_id, item_id, amount, expected_return, purpose, status)
            VALUES (?, ?, ?, ?, ?, 'active')
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $itemId,
            $amount,
            $expectedReturn,
            $purpose
        ]);
        
        // Increment quantity_rented (track rented items without touching total stock)
        $stmt = $db->prepare("UPDATE inventory_items SET quantity_rented = COALESCE(quantity_rented, 0) + ? WHERE id = ?");
        $stmt->execute([$amount, $itemId]);
        
        // Log the change
        Inventory::logHistory(
            $itemId,
            $_SESSION['user_id'],
            'checkout',
            $item['quantity'],
            $item['quantity'],
            -$amount,
            'Ausgeliehen',
            $purpose
        );
        
        $db->commit();
        
        // Send notification email to board
        $borrowerEmail = $_SESSION['user_email'] ?? 'Unbekannt';
        $safeSubject = str_replace(["\r", "\n"], '', $item['name']);
        $emailBody = MailService::getTemplate(
            'Neue Ausleihe im Inventar',
            '<p class="email-text">Ein Mitglied hat einen Artikel aus dem Inventar ausgeliehen.</p>
            <table class="info-table">
                <tr><td>Artikel</td><td>' . htmlspecialchars($item['name']) . '</td></tr>
                <tr><td>Menge</td><td>' . htmlspecialchars($amount . ' ' . ($item['unit'] ?? 'Stück')) . '</td></tr>
                <tr><td>Ausgeliehen von</td><td>' . htmlspecialchars($borrowerEmail) . '</td></tr>
                <tr><td>Verwendungszweck</td><td>' . htmlspecialchars($purpose) . '</td></tr>
                <tr><td>Rückgabe bis</td><td>' . htmlspecialchars(date('d.m.Y', strtotime($expectedReturn))) . '</td></tr>
                <tr><td>Datum</td><td>' . date('d.m.Y H:i') . '</td></tr>
            </table>'
        );
        MailService::sendEmail(INVENTORY_BOARD_EMAIL, 'Neue Ausleihe: ' . $safeSubject, $emailBody);
        
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
            WHERE r.id = ? AND r.user_id = ? AND r.actual_return IS NULL AND r.status = 'active'
        ");
        $stmt->execute([$rentalId, $_SESSION['user_id']]);
        $rental = $stmt->fetch();
        
        if (!$rental) {
            $_SESSION['rental_error'] = 'Ausleihe nicht gefunden oder bereits zurückgegeben';
            header('Location: my_rentals.php');
            exit;
        }
        
        $db->beginTransaction();
        
        // Mark rental as pending confirmation (board must confirm the return)
        $stmt = $db->prepare("
            UPDATE rentals 
            SET status = 'pending_confirmation', 
                defect_notes = ?
            WHERE id = ?
        ");
        $stmt->execute([$defectNotes, $rentalId]);
        
        $db->commit();
        
        $_SESSION['rental_success'] = 'Rückgabe wurde als "Ausstehend" markiert. Ein Vorstandsmitglied wird die Rückgabe bestätigen.';
        
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
