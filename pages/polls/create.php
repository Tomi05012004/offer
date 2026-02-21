<?php
/**
 * Create Poll - Form to create a new poll
 * Access: head, board (all variants)
 */

require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/helpers.php';

// Check authentication
if (!Auth::check()) {
    header('Location: ../auth/login.php');
    exit;
}

$user = Auth::user();

// Check if user has permission (head or board roles)
if (!(Auth::isBoard() || Auth::hasRole('head'))) {
    header('Location: ../dashboard/index.php');
    exit;
}

$successMessage = '';
$errorMessage = '';

// Default roles as fallback
const DEFAULT_ROLES = ['Board', 'Ressortleiter', 'Mitglied', 'Alumni'];

// Load available roles dynamically from database
$availableRoles = [];
try {
    $userDb = Database::getUserDB();
    
    // Get all non-null entra_roles from users table
    $stmt = $userDb->query("SELECT DISTINCT entra_roles FROM users WHERE entra_roles IS NOT NULL AND entra_roles != ''");
    $rolesSet = [];
    
    while ($row = $stmt->fetch()) {
        $roles = json_decode($row['entra_roles'], true);
        if (is_array($roles)) {
            foreach ($roles as $role) {
                if (!empty($role) && is_string($role)) {
                    $rolesSet[$role] = true;
                }
            }
        }
    }
    
    // Convert to sorted array
    $availableRoles = array_keys($rolesSet);
    sort($availableRoles);
    
} catch (Exception $e) {
    error_log('Error loading roles from database: ' . $e->getMessage());
    // Fallback to default roles if database query fails
    $availableRoles = DEFAULT_ROLES;
}

// If no roles found in database, use defaults
if (empty($availableRoles)) {
    $availableRoles = DEFAULT_ROLES;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_poll'])) {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $microsoftFormsUrl = trim($_POST['microsoft_forms_url'] ?? '');
    $targetGroups = $_POST['target_groups'] ?? [];
    $allowedRoles = $_POST['allowed_roles'] ?? [];
    $targetRoles  = $_POST['target_roles']  ?? [];
    $customRoles = trim($_POST['custom_roles'] ?? '');
    $visibleToAll = isset($_POST['visible_to_all']) ? 1 : 0;
    $isInternal = isset($_POST['is_internal']) ? 1 : 0;
    
    // Merge custom roles with selected roles
    if (!empty($customRoles)) {
        $customRolesArray = array_map('trim', explode(',', $customRoles));
        // Validate each custom role name
        $validatedCustomRoles = [];
        foreach ($customRolesArray as $role) {
            // Allow only alphanumeric characters, spaces, hyphens, underscores, and umlauts
            if (preg_match('/^[a-zA-Z0-9\s\-_äöüÄÖÜß]+$/', $role) && !empty($role)) {
                $validatedCustomRoles[] = $role;
            } else if (!empty($role)) {
                error_log('Invalid custom role name rejected: ' . $role);
            }
        }
        $allowedRoles = array_merge($allowedRoles, $validatedCustomRoles);
        $allowedRoles = array_unique(array_filter($allowedRoles));
    }
    
    // Validation
    if (empty($title)) {
        $errorMessage = 'Bitte geben Sie einen Titel ein.';
    } elseif (empty($microsoftFormsUrl)) {
        $errorMessage = 'Bitte geben Sie die Microsoft Forms URL ein.';
    } elseif (empty($targetGroups)) {
        $errorMessage = 'Bitte wählen Sie mindestens eine Zielgruppe aus.';
    } else {
        try {
            $db = Database::getContentDB();
            
            // Insert poll with Microsoft Forms URL and new fields
            $stmt = $db->prepare("
                INSERT INTO polls (title, description, created_by, microsoft_forms_url, target_groups, 
                                   allowed_roles, target_roles, visible_to_all, is_internal, is_active, end_date)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, DATE_ADD(NOW(), INTERVAL 30 DAY))
            ");
            $targetGroupsJson = json_encode($targetGroups);
            $allowedRolesJson = !empty($allowedRoles) ? json_encode($allowedRoles) : null;
            $targetRolesJson  = !empty($targetRoles)  ? json_encode(array_values(array_unique($targetRoles))) : null;
            $stmt->execute([
                $title, 
                $description, 
                $user['id'], 
                $microsoftFormsUrl, 
                $targetGroupsJson,
                $allowedRolesJson,
                $targetRolesJson,
                $visibleToAll,
                $isInternal
            ]);
            
            // Redirect to polls list
            header('Location: ' . asset('pages/polls/index.php'));
            exit;
            
        } catch (Exception $e) {
            error_log('Error creating poll: ' . $e->getMessage());
            $errorMessage = 'Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.';
        }
    }
}

$title = 'Umfrage erstellen - IBC Intranet';
ob_start();
?>

<div class="max-w-4xl mx-auto">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-4xl font-bold text-gray-800 dark:text-gray-100 mb-2">
            <i class="fas fa-poll mr-3 text-blue-500"></i>
            Umfrage erstellen
        </h1>
        <p class="text-gray-600 dark:text-gray-300">Erstellen Sie eine neue Umfrage für Ihre Mitglieder</p>
    </div>

    <!-- Error Message -->
    <?php if ($errorMessage): ?>
    <div class="mb-6 p-4 bg-red-100 dark:bg-red-900 border border-red-400 dark:border-red-700 text-red-700 dark:text-red-300 rounded-lg">
        <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($errorMessage); ?>
    </div>
    <?php endif; ?>

    <!-- Create Poll Form -->
    <div class="card p-8">
        <form method="POST" class="space-y-6" id="pollForm">
            <!-- Title -->
            <div>
                <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Titel <span class="text-red-500 dark:text-red-400">*</span>
                </label>
                <input 
                    type="text" 
                    id="title" 
                    name="title" 
                    required
                    maxlength="255"
                    value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>"
                    class="w-full px-4 py-3 bg-white border-gray-300 text-gray-900 rounded-lg focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white"
                    placeholder="Z.B. Wahl des Veranstaltungsortes"
                >
            </div>

            <!-- Description -->
            <div>
                <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Beschreibung (optional)
                </label>
                <textarea 
                    id="description" 
                    name="description" 
                    rows="4"
                    class="w-full px-4 py-3 bg-white border-gray-300 text-gray-900 rounded-lg focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white"
                    placeholder="Zusätzliche Informationen zur Umfrage..."
                ><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
            </div>

            <!-- Microsoft Forms URL -->
            <div>
                <label for="microsoft_forms_url" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Microsoft Forms URL <span class="text-red-500 dark:text-red-400">*</span>
                </label>
                <input 
                    type="url" 
                    id="microsoft_forms_url" 
                    name="microsoft_forms_url" 
                    required
                    value="<?php echo htmlspecialchars($_POST['microsoft_forms_url'] ?? ''); ?>"
                    class="w-full px-4 py-3 bg-white border-gray-300 text-gray-900 rounded-lg focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white"
                    placeholder="https://forms.office.com/Pages/ResponsePage.aspx?id=..."
                >
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    <i class="fas fa-info-circle mr-1"></i>
                    Fügen Sie die Embed-URL oder die direkte URL zu Ihrem Microsoft Forms ein.
                </p>
                <p class="mt-2 text-sm text-blue-600 dark:text-blue-400">
                    <i class="fas fa-lightbulb mr-1"></i>
                    <strong>Hinweis:</strong> Microsoft bietet keine API zum automatischen erstellen. Bitte erstellen Sie das Formular manuell auf forms.office.com und fügen Sie hier den Einbettungs-Code ein.
                </p>
            </div>

            <!-- Target Groups -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                    Zielgruppen <span class="text-red-500 dark:text-red-400">*</span>
                </label>
                <div class="space-y-2">
                    <label class="flex items-center p-3 bg-gray-50 dark:bg-gray-800 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer">
                        <input 
                            type="checkbox" 
                            name="target_groups[]" 
                            value="candidate"
                            <?php echo (isset($_POST['target_groups']) && in_array('candidate', $_POST['target_groups'])) ? 'checked' : ''; ?>
                            class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600"
                        >
                        <span class="ml-3 text-gray-700 dark:text-gray-300">Candidate</span>
                    </label>
                    <label class="flex items-center p-3 bg-gray-50 dark:bg-gray-800 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer">
                        <input 
                            type="checkbox" 
                            name="target_groups[]" 
                            value="alumni_board"
                            <?php echo (isset($_POST['target_groups']) && in_array('alumni_board', $_POST['target_groups'])) ? 'checked' : ''; ?>
                            class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600"
                        >
                        <span class="ml-3 text-gray-700 dark:text-gray-300">Alumni Board</span>
                    </label>
                    <label class="flex items-center p-3 bg-gray-50 dark:bg-gray-800 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer">
                        <input 
                            type="checkbox" 
                            name="target_groups[]" 
                            value="board"
                            <?php echo (isset($_POST['target_groups']) && in_array('board', $_POST['target_groups'])) ? 'checked' : ''; ?>
                            class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600"
                        >
                        <span class="ml-3 text-gray-700 dark:text-gray-300">Board</span>
                    </label>
                    <label class="flex items-center p-3 bg-gray-50 dark:bg-gray-800 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer">
                        <input 
                            type="checkbox" 
                            name="target_groups[]" 
                            value="member"
                            <?php echo (isset($_POST['target_groups']) && in_array('member', $_POST['target_groups'])) ? 'checked' : ''; ?>
                            class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600"
                        >
                        <span class="ml-3 text-gray-700 dark:text-gray-300">Member</span>
                    </label>
                    <label class="flex items-center p-3 bg-gray-50 dark:bg-gray-800 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer">
                        <input 
                            type="checkbox" 
                            name="target_groups[]" 
                            value="head"
                            <?php echo (isset($_POST['target_groups']) && in_array('head', $_POST['target_groups'])) ? 'checked' : ''; ?>
                            class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600"
                        >
                        <span class="ml-3 text-gray-700 dark:text-gray-300">Head</span>
                    </label>
                </div>
            </div>

            <!-- Visible to All Option -->
            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                <label class="flex items-start cursor-pointer">
                    <input 
                        type="checkbox" 
                        name="visible_to_all" 
                        id="visible_to_all"
                        value="1"
                        <?php echo (isset($_POST['visible_to_all'])) ? 'checked' : ''; ?>
                        class="w-5 h-5 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 mt-0.5"
                    >
                    <div class="ml-3">
                        <span class="font-medium text-gray-700 dark:text-gray-300">Für alle sichtbar</span>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            Wenn aktiviert, wird die Umfrage für alle Benutzer angezeigt, unabhängig von ihren Rollen.
                        </p>
                    </div>
                </label>
            </div>

            <!-- Allowed Roles (Entra Roles) - Multi-select -->
            <div id="allowed_roles_section" class="<?php echo (isset($_POST['visible_to_all'])) ? 'hidden' : ''; ?>">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                    Sichtbar für Rollen (optional)
                </label>
                <div class="space-y-2 mb-4">
                    <?php
                    $selectedRoles = $_POST['allowed_roles'] ?? [];
                    foreach ($availableRoles as $role):
                    ?>
                    <label class="flex items-center p-3 bg-gray-50 dark:bg-gray-800 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer">
                        <input 
                            type="checkbox" 
                            name="allowed_roles[]" 
                            value="<?php echo htmlspecialchars($role); ?>"
                            <?php echo (in_array($role, $selectedRoles)) ? 'checked' : ''; ?>
                            class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600"
                        >
                        <span class="ml-3 text-gray-700 dark:text-gray-300"><?php echo htmlspecialchars($role); ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
                
                <!-- Custom Roles Field -->
                <div class="mt-4">
                    <label for="custom_roles" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Zusätzliche Rollen (optional)
                    </label>
                    <input 
                        type="text" 
                        id="custom_roles" 
                        name="custom_roles" 
                        value="<?php echo htmlspecialchars($_POST['custom_roles'] ?? ''); ?>"
                        class="w-full px-4 py-3 bg-white border-gray-300 text-gray-900 rounded-lg focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white"
                        placeholder="z.B. Projektleiter, IT-Team"
                    >
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        <i class="fas fa-info-circle mr-1"></i>
                        Geben Sie weitere Rollen durch Komma getrennt ein, falls diese nicht in der Liste oben erscheinen.
                    </p>
                </div>
                
                <!-- Important Notice about Entra Roles -->
                <div class="mt-4 p-4 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg">
                    <div class="flex items-start">
                        <i class="fas fa-exclamation-triangle text-yellow-600 dark:text-yellow-400 mt-1 mr-3"></i>
                        <div class="text-sm text-yellow-800 dark:text-yellow-300">
                            <p class="font-semibold mb-1">Wichtig:</p>
                            <p>Die Rollennamen müssen <strong>exakt</strong> mit den Rollen in Microsoft Entra ID übereinstimmen. 
                            Groß-/Kleinschreibung und Leerzeichen müssen identisch sein. Bei Unsicherheit überprüfen Sie die 
                            Rollennamen in Entra ID oder im Benutzerprofil.</p>
                        </div>
                    </div>
                </div>
                
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                    <i class="fas fa-info-circle mr-1"></i>
                    Wählen Sie die Entra-Rollen aus, die diese Umfrage sehen dürfen. Leer lassen, um nur die Standard-Zielgruppen zu verwenden.
                </p>
            </div>

            <!-- Target Roles (Entra-based visibility) -->
            <div id="target_roles_section" class="<?php echo (isset($_POST['visible_to_all'])) ? 'hidden' : ''; ?>">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Zielgruppen-Rollen (Microsoft Entra)
                </label>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">
                    <i class="fas fa-shield-alt mr-1"></i>
                    Nur Nutzer, deren Entra-Rollen mindestens eine der folgenden Rollen beinhalten, sehen diese Umfrage.
                    Leer lassen, um keine Entra-Einschränkung zu setzen.
                </p>
                <div class="space-y-2 mb-3">
                    <?php
                    $selectedTargetRoles = $_POST['target_roles'] ?? [];
                    foreach ($availableRoles as $role):
                    ?>
                    <label class="flex items-center p-3 bg-indigo-50 dark:bg-indigo-900/20 rounded-lg hover:bg-indigo-100 dark:hover:bg-indigo-900/40 cursor-pointer border border-indigo-100 dark:border-indigo-800">
                        <input 
                            type="checkbox" 
                            name="target_roles[]" 
                            value="<?php echo htmlspecialchars($role); ?>"
                            <?php echo (in_array($role, $selectedTargetRoles)) ? 'checked' : ''; ?>
                            class="w-4 h-4 text-indigo-600 bg-gray-100 border-gray-300 rounded focus:ring-indigo-500 dark:focus:ring-indigo-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600"
                        >
                        <span class="ml-3 text-gray-700 dark:text-gray-300"><?php echo htmlspecialchars($role); ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Is Internal Option -->
            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                <label class="flex items-start cursor-pointer">
                    <input 
                        type="checkbox" 
                        name="is_internal" 
                        value="1"
                        <?php echo (!isset($_POST['create_poll']) || (isset($_POST['create_poll']) && isset($_POST['is_internal']))) ? 'checked' : ''; ?>
                        class="w-5 h-5 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 mt-0.5"
                    >
                    <div class="ml-3">
                        <span class="font-medium text-gray-700 dark:text-gray-300">Interne Umfrage</span>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            Wenn aktiviert, wird die Umfrage automatisch ausgeblendet, nachdem der Benutzer abgestimmt hat. 
                            Deaktivieren Sie diese Option für externe Microsoft Forms-Umfragen, um den "Erledigt / Ausblenden"-Button anzuzeigen.
                        </p>
                    </div>
                </label>
            </div>

            <!-- Info Box -->
            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                <div class="flex items-start">
                    <i class="fas fa-info-circle text-blue-600 dark:text-blue-400 mt-1 mr-3"></i>
                    <div class="text-sm text-blue-800 dark:text-blue-300">
                        <p class="font-semibold mb-1">Hinweis:</p>
                        <p>Die Umfrage wird automatisch aktiv und die ausgewählten Zielgruppen können über Microsoft Forms teilnehmen. Die Umfrage läuft standardmäßig 30 Tage.</p>
                    </div>
                </div>
            </div>

            <!-- Submit Buttons -->
            <div class="flex gap-4">
                <button 
                    type="submit"
                    name="create_poll"
                    class="flex-1 px-6 py-3 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-lg font-semibold hover:from-blue-600 hover:to-blue-700 transition-all shadow-lg"
                >
                    <i class="fas fa-check mr-2"></i>
                    Umfrage erstellen
                </button>
                <a 
                    href="<?php echo asset('pages/polls/index.php'); ?>"
                    class="px-6 py-3 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg font-semibold hover:bg-gray-300 dark:hover:bg-gray-600 transition-all text-center"
                >
                    Abbrechen
                </a>
            </div>
        </form>
    </div>
</div>

<script>
// Toggle visibility of the allowed_roles_section and target_roles_section based on visible_to_all checkbox
document.addEventListener('DOMContentLoaded', function() {
    const visibleToAllCheckbox = document.getElementById('visible_to_all');
    const allowedRolesSection  = document.getElementById('allowed_roles_section');
    const targetRolesSection   = document.getElementById('target_roles_section');
    
    function toggleRoleSections(hide) {
        [allowedRolesSection, targetRolesSection].forEach(function(section) {
            if (!section) return;
            if (hide) {
                section.classList.add('hidden');
            } else {
                section.classList.remove('hidden');
            }
        });
    }

    if (visibleToAllCheckbox) {
        visibleToAllCheckbox.addEventListener('change', function() {
            toggleRoleSections(this.checked);
        });
    }
});
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../../includes/templates/main_layout.php';
?>
