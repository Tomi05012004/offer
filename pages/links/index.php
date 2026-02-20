<?php
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/handlers/CSRFHandler.php';
require_once __DIR__ . '/../../includes/models/Link.php';

if (!Auth::check()) {
    header('Location: ../auth/login.php');
    exit;
}

$allowedRoles = ['board_finance', 'board_internal', 'board_external', 'alumni_board', 'alumni_auditor'];
$currentUser = Auth::user();
if (!$currentUser || !in_array($currentUser['role'] ?? '', $allowedRoles)) {
    header('Location: /index.php');
    exit;
}

$userRole = $currentUser['role'] ?? '';
$canManage = Link::canManage($userRole);

// Handle delete action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete' && $canManage) {
    CSRFHandler::verifyToken($_POST['csrf_token'] ?? '');
    $deleteId = (int)($_POST['link_id'] ?? 0);
    if ($deleteId > 0) {
        try {
            Link::delete($deleteId);
            $_SESSION['success_message'] = 'Link erfolgreich gelöscht.';
        } catch (Exception $e) {
            $_SESSION['error_message'] = 'Fehler beim Löschen des Links.';
        }
    }
    header('Location: index.php');
    exit;
}

// Load links from DB
$links = [];
$dbAvailable = false;
try {
    $links = Link::getAll();
    $dbAvailable = true;
} catch (Exception $e) {
    // Table doesn't exist yet – use predefined fallback links
}

if (!$dbAvailable || empty($links)) {
    $links = [
        [
            'id'          => null,
            'title'       => 'IBC Website',
            'url'         => 'https://www.business-consulting.de',
            'description' => 'Offizielle IBC-Vereinswebsite',
            'icon'        => 'fas fa-globe',
        ],
        [
            'id'          => null,
            'title'       => 'EasyVerein',
            'url'         => 'https://app.easyverein.com',
            'description' => 'Mitgliederverwaltung und Vereinsbuchhaltung',
            'icon'        => 'fas fa-users-cog',
        ],
        [
            'id'          => null,
            'title'       => 'Microsoft 365',
            'url'         => 'https://www.office.com',
            'description' => 'Office-Apps, E-Mail und Kalender',
            'icon'        => 'fab fa-microsoft',
        ],
        [
            'id'          => null,
            'title'       => 'Microsoft Entra Admin',
            'url'         => 'https://entra.microsoft.com',
            'description' => 'Benutzerverwaltung und Identitäten',
            'icon'        => 'fas fa-id-badge',
        ],
        [
            'id'          => null,
            'title'       => 'SharePoint',
            'url'         => 'https://sharepoint.com',
            'description' => 'Dokumente und Zusammenarbeit',
            'icon'        => 'fas fa-folder-open',
        ],
        [
            'id'          => null,
            'title'       => 'Teams',
            'url'         => 'https://teams.microsoft.com',
            'description' => 'Chats, Meetings und Kanäle',
            'icon'        => 'fas fa-comments',
        ],
        [
            'id'          => null,
            'title'       => 'Azure Portal',
            'url'         => 'https://portal.azure.com',
            'description' => 'Cloud-Infrastruktur und Dienste',
            'icon'        => 'fas fa-cloud',
        ],
        [
            'id'          => null,
            'title'       => 'GitHub',
            'url'         => 'https://github.com',
            'description' => 'Quellcode und Versionsverwaltung',
            'icon'        => 'fab fa-github',
        ],
    ];
}

$title = 'Nützliche Links - IBC Intranet';
ob_start();
?>

<div class="mb-8 flex justify-between items-center">
    <div>
        <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-100 mb-2">
            <i class="fas fa-link text-ibc-green mr-2"></i>
            Nützliche Links
        </h1>
        <p class="text-gray-600 dark:text-gray-300">Schnellzugriff auf häufig genutzte Tools und Ressourcen</p>
    </div>

    <?php if ($canManage): ?>
    <a href="edit.php"
       class="px-6 py-3 bg-gradient-to-r from-ibc-green to-ibc-green-dark text-white rounded-lg font-semibold hover:opacity-90 transition-all shadow-lg">
        <i class="fas fa-plus mr-2"></i>
        Neuen Link erstellen
    </a>
    <?php endif; ?>
</div>

<?php if (isset($_SESSION['success_message'])): ?>
<div class="mb-6 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
    <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($_SESSION['success_message']); ?>
</div>
<?php unset($_SESSION['success_message']); endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
<div class="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
    <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($_SESSION['error_message']); ?>
</div>
<?php unset($_SESSION['error_message']); endif; ?>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
    <?php foreach ($links as $link):
        $rawUrl  = $link['url'] ?? '';
        $parsed  = parse_url($rawUrl);
        $scheme  = strtolower($parsed['scheme'] ?? '');
        $url     = (in_array($scheme, ['http', 'https']) && !empty($parsed['host'])) ? $rawUrl : '#';
        $icon = htmlspecialchars($link['icon'] ?? 'fas fa-external-link-alt', ENT_QUOTES, 'UTF-8');
        $linkDbId = $link['id'] ?? null;
    ?>
    <div class="card p-6 flex flex-col group hover:shadow-lg transition-shadow duration-200">
        <a href="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>"
           target="_blank"
           rel="noopener noreferrer"
           class="flex items-start space-x-4 flex-1">
            <div class="flex-shrink-0 w-12 h-12 bg-gradient-to-br from-ibc-green/20 to-emerald-100 dark:from-ibc-green/30 dark:to-emerald-900/30 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform duration-200">
                <i class="<?php echo $icon; ?> text-ibc-green text-xl"></i>
            </div>
            <div class="min-w-0 flex-1">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 group-hover:text-ibc-green transition-colors duration-200">
                    <?php echo htmlspecialchars($link['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                </h3>
                <?php if (!empty($link['description'])): ?>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    <?php echo htmlspecialchars($link['description'], ENT_QUOTES, 'UTF-8'); ?>
                </p>
                <?php endif; ?>
            </div>
            <i class="fas fa-external-link-alt text-gray-300 dark:text-gray-600 text-xs ml-auto flex-shrink-0 mt-1 group-hover:text-ibc-green transition-colors duration-200"></i>
        </a>

        <?php if ($canManage && $linkDbId !== null): ?>
        <div class="mt-4 pt-3 border-t border-gray-100 dark:border-gray-700 flex justify-end gap-2">
            <a href="edit.php?id=<?php echo (int)$linkDbId; ?>"
               class="px-3 py-1 text-xs bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded hover:bg-gray-200 dark:hover:bg-gray-600 transition">
                <i class="fas fa-edit mr-1"></i>Bearbeiten
            </a>
            <form method="POST" action="index.php" data-confirm="Link wirklich löschen?" class="inline delete-form">
                <input type="hidden" name="csrf_token" value="<?php echo CSRFHandler::getToken(); ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="link_id" value="<?php echo (int)$linkDbId; ?>">
                <button type="submit"
                        class="px-3 py-1 text-xs bg-red-50 dark:bg-red-900/30 text-red-600 dark:text-red-400 rounded hover:bg-red-100 dark:hover:bg-red-900/50 transition">
                    <i class="fas fa-trash mr-1"></i>Löschen
                </button>
            </form>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>

<script>
document.querySelectorAll('.delete-form').forEach(function(form) {
    form.addEventListener('submit', function(e) {
        var msg = this.dataset.confirm || 'Wirklich löschen?';
        if (!confirm(msg)) {
            e.preventDefault();
        }
    });
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../includes/templates/main_layout.php';
