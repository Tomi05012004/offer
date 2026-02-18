<?php
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../includes/database.php';

if (!Auth::check()) {
    header('Location: ../auth/login.php');
    exit;
}

// Get user's rentals
function getUserRentals($userId, $includeReturned = false) {
    $db = Database::getContentDB();
    $sql = "
        SELECT r.*, r.created_at as rented_at, i.name as item_name, i.unit, i.image_path
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

$activeRentals = getUserRentals($_SESSION['user_id'], false);
$allRentals = getUserRentals($_SESSION['user_id'], true);

// Check for success messages
$successMessage = $_SESSION['rental_success'] ?? null;
unset($_SESSION['rental_success']);

$errorMessage = $_SESSION['rental_error'] ?? null;
unset($_SESSION['rental_error']);

$title = 'Meine Ausleihen - IBC Intranet';
ob_start();
?>

<div class="mb-8">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-800 mb-2">
                <i class="fas fa-clipboard-list text-purple-600 mr-2"></i>
                Meine Ausleihen
            </h1>
            <p class="text-gray-600"><?php echo count($activeRentals); ?> aktive Ausleihen</p>
        </div>
        <div class="mt-4 md:mt-0">
            <a href="index.php" class="btn-primary inline-block">
                <i class="fas fa-plus-circle mr-2"></i>
                Neuen Gegenstand ausleihen
            </a>
        </div>
    </div>
</div>

<?php if ($successMessage): ?>
<div class="mb-6 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
    <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($successMessage); ?>
</div>
<?php endif; ?>

<?php if ($errorMessage): ?>
<div class="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
    <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($errorMessage); ?>
</div>
<?php endif; ?>

<!-- Active Rentals -->
<div class="card p-6 mb-6">
    <h2 class="text-xl font-bold text-gray-800 mb-4">
        <i class="fas fa-hourglass-half text-blue-600 mr-2"></i>
        Aktive Ausleihen
    </h2>
    
    <?php if (empty($activeRentals)): ?>
    <div class="text-center py-8">
        <i class="fas fa-inbox text-6xl text-gray-300 mb-4"></i>
        <p class="text-gray-500 text-lg mb-4">Keine aktiven Ausleihen</p>
        <a href="index.php" class="btn-primary inline-block">
            <i class="fas fa-search mr-2"></i>Artikel ausleihen
        </a>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Artikel</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Menge</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ausgeliehen am</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rückgabe bis</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aktion</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($activeRentals as $rental): ?>
                <?php
                $isOverdue = strtotime($rental['expected_return']) < time();
                $daysRemaining = ceil((strtotime($rental['expected_return']) - time()) / (60 * 60 * 24));
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3">
                        <a href="view.php?id=<?php echo $rental['item_id']; ?>" class="font-semibold text-purple-600 hover:text-purple-800">
                            <?php echo htmlspecialchars($rental['item_name']); ?>
                        </a>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600">
                        <span class="font-semibold"><?php echo $rental['amount']; ?></span> <?php echo htmlspecialchars($rental['unit']); ?>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600">
                        <?php echo date('d.m.Y', strtotime($rental['rented_at'])); ?>
                    </td>
                    <td class="px-4 py-3 text-sm">
                        <?php if ($rental['expected_return']): ?>
                            <span class="<?php echo $isOverdue ? 'text-red-600 font-semibold' : 'text-gray-600'; ?>">
                                <?php echo date('d.m.Y', strtotime($rental['expected_return'])); ?>
                            </span>
                            <?php if ($isOverdue): ?>
                                <span class="block text-xs text-red-500">Überfällig!</span>
                            <?php elseif ($daysRemaining <= 3): ?>
                                <span class="block text-xs text-orange-500">Bald fällig (<?php echo $daysRemaining; ?> Tage)</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-gray-400">-</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3">
                        <?php if ($isOverdue): ?>
                            <span class="px-2 py-1 text-xs bg-red-100 text-red-700 rounded-full">
                                Überfällig
                            </span>
                        <?php else: ?>
                            <span class="px-2 py-1 text-xs bg-green-100 text-green-700 rounded-full">
                                Aktiv
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3">
                        <button onclick="openReturnModal(<?php echo $rental['id']; ?>, '<?php echo htmlspecialchars($rental['item_name'], ENT_QUOTES); ?>', <?php echo $rental['amount']; ?>, '<?php echo htmlspecialchars($rental['unit'], ENT_QUOTES); ?>')" 
                                class="inline-flex items-center px-3 py-1 bg-green-600 text-white rounded hover:bg-green-700 transition text-sm">
                            <i class="fas fa-undo mr-1"></i>Zurückgeben
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- All Rentals History -->
<div class="card p-6">
    <h2 class="text-xl font-bold text-gray-800 mb-4">
        <i class="fas fa-history text-gray-600 mr-2"></i>
        Verlauf
    </h2>
    
    <?php if (empty($allRentals)): ?>
    <p class="text-gray-500 text-center py-4">Keine Ausleihen vorhanden</p>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Artikel</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Menge</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ausgeliehen am</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Zurückgegeben am</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($allRentals as $rental): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3">
                        <a href="view.php?id=<?php echo $rental['item_id']; ?>" class="font-semibold text-purple-600 hover:text-purple-800">
                            <?php echo htmlspecialchars($rental['item_name']); ?>
                        </a>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600">
                        <span class="font-semibold"><?php echo $rental['amount']; ?></span> <?php echo htmlspecialchars($rental['unit']); ?>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600">
                        <?php echo date('d.m.Y', strtotime($rental['rented_at'])); ?>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600">
                        <?php echo $rental['actual_return'] ? date('d.m.Y', strtotime($rental['actual_return'])) : '-'; ?>
                    </td>
                    <td class="px-4 py-3">
                        <?php
                        $statusColors = [
                            'active' => 'green',
                            'returned' => 'blue',
                            'overdue' => 'red',
                            'defective' => 'red'
                        ];
                        $statusLabels = [
                            'active' => 'Aktiv',
                            'returned' => 'Zurückgegeben',
                            'overdue' => 'Überfällig',
                            'defective' => 'Defekt'
                        ];
                        $statusClasses = [
                            'active' => 'bg-green-100 text-green-700',
                            'returned' => 'bg-blue-100 text-blue-700',
                            'overdue' => 'bg-red-100 text-red-700',
                            'defective' => 'bg-red-100 text-red-700'
                        ];
                        $statusClass = $statusClasses[$rental['status']] ?? 'bg-gray-100 text-gray-700';
                        $label = $statusLabels[$rental['status']] ?? htmlspecialchars($rental['status']);
                        ?>
                        <span class="px-2 py-1 text-xs <?php echo $statusClass; ?> rounded-full">
                            <?php echo $label; ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Return Modal -->
<div id="returnModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-2xl font-bold text-gray-800">
                <i class="fas fa-undo text-green-600 mr-2"></i>
                Artikel zurückgeben
            </h2>
            <button onclick="closeReturnModal()" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <form method="POST" action="rental.php" class="space-y-4">
            <input type="hidden" name="rental_id" id="return_rental_id" value="">
            <input type="hidden" name="return_rental" value="1">

            <div class="bg-gray-50 p-3 rounded-lg mb-4">
                <p class="font-semibold text-gray-800" id="return_item_name"></p>
                <p class="text-sm text-gray-600">Menge: <span id="return_amount"></span> <span id="return_unit"></span></p>
            </div>

            <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-4">
                <p class="text-sm text-blue-800">
                    <i class="fas fa-info-circle mr-2"></i>
                    <strong>Alles in Ordnung?</strong>
                </p>
                <p class="text-sm text-blue-700 mt-1">
                    Wenn der Artikel beschädigt oder defekt ist, bitte unten ankreuzen.
                </p>
            </div>

            <div class="flex items-center mb-4">
                <input 
                    type="checkbox" 
                    id="is_defective" 
                    name="is_defective" 
                    value="yes"
                    onchange="toggleDefectiveSection()"
                    class="w-4 h-4 text-purple-600 bg-white border-gray-300 rounded focus:ring-blue-500 dark:bg-gray-800 dark:border-gray-600 dark:focus:ring-blue-500"
                >
                <label for="is_defective" class="ml-2 text-sm font-medium text-gray-700">
                    Artikel ist beschädigt/defekt
                </label>
            </div>

            <div id="defective_section" class="hidden space-y-3">
                <div class="bg-yellow-50 border-l-4 border-yellow-400 p-3">
                    <p class="text-sm text-yellow-800">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        Der Artikel wird als Ausschuss markiert und nicht zum Bestand hinzugefügt.
                    </p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Beschreibung des Mangels <span class="text-red-500">*</span>
                    </label>
                    <textarea 
                        name="defect_notes" 
                        id="defect_notes"
                        rows="4"
                        class="w-full px-4 py-2 bg-white border-gray-300 text-gray-900 rounded-lg focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                        placeholder="Bitte beschreiben Sie den Defekt oder Schaden..."
                    ></textarea>
                </div>
            </div>

            <div class="flex gap-3 pt-4">
                <button type="button" onclick="closeReturnModal()" class="flex-1 px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                    Abbrechen
                </button>
                <button type="submit" class="flex-1 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                    <i class="fas fa-check mr-2"></i>Zurückgeben
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openReturnModal(rentalId, itemName, amount, unit) {
    document.getElementById('return_rental_id').value = rentalId;
    document.getElementById('return_item_name').textContent = itemName;
    document.getElementById('return_amount').textContent = amount;
    document.getElementById('return_unit').textContent = unit;
    document.getElementById('returnModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeReturnModal() {
    document.getElementById('returnModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
    // Reset form
    document.getElementById('is_defective').checked = false;
    document.getElementById('defective_section').classList.add('hidden');
    document.getElementById('defect_notes').value = '';
    document.getElementById('defect_notes').removeAttribute('required');
}

function toggleDefectiveSection() {
    const checkbox = document.getElementById('is_defective');
    const section = document.getElementById('defective_section');
    const textarea = document.getElementById('defect_notes');
    
    if (checkbox.checked) {
        section.classList.remove('hidden');
        textarea.setAttribute('required', 'required');
    } else {
        section.classList.add('hidden');
        textarea.removeAttribute('required');
    }
}

// Close modal when clicking outside
document.getElementById('returnModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeReturnModal();
    }
});

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeReturnModal();
    }
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../includes/templates/main_layout.php';
