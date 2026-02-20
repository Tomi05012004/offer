<?php
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../includes/helpers.php';

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

// Try to load links from DB, fall back to predefined array
$links = [];
try {
    $db = Database::getContentDB();
    $stmt = $db->prepare("SELECT title, url, description, icon FROM links ORDER BY title ASC");
    $stmt->execute();
    $links = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Table doesn't exist yet – use predefined fallback links
}

if (empty($links)) {
    $links = [
        [
            'title'       => 'Microsoft 365',
            'url'         => 'https://www.office.com',
            'description' => 'Office-Apps, E-Mail und Kalender',
            'icon'        => 'fab fa-microsoft',
        ],
        [
            'title'       => 'Microsoft Entra Admin',
            'url'         => 'https://entra.microsoft.com',
            'description' => 'Benutzerverwaltung und Identitäten',
            'icon'        => 'fas fa-id-badge',
        ],
        [
            'title'       => 'SharePoint',
            'url'         => 'https://sharepoint.com',
            'description' => 'Dokumente und Zusammenarbeit',
            'icon'        => 'fas fa-folder-open',
        ],
        [
            'title'       => 'Teams',
            'url'         => 'https://teams.microsoft.com',
            'description' => 'Chats, Meetings und Kanäle',
            'icon'        => 'fas fa-comments',
        ],
        [
            'title'       => 'Azure Portal',
            'url'         => 'https://portal.azure.com',
            'description' => 'Cloud-Infrastruktur und Dienste',
            'icon'        => 'fas fa-cloud',
        ],
        [
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

<div class="mb-8">
    <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-100 mb-2">
        <i class="fas fa-link text-ibc-green mr-2"></i>
        Nützliche Links
    </h1>
    <p class="text-gray-600 dark:text-gray-300">Schnellzugriff auf häufig genutzte Tools und Ressourcen</p>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
    <?php foreach ($links as $link):
        $rawUrl  = $link['url'] ?? '';
        $parsed  = parse_url($rawUrl);
        $scheme  = strtolower($parsed['scheme'] ?? '');
        $url     = (in_array($scheme, ['http', 'https']) && !empty($parsed['host'])) ? $rawUrl : '#';
        $icon = htmlspecialchars($link['icon'] ?? 'fas fa-external-link-alt', ENT_QUOTES, 'UTF-8');
    ?>
    <a href="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>"
       target="_blank"
       rel="noopener noreferrer"
       class="card p-6 flex items-start space-x-4 hover:shadow-lg transition-shadow duration-200 group">
        <div class="flex-shrink-0 w-12 h-12 bg-gradient-to-br from-ibc-green/20 to-emerald-100 dark:from-ibc-green/30 dark:to-emerald-900/30 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform duration-200">
            <i class="<?php echo $icon; ?> text-ibc-green text-xl"></i>
        </div>
        <div class="min-w-0">
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
    <?php endforeach; ?>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../includes/templates/main_layout.php';
