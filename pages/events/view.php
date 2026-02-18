<?php
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../includes/models/Event.php';
require_once __DIR__ . '/../../includes/models/EventDocumentation.php';
require_once __DIR__ . '/../../src/CalendarService.php';

// Check authentication
if (!Auth::check()) {
    header('Location: ../auth/login.php');
    exit;
}

$user = Auth::user();
$userRole = $_SESSION['user_role'] ?? 'member';

// Get event ID
$eventId = $_GET['id'] ?? null;
if (!$eventId) {
    header('Location: index.php');
    exit;
}

// Get event details
$event = Event::getById($eventId, true);
if (!$event) {
    header('Location: index.php');
    exit;
}

// Check if user has permission to view this event
$allowedRoles = $event['allowed_roles'] ?? [];
if (!empty($allowedRoles) && !in_array($userRole, $allowedRoles)) {
    header('Location: index.php');
    exit;
}

// Get user's signups
$userSignups = Event::getUserSignups($user['id']);
$isRegistered = false;
$userSignupId = null;
$userSlotId = null;
foreach ($userSignups as $signup) {
    if ($signup['event_id'] == $eventId) {
        $isRegistered = true;
        $userSignupId = $signup['id'];
        $userSlotId = $signup['slot_id'];
        break;
    }
}

// Get registration count
$registrationCount = Event::getRegistrationCount($eventId);

// Get helper types and slots if needed
$helperTypes = [];
if ($event['needs_helpers'] && $userRole !== 'alumni') {
    $helperTypes = Event::getHelperTypes($eventId);
    
    // For each helper type, get slots with signup counts
    foreach ($helperTypes as &$helperType) {
        $slots = Event::getSlots($helperType['id']);
        
        // Add signup counts to each slot
        foreach ($slots as &$slot) {
            $signups = Event::getSignups($eventId);
            $confirmedCount = 0;
            $userInSlot = false;
            
            foreach ($signups as $signup) {
                if ($signup['slot_id'] == $slot['id'] && $signup['status'] == 'confirmed') {
                    $confirmedCount++;
                    if ($signup['user_id'] == $user['id']) {
                        $userInSlot = true;
                    }
                }
            }
            
            $slot['signups_count'] = $confirmedCount;
            $slot['user_in_slot'] = $userInSlot;
            $slot['is_full'] = $confirmedCount >= $slot['quantity_needed'];
        }
        
        $helperType['slots'] = $slots;
    }
}

// Check if event signup has a deadline
$signupDeadline = $event['start_time']; // Default to event start time
$canCancel = strtotime($signupDeadline) > time();

// Check if user can view documentation (board and alumni_board only)
// This includes all board role variants: board, vorstand_intern, vorstand_extern, vorstand_finanzen_recht
$allowedDocRoles = array_merge(Auth::BOARD_ROLES, ['alumni_board']);
$canViewDocumentation = in_array($userRole, $allowedDocRoles);

// Load event documentation if user has permission
$documentation = null;
if ($canViewDocumentation) {
    $documentation = EventDocumentation::getByEventId($eventId);
}

$title = htmlspecialchars($event['title']) . ' - Events';
ob_start();
?>

<div class="max-w-5xl mx-auto">
    <!-- Back Button -->
    <a href="index.php" class="inline-flex items-center text-ibc-blue hover:text-ibc-blue-dark mb-6 ease-premium">
        <i class="fas fa-arrow-left mr-2"></i>
        Zurück zur Übersicht
    </a>

    <!-- Event Hero Header -->
    <div class="bg-gradient-to-br from-ibc-blue to-ibc-blue-dark shadow-premium rounded-xl p-8 mb-6 text-white">
        <div class="flex flex-col md:flex-row justify-between items-start gap-4 mb-4">
            <div class="flex-1">
                <h1 class="text-3xl md:text-4xl font-bold mb-4">
                    <?php echo htmlspecialchars($event['title']); ?>
                </h1>
                
                <!-- Status Badge -->
                <?php 
                $statusLabels = [
                    'planned' => ['label' => 'Geplant', 'color' => 'bg-white/20 text-white border-white/30'],
                    'open' => ['label' => 'Anmeldung offen', 'color' => 'bg-ibc-green/20 text-white border-ibc-green/30'],
                    'closed' => ['label' => 'Anmeldung geschlossen', 'color' => 'bg-yellow-500/20 text-white border-yellow-500/30'],
                    'running' => ['label' => 'Läuft gerade', 'color' => 'bg-white/30 text-white border-white/50'],
                    'past' => ['label' => 'Beendet', 'color' => 'bg-white/10 text-white/70 border-white/20']
                ];
                $currentStatus = $event['status'] ?? 'planned';
                $statusInfo = $statusLabels[$currentStatus] ?? ['label' => $currentStatus, 'color' => 'bg-white/20 text-white border-white/30'];
                ?>
                <div class="inline-flex items-center px-4 py-2 rounded-xl font-semibold text-sm <?php echo $statusInfo['color']; ?> border mb-4">
                    <i class="fas fa-circle text-xs mr-2"></i>
                    <?php echo $statusInfo['label']; ?>
                </div>
            </div>
            
            <!-- Registration Status -->
            <?php if ($isRegistered): ?>
                <div class="flex-shrink-0">
                    <div class="px-6 py-3 bg-white/20 backdrop-blur-sm text-white rounded-xl font-semibold text-center border border-white/30">
                        <i class="fas fa-check-circle mr-2"></i>
                        Angemeldet
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Event Details Card -->
    <div class="glass-card shadow-soft rounded-xl p-8 mb-6">
        <!-- Event Meta -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-gray-600">
            <div class="flex items-start">
                <i class="fas fa-calendar w-6 mt-1 text-ibc-blue"></i>
                <div>
                    <div class="font-semibold">Beginn</div>
                    <div><?php echo date('d.m.Y H:i', strtotime($event['start_time'])); ?> Uhr</div>
                </div>
            </div>
            
            <div class="flex items-start">
                <i class="fas fa-clock w-6 mt-1 text-ibc-blue"></i>
                <div>
                    <div class="font-semibold">Ende</div>
                    <div><?php echo date('d.m.Y H:i', strtotime($event['end_time'])); ?> Uhr</div>
                </div>
            </div>
            
            <?php if (!empty($event['location'])): ?>
                <div class="flex items-start">
                    <i class="fas fa-map-marker-alt w-6 mt-1 text-ibc-blue"></i>
                    <div class="flex-1">
                        <div class="font-semibold">Veranstaltungsort</div>
                        <div class="text-lg font-medium text-gray-800"><?php echo htmlspecialchars($event['location']); ?></div>
                        <?php if (!empty($event['maps_link'])): ?>
                            <a href="<?php echo htmlspecialchars($event['maps_link']); ?>" 
                               target="_blank" 
                               rel="noopener noreferrer"
                               class="inline-flex items-center mt-2 px-4 py-2 bg-ibc-green text-white rounded-xl hover:shadow-glow-green ease-premium font-semibold text-sm">
                                <i class="fas fa-route mr-2"></i>
                                Route planen
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($event['contact_person'])): ?>
                <div class="flex items-start">
                    <i class="fas fa-user w-6 mt-1 text-ibc-blue"></i>
                    <div>
                        <div class="font-semibold">Ansprechpartner</div>
                        <div><?php echo htmlspecialchars($event['contact_person']); ?></div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Description -->
        <?php if (!empty($event['description'])): ?>
            <div class="mt-6 pt-6 border-t border-gray-200">
                <h2 class="text-xl font-bold text-gray-800 mb-3">Beschreibung</h2>
                <p class="text-gray-700 whitespace-pre-line"><?php echo htmlspecialchars($event['description']); ?></p>
            </div>
        <?php endif; ?>

        <!-- Event Image -->
        <?php if (!empty($event['image_path'])): ?>
            <div class="mt-6 pt-6 border-t border-gray-200">
                <h2 class="text-xl font-bold text-gray-800 mb-3">Event-Bild</h2>
                <?php 
                    // Check if image file exists before displaying
                    $imagePath = $event['image_path'];
                    $fullImagePath = __DIR__ . '/../../' . $imagePath;
                    
                    // Validate path to prevent directory traversal
                    $realPath = realpath($fullImagePath);
                    $baseDir = realpath(__DIR__ . '/../../');
                    $imageExists = $realPath && $baseDir && strpos($realPath, $baseDir) === 0 && file_exists($realPath);
                ?>
                <?php if ($imageExists): ?>
                    <img 
                        src="<?php echo htmlspecialchars(BASE_URL . '/' . $imagePath); ?>" 
                        alt="<?php echo htmlspecialchars($event['title']); ?>"
                        class="w-full max-w-2xl rounded-xl border border-gray-300 shadow-soft"
                    >
                <?php else: ?>
                    <div class="w-full max-w-2xl rounded-xl border border-gray-300 bg-gray-100 p-8 text-center">
                        <i class="fas fa-image text-gray-400 text-6xl mb-4"></i>
                        <p class="text-gray-600">Bild nicht verfügbar</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Participant Counter -->
        <?php if (!$event['is_external']): ?>
            <div class="mt-6 pt-6 border-t border-gray-200">
                <div class="flex items-center justify-center bg-gradient-to-r from-ibc-blue/10 to-ibc-green/10 rounded-xl p-6">
                    <div class="text-center">
                        <div class="text-4xl font-bold text-ibc-blue mb-2">
                            <?php echo $registrationCount; ?>
                        </div>
                        <div class="text-lg font-semibold text-gray-700">
                            Angemeldete Teilnehmer
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Calendar Export Buttons -->
        <div class="mt-6 pt-6 border-t border-gray-200">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-calendar-plus mr-2 text-ibc-blue"></i>
                In Kalender eintragen
            </h3>
            <div class="flex flex-wrap gap-3">
                <!-- Google Calendar Button -->
                <a href="<?php echo htmlspecialchars(CalendarService::getGoogleLink($event)); ?>" 
                   target="_blank" 
                   rel="noopener noreferrer"
                   class="inline-flex items-center px-5 py-2.5 bg-white border-2 border-gray-300 text-gray-700 rounded-xl font-semibold hover:border-ibc-blue hover:text-ibc-blue ease-premium shadow-sm">
                    <i class="fab fa-google mr-2 text-lg"></i>
                    Google Kalender
                </a>
                
                <!-- iCal Download Button -->
                <a href="../../api/download_ics.php?event_id=<?php echo htmlspecialchars($eventId, ENT_QUOTES, 'UTF-8'); ?>" 
                   class="inline-flex items-center px-5 py-2.5 bg-white border-2 border-gray-300 text-gray-700 rounded-xl font-semibold hover:border-ibc-blue hover:text-ibc-blue ease-premium shadow-sm">
                    <i class="fas fa-download mr-2"></i>
                    iCal herunterladen
                </a>
            </div>
        </div>

        <!-- Participation Button -->
        <div class="flex gap-4 mt-6 pt-6 border-t border-gray-200">
            <?php if (!empty($event['registration_link'])): ?>
                <!-- External Registration (Microsoft Forms) - Open Link in new tab -->
                <a href="<?php echo htmlspecialchars($event['registration_link']); ?>" 
                   target="_blank" 
                   rel="noopener noreferrer"
                   class="inline-flex items-center px-8 py-3 bg-ibc-green text-white rounded-xl font-semibold hover:shadow-glow-green ease-premium">
                    <i class="fas fa-external-link-alt mr-2"></i>
                    Jetzt anmelden
                </a>
            <?php elseif ($event['is_external']): ?>
                <!-- External Event - Open Link -->
                <?php if (!empty($event['external_link'])): ?>
                    <a href="<?php echo htmlspecialchars($event['external_link']); ?>" 
                       target="_blank" 
                       rel="noopener noreferrer"
                       class="inline-flex items-center px-8 py-3 bg-ibc-blue text-white rounded-xl font-semibold hover:bg-ibc-blue-dark ease-premium shadow-soft">
                        <i class="fas fa-external-link-alt mr-2"></i>
                        Zur Anmeldung (extern)
                    </a>
                <?php endif; ?>
            <?php else: ?>
                <!-- Internal Event - AJAX Signup -->
                <?php if (!$isRegistered && !$userSlotId): ?>
                    <button onclick="signupForEvent(<?php echo $eventId; ?>)" 
                            class="inline-flex items-center px-8 py-3 bg-ibc-green text-white rounded-xl font-semibold hover:shadow-glow-green ease-premium">
                        <i class="fas fa-user-plus mr-2"></i>
                        Jetzt anmelden
                    </button>
                <?php elseif ($canCancel && $userSignupId && !$userSlotId): ?>
                    <button onclick="cancelSignup(<?php echo $userSignupId; ?>)" 
                            class="inline-flex items-center px-8 py-3 bg-red-600 text-white rounded-xl font-semibold hover:bg-red-700 ease-premium">
                        <i class="fas fa-user-times mr-2"></i>
                        Abmelden
                    </button>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Helper Slots Section (Only for non-alumni and if event needs helpers) -->
    <?php if ($event['needs_helpers'] && $userRole !== 'alumni' && !empty($helperTypes)): ?>
        <div class="glass-card shadow-soft rounded-xl p-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">
                <i class="fas fa-hands-helping mr-2 text-ibc-green"></i>
                Helfer-Bereich
            </h2>
            
            <p class="text-gray-600 dark:text-gray-300 mb-6">Unterstütze uns als Helfer! Wähle einen freien Slot aus.</p>
            
            <?php foreach ($helperTypes as $helperType): ?>
                <div class="mb-6 last:mb-0">
                    <h3 class="text-xl font-bold text-gray-800 mb-2">
                        <?php echo htmlspecialchars($helperType['title']); ?>
                    </h3>
                    
                    <?php if (!empty($helperType['description'])): ?>
                        <p class="text-gray-600 mb-4"><?php echo htmlspecialchars($helperType['description']); ?></p>
                    <?php endif; ?>
                    
                    <!-- Slots -->
                    <div class="space-y-3">
                        <?php foreach ($helperType['slots'] as $slot): ?>
                            <?php
                                $slotStart = new DateTime($slot['start_time']);
                                $slotEnd = new DateTime($slot['end_time']);
                                $occupancy = $slot['signups_count'] . '/' . $slot['quantity_needed'];
                                $canSignup = !$slot['is_full'] && !$slot['user_in_slot'];
                                $onWaitlist = $slot['is_full'] && !$slot['user_in_slot'];
                                
                                // Prepare slot parameters for onclick handlers
                                $slotStartFormatted = htmlspecialchars($slotStart->format('Y-m-d H:i:s'), ENT_QUOTES);
                                $slotEndFormatted = htmlspecialchars($slotEnd->format('Y-m-d H:i:s'), ENT_QUOTES);
                                $slotSignupHandler = "signupForSlot({$eventId}, {$slot['id']}, '{$slotStartFormatted}', '{$slotEndFormatted}')";
                            ?>
                            
                            <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 p-4 bg-gray-50 rounded-xl border border-gray-200">
                                <div class="flex-1">
                                    <div class="font-semibold text-gray-800">
                                        <i class="fas fa-clock mr-2 text-ibc-blue"></i>
                                        <?php echo $slotStart->format('H:i'); ?> - <?php echo $slotEnd->format('H:i'); ?> Uhr
                                    </div>
                                    <div class="text-sm text-gray-600 mt-1">
                                        <span class="font-semibold"><?php echo $occupancy; ?> belegt</span>
                                    </div>
                                </div>
                                
                                <div class="flex-shrink-0">
                                    <?php if ($slot['user_in_slot']): ?>
                                        <div class="flex items-center gap-3">
                                            <span class="px-4 py-2 bg-ibc-green/10 text-ibc-green border border-ibc-green/20 rounded-xl font-semibold text-sm">
                                                <i class="fas fa-check mr-1"></i>
                                                Eingetragen
                                            </span>
                                            <?php if ($canCancel): ?>
                                                <button onclick="cancelHelperSlot(<?php echo $userSignupId; ?>)" 
                                                        class="px-4 py-2 bg-red-100 text-red-800 rounded-xl font-semibold text-sm hover:bg-red-200 ease-premium">
                                                    <i class="fas fa-times mr-1"></i>
                                                    Austragen
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    <?php elseif ($canSignup): ?>
                                        <button onclick="<?php echo $slotSignupHandler; ?>" 
                                                class="px-6 py-2 bg-ibc-green text-white rounded-xl font-semibold hover:shadow-glow-green ease-premium">
                                            <i class="fas fa-user-plus mr-2"></i>
                                            Eintragen
                                        </button>
                                    <?php elseif ($onWaitlist): ?>
                                        <button onclick="<?php echo $slotSignupHandler; ?>" 
                                                class="px-6 py-2 bg-yellow-600 text-white rounded-xl font-semibold hover:bg-yellow-700 ease-premium">
                                            <i class="fas fa-list mr-2"></i>
                                            Warteliste
                                        </button>
                                    <?php else: ?>
                                        <span class="px-4 py-2 bg-gray-100 text-gray-600 rounded-xl font-semibold text-sm">
                                            Belegt
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <!-- Event Documentation Section (Board and Alumni Board Only) -->
    <?php if ($canViewDocumentation): ?>
        <div class="glass-card shadow-soft rounded-xl p-8 mt-6">
            <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-100 mb-6">
                <i class="fas fa-file-alt mr-2 text-purple-600"></i>
                Statistiken & Dokumentation
                <span class="text-sm font-normal text-gray-500 dark:text-gray-400 ml-2">(Nur für Vorstand sichtbar)</span>
            </h2>
            
            <!-- Sellers Section -->
            <div class="mb-8">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-semibold text-gray-800 dark:text-gray-100">
                        <i class="fas fa-user-tie mr-2 text-blue-600"></i>
                        Verkäufer-Tracking
                    </h3>
                    <button 
                        id="addSellerBtn"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-all"
                    >
                        <i class="fas fa-plus mr-2"></i>
                        Neuen Verkäufer tracken
                    </button>
                </div>
                
                <!-- Sellers Entries -->
                <div id="sellersEntries" class="space-y-3 mb-4">
                    <!-- Sellers entries will be rendered here by JavaScript -->
                </div>
            </div>
            
            <!-- Calculations Section -->
            <div class="mb-8">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-xl font-semibold text-gray-800 dark:text-gray-100">
                        <i class="fas fa-calculator mr-2 text-green-600"></i>
                        Kalkulationen
                    </h3>
                    <button 
                        id="addCalculationBtn"
                        class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-all"
                        onclick="document.getElementById('calculations').focus()"
                    >
                        <i class="fas fa-plus mr-2"></i>
                        Neue Kalkulation tracken
                    </button>
                </div>
                <textarea 
                    id="calculations"
                    rows="6"
                    class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100"
                    placeholder="Notieren Sie hier Kalkulationen, Kosten, Budget-Details..."
                ><?php echo htmlspecialchars($documentation['calculations'] ?? ''); ?></textarea>
            </div>
            
            <!-- Sales Data Section (Legacy) -->
            <div class="mb-8">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-semibold text-gray-800 dark:text-gray-100">
                        <i class="fas fa-chart-line mr-2 text-purple-600"></i>
                        Verkaufsdaten (Gesamt)
                    </h3>
                    <button 
                        id="addSaleBtn"
                        class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-all"
                    >
                        <i class="fas fa-plus mr-2"></i>
                        Verkauf hinzufügen
                    </button>
                </div>
                
                <!-- Sales Entries -->
                <div id="salesEntries" class="space-y-3 mb-4">
                    <!-- Sales entries will be rendered here by JavaScript -->
                </div>
                
                <!-- Chart Display -->
                <div class="bg-white dark:bg-gray-700 p-6 rounded-lg border border-gray-200 dark:border-gray-600">
                    <h4 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">Verkaufsübersicht</h4>
                    <canvas id="salesChart" style="max-height: 300px;"></canvas>
                </div>
            </div>
            
            <!-- Save Button -->
            <div class="flex justify-end">
                <button 
                    id="saveDocumentationBtn"
                    class="px-6 py-3 bg-gradient-to-r from-purple-600 to-purple-700 text-white rounded-lg font-semibold hover:from-purple-700 hover:to-purple-800 transition-all shadow-lg hover:shadow-xl"
                >
                    <i class="fas fa-save mr-2"></i>
                    Dokumentation speichern
                </button>
            </div>
        </div>
        
        <!-- Financial Statistics Section (New) -->
        <div class="glass-card shadow-soft rounded-xl p-8 mt-6">
            <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-100 mb-6">
                <i class="fas fa-chart-bar mr-2 text-teal-600"></i>
                Finanzstatistiken & Jahresvergleich
                <span class="text-sm font-normal text-gray-500 dark:text-gray-400 ml-2">(Nur für Vorstand sichtbar)</span>
            </h2>
            
            <!-- Action Buttons -->
            <div class="flex gap-3 mb-6">
                <button 
                    id="addSalesTrackingBtn"
                    class="px-5 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg font-semibold hover:from-blue-700 hover:to-blue-800 transition-all shadow-md"
                    onclick="openFinancialStatsModal('Verkauf')"
                >
                    <i class="fas fa-shopping-cart mr-2"></i>
                    Neue Verkäufe tracken
                </button>
                <button 
                    id="addCalculationTrackingBtn"
                    class="px-5 py-3 bg-gradient-to-r from-green-600 to-green-700 text-white rounded-lg font-semibold hover:from-green-700 hover:to-green-800 transition-all shadow-md"
                    onclick="openFinancialStatsModal('Kalkulation')"
                >
                    <i class="fas fa-calculator mr-2"></i>
                    Neue Kalkulation erfassen
                </button>
            </div>
            
            <!-- Comparison Table -->
            <div id="financialStatsContainer">
                <p class="text-gray-500 dark:text-gray-400 text-center py-8">
                    <i class="fas fa-spinner fa-spin mr-2"></i>
                    Lade Finanzstatistiken...
                </p>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Success/Error Message Container -->
<div id="message-container" class="fixed top-4 right-4 z-50 hidden">
    <div id="message-content" class="card px-6 py-4 shadow-2xl"></div>
</div>

<script>
// Show message helper
function showMessage(message, type = 'success') {
    const container = document.getElementById('message-container');
    const content = document.getElementById('message-content');
    
    const bgColor = type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
    const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
    
    content.className = `card px-6 py-4 shadow-2xl ${bgColor}`;
    content.innerHTML = `<i class="fas ${icon} mr-2"></i>${message}`;
    
    container.classList.remove('hidden');
    
    setTimeout(() => {
        container.classList.add('hidden');
    }, 5000);
}

// Signup for event (general participation)
function signupForEvent(eventId) {
    fetch('../../api/event_signup.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'signup',
            event_id: eventId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage('Erfolgreich angemeldet!', 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showMessage(data.message || 'Fehler bei der Anmeldung', 'error');
        }
    })
    .catch(error => {
        showMessage('Netzwerkfehler', 'error');
    });
}

// Signup for helper slot
function signupForSlot(eventId, slotId, slotStart, slotEnd) {
    fetch('../../api/event_signup.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'signup',
            event_id: eventId,
            slot_id: slotId,
            slot_start: slotStart,
            slot_end: slotEnd
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (data.status === 'waitlist') {
                showMessage('Sie wurden auf die Warteliste gesetzt', 'success');
            } else {
                showMessage('Erfolgreich eingetragen!', 'success');
            }
            setTimeout(() => location.reload(), 1500);
        } else {
            showMessage(data.message || 'Fehler bei der Anmeldung', 'error');
        }
    })
    .catch(error => {
        showMessage('Netzwerkfehler', 'error');
    });
}

// Cancel signup (general or helper slot)
function cancelSignup(signupId, message = 'Möchtest Du Deine Anmeldung wirklich stornieren?', successMessage = 'Abmeldung erfolgreich') {
    if (!confirm(message)) {
        return;
    }
    
    fetch('../../api/event_signup.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'cancel',
            signup_id: signupId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage(successMessage, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showMessage(data.message || 'Fehler bei der Abmeldung', 'error');
        }
    })
    .catch(error => {
        showMessage('Netzwerkfehler', 'error');
    });
}

// Cancel helper slot (wrapper for consistency)
function cancelHelperSlot(signupId) {
    cancelSignup(signupId, 'Möchtest Du Dich wirklich austragen?', 'Erfolgreich ausgetragen');
}

<?php if ($canViewDocumentation): ?>
// ===== Event Documentation Management =====

// Initialize sales data and sellers data from PHP
let salesData = <?php echo json_encode($documentation['sales_data'] ?? []); ?>;
let sellersData = <?php echo json_encode($documentation['sellers_data'] ?? []); ?>;

// Render sellers entries
function renderSellersEntries() {
    const container = document.getElementById('sellersEntries');
    if (!container) return;
    
    if (sellersData.length === 0) {
        container.innerHTML = '<p class="text-gray-500 dark:text-gray-400 text-center py-4">Keine Verkäufer-Daten vorhanden. Klicken Sie auf "Neuen Verkäufer tracken".</p>';
        return;
    }
    
    // Helper function to escape HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    container.innerHTML = sellersData.map((seller, index) => `
        <div class="flex items-center gap-4 p-4 bg-gray-50 dark:bg-gray-600 rounded-lg border border-gray-200 dark:border-gray-500" data-seller-index="${index}">
            <div class="flex-1 grid grid-cols-4 gap-4">
                <div>
                    <label class="text-xs text-gray-600 dark:text-gray-300 mb-1 block">Verkäufer/Stand</label>
                    <input 
                        type="text" 
                        value="${escapeHtml(seller.seller_name || '')}"
                        data-field="seller_name"
                        class="seller-field w-full px-3 py-2 border border-gray-300 dark:border-gray-500 rounded bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100"
                        placeholder="z.B. BSW, Grillstand"
                    >
                </div>
                <div>
                    <label class="text-xs text-gray-600 dark:text-gray-300 mb-1 block">Artikel</label>
                    <input 
                        type="text" 
                        value="${escapeHtml(seller.items || '')}"
                        data-field="items"
                        class="seller-field w-full px-3 py-2 border border-gray-300 dark:border-gray-500 rounded bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100"
                        placeholder="z.B. Brezeln, Äpfel"
                    >
                </div>
                <div>
                    <label class="text-xs text-gray-600 dark:text-gray-300 mb-1 block">Menge/Anzahl</label>
                    <input 
                        type="text" 
                        value="${escapeHtml(seller.quantity || '')}"
                        data-field="quantity"
                        class="seller-field w-full px-3 py-2 border border-gray-300 dark:border-gray-500 rounded bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100"
                        placeholder="z.B. 50, 25 Verkauft"
                    >
                </div>
                <div>
                    <label class="text-xs text-gray-600 dark:text-gray-300 mb-1 block">Umsatz (€)</label>
                    <input 
                        type="text" 
                        value="${escapeHtml(seller.revenue || '')}"
                        data-field="revenue"
                        class="seller-field w-full px-3 py-2 border border-gray-300 dark:border-gray-500 rounded bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100"
                        placeholder="z.B. 450€ (optional)"
                    >
                </div>
            </div>
            <button 
                class="seller-delete-btn px-3 py-2 bg-red-600 text-white rounded hover:bg-red-700 transition-all"
                title="Löschen"
            >
                <i class="fas fa-trash"></i>
            </button>
        </div>
    `).join('');
    
    // Add event delegation for seller field changes
    container.querySelectorAll('.seller-field').forEach(input => {
        input.addEventListener('change', function() {
            const sellerDiv = this.closest('[data-seller-index]');
            const index = parseInt(sellerDiv.dataset.sellerIndex);
            const field = this.dataset.field;
            updateSeller(index, field, this.value);
        });
    });
    
    // Add event delegation for delete buttons
    container.querySelectorAll('.seller-delete-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const sellerDiv = this.closest('[data-seller-index]');
            const index = parseInt(sellerDiv.dataset.sellerIndex);
            removeSeller(index);
        });
    });
}

// Update a seller entry
function updateSeller(index, field, value) {
    // Validate index
    if (index < 0 || index >= sellersData.length || !sellersData[index]) {
        console.error('Invalid seller index:', index);
        return;
    }
    
    // Validate field
    const validFields = ['seller_name', 'items', 'quantity', 'revenue'];
    if (!validFields.includes(field)) {
        console.error('Invalid field:', field);
        return;
    }
    
    sellersData[index][field] = value;
}

// Add new seller entry
document.getElementById('addSellerBtn')?.addEventListener('click', function() {
    sellersData.push({
        seller_name: '',
        items: '',
        quantity: '',
        revenue: ''
    });
    renderSellersEntries();
});

// Remove seller entry
function removeSeller(index) {
    if (confirm('Diesen Verkäufer wirklich löschen?')) {
        sellersData.splice(index, 1);
        renderSellersEntries();
    }
}

// Render sales entries
function renderSalesEntries() {
    const container = document.getElementById('salesEntries');
    if (!container) return;
    
    if (salesData.length === 0) {
        container.innerHTML = '<p class="text-gray-500 dark:text-gray-400 text-center py-4">Keine Verkaufsdaten vorhanden. Klicken Sie auf "Verkauf hinzufügen".</p>';
        return;
    }
    
    container.innerHTML = salesData.map((sale, index) => `
        <div class="flex items-center gap-4 p-4 bg-gray-50 dark:bg-gray-600 rounded-lg border border-gray-200 dark:border-gray-500">
            <div class="flex-1 grid grid-cols-3 gap-4">
                <div>
                    <label class="text-xs text-gray-600 dark:text-gray-300 mb-1 block">Bezeichnung</label>
                    <input 
                        type="text" 
                        value="${sale.label || ''}"
                        onchange="updateSale(${index}, 'label', this.value)"
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-500 rounded bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100"
                        placeholder="z.B. Ticketverkauf"
                    >
                </div>
                <div>
                    <label class="text-xs text-gray-600 dark:text-gray-300 mb-1 block">Betrag (€)</label>
                    <input 
                        type="number" 
                        step="0.01"
                        value="${sale.amount || 0}"
                        onchange="updateSale(${index}, 'amount', parseFloat(this.value))"
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-500 rounded bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100"
                    >
                </div>
                <div>
                    <label class="text-xs text-gray-600 dark:text-gray-300 mb-1 block">Datum</label>
                    <input 
                        type="date" 
                        value="${sale.date || ''}"
                        onchange="updateSale(${index}, 'date', this.value)"
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-500 rounded bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100"
                    >
                </div>
            </div>
            <button 
                onclick="removeSale(${index})"
                class="px-3 py-2 bg-red-600 text-white rounded hover:bg-red-700 transition-all"
                title="Löschen"
            >
                <i class="fas fa-trash"></i>
            </button>
        </div>
    `).join('');
}

// Update a sale entry
function updateSale(index, field, value) {
    if (salesData[index]) {
        salesData[index][field] = value;
        updateChart();
    }
}

// Add new sale entry
document.getElementById('addSaleBtn')?.addEventListener('click', function() {
    salesData.push({
        label: '',
        amount: 0,
        date: new Date().toISOString().split('T')[0]
    });
    renderSalesEntries();
    updateChart();
});

// Remove sale entry
function removeSale(index) {
    if (confirm('Diesen Verkauf wirklich löschen?')) {
        salesData.splice(index, 1);
        renderSalesEntries();
        updateChart();
    }
}

// Chart.js instance
let salesChart = null;

// Update chart with current sales data
function updateChart() {
    const canvas = document.getElementById('salesChart');
    if (!canvas) return;
    
    const ctx = canvas.getContext('2d');
    
    // Destroy existing chart
    if (salesChart) {
        salesChart.destroy();
    }
    
    // Prepare data for chart
    const labels = salesData.map(s => s.label || 'Unbenannt');
    const amounts = salesData.map(s => parseFloat(s.amount) || 0);
    
    // Create new chart
    salesChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels.length > 0 ? labels : ['Keine Daten'],
            datasets: [{
                label: 'Verkaufsbetrag (€)',
                data: amounts.length > 0 ? amounts : [0],
                backgroundColor: 'rgba(147, 51, 234, 0.5)',
                borderColor: 'rgba(147, 51, 234, 1)',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return value.toFixed(2) + ' €';
                        }
                    }
                }
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ' + context.parsed.y.toFixed(2) + ' €';
                        }
                    }
                }
            }
        }
    });
}

// Save documentation
document.getElementById('saveDocumentationBtn')?.addEventListener('click', function() {
    const calculations = document.getElementById('calculations').value;
    const eventId = <?php echo $eventId; ?>;
    
    // Disable button during save
    this.disabled = true;
    this.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Wird gespeichert...';
    
    fetch('../../api/save_event_documentation.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            event_id: eventId,
            calculations: calculations,
            sales_data: salesData,
            sellers_data: sellersData
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage('Dokumentation erfolgreich gespeichert', 'success');
        } else {
            showMessage(data.message || 'Fehler beim Speichern', 'error');
        }
    })
    .catch(error => {
        showMessage('Netzwerkfehler beim Speichern', 'error');
    })
    .finally(() => {
        // Re-enable button
        this.disabled = false;
        this.innerHTML = '<i class="fas fa-save mr-2"></i>Dokumentation speichern';
    });
});

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    renderSellersEntries();
    renderSalesEntries();
    updateChart();
    
    // Load financial stats if section exists
    if (document.getElementById('financialStatsContainer')) {
        loadFinancialStats();
    }
});

// ===== Financial Stats Management =====
function openFinancialStatsModal(category) {
    const modal = document.getElementById('financialStatsModal');
    const modalTitle = document.getElementById('modalTitle');
    const categoryInput = document.getElementById('finStatCategory');
    
    modalTitle.textContent = category === 'Verkauf' ? 'Neue Verkäufe tracken' : 'Neue Kalkulation erfassen';
    categoryInput.value = category;
    
    // Reset form
    document.getElementById('financialStatsForm').reset();
    document.getElementById('finStatCategory').value = category;
    document.getElementById('finStatYear').value = new Date().getFullYear();
    
    modal.classList.remove('hidden');
}

function closeFinancialStatsModal() {
    document.getElementById('financialStatsModal').classList.add('hidden');
}

function saveFinancialStats() {
    const form = document.getElementById('financialStatsForm');
    const formData = new FormData(form);
    
    const data = {
        event_id: <?php echo $eventId; ?>,
        category: formData.get('category'),
        item_name: formData.get('item_name'),
        quantity: parseInt(formData.get('quantity')),
        revenue: formData.get('revenue') ? parseFloat(formData.get('revenue')) : null,
        record_year: parseInt(formData.get('record_year'))
    };
    
    // Validation
    if (!data.item_name || data.item_name.trim() === '') {
        showMessage('Bitte geben Sie einen Artikelnamen ein', 'error');
        return;
    }
    
    if (isNaN(data.quantity) || data.quantity < 0) {
        showMessage('Bitte geben Sie eine gültige Menge ein (≥ 0)', 'error');
        return;
    }
    
    if (data.revenue !== null && (isNaN(data.revenue) || data.revenue < 0)) {
        showMessage('Bitte geben Sie einen gültigen Umsatz ein (≥ 0)', 'error');
        return;
    }
    
    // Save via API
    fetch('../../api/save_financial_stats.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage('Eintrag erfolgreich gespeichert!', 'success');
            closeFinancialStatsModal();
            loadFinancialStats();
        } else {
            showMessage(data.message || 'Fehler beim Speichern', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('Netzwerkfehler beim Speichern', 'error');
    });
}

function loadFinancialStats() {
    const container = document.getElementById('financialStatsContainer');
    if (!container) return;
    
    fetch('../../api/get_financial_stats.php?event_id=<?php echo $eventId; ?>')
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            renderFinancialStats(result.data);
        } else {
            container.innerHTML = '<p class="text-red-500 text-center py-4">Fehler beim Laden der Daten</p>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        container.innerHTML = '<p class="text-red-500 text-center py-4">Netzwerkfehler</p>';
    });
}

function renderFinancialStats(data) {
    const container = document.getElementById('financialStatsContainer');
    
    if (!data.comparison || data.comparison.length === 0) {
        container.innerHTML = `
            <p class="text-gray-500 dark:text-gray-400 text-center py-8">
                Noch keine Finanzstatistiken vorhanden. Klicken Sie auf einen der Buttons oben, um Daten zu erfassen.
            </p>
        `;
        return;
    }
    
    // Group by category
    const verkauf = data.comparison.filter(item => item.category === 'Verkauf');
    const kalkulation = data.comparison.filter(item => item.category === 'Kalkulation');
    
    let html = '';
    
    // Render Verkauf section
    if (verkauf.length > 0) {
        html += renderCategoryTable('Verkauf', verkauf, data.available_years);
    }
    
    // Render Kalkulation section
    if (kalkulation.length > 0) {
        html += renderCategoryTable('Kalkulation', kalkulation, data.available_years);
    }
    
    container.innerHTML = html;
}

function renderCategoryTable(category, items, availableYears) {
    const icon = category === 'Verkauf' ? 'fa-shopping-cart' : 'fa-calculator';
    const color = category === 'Verkauf' ? 'blue' : 'green';
    
    // Group items by item_name
    const grouped = {};
    items.forEach(item => {
        if (!grouped[item.item_name]) {
            grouped[item.item_name] = {};
        }
        grouped[item.item_name][item.record_year] = {
            quantity: item.total_quantity,
            revenue: item.total_revenue
        };
    });
    
    let tableHtml = `
        <div class="mb-8">
            <h3 class="text-xl font-semibold text-gray-800 dark:text-gray-100 mb-4">
                <i class="fas ${icon} mr-2 text-${color}-600"></i>
                ${category}
            </h3>
            <div class="overflow-x-auto">
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="bg-gray-100 dark:bg-gray-700">
                            <th class="border border-gray-300 dark:border-gray-600 px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200">Artikel</th>
    `;
    
    // Add year columns
    availableYears.forEach(year => {
        tableHtml += `
            <th class="border border-gray-300 dark:border-gray-600 px-4 py-3 text-center font-semibold text-gray-700 dark:text-gray-200">${year}</th>
        `;
    });
    
    tableHtml += `
                        </tr>
                    </thead>
                    <tbody>
    `;
    
    // Add rows for each item
    Object.keys(grouped).sort().forEach(itemName => {
        tableHtml += `
            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                <td class="border border-gray-300 dark:border-gray-600 px-4 py-3 font-medium text-gray-800 dark:text-gray-200">${escapeHtml(itemName)}</td>
        `;
        
        availableYears.forEach(year => {
            const data = grouped[itemName][year];
            if (data) {
                const revenueText = data.revenue ? ` (${parseFloat(data.revenue).toFixed(2)}€)` : '';
                tableHtml += `
                    <td class="border border-gray-300 dark:border-gray-600 px-4 py-3 text-center text-gray-700 dark:text-gray-300">
                        <span class="font-semibold">${data.quantity}</span>${revenueText}
                    </td>
                `;
            } else {
                tableHtml += `
                    <td class="border border-gray-300 dark:border-gray-600 px-4 py-3 text-center text-gray-400">-</td>
                `;
            }
        });
        
        tableHtml += `</tr>`;
    });
    
    tableHtml += `
                    </tbody>
                </table>
            </div>
        </div>
    `;
    
    return tableHtml;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
<?php endif; ?>
</script>

<!-- Financial Stats Modal -->
<?php if ($canViewDocumentation): ?>
<div id="financialStatsModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl max-w-md w-full p-6">
        <div class="flex justify-between items-center mb-6">
            <h3 id="modalTitle" class="text-2xl font-bold text-gray-800 dark:text-gray-100">Neuer Eintrag</h3>
            <button onclick="closeFinancialStatsModal()" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
        
        <form id="financialStatsForm" onsubmit="event.preventDefault(); saveFinancialStats();">
            <input type="hidden" id="finStatCategory" name="category">
            
            <div class="mb-4">
                <label class="block text-gray-700 dark:text-gray-300 font-semibold mb-2">
                    Artikel/Stand-Name *
                </label>
                <input 
                    type="text" 
                    name="item_name" 
                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100"
                    placeholder="z.B. Brezeln, Äpfel, Grillstand"
                    required
                >
            </div>
            
            <div class="mb-4">
                <label class="block text-gray-700 dark:text-gray-300 font-semibold mb-2">
                    Menge *
                </label>
                <input 
                    type="number" 
                    name="quantity" 
                    min="0"
                    step="1"
                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100"
                    placeholder="z.B. 50"
                    required
                >
            </div>
            
            <div class="mb-4">
                <label class="block text-gray-700 dark:text-gray-300 font-semibold mb-2">
                    Umsatz (€) <span class="text-sm font-normal text-gray-500">(optional)</span>
                </label>
                <input 
                    type="number" 
                    name="revenue" 
                    min="0"
                    step="0.01"
                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100"
                    placeholder="z.B. 450.00"
                >
            </div>
            
            <div class="mb-6">
                <label class="block text-gray-700 dark:text-gray-300 font-semibold mb-2">
                    Jahr *
                </label>
                <input 
                    type="number" 
                    id="finStatYear"
                    name="record_year" 
                    min="2020"
                    max="2100"
                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100"
                    required
                >
            </div>
            
            <div class="flex gap-3">
                <button 
                    type="button"
                    onclick="closeFinancialStatsModal()"
                    class="flex-1 px-4 py-2 bg-gray-300 dark:bg-gray-600 text-gray-800 dark:text-gray-100 rounded-lg hover:bg-gray-400 dark:hover:bg-gray-500 transition-all"
                >
                    Abbrechen
                </button>
                <button 
                    type="submit"
                    class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-all"
                >
                    <i class="fas fa-save mr-2"></i>
                    Speichern
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
</script>

<!-- Load Chart.js from CDN (only if documentation is visible) -->
<?php if ($canViewDocumentation): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" 
        integrity="sha384-5fqx1ldll1ToJjLlNMfvgLr8aHmgv0yUHlK+TQevjvdX5v6aFJf0jShiTsvjN0hK" 
        crossorigin="anonymous"></script>
<?php endif; ?>

<script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../../includes/templates/main_layout.php';
?>
