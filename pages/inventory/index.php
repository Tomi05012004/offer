<?php
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../includes/handlers/AuthHandler.php';
require_once __DIR__ . '/../../includes/models/Inventory.php';

if (!Auth::check()) {
    header('Location: ../auth/login.php');
    exit;
}

// Handle JSON import
$importMessage = null;
$importSuccess = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_json']) && Auth::hasPermission('manager')) {
    if (isset($_FILES['json_file']) && $_FILES['json_file']['error'] === UPLOAD_ERR_OK) {
        $jsonContent = file_get_contents($_FILES['json_file']['tmp_name']);
        $data = json_decode($jsonContent, true);
        
        if ($data === null) {
            $importMessage = 'Fehler: Ungültiges JSON-Format';
            $importSuccess = false;
        } else {
            $result = Inventory::importFromJson($data, $_SESSION['user_id']);
            
            if ($result['success']) {
                $importMessage = "Import erfolgreich! {$result['imported']} Artikel importiert";
                if ($result['skipped'] > 0) {
                    $importMessage .= ", {$result['skipped']} übersprungen";
                }
                $importSuccess = true;
            } else {
                $importMessage = "Import fehlgeschlagen";
                $importSuccess = false;
            }
            
            // Store errors in session for display
            if (!empty($result['errors'])) {
                $_SESSION['import_errors'] = $result['errors'];
            }
        }
    } else {
        $importMessage = 'Fehler: Keine Datei hochgeladen oder Upload-Fehler';
        $importSuccess = false;
    }
}

// Get import errors from session and clear them
$importErrors = $_SESSION['import_errors'] ?? [];
unset($_SESSION['import_errors']);

// Get sync results from session and clear them
$syncResult = $_SESSION['sync_result'] ?? null;
unset($_SESSION['sync_result']);

// Get filters
$filters = [];
if (!empty($_GET['category_id'])) {
    $filters['category_id'] = $_GET['category_id'];
}
if (!empty($_GET['location'])) {
    $filters['location'] = $_GET['location'];
}
if (!empty($_GET['search'])) {
    $filters['search'] = $_GET['search'];
}
if (isset($_GET['filter']) && $_GET['filter'] === 'low_stock') {
    $filters['low_stock'] = true;
}

// Get sort parameter
$sort = $_GET['sort'] ?? 'name_asc';
$filters['sort'] = $sort;

$items = Inventory::getAll($filters);
$categories = Inventory::getCategories();
$locations = Inventory::getLocations();

// Get distinct locations dynamically for the filter dropdown
// Note: Uses INNER JOIN to only show locations that are currently assigned to inventory items
$db = Database::getContentDB();
$locationsQuery = $db->query("
    SELECT DISTINCT l.name 
    FROM inventory_items i
    JOIN locations l ON i.location_id = l.id
    WHERE l.name IS NOT NULL AND l.name != '' 
    ORDER BY l.name ASC
");
$distinctLocations = $locationsQuery->fetchAll(PDO::FETCH_COLUMN);

$title = 'Inventar - IBC Intranet';
ob_start();
?>

<div class="mb-8">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-4xl font-extrabold bg-gradient-to-r from-purple-600 to-blue-600 bg-clip-text text-transparent mb-2">
                <i class="fas fa-boxes text-purple-600 mr-3"></i>
                Inventar
            </h1>
            <p class="text-slate-600 dark:text-slate-400 text-lg"><?php echo count($items); ?> Artikel gefunden</p>
        </div>
        <!-- Action Buttons -->
        <div class="mt-4 md:mt-0 flex gap-2 flex-wrap">
            <!-- Neuer Artikel Button -->
            <a href="add.php" class="px-5 py-3 bg-gradient-to-r from-green-600 to-green-700 text-white rounded-xl hover:from-green-700 hover:to-green-800 transition-all transform hover:scale-105 flex items-center shadow-lg font-semibold">
                <i class="fas fa-plus mr-2"></i>
                Neuer Artikel
            </a>
            <!-- EasyVerein Sync Button - Admin/Board only -->
            <?php if (AuthHandler::isAdmin()): ?>
            <a href="sync.php" class="bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white px-5 py-3 rounded-xl flex items-center shadow-lg font-semibold transition-all transform hover:scale-105">
                <i class="fas fa-sync-alt mr-2"></i> EasyVerein Sync
            </a>
            <?php endif; ?>
            <!-- Import Button - Manager level and above -->
            <?php if (Auth::hasPermission('manager')): ?>
            <button type="button" onclick="document.getElementById('importModal').classList.remove('hidden')" class="px-5 py-3 bg-gradient-to-r from-purple-600 to-purple-700 text-white rounded-xl hover:from-purple-700 hover:to-purple-800 transition-all transform hover:scale-105 shadow-lg font-semibold">
                <i class="fas fa-file-import mr-2"></i>
                Massenimport
            </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Import Success/Error Messages -->
<?php if ($importMessage): ?>
<div class="mb-6 p-4 rounded-lg <?php echo $importSuccess ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-red-100 border border-red-400 text-red-700'; ?>">
    <div class="flex items-start">
        <i class="fas <?php echo $importSuccess ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> mr-3 mt-1"></i>
        <div class="flex-1">
            <p class="font-semibold"><?php echo htmlspecialchars($importMessage); ?></p>
            <?php if (!empty($importErrors)): ?>
            <details class="mt-2">
                <summary class="cursor-pointer text-sm underline">Details anzeigen (<?php echo count($importErrors); ?> Fehler)</summary>
                <ul class="mt-2 list-disc list-inside text-sm">
                    <?php foreach ($importErrors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </details>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Sync Results -->
<?php if ($syncResult): ?>
<div class="mb-6 p-4 rounded-lg bg-blue-100 border border-blue-400 text-blue-700">
    <div class="flex items-start">
        <i class="fas fa-sync-alt mr-3 mt-1"></i>
        <div class="flex-1">
            <p class="font-semibold">EasyVerein Synchronisierung abgeschlossen</p>
            <ul class="mt-2 text-sm">
                <li>✓ Erstellt: <?php echo htmlspecialchars($syncResult['created']); ?> Artikel</li>
                <li>✓ Aktualisiert: <?php echo htmlspecialchars($syncResult['updated']); ?> Artikel</li>
                <li>✓ Archiviert: <?php echo htmlspecialchars($syncResult['archived']); ?> Artikel</li>
            </ul>
            <?php if (!empty($syncResult['errors'])): ?>
            <details class="mt-2">
                <summary class="cursor-pointer text-sm underline">Fehler anzeigen (<?php echo count($syncResult['errors']); ?>)</summary>
                <ul class="mt-2 list-disc list-inside text-sm">
                    <?php foreach ($syncResult['errors'] as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </details>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Import Modal -->
<?php if (Auth::hasPermission('manager')): ?>
<div id="importModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white dark:bg-slate-800 rounded-lg p-8 max-w-2xl w-full mx-4">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-bold text-slate-900 dark:text-slate-100">
                <i class="fas fa-file-import text-green-600 mr-2"></i>
                Inventar Massenimport
            </h2>
            <button type="button" onclick="document.getElementById('importModal').classList.add('hidden')" class="text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
        
        <form method="POST" enctype="multipart/form-data" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-900 dark:text-slate-100 mb-2">JSON-Datei auswählen</label>
                <input 
                    type="file" 
                    name="json_file" 
                    accept=".json,application/json"
                    required
                    class="w-full px-4 py-2 border border-gray-300 dark:border-slate-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100"
                >
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
                    Laden Sie eine JSON-Datei mit Inventar-Artikeln hoch
                </p>
            </div>
            
            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                <h3 class="font-semibold text-blue-900 dark:text-blue-300 mb-2">
                    <i class="fas fa-info-circle mr-2"></i>JSON-Format
                </h3>
                <p class="text-sm text-blue-800 dark:text-blue-300 mb-2">Die JSON-Datei sollte ein Array von Objekten enthalten:</p>
                <pre class="text-xs bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 p-3 rounded border border-blue-200 dark:border-blue-800 overflow-x-auto"><code>[
  {
    "name": "Laptop Dell XPS 15",
    "category": "IT-Equipment",
    "status": "available",
    "description": "15 Zoll Laptop",
    "serial_number": "DXPS123456",
    "location": "Büro München",
    "purchase_date": "2024-01-15"
  }
]</code></pre>
                <div class="mt-3 text-sm text-blue-800 dark:text-blue-300">
                    <p class="font-semibold">Pflichtfelder:</p>
                    <ul class="list-disc list-inside ml-2">
                        <li><strong>name</strong>: Name des Artikels</li>
                        <li><strong>category</strong>: Kategorie (wird erstellt, falls nicht vorhanden)</li>
                    </ul>
                    <p class="font-semibold mt-2">Optionale Felder:</p>
                    <ul class="list-disc list-inside ml-2">
                        <li><strong>status</strong>: Status (available, in_use, maintenance, retired) - Standard: "available"</li>
                        <li><strong>description</strong>: Beschreibung</li>
                        <li><strong>serial_number</strong>: Seriennummer (muss eindeutig sein)</li>
                        <li><strong>location</strong>: Standort (wird erstellt, falls nicht vorhanden)</li>
                        <li><strong>purchase_date</strong>: Kaufdatum (Format: YYYY-MM-DD)</li>
                    </ul>
                </div>
            </div>
            
            <div class="flex justify-end gap-3 mt-6">
                <button 
                    type="button" 
                    onclick="document.getElementById('importModal').classList.add('hidden')"
                    class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-slate-900 dark:text-slate-100 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition"
                >
                    Abbrechen
                </button>
                <button 
                    type="submit" 
                    name="import_json"
                    class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition"
                >
                    <i class="fas fa-upload mr-2"></i>
                    Importieren
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="card p-6 mb-8 shadow-lg border border-gray-200 dark:border-slate-700">
    <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div>
            <label class="block text-sm font-semibold text-slate-900 dark:text-slate-100 mb-2 flex items-center">
                <i class="fas fa-search mr-2 text-purple-600"></i>Suche
            </label>
            <input 
                type="text" 
                name="search" 
                placeholder="Name oder Beschreibung..."
                value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>"
                class="w-full px-4 py-2.5 border border-gray-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 transition-all"
            >
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-900 dark:text-slate-100 mb-2 flex items-center">
                <i class="fas fa-map-marker-alt mr-2 text-blue-600"></i>Standort
            </label>
            <select 
                name="location" 
                class="w-full px-4 py-2.5 border border-gray-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 transition-all"
            >
                <option value="">Alle Standorte</option>
                <?php foreach ($distinctLocations as $location): ?>
                <option value="<?php echo htmlspecialchars($location); ?>" <?php echo (isset($_GET['location']) && $_GET['location'] == $location) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($location); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-semibold text-slate-900 dark:text-slate-100 mb-2 flex items-center">
                <i class="fas fa-sort mr-2 text-green-600"></i>Sortieren nach
            </label>
            <select 
                name="sort" 
                class="w-full px-4 py-2.5 border border-gray-300 dark:border-slate-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 transition-all"
            >
                <option value="name_asc" <?php echo ($sort == 'name_asc') ? 'selected' : ''; ?>>Name (A-Z)</option>
                <option value="name_desc" <?php echo ($sort == 'name_desc') ? 'selected' : ''; ?>>Name (Z-A)</option>
                <option value="quantity_desc" <?php echo ($sort == 'quantity_desc') ? 'selected' : ''; ?>>Menge (Hoch-Niedrig)</option>
                <option value="quantity_asc" <?php echo ($sort == 'quantity_asc') ? 'selected' : ''; ?>>Menge (Niedrig-Hoch)</option>
                <option value="price_desc" <?php echo ($sort == 'price_desc') ? 'selected' : ''; ?>>Preis (Hoch-Niedrig)</option>
                <option value="price_asc" <?php echo ($sort == 'price_asc') ? 'selected' : ''; ?>>Preis (Niedrig-Hoch)</option>
            </select>
        </div>
        <div class="flex items-end space-x-2">
            <button type="submit" class="flex-1 btn-primary shadow-md hover:shadow-lg transition-all transform hover:scale-105">
                <i class="fas fa-search mr-2"></i>Filtern
            </button>
            <a href="index.php" class="px-4 py-2.5 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition-all hover:scale-105">
                <i class="fas fa-times"></i>
            </a>
        </div>
    </form>
</div>

<!-- Items Grid Layout -->
<?php if (empty($items)): ?>
<div class="card p-12 text-center">
    <i class="fas fa-inbox text-6xl text-gray-300 dark:text-gray-600 mb-4"></i>
    <p class="text-slate-900 dark:text-slate-100 text-lg">Keine Artikel gefunden</p>
    <?php if (AuthHandler::isAdmin()): ?>
    <a href="sync.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg inline-flex items-center mt-4">
        <i class="fas fa-sync-alt mr-2"></i> EasyVerein Sync
    </a>
    <?php endif; ?>
</div>
<?php else: ?>
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
    <?php foreach ($items as $item): ?>
    <div class="group bg-white dark:bg-slate-800 rounded-xl shadow-md overflow-hidden hover:shadow-2xl hover:-translate-y-1 transition-all duration-300 border border-gray-100 dark:border-slate-700 <?php echo $item['is_archived_in_easyverein'] ? 'opacity-60' : ''; ?>">
        <!-- Image -->
        <div class="relative h-52 bg-gradient-to-br from-purple-50 via-blue-50 to-indigo-50 dark:from-purple-900/30 dark:via-blue-900/30 dark:to-indigo-900/30 flex items-center justify-center overflow-hidden <?php echo $item['is_archived_in_easyverein'] ? 'grayscale' : ''; ?>">
            <?php if (!empty($item['image_path'])): ?>
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
            <img src="<?php echo htmlspecialchars($imageSrc); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500">
            <?php else: ?>
            <div class="relative">
                <div class="absolute inset-0 bg-gradient-to-br from-purple-200/20 to-blue-200/20 dark:from-purple-800/20 dark:to-blue-800/20 rounded-full blur-2xl"></div>
                <i class="fas fa-box-open text-gray-300 dark:text-gray-600 text-6xl relative z-10" aria-label="Kein Bild verfügbar"></i>
            </div>
            <?php endif; ?>
            
            <!-- Badges -->
            <div class="absolute top-3 right-3 flex flex-col gap-2">
                <?php if (!empty($item['easyverein_id'])): ?>
                <span class="px-2.5 py-1.5 text-xs font-semibold bg-blue-500/90 text-white rounded-lg shadow-lg backdrop-blur-sm" title="Synchronisiert mit EasyVerein">
                    <i class="fas fa-sync-alt"></i>
                </span>
                <?php endif; ?>
                <?php if ($item['is_archived_in_easyverein']): ?>
                <span class="px-2.5 py-1.5 text-xs font-semibold bg-red-500/90 text-white rounded-lg shadow-lg backdrop-blur-sm" title="Archiviert in EasyVerein">
                    <i class="fas fa-archive"></i>
                </span>
                <?php endif; ?>
                <?php if ($item['quantity'] <= $item['min_stock'] && $item['min_stock'] > 0): ?>
                <span class="px-2.5 py-1.5 text-xs font-semibold bg-orange-500/90 text-white rounded-lg shadow-lg backdrop-blur-sm low-stock-badge" title="Niedriger Bestand">
                    <i class="fas fa-exclamation-triangle"></i>
                </span>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Content -->
        <div class="p-5">
            <h3 class="font-bold text-slate-900 dark:text-white text-xl mb-2 truncate group-hover:text-purple-600 dark:group-hover:text-purple-400 transition-colors" title="<?php echo htmlspecialchars($item['name']); ?>">
                <?php echo htmlspecialchars($item['name']); ?>
            </h3>
            
            <?php if ($item['category_name']): ?>
            <div class="flex items-center gap-2 mb-4">
                <span class="inline-flex items-center px-3 py-1 text-xs font-semibold rounded-full bg-gradient-to-r from-purple-100 to-blue-100 text-purple-700 dark:from-purple-900/40 dark:to-blue-900/40 dark:text-purple-300">
                    <i class="fas fa-tag mr-1.5 text-xs"></i><?php echo htmlspecialchars($item['category_name']); ?>
                </span>
            </div>
            <?php endif; ?>
            
            <div class="space-y-3 text-sm mb-5">
                <!-- Quantity -->
                <div class="flex justify-between items-center p-2.5 bg-gray-50 dark:bg-slate-700/50 rounded-lg">
                    <span class="text-slate-600 dark:text-slate-400 font-medium flex items-center">
                        <i class="fas fa-cubes mr-2 text-purple-500"></i>Anzahl:
                    </span>
                    <span class="font-bold text-lg <?php echo $item['quantity'] <= $item['min_stock'] && $item['min_stock'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-900 dark:text-white'; ?>">
                        <?php echo $item['quantity']; ?> <span class="text-sm font-normal"><?php echo htmlspecialchars($item['unit']); ?></span>
                    </span>
                </div>
                
                <!-- Price -->
                <div class="flex justify-between items-center p-2.5 bg-gray-50 dark:bg-slate-700/50 rounded-lg">
                    <span class="text-slate-600 dark:text-slate-400 font-medium flex items-center">
                        <i class="fas fa-euro-sign mr-2 text-green-500"></i>Preis:
                    </span>
                    <span class="font-bold text-lg text-slate-900 dark:text-white"><?php echo number_format($item['unit_price'], 2, ',', '.') . ' €'; ?></span>
                </div>
                
                <!-- Location -->
                <?php if ($item['location_name']): ?>
                <div class="flex justify-between items-center p-2.5 bg-gray-50 dark:bg-slate-700/50 rounded-lg">
                    <span class="text-slate-600 dark:text-slate-400 font-medium flex items-center">
                        <i class="fas fa-map-marker-alt mr-2 text-blue-500"></i>Lagerort:
                    </span>
                    <span class="text-slate-900 dark:text-white truncate ml-2 font-medium" title="<?php echo htmlspecialchars($item['location_name']); ?>">
                        <?php echo htmlspecialchars($item['location_name']); ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Actions -->
            <div class="flex gap-2 pt-4 border-t border-gray-200 dark:border-slate-700">
                <a href="view.php?id=<?php echo $item['id']; ?>" class="flex-1 text-center px-4 py-2.5 bg-gradient-to-r from-purple-600 to-purple-700 hover:from-purple-700 hover:to-purple-800 text-white rounded-lg transition-all transform hover:scale-105 text-sm font-semibold shadow-md" title="Details anzeigen">
                    <i class="fas fa-eye mr-1"></i>Details
                </a>
                <?php if (Auth::hasPermission('manager')): ?>
                <a href="edit.php?id=<?php echo $item['id']; ?>" class="flex-1 text-center px-4 py-2.5 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white rounded-lg transition-all transform hover:scale-105 text-sm font-semibold shadow-md" title="Bearbeiten">
                    <i class="fas fa-edit mr-1"></i>Bearbeiten
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<style>
/* Accessible animation - respects user's motion preferences */
.low-stock-badge {
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.7;
    }
}

/* Respect user's preference for reduced motion */
@media (prefers-reduced-motion: reduce) {
    .low-stock-badge {
        animation: none;
    }
}
</style>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../includes/templates/main_layout.php';
