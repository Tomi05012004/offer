<?php
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../includes/models/Inventory.php';

if (!Auth::check()) {
    header('Location: ../auth/login.php');
    exit;
}

$itemId = $_GET['id'] ?? null;
if (!$itemId) {
    header('Location: index.php');
    exit;
}

$item = Inventory::getById($itemId);
if (!$item) {
    header('Location: index.php');
    exit;
}

$message = '';
$error = '';

// Check for success messages from checkout
if (isset($_SESSION['checkout_success'])) {
    $message = $_SESSION['checkout_success'];
    unset($_SESSION['checkout_success']);
}

// Check for rental messages
if (isset($_SESSION['rental_success'])) {
    $message = $_SESSION['rental_success'];
    unset($_SESSION['rental_success']);
}

if (isset($_SESSION['rental_error'])) {
    $error = $_SESSION['rental_error'];
    unset($_SESSION['rental_error']);
}

// Handle stock adjustment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adjust_stock'])) {
    if (!Auth::hasPermission('manager')) {
        $error = 'Keine Berechtigung';
    } else {
        $amount = intval($_POST['amount'] ?? 0);
        $reason = $_POST['reason'] ?? '';
        $comment = $_POST['comment'] ?? '';
        
        if (empty($reason) || empty($comment)) {
            $error = 'Grund und Kommentar sind erforderlich';
        } else {
            if (Inventory::adjustStock($itemId, $amount, $reason, $comment, $_SESSION['user_id'])) {
                $message = 'Bestand erfolgreich aktualisiert';
                $item = Inventory::getById($itemId); // Reload item
            } else {
                $error = 'Fehler beim Aktualisieren des Bestands';
            }
        }
    }
}

/**
 * Helper function to format history comment/details
 * Handles JSON data smartly - showing changes or summary
 * 
 * @param string $data The comment/details data (plain text or JSON)
 * @return string HTML formatted output
 */
function formatHistoryComment($data) {
    if (empty($data)) {
        return '-';
    }
    
    // Try to decode as JSON
    $json = json_decode($data, true);
    
    // Check if JSON is valid and is an array
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($json)) {
        // Not valid JSON or not an array - return as plain text
        return htmlspecialchars($data);
    }
    
    // Filter out empty values and unwanted fields
    $filtered = array_filter($json, function($value, $key) {
        // Ignore empty values, image paths, and null values
        if (empty($value) && $value !== 0 && $value !== '0') return false;
        if ($key === 'image_path') return false;
        if ($key === 'original_data') return false; // Skip nested import data
        return true;
    }, ARRAY_FILTER_USE_BOTH);
    
    // If no meaningful data after filtering, show generic message
    if (empty($filtered)) {
        return '<span class="text-gray-500 dark:text-gray-400 italic">Details aktualisiert</span>';
    }
    
    // Check if this looks like a full snapshot (many fields typically used in an item)
    $snapshotFields = ['name', 'description', 'category_id', 'location_id', 'quantity', 
                       'min_stock', 'unit', 'unit_price', 'serial_number', 'notes', 'status'];
    $matchCount = count(array_intersect(array_keys($filtered), $snapshotFields));
    
    // If it has many item fields (>= 4), it's likely a full snapshot
    if ($matchCount >= 4) {
        return '<span class="text-gray-500 dark:text-gray-400 italic">Details aktualisiert</span>';
    }
    
    // Format as HTML definition list for changes
    $output = '<dl class="text-xs space-y-1">';
    
    foreach ($filtered as $key => $value) {
        // Format the key for display (convert snake_case to readable format)
        $displayKey = ucfirst(str_replace('_', ' ', $key));
        
        // Handle different value types
        if (is_array($value)) {
            // If array, check if it looks like old/new value pair
            if (isset($value['old']) && isset($value['new'])) {
                $oldValue = htmlspecialchars($value['old']);
                $newValue = htmlspecialchars($value['new']);
                $output .= '<div class="flex gap-2">';
                $output .= '<dt class="font-semibold text-gray-600 dark:text-gray-300 w-28 shrink-0">' . htmlspecialchars($displayKey) . ':</dt>';
                $output .= '<dd class="text-gray-800 dark:text-gray-100">' . $oldValue . ' → ' . $newValue . '</dd>';
                $output .= '</div>';
            } else {
                // Generic array display
                $output .= '<div class="flex gap-2">';
                $output .= '<dt class="font-semibold text-gray-600 dark:text-gray-300 w-28 shrink-0">' . htmlspecialchars($displayKey) . ':</dt>';
                $output .= '<dd class="text-gray-800 dark:text-gray-100">' . htmlspecialchars(json_encode($value)) . '</dd>';
                $output .= '</div>';
            }
        } else {
            // Simple value
            $output .= '<div class="flex gap-2">';
            $output .= '<dt class="font-semibold text-gray-600 dark:text-gray-300 w-28 shrink-0">' . htmlspecialchars($displayKey) . ':</dt>';
            $output .= '<dd class="text-gray-800 dark:text-gray-100">' . htmlspecialchars($value) . '</dd>';
            $output .= '</div>';
        }
    }
    
    $output .= '</dl>';
    return $output;
}

$history = Inventory::getHistory($itemId, 20);
$activeCheckouts = Inventory::getItemCheckouts($itemId);

$title = htmlspecialchars($item['name']) . ' - Inventar';
ob_start();
?>

<div class="mb-6">
    <a href="index.php" class="text-purple-600 hover:text-purple-700 dark:text-purple-400 dark:hover:text-purple-300 inline-flex items-center mb-4 text-lg font-semibold group transition-all">
        <i class="fas fa-arrow-left mr-2 group-hover:-translate-x-1 transition-transform"></i>Zurück zum Inventar
    </a>
</div>

<?php if ($message): ?>
<div class="mb-6 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
    <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
    <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error); ?>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Item Details -->
    <div class="lg:col-span-2">
        <div class="card p-8 shadow-xl border border-gray-200 dark:border-slate-700">
            <div class="flex items-start justify-between mb-6">
                <div>
                    <h1 class="text-4xl font-extrabold bg-gradient-to-r from-purple-600 to-blue-600 bg-clip-text text-transparent mb-3"><?php echo htmlspecialchars($item['name']); ?></h1>
                    <div class="flex flex-wrap gap-2">
                        <?php if ($item['category_name']): ?>
                        <span class="px-4 py-2 text-sm font-semibold rounded-full inline-color-badge shadow-md" style="background-color: <?php echo htmlspecialchars($item['category_color']); ?>20; color: <?php echo htmlspecialchars($item['category_color']); ?>">
                            <i class="fas fa-tag mr-1"></i><?php echo htmlspecialchars($item['category_name']); ?>
                        </span>
                        <?php endif; ?>
                        <?php if ($item['location_name']): ?>
                        <span class="px-4 py-2 text-sm font-semibold bg-gradient-to-r from-gray-100 to-gray-200 dark:from-gray-700 dark:to-gray-600 text-gray-700 dark:text-gray-300 rounded-full shadow-md">
                            <i class="fas fa-map-marker-alt mr-1"></i><?php echo htmlspecialchars($item['location_name']); ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if (Auth::hasPermission('manager')): ?>
                <div class="flex space-x-2">
                    <a href="edit.php?id=<?php echo $item['id']; ?>" class="btn-primary shadow-lg hover:shadow-xl transition-all transform hover:scale-105">
                        <i class="fas fa-edit mr-2"></i>Bearbeiten
                    </a>
                </div>
                <?php endif; ?>
            </div>

            <!-- Checkout/Borrow Button for all users -->
            <?php if ($item['quantity'] > 0): ?>
            <div class="mb-8 flex gap-3">
                <a href="checkout.php?id=<?php echo $item['id']; ?>" class="inline-flex items-center px-8 py-4 bg-gradient-to-r from-green-600 to-green-700 text-white rounded-xl hover:from-green-700 hover:to-green-800 transition-all transform hover:scale-105 font-bold shadow-lg text-lg">
                    <i class="fas fa-hand-holding-box mr-3"></i>Entnehmen / Ausleihen
                </a>
                <button onclick="openRentalModal()" class="inline-flex items-center px-8 py-4 bg-gradient-to-r from-purple-600 to-purple-700 text-white rounded-xl hover:from-purple-700 hover:to-purple-800 transition-all transform hover:scale-105 font-bold shadow-lg text-lg">
                    <i class="fas fa-calendar-alt mr-3"></i>Ausleihen (mit Rückgabedatum)
                </button>
            </div>
            <?php endif; ?>

            <!-- Image -->
            <?php if ($item['image_path']): ?>
            <div class="mb-8">
                <?php
                // Check if image is from EasyVerein and needs proxy
                $imageSrc = $item['image_path'];
                if (strpos($imageSrc, 'easyverein.com') !== false) {
                    // Use proxy for EasyVerein images
                    $imageSrc = '/api/easyverein_image.php?url=' . urlencode($imageSrc);
                } else {
                    // Local image - ensure leading slash
                    $imageSrc = '/' . ltrim($imageSrc, '/');
                }
                ?>
                <img src="<?php echo htmlspecialchars($imageSrc); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="w-full max-w-2xl rounded-2xl shadow-2xl border border-gray-200 dark:border-slate-700">
            </div>
            <?php endif; ?>

            <!-- Description -->
            <?php if ($item['description']): ?>
            <div class="mb-8 p-5 bg-gradient-to-br from-gray-50 to-gray-100 dark:from-slate-800 dark:to-slate-700 rounded-xl border border-gray-200 dark:border-slate-600">
                <h2 class="text-xl font-bold text-gray-800 dark:text-gray-100 mb-3 flex items-center">
                    <i class="fas fa-align-left mr-2 text-purple-600"></i>Beschreibung
                </h2>
                <p class="text-gray-700 dark:text-gray-300 leading-relaxed"><?php echo nl2br(htmlspecialchars($item['description'])); ?></p>
            </div>
            <?php endif; ?>

            <!-- Details Grid -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-5 mb-8">
                <div class="p-5 bg-gradient-to-br from-purple-50 to-purple-100 dark:from-purple-900/30 dark:to-purple-800/30 rounded-xl border border-purple-200 dark:border-purple-700 shadow-md">
                    <p class="text-sm text-purple-600 dark:text-purple-400 mb-2 font-semibold flex items-center">
                        <i class="fas fa-cubes mr-2"></i>Aktueller Bestand
                    </p>
                    <p class="text-3xl font-extrabold <?php echo $item['quantity'] <= $item['min_stock'] && $item['min_stock'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-800 dark:text-gray-100'; ?>">
                        <?php echo htmlspecialchars($item['quantity']); ?>
                    </p>
                    <p class="text-sm text-purple-600 dark:text-purple-400 font-medium mt-1"><?php echo htmlspecialchars($item['unit']); ?></p>
                </div>
                <div class="p-5 bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-900/30 dark:to-blue-800/30 rounded-xl border border-blue-200 dark:border-blue-700 shadow-md">
                    <p class="text-sm text-blue-600 dark:text-blue-400 mb-2 font-semibold flex items-center">
                        <i class="fas fa-layer-group mr-2"></i>Mindestbestand
                    </p>
                    <p class="text-3xl font-extrabold text-gray-800 dark:text-gray-100"><?php echo $item['min_stock']; ?></p>
                    <p class="text-sm text-blue-600 dark:text-blue-400 font-medium mt-1"><?php echo htmlspecialchars($item['unit']); ?></p>
                </div>
                <div class="p-5 bg-gradient-to-br from-green-50 to-green-100 dark:from-green-900/30 dark:to-green-800/30 rounded-xl border border-green-200 dark:border-green-700 shadow-md">
                    <p class="text-sm text-green-600 dark:text-green-400 mb-2 font-semibold flex items-center">
                        <i class="fas fa-euro-sign mr-2"></i>Stückpreis
                    </p>
                    <p class="text-3xl font-extrabold text-gray-800 dark:text-gray-100"><?php echo number_format($item['unit_price'], 2); ?> €</p>
                </div>
                <div class="p-5 bg-gradient-to-br from-orange-50 to-orange-100 dark:from-orange-900/30 dark:to-orange-800/30 rounded-xl border border-orange-200 dark:border-orange-700 shadow-md">
                    <p class="text-sm text-orange-600 dark:text-orange-400 mb-2 font-semibold flex items-center">
                        <i class="fas fa-coins mr-2"></i>Gesamtwert
                    </p>
                    <p class="text-3xl font-extrabold text-gray-800 dark:text-gray-100"><?php echo number_format($item['quantity'] * $item['unit_price'], 2); ?> €</p>
                </div>
            </div>

            <!-- Notes -->
            <?php if ($item['notes']): ?>
            <div class="bg-gradient-to-r from-yellow-50 to-amber-50 dark:from-yellow-900/20 dark:to-amber-900/20 border-l-4 border-yellow-400 dark:border-yellow-600 p-5 rounded-lg shadow-md">
                <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed"><strong class="text-yellow-700 dark:text-yellow-400 flex items-center mb-2"><i class="fas fa-sticky-note mr-2"></i>Notizen:</strong> <?php echo nl2br(htmlspecialchars($item['notes'])); ?></p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Stock Adjustment -->
    <div class="lg:col-span-1">
        <?php if (Auth::hasPermission('manager')): ?>
        <div class="card p-6 mb-6 shadow-xl border border-purple-200 dark:border-purple-700 bg-gradient-to-br from-white to-purple-50 dark:from-slate-800 dark:to-purple-900/20">
            <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-100 mb-4">
                <i class="fas fa-exchange-alt text-purple-600 mr-2"></i>
                Bestand anpassen
            </h2>
            <form method="POST">
                <input type="hidden" name="adjust_stock" value="1">
                
                <!-- Quick Buttons -->
                <div class="grid grid-cols-2 gap-2 mb-5">
                    <button type="button" onclick="setAmount(-10)" class="px-4 py-3 bg-gradient-to-br from-red-100 to-red-200 text-red-700 rounded-lg hover:from-red-200 hover:to-red-300 transition-all transform hover:scale-105 font-semibold shadow-md">
                        <i class="fas fa-minus mr-1"></i>10
                    </button>
                    <button type="button" onclick="setAmount(-1)" class="px-4 py-3 bg-gradient-to-br from-red-100 to-red-200 text-red-700 rounded-lg hover:from-red-200 hover:to-red-300 transition-all transform hover:scale-105 font-semibold shadow-md">
                        <i class="fas fa-minus mr-1"></i>1
                    </button>
                    <button type="button" onclick="setAmount(1)" class="px-4 py-3 bg-gradient-to-br from-green-100 to-green-200 text-green-700 rounded-lg hover:from-green-200 hover:to-green-300 transition-all transform hover:scale-105 font-semibold shadow-md">
                        <i class="fas fa-plus mr-1"></i>1
                    </button>
                    <button type="button" onclick="setAmount(10)" class="px-4 py-3 bg-gradient-to-br from-green-100 to-green-200 text-green-700 rounded-lg hover:from-green-200 hover:to-green-300 transition-all transform hover:scale-105 font-semibold shadow-md">
                        <i class="fas fa-plus mr-1"></i>10
                    </button>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Menge</label>
                    <input 
                        type="number" 
                        id="amount" 
                        name="amount" 
                        required 
                        class="w-full px-4 py-3 border-2 border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent dark:bg-gray-700 dark:text-gray-100 transition-all"
                        placeholder="z.B. +5 oder -3"
                    >
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Grund *</label>
                    <select 
                        name="reason" 
                        required 
                        class="w-full px-4 py-3 border-2 border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent dark:bg-gray-700 dark:text-gray-100 transition-all"
                    >
                        <option value="">Bitte wählen...</option>
                        <option value="Verliehen">Verliehen</option>
                        <option value="Zurückgegeben">Zurückgegeben</option>
                        <option value="Gekauft">Gekauft</option>
                        <option value="Verkauft">Verkauft</option>
                        <option value="Beschädigt">Beschädigt</option>
                        <option value="Verloren">Verloren</option>
                        <option value="Inventur">Inventur</option>
                        <option value="Sonstiges">Sonstiges</option>
                    </select>
                </div>

                <div class="mb-5">
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Kommentar *</label>
                    <textarea 
                        name="comment" 
                        required 
                        rows="3"
                        class="w-full px-4 py-3 border-2 border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent dark:bg-gray-700 dark:text-gray-100 transition-all"
                        placeholder="Bitte beschreiben Sie die Änderung..."
                    ></textarea>
                </div>

                <button type="submit" class="w-full btn-primary shadow-lg hover:shadow-xl transition-all transform hover:scale-105">
                    <i class="fas fa-check mr-2"></i>Bestätigen
                </button>
            </form>
        </div>
        <?php endif; ?>

        <!-- Quick Info -->
        <div class="card p-6 shadow-xl border border-blue-200 dark:border-blue-700 bg-gradient-to-br from-white to-blue-50 dark:from-slate-800 dark:to-blue-900/20">
            <h3 class="font-bold text-gray-800 dark:text-gray-100 mb-4 text-lg flex items-center">
                <i class="fas fa-info-circle text-blue-600 mr-2"></i>Information
            </h3>
            <div class="space-y-3 text-sm">
                <p class="text-gray-600 dark:text-gray-300 flex items-center p-3 bg-gray-50 dark:bg-slate-700/50 rounded-lg">
                    <i class="fas fa-calendar-alt text-purple-500 mr-3 text-lg"></i>
                    <span><strong>Erstellt:</strong><br><?php echo date('d.m.Y', strtotime($item['created_at'])); ?></span>
                </p>
                <p class="text-gray-600 dark:text-gray-300 flex items-center p-3 bg-gray-50 dark:bg-slate-700/50 rounded-lg">
                    <i class="fas fa-clock text-blue-500 mr-3 text-lg"></i>
                    <span><strong>Aktualisiert:</strong><br><?php echo date('d.m.Y', strtotime($item['updated_at'])); ?></span>
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Active Checkouts -->
<?php if (!empty($activeCheckouts)): ?>
<div class="card p-8 mt-8 shadow-xl border border-green-200 dark:border-green-700 bg-gradient-to-br from-white to-green-50 dark:from-slate-800 dark:to-green-900/10">
    <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-100 mb-6 flex items-center">
        <i class="fas fa-clipboard-list text-green-600 mr-3"></i>
        Aktive Ausleihen
    </h2>
    <div class="overflow-x-auto rounded-xl border border-green-200 dark:border-green-700">
        <table class="w-full">
            <thead class="bg-gradient-to-r from-green-100 to-emerald-100 dark:from-green-900/40 dark:to-emerald-900/40">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-bold text-green-700 dark:text-green-300 uppercase tracking-wider">Benutzer</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-green-700 dark:text-green-300 uppercase tracking-wider">Menge</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-green-700 dark:text-green-300 uppercase tracking-wider">Ausgeliehen am</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-green-700 dark:text-green-300 uppercase tracking-wider">Erwartete Rückgabe</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-green-700 dark:text-green-300 uppercase tracking-wider">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-green-200 dark:divide-green-700 bg-white dark:bg-slate-800">
                <?php foreach ($activeCheckouts as $checkout): ?>
                <tr class="hover:bg-green-50 dark:hover:bg-green-900/20 transition-colors">
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300 font-medium">
                        <?php echo htmlspecialchars($checkout['user_email'] ?? 'User ID: ' . $checkout['user_id']); ?>
                    </td>
                    <td class="px-6 py-4 text-sm">
                        <span class="font-bold text-lg text-gray-800 dark:text-gray-100"><?php echo $checkout['amount']; ?></span>
                        <span class="text-gray-500 dark:text-gray-400 ml-1"><?php echo htmlspecialchars($item['unit']); ?></span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300 font-medium">
                        <?php echo date('d.m.Y H:i', strtotime($checkout['rented_at'])); ?>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300 font-medium">
                        <?php echo $checkout['expected_return'] ? date('d.m.Y', strtotime($checkout['expected_return'])) : '-'; ?>
                    </td>
                    <td class="px-6 py-4 text-sm">
                        <span class="px-3 py-1.5 text-xs font-semibold rounded-full <?php echo $checkout['status'] === 'active' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' : 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-300'; ?>">
                            <?php echo htmlspecialchars($checkout['status']); ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- History -->
<div class="card p-8 mt-8 shadow-xl border border-gray-200 dark:border-slate-700">
    <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-100 mb-6 flex items-center">
        <i class="fas fa-history text-blue-600 mr-3"></i>
        Verlauf
    </h2>
    <?php if (empty($history)): ?>
    <p class="text-gray-500 dark:text-gray-400 text-center py-8">Keine Verlaufsdaten vorhanden</p>
    <?php else: ?>
    <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-slate-700">
        <table class="w-full">
            <thead class="bg-gradient-to-r from-gray-100 to-gray-200 dark:from-gray-700 dark:to-gray-600">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Datum</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Typ</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Änderung</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Grund</th>
                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">Kommentar</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-slate-800">
                <?php foreach ($history as $entry): ?>
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300 font-medium">
                        <?php echo date('d.m.Y H:i', strtotime($entry['created_at'])); ?>
                    </td>
                    <td class="px-6 py-4">
                        <?php
                        $typeClasses = [
                            'adjustment' => 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300',
                            'create' => 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
                            'update' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300',
                            'delete' => 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300',
                            'checkout' => 'bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300',
                            'checkin' => 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
                            'writeoff' => 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300'
                        ];
                        $badgeClass = $typeClasses[$entry['change_type']] ?? 'bg-gray-100 text-gray-700 dark:bg-gray-900 dark:text-gray-300';
                        $typeLabels = [
                            'adjustment' => 'Anpassung',
                            'create' => 'Erstellt',
                            'update' => 'Aktualisiert',
                            'delete' => 'Gelöscht',
                            'checkout' => 'Ausgeliehen',
                            'checkin' => 'Zurückgegeben',
                            'writeoff' => 'Ausschuss'
                        ];
                        $label = $typeLabels[$entry['change_type']] ?? $entry['change_type'];
                        ?>
                        <span class="px-3 py-1.5 text-xs font-semibold <?php echo $badgeClass; ?> rounded-full">
                            <?php echo $label; ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm">
                        <?php if ($entry['change_type'] === 'adjustment'): ?>
                        <span class="font-bold text-lg <?php echo $entry['change_amount'] >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'; ?>">
                            <?php echo ($entry['change_amount'] >= 0 ? '+' : '') . $entry['change_amount']; ?>
                        </span>
                        <span class="text-gray-500 dark:text-gray-400 text-xs ml-2">
                            (<?php echo $entry['old_stock']; ?> → <?php echo $entry['new_stock']; ?>)
                        </span>
                        <?php else: ?>
                        <span class="text-gray-500 dark:text-gray-400">-</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300 font-medium">
                        <?php echo htmlspecialchars($entry['reason'] ?? '-'); ?>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">
                        <?php 
                        // Use helper function to format history comment/details
                        $details = $entry['details'] ?? $entry['comment'] ?? '';
                        echo formatHistoryComment($details);
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Rental Modal -->
<div id="rentalModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-100">
                <i class="fas fa-calendar-check text-purple-600 mr-2"></i>
                Artikel ausleihen
            </h2>
            <button onclick="closeRentalModal()" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <form method="POST" action="rental.php" class="space-y-4">
            <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
            <input type="hidden" name="create_rental" value="1">

            <div class="bg-gray-50 dark:bg-gray-700 p-3 rounded-lg mb-4">
                <p class="font-semibold text-gray-800 dark:text-gray-100"><?php echo htmlspecialchars($item['name']); ?></p>
                <p class="text-sm text-gray-600 dark:text-gray-300">Verfügbar: <?php echo htmlspecialchars($item['quantity']); ?> <?php echo htmlspecialchars($item['unit']); ?></p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Anzahl <span class="text-red-500">*</span>
                </label>
                <input 
                    type="number" 
                    name="amount" 
                    required 
                    min="1" 
                    max="<?php echo htmlspecialchars($item['quantity']); ?>"
                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 dark:bg-gray-700 dark:text-gray-100"
                    placeholder="Anzahl eingeben"
                >
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Voraussichtliches Rückgabedatum <span class="text-red-500">*</span>
                </label>
                <input 
                    type="date" 
                    name="expected_return" 
                    required
                    min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"
                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 dark:bg-gray-700 dark:text-gray-100"
                >
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Verwendungszweck <span class="text-red-500">*</span>
                </label>
                <textarea 
                    name="purpose" 
                    required 
                    rows="3"
                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 dark:bg-gray-700 dark:text-gray-100"
                    placeholder="Wofür benötigen Sie diesen Artikel?"
                ></textarea>
            </div>

            <div class="flex gap-3 pt-4">
                <button type="button" onclick="closeRentalModal()" class="flex-1 px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition">
                    Abbrechen
                </button>
                <button type="submit" class="flex-1 px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition">
                    <i class="fas fa-check mr-2"></i>Ausleihen
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function setAmount(value) {
    document.getElementById('amount').value = value;
}

function openRentalModal() {
    document.getElementById('rentalModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden'; // Prevent background scrolling
}

function closeRentalModal() {
    document.getElementById('rentalModal').classList.add('hidden');
    document.body.style.overflow = 'auto'; // Re-enable scrolling
}

// Close modal when clicking outside
document.getElementById('rentalModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeRentalModal();
    }
});

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeRentalModal();
    }
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../includes/templates/main_layout.php';
