<?php
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../includes/models/EventDocumentation.php';
require_once __DIR__ . '/../../src/Database.php';

// Check authentication
if (!Auth::check()) {
    header('Location: ../auth/login.php');
    exit;
}

$user = Auth::user();
$userRole = $_SESSION['user_role'] ?? 'member';

// Check if user has permission to view documentation (board and alumni_board only)
$allowedDocRoles = array_merge(Auth::BOARD_ROLES, ['alumni_board']);
if (!in_array($userRole, $allowedDocRoles)) {
    header('Location: ../events/index.php');
    exit;
}

// Get all event documentation with event titles
$allDocs = EventDocumentation::getAllWithEvents();

$title = 'Event-Statistiken - Historie';
ob_start();
?>

<div class="max-w-7xl mx-auto">
    <!-- Back Button -->
    <a href="../events/index.php" class="inline-flex items-center text-ibc-blue hover:text-ibc-blue-dark mb-6 ease-premium">
        <i class="fas fa-arrow-left mr-2"></i>
        Zurück zur Übersicht
    </a>

    <!-- Page Header -->
    <div class="bg-gradient-to-br from-purple-600 to-purple-700 shadow-premium rounded-xl p-8 mb-6 text-white">
        <h1 class="text-3xl md:text-4xl font-bold mb-2">
            <i class="fas fa-chart-bar mr-3"></i>
            Event-Statistiken Historie
        </h1>
        <p class="text-purple-100 text-lg">
            Übersicht aller Verkäufer und Statistiken vergangener Events
        </p>
    </div>

    <?php if (empty($allDocs)): ?>
        <div class="glass-card shadow-soft rounded-xl p-12 text-center">
            <i class="fas fa-chart-line text-gray-400 text-6xl mb-4"></i>
            <h3 class="text-xl font-semibold text-gray-700 mb-2">Noch keine Statistiken vorhanden</h3>
            <p class="text-gray-500">Erstellen Sie Event-Dokumentationen, um hier Statistiken zu sehen.</p>
        </div>
    <?php else: ?>
        <!-- Statistics Summary -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <?php
            $totalEvents = count($allDocs);
            $totalSellers = 0;
            $totalSales = 0;
            
            foreach ($allDocs as $doc) {
                if (!empty($doc['sellers_data'])) {
                    $totalSellers += count($doc['sellers_data']);
                }
                if (!empty($doc['sales_data'])) {
                    foreach ($doc['sales_data'] as $sale) {
                        $totalSales += floatval($sale['amount'] ?? 0);
                    }
                }
            }
            ?>
            
            <div class="glass-card shadow-soft rounded-xl p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm font-semibold mb-1">Events dokumentiert</p>
                        <p class="text-3xl font-bold text-purple-600"><?php echo $totalEvents; ?></p>
                    </div>
                    <i class="fas fa-calendar-check text-4xl text-purple-600 opacity-20"></i>
                </div>
            </div>
            
            <div class="glass-card shadow-soft rounded-xl p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm font-semibold mb-1">Verkäufer-Einträge</p>
                        <p class="text-3xl font-bold text-blue-600"><?php echo $totalSellers; ?></p>
                    </div>
                    <i class="fas fa-user-tie text-4xl text-blue-600 opacity-20"></i>
                </div>
            </div>
            
            <div class="glass-card shadow-soft rounded-xl p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm font-semibold mb-1">Gesamtumsatz</p>
                        <p class="text-3xl font-bold text-green-600"><?php echo number_format($totalSales, 2, ',', '.'); ?>€</p>
                    </div>
                    <i class="fas fa-euro-sign text-4xl text-green-600 opacity-20"></i>
                </div>
            </div>
        </div>

        <!-- Events List with Statistics -->
        <div class="space-y-6">
            <?php foreach ($allDocs as $doc): ?>
                <div class="glass-card shadow-soft rounded-xl p-6">
                    <!-- Event Header -->
                    <div class="flex items-center justify-between mb-4 pb-4 border-b border-gray-200">
                        <div>
                            <h2 class="text-2xl font-bold text-gray-800">
                                <?php echo htmlspecialchars($doc['event_title']); ?>
                            </h2>
                            <p class="text-gray-600 mt-1">
                                <i class="fas fa-calendar mr-2"></i>
                                <?php echo date('d.m.Y', strtotime($doc['start_time'])); ?>
                            </p>
                        </div>
                        <a href="../events/view.php?id=<?php echo $doc['event_id']; ?>" 
                           class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-all">
                            <i class="fas fa-eye mr-2"></i>
                            Event ansehen
                        </a>
                    </div>

                    <!-- Sellers Data -->
                    <?php if (!empty($doc['sellers_data'])): ?>
                        <div class="mb-6">
                            <h3 class="text-lg font-semibold text-gray-800 mb-3">
                                <i class="fas fa-user-tie mr-2 text-blue-600"></i>
                                Verkäufer
                            </h3>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead class="bg-gray-100 dark:bg-gray-700">
                                        <tr>
                                            <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200">Verkäufer/Stand</th>
                                            <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200">Artikel</th>
                                            <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200">Menge</th>
                                            <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200">Umsatz</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 dark:divide-gray-600">
                                        <?php foreach ($doc['sellers_data'] as $seller): ?>
                                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                                <td class="px-4 py-3 font-medium text-gray-800 dark:text-gray-200">
                                                    <?php echo htmlspecialchars($seller['seller_name'] ?? '-'); ?>
                                                </td>
                                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                                    <?php echo htmlspecialchars($seller['items'] ?? '-'); ?>
                                                </td>
                                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                                    <?php echo htmlspecialchars($seller['quantity'] ?? '-'); ?>
                                                </td>
                                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                                    <?php echo htmlspecialchars($seller['revenue'] ?? '-'); ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Sales Data -->
                    <?php if (!empty($doc['sales_data'])): ?>
                        <div class="mb-6">
                            <h3 class="text-lg font-semibold text-gray-800 mb-3">
                                <i class="fas fa-chart-line mr-2 text-purple-600"></i>
                                Verkaufsdaten
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <?php foreach ($doc['sales_data'] as $sale): ?>
                                    <div class="p-4 bg-purple-50 dark:bg-purple-900/20 rounded-lg border border-purple-200 dark:border-purple-700">
                                        <p class="font-semibold text-gray-800 dark:text-gray-200"><?php echo htmlspecialchars($sale['label'] ?? 'Unbenannt'); ?></p>
                                        <p class="text-2xl font-bold text-purple-600 mt-1">
                                            <?php echo number_format(floatval($sale['amount'] ?? 0), 2, ',', '.'); ?>€
                                        </p>
                                        <?php if (!empty($sale['date'])): ?>
                                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                                <?php echo date('d.m.Y', strtotime($sale['date'])); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Calculations -->
                    <?php if (!empty($doc['calculations'])): ?>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800 mb-3">
                                <i class="fas fa-calculator mr-2 text-green-600"></i>
                                Kalkulationen
                            </h3>
                            <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                <pre class="whitespace-pre-wrap text-gray-700 dark:text-gray-300 text-sm"><?php echo htmlspecialchars($doc['calculations']); ?></pre>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <!-- Financial Statistics Section -->
    <?php
    require_once __DIR__ . '/../../includes/models/EventFinancialStats.php';
    
    // Get all events for financial stats
    $db = Database::getContentDB();
    $eventsStmt = $db->query("SELECT id, title, start_time FROM events ORDER BY start_time DESC LIMIT 50");
    $events = $eventsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $hasFinancialStats = false;
    foreach ($events as $event) {
        $stats = EventFinancialStats::getByEventId($event['id']);
        if (!empty($stats)) {
            $hasFinancialStats = true;
            break;
        }
    }
    ?>
    
    <?php if ($hasFinancialStats): ?>
        <div class="mt-8">
            <div class="bg-gradient-to-br from-teal-600 to-teal-700 shadow-premium rounded-xl p-8 mb-6 text-white">
                <h2 class="text-3xl md:text-4xl font-bold mb-2">
                    <i class="fas fa-chart-line mr-3"></i>
                    Finanzstatistiken - Jahresvergleich
                </h2>
                <p class="text-teal-100 text-lg">
                    Vergleich von Verkäufen und Kalkulationen über verschiedene Jahre
                </p>
            </div>
            
            <?php foreach ($events as $event):
                $comparison = EventFinancialStats::getYearlyComparison($event['id']);
                if (empty($comparison)) continue;
                
                $availableYears = EventFinancialStats::getAvailableYears($event['id']);
                
                // Group by category
                $verkaufData = array_filter($comparison, function($item) {
                    return $item['category'] === 'Verkauf';
                });
                $kalkulationData = array_filter($comparison, function($item) {
                    return $item['category'] === 'Kalkulation';
                });
            ?>
                <div class="glass-card shadow-soft rounded-xl p-6 mb-6">
                    <div class="flex items-center justify-between mb-4 pb-4 border-b border-gray-200">
                        <div>
                            <h3 class="text-2xl font-bold text-gray-800">
                                <?php echo htmlspecialchars($event['title']); ?>
                            </h3>
                            <p class="text-gray-600 mt-1">
                                <i class="fas fa-calendar mr-2"></i>
                                <?php echo date('d.m.Y', strtotime($event['start_time'])); ?>
                            </p>
                        </div>
                        <a href="../events/view.php?id=<?php echo $event['id']; ?>" 
                           class="px-4 py-2 bg-teal-600 text-white rounded-lg hover:bg-teal-700 transition-all">
                            <i class="fas fa-eye mr-2"></i>
                            Event ansehen
                        </a>
                    </div>
                    
                    <?php if (!empty($verkaufData)): ?>
                        <div class="mb-6">
                            <h4 class="text-xl font-semibold text-gray-800 mb-3">
                                <i class="fas fa-shopping-cart mr-2 text-blue-600"></i>
                                Verkäufe
                            </h4>
                            <?php
                            // Group verkauf data by item_name
                            $verkaufGrouped = [];
                            foreach ($verkaufData as $item) {
                                if (!isset($verkaufGrouped[$item['item_name']])) {
                                    $verkaufGrouped[$item['item_name']] = [];
                                }
                                $verkaufGrouped[$item['item_name']][$item['record_year']] = [
                                    'quantity' => $item['total_quantity'],
                                    'revenue' => $item['total_revenue']
                                ];
                            }
                            ?>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm border-collapse">
                                    <thead class="bg-blue-100 dark:bg-blue-900/30">
                                        <tr>
                                            <th class="border border-gray-300 dark:border-gray-600 px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200">Artikel</th>
                                            <?php foreach ($availableYears as $year): ?>
                                                <th class="border border-gray-300 dark:border-gray-600 px-4 py-3 text-center font-semibold text-gray-700 dark:text-gray-200">
                                                    <?php echo $year; ?>
                                                </th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 dark:divide-gray-600">
                                        <?php foreach ($verkaufGrouped as $itemName => $yearData): ?>
                                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                                <td class="border border-gray-300 dark:border-gray-600 px-4 py-3 font-medium text-gray-800 dark:text-gray-200">
                                                    <?php echo htmlspecialchars($itemName); ?>
                                                </td>
                                                <?php foreach ($availableYears as $year): ?>
                                                    <td class="border border-gray-300 dark:border-gray-600 px-4 py-3 text-center text-gray-700 dark:text-gray-300">
                                                        <?php if (isset($yearData[$year])): ?>
                                                            <span class="font-semibold text-blue-600"><?php echo $yearData[$year]['quantity']; ?> Stück</span>
                                                            <?php if ($yearData[$year]['revenue']): ?>
                                                                <br>
                                                                <span class="text-sm text-gray-600 dark:text-gray-400">
                                                                    <?php echo number_format($yearData[$year]['revenue'], 2, ',', '.'); ?>€
                                                                </span>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            <span class="text-gray-400">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($kalkulationData)): ?>
                        <div>
                            <h4 class="text-xl font-semibold text-gray-800 mb-3">
                                <i class="fas fa-calculator mr-2 text-green-600"></i>
                                Kalkulationen
                            </h4>
                            <?php
                            // Group kalkulation data by item_name
                            $kalkulationGrouped = [];
                            foreach ($kalkulationData as $item) {
                                if (!isset($kalkulationGrouped[$item['item_name']])) {
                                    $kalkulationGrouped[$item['item_name']] = [];
                                }
                                $kalkulationGrouped[$item['item_name']][$item['record_year']] = [
                                    'quantity' => $item['total_quantity'],
                                    'revenue' => $item['total_revenue']
                                ];
                            }
                            ?>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm border-collapse">
                                    <thead class="bg-green-100 dark:bg-green-900/30">
                                        <tr>
                                            <th class="border border-gray-300 dark:border-gray-600 px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200">Artikel</th>
                                            <?php foreach ($availableYears as $year): ?>
                                                <th class="border border-gray-300 dark:border-gray-600 px-4 py-3 text-center font-semibold text-gray-700 dark:text-gray-200">
                                                    <?php echo $year; ?>
                                                </th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 dark:divide-gray-600">
                                        <?php foreach ($kalkulationGrouped as $itemName => $yearData): ?>
                                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                                <td class="border border-gray-300 dark:border-gray-600 px-4 py-3 font-medium text-gray-800 dark:text-gray-200">
                                                    <?php echo htmlspecialchars($itemName); ?>
                                                </td>
                                                <?php foreach ($availableYears as $year): ?>
                                                    <td class="border border-gray-300 dark:border-gray-600 px-4 py-3 text-center text-gray-700 dark:text-gray-300">
                                                        <?php if (isset($yearData[$year])): ?>
                                                            <span class="font-semibold text-green-600"><?php echo $yearData[$year]['quantity']; ?> Stück</span>
                                                            <?php if ($yearData[$year]['revenue']): ?>
                                                                <br>
                                                                <span class="text-sm text-gray-600 dark:text-gray-400">
                                                                    <?php echo number_format($yearData[$year]['revenue'], 2, ',', '.'); ?>€
                                                                </span>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            <span class="text-gray-400">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../../includes/templates/main_layout.php';
?>
