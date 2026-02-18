<?php
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../includes/models/Inventory.php';

if (!Auth::check()) {
    header('Location: ../auth/login.php');
    exit;
}

$activeCheckouts = Inventory::getUserCheckouts($_SESSION['user_id'], false);
$allCheckouts = Inventory::getUserCheckouts($_SESSION['user_id'], true);

// Check for success messages
$successMessage = $_SESSION['checkin_success'] ?? null;
unset($_SESSION['checkin_success']);

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
            <p class="text-gray-600"><?php echo count($activeCheckouts); ?> aktive Ausleihen</p>
        </div>
        <div class="mt-4 md:mt-0">
            <a href="index.php" class="btn-primary inline-block">
                <i class="fas fa-box mr-2"></i>
                Zum Inventar
            </a>
        </div>
    </div>
</div>

<?php if ($successMessage): ?>
<div class="mb-6 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
    <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($successMessage); ?>
</div>
<?php endif; ?>

<!-- Active Checkouts -->
<div class="card p-6 mb-6">
    <h2 class="text-xl font-bold text-gray-800 mb-4">
        <i class="fas fa-hourglass-half text-blue-600 mr-2"></i>
        Aktive Ausleihen
    </h2>
    
    <?php if (empty($activeCheckouts)): ?>
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
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Erwartete Rückgabe</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aktion</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($activeCheckouts as $checkout): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3">
                        <a href="view.php?id=<?php echo $checkout['item_id']; ?>" class="font-semibold text-purple-600 hover:text-purple-800">
                            <?php echo htmlspecialchars($checkout['item_name']); ?>
                        </a>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600">
                        <span class="font-semibold"><?php echo $checkout['amount']; ?></span> <?php echo htmlspecialchars($checkout['unit']); ?>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600">
                        <?php echo date('d.m.Y H:i', strtotime($checkout['rented_at'])); ?>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600">
                        <?php echo $checkout['expected_return'] ? date('d.m.Y', strtotime($checkout['expected_return'])) : '-'; ?>
                    </td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-1 text-xs rounded-full <?php echo $checkout['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-orange-100 text-orange-800'; ?>">
                            <?php echo htmlspecialchars($checkout['status']); ?>
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        <a href="checkin.php?id=<?php echo $checkout['id']; ?>" class="inline-flex items-center px-3 py-1 bg-green-600 text-white rounded hover:bg-green-700 transition text-sm">
                            <i class="fas fa-undo mr-1"></i>Zurückgeben
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- History -->
<?php
$returnedCheckouts = array_filter($allCheckouts, function($c) {
    return $c['status'] === 'returned' || $c['status'] === 'defective';
});
?>

<?php if (!empty($returnedCheckouts)): ?>
<div class="card p-6">
    <h2 class="text-xl font-bold text-gray-800 mb-4">
        <i class="fas fa-history text-gray-600 mr-2"></i>
        Verlauf (Zurückgegebene Ausleihen)
    </h2>
    
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Artikel</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Menge</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ausgeliehen</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Zurückgegeben</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($returnedCheckouts as $checkout): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3">
                        <a href="view.php?id=<?php echo $checkout['item_id']; ?>" class="font-semibold text-purple-600 hover:text-purple-800">
                            <?php echo htmlspecialchars($checkout['item_name']); ?>
                        </a>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600">
                        <span class="font-semibold"><?php echo $checkout['amount']; ?></span> <?php echo htmlspecialchars($checkout['unit']); ?>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600">
                        <?php echo date('d.m.Y', strtotime($checkout['rented_at'])); ?>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600">
                        <?php echo $checkout['actual_return'] ? date('d.m.Y', strtotime($checkout['actual_return'])) : '-'; ?>
                    </td>
                    <td class="px-4 py-3">
                        <?php if ($checkout['status'] === 'defective'): ?>
                        <span class="px-2 py-1 text-xs bg-red-100 text-red-700 rounded-full" title="<?php echo htmlspecialchars($checkout['defect_notes'] ?? ''); ?>">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            Defekt
                        </span>
                        <?php else: ?>
                        <span class="px-2 py-1 text-xs bg-green-100 text-green-700 rounded-full">
                            <i class="fas fa-check mr-1"></i>OK
                        </span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../includes/templates/main_layout.php';
