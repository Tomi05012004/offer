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

$userRole = $_SESSION['user_role'] ?? '';
if (!Link::canManage($userRole)) {
    header('Location: index.php');
    exit;
}

$linkId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$link = null;
$isEdit = false;

if ($linkId) {
    $link = Link::getById($linkId);
    if (!$link) {
        $_SESSION['error_message'] = 'Link nicht gefunden.';
        header('Location: index.php');
        exit;
    }
    $isEdit = true;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRFHandler::verifyToken($_POST['csrf_token'] ?? '');

    $title      = trim($_POST['title'] ?? '');
    $url        = trim($_POST['url'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $icon       = trim($_POST['icon'] ?? 'fas fa-link');
    $sortOrder  = (int)($_POST['sort_order'] ?? 0);

    if (empty($title)) {
        $errors[] = 'Bitte geben Sie einen Titel ein.';
    }
    if (empty($url)) {
        $errors[] = 'Bitte geben Sie eine URL ein.';
    } elseif (!filter_var($url, FILTER_VALIDATE_URL)) {
        $errors[] = 'Bitte geben Sie eine gültige URL ein (z.B. https://beispiel.de).';
    } else {
        $parsed = parse_url($url);
        $scheme = strtolower($parsed['scheme'] ?? '');
        if (!in_array($scheme, ['http', 'https'])) {
            $errors[] = 'Nur http:// und https:// URLs sind erlaubt.';
        }
    }
    if (empty($icon)) {
        $icon = 'fas fa-link';
    }

    if (empty($errors)) {
        $data = [
            'title'       => $title,
            'url'         => $url,
            'description' => $description ?: null,
            'icon'        => $icon,
            'sort_order'  => $sortOrder,
        ];

        try {
            if ($isEdit) {
                Link::update($linkId, $data);
                $_SESSION['success_message'] = 'Link erfolgreich aktualisiert!';
            } else {
                $data['created_by'] = $_SESSION['user_id'];
                Link::create($data);
                $_SESSION['success_message'] = 'Link erfolgreich erstellt!';
            }
            header('Location: index.php');
            exit;
        } catch (Exception $e) {
            $errors[] = 'Fehler beim Speichern: ' . htmlspecialchars($e->getMessage());
        }
    }
}

// Pre-fill form values
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['title'] ?? '');
    $url         = trim($_POST['url'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $icon        = trim($_POST['icon'] ?? 'fas fa-link');
    $sortOrder   = (int)($_POST['sort_order'] ?? 0);
} else {
    $title       = $link['title'] ?? '';
    $url         = $link['url'] ?? '';
    $description = $link['description'] ?? '';
    $icon        = $link['icon'] ?? 'fas fa-link';
    $sortOrder   = (int)($link['sort_order'] ?? 0);
}

$title_page = $isEdit ? 'Link bearbeiten - IBC Intranet' : 'Neuen Link erstellen - IBC Intranet';
ob_start();
?>

<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <a href="index.php" class="text-ibc-green hover:text-ibc-green-dark inline-flex items-center mb-4">
            <i class="fas fa-arrow-left mr-2"></i>Zurück zu Nützliche Links
        </a>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
        <?php foreach ($errors as $error): ?>
            <div><i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error); ?></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="card p-8">
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-100">
                <i class="fas fa-<?php echo $isEdit ? 'edit' : 'plus-circle'; ?> text-ibc-green mr-2"></i>
                <?php echo $isEdit ? 'Link bearbeiten' : 'Neuen Link erstellen'; ?>
            </h1>
        </div>

        <form method="POST" class="space-y-6">
            <input type="hidden" name="csrf_token" value="<?php echo CSRFHandler::getToken(); ?>">

            <!-- Title -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Titel *</label>
                <input
                    type="text"
                    name="title"
                    required
                    value="<?php echo htmlspecialchars($title); ?>"
                    placeholder="z.B. IBC Website"
                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-ibc-green dark:bg-gray-700 dark:text-gray-100"
                >
            </div>

            <!-- URL -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">URL *</label>
                <input
                    type="url"
                    name="url"
                    required
                    value="<?php echo htmlspecialchars($url); ?>"
                    placeholder="https://beispiel.de"
                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-ibc-green dark:bg-gray-700 dark:text-gray-100"
                >
            </div>

            <!-- Description -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Beschreibung (optional)</label>
                <input
                    type="text"
                    name="description"
                    value="<?php echo htmlspecialchars($description); ?>"
                    placeholder="Kurze Beschreibung des Links"
                    maxlength="500"
                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-ibc-green dark:bg-gray-700 dark:text-gray-100"
                >
            </div>

            <!-- Icon -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Icon (Font Awesome Klasse)</label>
                <div class="flex items-center gap-3">
                    <input
                        type="text"
                        name="icon"
                        id="icon_input"
                        value="<?php echo htmlspecialchars($icon); ?>"
                        placeholder="fas fa-link"
                        class="flex-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-ibc-green dark:bg-gray-700 dark:text-gray-100"
                    >
                    <span id="icon_preview" class="w-10 h-10 flex items-center justify-center bg-gray-100 dark:bg-gray-700 rounded-lg text-ibc-green text-xl">
                        <i class="<?php echo htmlspecialchars($icon); ?>"></i>
                    </span>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    z.B. <code>fas fa-globe</code>, <code>fab fa-github</code>, <code>fas fa-users-cog</code>
                    – <a href="https://fontawesome.com/icons" target="_blank" rel="noopener noreferrer" class="text-ibc-green hover:underline">Font Awesome Icons</a>
                </p>
            </div>

            <!-- Sort Order -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Reihenfolge</label>
                <input
                    type="number"
                    name="sort_order"
                    value="<?php echo (int)$sortOrder; ?>"
                    min="0"
                    class="w-32 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-ibc-green dark:bg-gray-700 dark:text-gray-100"
                >
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Niedrigere Zahlen werden zuerst angezeigt.</p>
            </div>

            <!-- Submit Buttons -->
            <div class="flex justify-end space-x-4 pt-4">
                <a href="index.php" class="px-6 py-2 bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-500 transition">
                    Abbrechen
                </a>
                <button type="submit" class="px-6 py-2 bg-gradient-to-r from-ibc-green to-ibc-green-dark text-white rounded-lg font-semibold hover:opacity-90 transition-all shadow-lg">
                    <i class="fas fa-save mr-2"></i><?php echo $isEdit ? 'Änderungen speichern' : 'Link erstellen'; ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Live icon preview
document.getElementById('icon_input').addEventListener('input', function() {
    var preview = document.getElementById('icon_preview');
    var iconEl = preview.querySelector('i');
    iconEl.className = this.value;
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../includes/templates/main_layout.php';
