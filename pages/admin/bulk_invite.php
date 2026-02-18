<?php
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../includes/services/MicrosoftGraphService.php';
require_once __DIR__ . '/../../includes/helpers.php';

// Admin authentication check
if (!Auth::check() || !Auth::canManageUsers()) {
    header('Location: ../auth/login.php');
    exit;
}

$message = '';
$error = '';
$successCount = 0;
$errorCount = 0;
$results = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_invite'])) {
    $emailsText = $_POST['emails'] ?? '';
    $role = $_POST['role'] ?? 'candidate';
    
    // Validate role
    if (!in_array($role, Auth::VALID_ROLES)) {
        $error = 'Ungültige Rolle ausgewählt';
    } else {
        // Split emails by line breaks (handle Unix, Windows, and Mac line breaks)
        $emails = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $emailsText)));
        
        if (empty($emails)) {
            $error = 'Bitte geben Sie mindestens eine E-Mail-Adresse ein';
        } else {
            try {
                // Initialize Microsoft Graph Service
                $graphService = new MicrosoftGraphService();
                
                // Get redirect URL for invitations using BASE_URL constant (secure)
                $redirectUrl = BASE_URL;
                
                // Process each email
                foreach ($emails as $email) {
                    // Validate email format
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $errorCount++;
                        $results[] = [
                            'email' => $email,
                            'status' => 'error',
                            'message' => 'Ungültige E-Mail-Adresse'
                        ];
                        continue;
                    }
                    
                    try {
                        // Extract username from email (local part before @)
                        $emailLocalPart = explode('@', $email)[0];
                        
                        // Invite user
                        $userId = $graphService->inviteUser($email, $emailLocalPart, $redirectUrl);
                        
                        // Assign role
                        try {
                            $roleAssigned = $graphService->assignRole($userId, $role);
                            
                            if ($roleAssigned) {
                                $successCount++;
                                $results[] = [
                                    'email' => $email,
                                    'status' => 'success',
                                    'message' => 'Erfolgreich eingeladen und Rolle zugewiesen'
                                ];
                            } else {
                                $errorCount++;
                                $results[] = [
                                    'email' => $email,
                                    'status' => 'warning',
                                    'message' => 'Eingeladen, aber Rollenzuweisung fehlgeschlagen'
                                ];
                            }
                        } catch (Exception $roleError) {
                            $errorCount++;
                            $results[] = [
                                'email' => $email,
                                'status' => 'warning',
                                'message' => 'Eingeladen, aber Rollenzuweisung fehlgeschlagen: ' . $roleError->getMessage()
                            ];
                        }
                    } catch (Exception $e) {
                        $errorCount++;
                        $results[] = [
                            'email' => $email,
                            'status' => 'error',
                            'message' => $e->getMessage()
                        ];
                    }
                }
                
                // Generate summary message
                $roleTranslated = translateRole($role);
                if ($successCount > 0 || $errorCount > 0) {
                    $message = sprintf(
                        '%d Benutzer erfolgreich als "%s" eingeladen, %d Fehler',
                        $successCount,
                        $roleTranslated,
                        $errorCount
                    );
                }
                
            } catch (Exception $e) {
                $error = 'Fehler beim Initialisieren des Microsoft Graph Service: ' . $e->getMessage();
            }
        }
    }
}

$title = 'Masseneinladung - IBC Intranet';
ob_start();
?>

<div class="mb-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-100 mb-2">
                <i class="fas fa-user-plus text-blue-600 dark:text-blue-400 mr-2"></i>
                Masseneinladung
            </h1>
            <p class="text-gray-600 dark:text-gray-300">Laden Sie mehrere Benutzer gleichzeitig über Microsoft Graph ein</p>
        </div>
        <a href="users.php" class="btn-secondary">
            <i class="fas fa-arrow-left mr-2"></i>
            Zurück zur Benutzerverwaltung
        </a>
    </div>
</div>

<?php if ($message): ?>
<div class="mb-6 p-4 bg-green-100 dark:bg-green-900/50 border border-green-400 dark:border-green-700 text-green-700 dark:text-green-300 rounded-lg">
    <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="mb-6 p-4 bg-red-100 dark:bg-red-900/50 border border-red-400 dark:border-red-700 text-red-700 dark:text-red-300 rounded-lg">
    <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error); ?>
</div>
<?php endif; ?>

<!-- Invitation Form -->
<div class="card p-6 mb-6">
    <h2 class="text-xl font-bold text-gray-800 dark:text-gray-100 mb-4">
        <i class="fas fa-envelope-open-text text-purple-600 dark:text-purple-400 mr-2"></i>
        Benutzer einladen
    </h2>
    
    <form method="POST" class="space-y-6">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                <i class="fas fa-at mr-1"></i>
                E-Mail-Adressen
                <span class="text-xs text-gray-500 dark:text-gray-400 ml-2">(eine pro Zeile)</span>
            </label>
            <textarea 
                name="emails" 
                rows="10" 
                required 
                class="w-full px-4 py-3 bg-white border-gray-300 text-gray-900 rounded-lg focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500 font-mono text-sm"
                placeholder="benutzer1@beispiel.de&#10;benutzer2@beispiel.de&#10;benutzer3@beispiel.de"
            ><?php echo isset($_POST['emails']) ? htmlspecialchars($_POST['emails']) : ''; ?></textarea>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                <i class="fas fa-info-circle mr-1"></i>
                Geben Sie eine E-Mail-Adresse pro Zeile ein. Ungültige Adressen werden übersprungen.
            </p>
        </div>
        
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                <i class="fas fa-user-tag mr-1"></i>
                Rolle
            </label>
            <select 
                name="role" 
                required
                class="w-full px-4 py-3 bg-white border-gray-300 text-gray-900 rounded-lg focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
            >
                <?php foreach (Auth::VALID_ROLES as $roleOption): ?>
                <option value="<?php echo htmlspecialchars($roleOption); ?>" <?php echo (isset($_POST['role']) && $_POST['role'] === $roleOption) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars(translateRole($roleOption)); ?>
                </option>
                <?php endforeach; ?>
            </select>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                <i class="fas fa-info-circle mr-1"></i>
                Alle eingeladenen Benutzer erhalten diese Rolle.
            </p>
        </div>
        
        <div class="flex items-center justify-between pt-4 border-t border-gray-200 dark:border-gray-700">
            <div class="text-sm text-gray-600 dark:text-gray-400">
                <i class="fas fa-shield-alt mr-1 text-blue-600 dark:text-blue-400"></i>
                Die Einladungen werden über Microsoft Graph API verschickt.
            </div>
            <button type="submit" name="bulk_invite" class="btn-primary">
                <i class="fas fa-paper-plane mr-2"></i>
                Einladungen versenden
            </button>
        </div>
    </form>
</div>

<!-- Results Table -->
<?php if (!empty($results)): ?>
<div class="card overflow-hidden">
    <div class="p-6 bg-gradient-to-r from-gray-50 to-gray-100 dark:from-gray-800 dark:to-gray-700 border-b border-gray-200 dark:border-gray-600">
        <h2 class="text-xl font-bold text-gray-800 dark:text-gray-100">
            <i class="fas fa-list-check text-green-600 dark:text-green-400 mr-2"></i>
            Ergebnisse
        </h2>
        <p class="text-sm text-gray-600 dark:text-gray-300 mt-2">
            <?php echo count($results); ?> Einladungen verarbeitet
        </p>
    </div>
    
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                        <i class="fas fa-envelope mr-1"></i>E-Mail
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                        <i class="fas fa-flag mr-1"></i>Status
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                        <i class="fas fa-info-circle mr-1"></i>Nachricht
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                <?php foreach ($results as $result): ?>
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                        <i class="fas fa-at text-gray-400 mr-2"></i>
                        <?php echo htmlspecialchars($result['email']); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?php if ($result['status'] === 'success'): ?>
                            <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300">
                                <i class="fas fa-check mr-1"></i>Erfolg
                            </span>
                        <?php elseif ($result['status'] === 'warning'): ?>
                            <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300">
                                <i class="fas fa-exclamation-triangle mr-1"></i>Warnung
                            </span>
                        <?php else: ?>
                            <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300">
                                <i class="fas fa-times mr-1"></i>Fehler
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">
                        <?php echo htmlspecialchars($result['message']); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Summary Footer -->
    <div class="p-6 bg-gradient-to-r from-gray-50 to-gray-100 dark:from-gray-800 dark:to-gray-700 border-t border-gray-200 dark:border-gray-600">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-6">
                <div class="flex items-center">
                    <div class="flex items-center justify-center w-10 h-10 bg-green-100 dark:bg-green-900 rounded-full mr-3">
                        <i class="fas fa-check text-green-600 dark:text-green-400"></i>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-gray-900 dark:text-gray-100"><?php echo $successCount; ?></div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">Erfolgreich</div>
                    </div>
                </div>
                <div class="flex items-center">
                    <div class="flex items-center justify-center w-10 h-10 bg-red-100 dark:bg-red-900 rounded-full mr-3">
                        <i class="fas fa-times text-red-600 dark:text-red-400"></i>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-gray-900 dark:text-gray-100"><?php echo $errorCount; ?></div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">Fehler</div>
                    </div>
                </div>
            </div>
            <button onclick="window.print()" class="btn-secondary text-sm">
                <i class="fas fa-print mr-2"></i>
                Drucken
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Information Box -->
<div class="card p-6 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800">
    <h3 class="text-lg font-semibold text-blue-900 dark:text-blue-300 mb-3">
        <i class="fas fa-info-circle mr-2"></i>
        Wichtige Hinweise
    </h3>
    <ul class="space-y-2 text-sm text-blue-800 dark:text-blue-300">
        <li class="flex items-start">
            <i class="fas fa-arrow-right text-blue-600 dark:text-blue-400 mt-1 mr-2"></i>
            <span>Die Einladungen werden über Microsoft Graph API verschickt und die Benutzer erhalten eine E-Mail mit einem Einladungslink.</span>
        </li>
        <li class="flex items-start">
            <i class="fas fa-arrow-right text-blue-600 dark:text-blue-400 mt-1 mr-2"></i>
            <span>Jedem eingeladenen Benutzer wird automatisch die ausgewählte Rolle zugewiesen.</span>
        </li>
        <li class="flex items-start">
            <i class="fas fa-arrow-right text-blue-600 dark:text-blue-400 mt-1 mr-2"></i>
            <span>Ungültige E-Mail-Adressen werden übersprungen und im Ergebnis als Fehler angezeigt.</span>
        </li>
        <li class="flex items-start">
            <i class="fas fa-arrow-right text-blue-600 dark:text-blue-400 mt-1 mr-2"></i>
            <span>Der Vorgang kann einige Zeit dauern, abhängig von der Anzahl der Einladungen.</span>
        </li>
    </ul>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../includes/templates/main_layout.php';
