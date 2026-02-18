<?php
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../includes/handlers/CSRFHandler.php';
require_once __DIR__ . '/../../includes/models/User.php';
require_once __DIR__ . '/../../includes/helpers.php';

// Redirect if already authenticated
if (Auth::check()) {
    header('Location: ../dashboard/index.php');
    exit;
}

$token = $_GET['token'] ?? '';
$error = '';
$invitation = null;

if (empty($token)) {
    $error = 'Kein Einladungstoken angegeben';
} else {
    // Check if token is valid
    $db = Database::getUserDB();
    $stmt = $db->prepare("SELECT * FROM invitation_tokens WHERE token = ? AND used_at IS NULL AND expires_at > NOW()");
    $stmt->execute([$token]);
    $invitation = $stmt->fetch();
    
    if (!$invitation) {
        $error = 'Einladungstoken ist ungültig oder abgelaufen';
    }
}

// Handle registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $invitation) {
    CSRFHandler::verifyToken($_POST['csrf_token'] ?? '');
    
    // Sanitize and validate email input using filter_var before processing
    // Note: Using only FILTER_VALIDATE_EMAIL as FILTER_SANITIZE_EMAIL is deprecated in PHP 8.1+
    $email = trim($invitation['email']);
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Ungültige E-Mail-Adresse';
    }
    
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (!empty($error)) {
        // Email validation failed, skip other validations
    } else if ($password !== $confirmPassword) {
        $error = 'Passwörter stimmen nicht überein';
    } else if (strlen($password) < 8) {
        $error = 'Passwort muss mindestens 8 Zeichen lang sein';
    } else {
        // Check if user already exists
        $existing = User::getByEmail($email);
        if ($existing) {
            $error = 'Benutzer existiert bereits';
        } else {
            // Create user
            $userId = User::create($email, $password, $invitation['role']);
            
            if ($userId) {
                // Mark invitation as used
                $stmt = $db->prepare("UPDATE invitation_tokens SET used_at = NOW(), used_by = ? WHERE id = ?");
                $stmt->execute([$userId, $invitation['id']]);
                
                // Auto-login
                $result = Auth::login($email, $password);
                if ($result['success']) {
                    header('Location: ../dashboard/index.php');
                    exit;
                }
            } else {
                $error = 'Fehler beim Erstellen des Benutzers';
            }
        }
    }
}

$title = 'Registrierung - IBC Intranet';
ob_start();
?>

<div class="w-full max-w-md p-8 bg-white rounded-2xl shadow-2xl">
    <div class="text-center mb-8">
        <div class="inline-block p-4 bg-gradient-to-br from-blue-500 to-green-500 rounded-full mb-4">
            <i class="fas fa-user-plus text-4xl text-white"></i>
        </div>
        <h1 class="text-3xl font-bold text-gray-800 mb-2">Registrierung</h1>
        <p class="text-gray-600">Erstellen Sie Ihr Konto</p>
    </div>

    <?php if ($error): ?>
    <div class="mb-6 p-4 bg-red-50 border border-red-300 rounded-lg text-red-800">
        <i class="fas fa-exclamation-circle mr-2"></i>
        <?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>

    <?php if ($invitation): ?>
    <form method="POST" class="space-y-6">
        <input type="hidden" name="csrf_token" value="<?php echo CSRFHandler::getToken(); ?>">
        <div class="bg-gray-50 p-4 rounded-lg mb-4 border border-gray-200">
            <p class="text-gray-600 text-sm mb-2">Sie wurden eingeladen als:</p>
            <p class="text-gray-800 font-semibold"><?php echo htmlspecialchars($invitation['email']); ?></p>
            <p class="text-gray-500 text-xs mt-1">
                Rolle: <?php 
                $roleNames = [
                    'board' => 'Vorstand',
                    'alumni_board' => 'Alumni-Vorstand',
                    'alumni_finanzprufer' => 'Alumni-Finanzprüfer',
                    'manager' => 'Ressortleiter',
                    'member' => 'Mitglied',
                    'alumni' => 'Alumni',
                    'candidate' => 'Anwärter'
                ];
                echo $roleNames[$invitation['role']] ?? ucfirst($invitation['role']);
                ?>
            </p>
            <?php if ($invitation['role'] === 'alumni'): ?>
            <div class="mt-3 p-2 bg-yellow-50 border border-yellow-300 rounded text-xs text-yellow-800">
                <i class="fas fa-info-circle mr-1"></i>
                <strong>Hinweis für Alumni:</strong> Ihr Profil wird nach der Registrierung vom Vorstand manuell geprüft und freigeschaltet, bevor Sie Zugriff auf interne Alumni-Netzwerkdaten erhalten.
            </div>
            <?php endif; ?>
        </div>

        <div>
            <label class="block text-gray-700 dark:text-gray-300 mb-2 font-medium">
                <i class="fas fa-lock mr-2"></i>Passwort
            </label>
            <input 
                type="password" 
                name="password" 
                required 
                minlength="8"
                autocomplete="new-password"
                class="w-full px-4 py-3 rounded-lg bg-white border border-gray-300 text-gray-900 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500 transition"
                placeholder="Mindestens 8 Zeichen"
            >
        </div>

        <div>
            <label class="block text-gray-700 dark:text-gray-300 mb-2 font-medium">
                <i class="fas fa-lock mr-2"></i>Passwort bestätigen
            </label>
            <input 
                type="password" 
                name="confirm_password" 
                required 
                minlength="8"
                autocomplete="new-password"
                class="w-full px-4 py-3 rounded-lg bg-white border border-gray-300 text-gray-900 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500 transition"
                placeholder="Passwort wiederholen"
            >
        </div>

        <button 
            type="submit" 
            class="w-full py-3 px-6 bg-gradient-to-r from-blue-500 to-green-500 text-white rounded-lg font-semibold hover:from-blue-600 hover:to-green-600 dark:hover:from-blue-600 dark:hover:to-green-600 transition transform hover:scale-105 shadow-lg"
        >
            <i class="fas fa-user-check mr-2"></i>
            Konto erstellen
        </button>
    </form>
    <?php endif; ?>

    <div class="mt-6 text-center">
        <a href="login.php" class="text-gray-600 text-sm hover:text-gray-800 transition">
            <i class="fas fa-arrow-left mr-1"></i>
            Zurück zum Login
        </a>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../includes/templates/auth_layout.php';
