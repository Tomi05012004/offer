<?php
/**
 * Admin Inventory Dashboard
 * Shows all active rentals and provides CSV export
 */

require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../includes/models/Inventory.php';

if (!Auth::check() || !Auth::isBoard()) {
    header('Location: ../auth/login.php');
    exit;
}

// Check if user can confirm returns (board roles + head)
$canConfirmReturns = Auth::hasRole(['board_finance', 'board_internal', 'board_external', 'head']);

// Handle return confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_return'])) {
    if (!$canConfirmReturns) {
        $confirmError = 'Keine Berechtigung zum Bestätigen von Rückgaben';
    } else {
        $rentalId = intval($_POST['rental_id'] ?? 0);
        if ($rentalId > 0) {
            $result = Inventory::confirmReturn($rentalId, $_SESSION['user_id']);
            if ($result['success']) {
                $confirmSuccess = $result['message'];
            } else {
                $confirmError = $result['message'];
            }
        }
    }
}

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $db = Database::getContentDB();
    $stmt = $db->query("
        SELECT 
            r.id,
            r.amount,
            r.purpose,
            r.destination,
            r.checkout_date,
            r.expected_return,
            r.created_at,
            i.name AS item_name,
            i.unit,
            r.user_id,
            r.status
        FROM rentals r
        JOIN inventory_items i ON r.item_id = i.id
        WHERE r.actual_return IS NULL
        AND r.status IN ('active', 'pending_confirmation')
        ORDER BY r.created_at DESC
    ");
    $rentals = $stmt->fetchAll();

    // Fetch user emails
    $userEmails = [];
    if (!empty($rentals)) {
        $userDb = Database::getUserDB();
        $userIds = array_unique(array_column($rentals, 'user_id'));
        $placeholders = str_repeat('?,', count($userIds) - 1) . '?';
        $userStmt = $userDb->prepare("SELECT id, email FROM users WHERE id IN ($placeholders)");
        $userStmt->execute($userIds);
        foreach ($userStmt->fetchAll() as $u) {
            $userEmails[$u['id']] = $u['email'];
        }
    }

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="ausleihen_' . date('Y-m-d') . '.csv"');
    header('Cache-Control: no-cache, no-store, must-revalidate');

    $out = fopen('php://output', 'w');
    // UTF-8 BOM for Excel compatibility
    fputs($out, "\xEF\xBB\xBF");
    fputcsv($out, ['ID', 'Benutzer', 'Artikel', 'Menge', 'Einheit', 'Verwendungszweck', 'Zielort', 'Ausgeliehen am', 'Rückgabe bis'], ';');

    foreach ($rentals as $r) {
        fputcsv($out, [
            $r['id'],
            $userEmails[$r['user_id']] ?? 'Unbekannt',
            $r['item_name'],
            $r['amount'],
            $r['unit'],
            $r['purpose'] ?? '',
            $r['destination'] ?? '',
            $r['checkout_date'] ? date('d.m.Y H:i', strtotime($r['checkout_date'])) : date('d.m.Y H:i', strtotime($r['created_at'])),
            $r['expected_return'] ? date('d.m.Y', strtotime($r['expected_return'])) : '',
        ], ';');
    }

    fclose($out);
    exit;
}

// Fetch all active rentals
$checkedOutStats = Inventory::getCheckedOutStats();
$activeRentals = array_filter($checkedOutStats['checkouts'], function($r) {
    return $r['status'] === 'active';
});

// Fetch pending confirmation rentals
$pendingRentals = [];
$db = Database::getContentDB();
$pendingStmt = $db->query("
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
    AND r.status = 'pending_confirmation'
    ORDER BY r.created_at DESC
");
$pendingRentalsRaw = $pendingStmt->fetchAll();

if (!empty($pendingRentalsRaw)) {
    $userDb = Database::getUserDB();
    $userIds = array_unique(array_column($pendingRentalsRaw, 'user_id'));
    $placeholders = str_repeat('?,', count($userIds) - 1) . '?';
    $userStmt = $userDb->prepare("SELECT id, email FROM users WHERE id IN ($placeholders)");
    $userStmt->execute($userIds);
    $users = [];
    foreach ($userStmt->fetchAll() as $u) {
        $users[$u['id']] = $u['email'];
    }
    foreach ($pendingRentalsRaw as &$pr) {
        $pr['borrower_email'] = $users[$pr['user_id']] ?? 'Unbekannt';
    }
    $pendingRentals = $pendingRentalsRaw;
}

$title = 'Inventar-Dashboard - IBC Intranet';
ob_start();
?>

<div class="mb-8">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-800 mb-2">
                <i class="fas fa-boxes text-purple-600 mr-2"></i>
                Inventar-Dashboard
            </h1>
            <p class="text-gray-600">Übersicht aller aktiven Ausleihen</p>
        </div>
        <div class="mt-4 md:mt-0 flex gap-3">
            <a href="?export=csv" class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-medium">
                <i class="fas fa-file-csv mr-2"></i>
                CSV Export
            </a>
            <a href="../inventory/index.php" class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition font-medium">
                <i class="fas fa-arrow-left mr-2"></i>
                Zum Inventar
            </a>
        </div>
    </div>
</div>

<?php if (!empty($confirmSuccess)): ?>
<div class="mb-6 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
    <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($confirmSuccess); ?>
</div>
<?php endif; ?>

<?php if (!empty($confirmError)): ?>
<div class="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
    <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($confirmError); ?>
</div>
<?php endif; ?>

<!-- Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
    <div class="card p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500 mb-1">Aktive Ausleihen</p>
                <p class="text-3xl font-bold text-gray-800"><?php echo count($activeRentals); ?></p>
            </div>
            <div class="p-3 bg-purple-100 rounded-full">
                <i class="fas fa-hand-holding-box text-purple-600 text-2xl"></i>
            </div>
        </div>
    </div>
    <div class="card p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500 mb-1">Ausgeliehene Artikel gesamt</p>
                <p class="text-3xl font-bold text-gray-800"><?php echo $checkedOutStats['total_items_out']; ?></p>
            </div>
            <div class="p-3 bg-blue-100 rounded-full">
                <i class="fas fa-cubes text-blue-600 text-2xl"></i>
            </div>
        </div>
    </div>
    <div class="card p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500 mb-1">Rückgabe ausstehend</p>
                <p class="text-3xl font-bold <?php echo count($pendingRentals) > 0 ? 'text-yellow-600' : 'text-gray-800'; ?>">
                    <?php echo count($pendingRentals); ?>
                </p>
            </div>
            <div class="p-3 <?php echo count($pendingRentals) > 0 ? 'bg-yellow-100' : 'bg-gray-100'; ?> rounded-full">
                <i class="fas fa-clock <?php echo count($pendingRentals) > 0 ? 'text-yellow-600' : 'text-gray-400'; ?> text-2xl"></i>
            </div>
        </div>
    </div>
    <div class="card p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500 mb-1">Überfällig</p>
                <p class="text-3xl font-bold <?php echo $checkedOutStats['overdue'] > 0 ? 'text-red-600' : 'text-gray-800'; ?>">
                    <?php echo $checkedOutStats['overdue']; ?>
                </p>
            </div>
            <div class="p-3 <?php echo $checkedOutStats['overdue'] > 0 ? 'bg-red-100' : 'bg-gray-100'; ?> rounded-full">
                <i class="fas fa-exclamation-triangle <?php echo $checkedOutStats['overdue'] > 0 ? 'text-red-600' : 'text-gray-400'; ?> text-2xl"></i>
            </div>
        </div>
    </div>
</div>

<!-- Pending Return Confirmations Table -->
<?php if (!empty($pendingRentals)): ?>
<div class="card p-6 mb-8 border-2 border-yellow-300">
    <h2 class="text-xl font-bold text-gray-800 mb-4">
        <i class="fas fa-clock text-yellow-600 mr-2"></i>
        Rückgaben ausstehend (<?php echo count($pendingRentals); ?>)
    </h2>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-yellow-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Benutzer</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Artikel</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Menge</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ausgeliehen am</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rückgabe bis</th>
                    <?php if ($canConfirmReturns): ?>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aktion</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($pendingRentals as $rental): ?>
                <tr class="hover:bg-yellow-50 bg-yellow-50/50">
                    <td class="px-4 py-3 text-sm text-gray-800">
                        <i class="fas fa-user text-gray-400 mr-1"></i>
                        <?php echo htmlspecialchars($rental['borrower_email'] ?? 'Unbekannt'); ?>
                    </td>
                    <td class="px-4 py-3 text-sm">
                        <a href="../inventory/view.php?id=<?php echo $rental['item_id']; ?>" class="font-semibold text-purple-600 hover:text-purple-800">
                            <?php echo htmlspecialchars($rental['item_name']); ?>
                        </a>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600">
                        <span class="font-semibold"><?php echo $rental['amount']; ?></span> <?php echo htmlspecialchars($rental['unit']); ?>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600">
                        <?php echo date('d.m.Y H:i', strtotime($rental['rented_at'])); ?>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600">
                        <?php echo !empty($rental['expected_return']) ? date('d.m.Y', strtotime($rental['expected_return'])) : '-'; ?>
                    </td>
                    <?php if ($canConfirmReturns): ?>
                    <td class="px-4 py-3 text-sm">
                        <form method="POST" onsubmit="return confirm('Rückgabe für ' + <?php echo json_encode($rental['item_name']); ?> + ' bestätigen?');">
                            <input type="hidden" name="confirm_return" value="1">
                            <input type="hidden" name="rental_id" value="<?php echo $rental['id']; ?>">
                            <button type="submit" class="inline-flex items-center px-3 py-1.5 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 transition text-sm font-medium">
                                <i class="fas fa-check mr-1"></i>Rückgabe bestätigen
                            </button>
                        </form>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Active Rentals Table -->
<div class="card p-6">
    <h2 class="text-xl font-bold text-gray-800 mb-4">
        <i class="fas fa-clipboard-list text-blue-600 mr-2"></i>
        Aktive Ausleihen
    </h2>

    <?php if (empty($activeRentals)): ?>
    <div class="text-center py-12">
        <i class="fas fa-inbox text-6xl text-gray-300 mb-4"></i>
        <p class="text-gray-500 text-lg">Keine aktiven Ausleihen vorhanden</p>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Benutzer</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Artikel</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Menge</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ausgeliehen am</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rückgabe bis</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($activeRentals as $rental): ?>
                <?php
                $isOverdue = !empty($rental['expected_return']) && strtotime($rental['expected_return']) < time();
                ?>
                <tr class="hover:bg-gray-50 <?php echo $isOverdue ? 'bg-red-50' : ''; ?>">
                    <td class="px-4 py-3 text-sm text-gray-800">
                        <i class="fas fa-user text-gray-400 mr-1"></i>
                        <?php echo htmlspecialchars($rental['borrower_email'] ?? 'Unbekannt'); ?>
                    </td>
                    <td class="px-4 py-3 text-sm">
                        <a href="../inventory/view.php?id=<?php echo $rental['item_id']; ?>" class="font-semibold text-purple-600 hover:text-purple-800">
                            <?php echo htmlspecialchars($rental['item_name']); ?>
                        </a>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600">
                        <span class="font-semibold"><?php echo $rental['amount']; ?></span> <?php echo htmlspecialchars($rental['unit']); ?>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600">
                        <?php echo date('d.m.Y H:i', strtotime($rental['rented_at'])); ?>
                    </td>
                    <td class="px-4 py-3 text-sm">
                        <?php if (!empty($rental['expected_return'])): ?>
                            <span class="<?php echo $isOverdue ? 'text-red-600 font-semibold' : 'text-gray-600'; ?>">
                                <?php echo date('d.m.Y', strtotime($rental['expected_return'])); ?>
                            </span>
                            <?php if ($isOverdue): ?>
                                <span class="block text-xs text-red-500">Überfällig!</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-gray-400">-</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-sm">
                        <?php if ($isOverdue): ?>
                            <span class="px-2 py-1 text-xs bg-red-100 text-red-700 rounded-full">Überfällig</span>
                        <?php else: ?>
                            <span class="px-2 py-1 text-xs bg-green-100 text-green-700 rounded-full">Aktiv</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../includes/templates/main_layout.php';
