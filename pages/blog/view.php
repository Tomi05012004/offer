<?php
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../includes/handlers/CSRFHandler.php';
require_once __DIR__ . '/../../includes/models/BlogPost.php';
require_once __DIR__ . '/../../src/Database.php';

// Check authentication
if (!Auth::check()) {
    header('Location: ../auth/login.php');
    exit;
}

// Get current user info
$user = Auth::user();
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'] ?? 'member';

// Get post ID from query parameter
$postId = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!$postId) {
    $_SESSION['error_message'] = 'Kein Beitrag angegeben.';
    header('Location: index.php');
    exit;
}

// Get the post with all details
$post = BlogPost::getById($postId);

if (!$post) {
    $_SESSION['error_message'] = 'Beitrag nicht gefunden.';
    header('Location: index.php');
    exit;
}

// Check if current user has liked this post
$contentDb = Database::getContentDB();
$likeStmt = $contentDb->prepare("SELECT COUNT(*) FROM blog_likes WHERE post_id = ? AND user_id = ?");
$likeStmt->execute([$postId, $userId]);
$userHasLiked = $likeStmt->fetchColumn() > 0;

$errors = [];
$successMessage = '';

// Handle like toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_like') {
    CSRFHandler::verifyToken($_POST['csrf_token'] ?? '');
    
    $newLikeState = BlogPost::toggleLike($postId, $userId);
    
    // Refresh post data to get updated like count
    $post = BlogPost::getById($postId);
    $userHasLiked = $newLikeState;
    
    $successMessage = $newLikeState ? 'Beitrag geliked!' : 'Like entfernt.';
}

// Handle add comment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_comment') {
    CSRFHandler::verifyToken($_POST['csrf_token'] ?? '');
    
    $commentContent = trim($_POST['comment_content'] ?? '');
    
    if (empty($commentContent)) {
        $errors[] = 'Bitte geben Sie einen Kommentar ein.';
    } elseif (strlen($commentContent) > 2000) {
        $errors[] = 'Der Kommentar ist zu lang. Maximum: 2000 Zeichen.';
    }
    
    if (empty($errors)) {
        try {
            BlogPost::addComment($postId, $userId, $commentContent);
            
            // Refresh post data to get new comment
            $post = BlogPost::getById($postId);
            
            $successMessage = 'Kommentar erfolgreich hinzugefügt!';
        } catch (Exception $e) {
            $errors[] = 'Fehler beim Hinzufügen des Kommentars. Bitte versuchen Sie es erneut.';
        }
    }
}

// Function to get category color classes
function getCategoryColor($category) {
    $colors = [
        'Allgemein' => 'bg-gray-100 text-gray-800',
        'IT' => 'bg-blue-100 text-blue-800',
        'Marketing' => 'bg-purple-100 text-purple-800',
        'Human Resources' => 'bg-green-100 text-green-800',
        'Qualitätsmanagement' => 'bg-yellow-100 text-yellow-800',
        'Akquise' => 'bg-red-100 text-red-800'
    ];
    return $colors[$category] ?? 'bg-gray-100 text-gray-800';
}

// Check if user can edit this post (is author OR admin/board)
$canEdit = ($post['author_id'] === $userId) || BlogPost::canAuth($userRole);

$title = htmlspecialchars($post['title']) . ' - IBC Intranet';
ob_start();
?>

<div class="max-w-6xl mx-auto">
    <!-- Back Button -->
    <div class="mb-6">
        <a href="index.php" class="text-blue-600 hover:text-blue-700 inline-flex items-center">
            <i class="fas fa-arrow-left mr-2"></i>Zurück zu News & Updates
        </a>
    </div>

    <!-- Success Message -->
    <?php if ($successMessage): ?>
    <div class="mb-6 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
        <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($successMessage); ?>
    </div>
    <?php endif; ?>

    <!-- Error Messages -->
    <?php if (!empty($errors)): ?>
    <div class="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
        <?php foreach ($errors as $error): ?>
            <div><i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error); ?></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Main Post Card -->
    <div class="card overflow-hidden mb-8">
        <!-- Full Width Header Image -->
        <?php if (!empty($post['image_path'])): ?>
            <div class="w-full h-96 overflow-hidden bg-gray-200">
                <img src="<?php echo htmlspecialchars($post['image_path']); ?>" 
                     alt="<?php echo htmlspecialchars($post['title']); ?>"
                     class="w-full h-full object-cover">
            </div>
        <?php else: ?>
            <div class="w-full h-96 bg-gradient-to-br from-blue-100 to-purple-100 flex items-center justify-center">
                <i class="fas fa-newspaper text-9xl text-gray-400"></i>
            </div>
        <?php endif; ?>
        
        <!-- Post Content -->
        <div class="p-8">
            <!-- Category Badge -->
            <div class="mb-4">
                <span class="px-4 py-2 text-sm font-semibold rounded-full <?php echo getCategoryColor($post['category']); ?>">
                    <?php echo htmlspecialchars($post['category']); ?>
                </span>
            </div>
            
            <!-- Title -->
            <h1 class="text-4xl font-bold text-gray-800 mb-4">
                <?php echo htmlspecialchars($post['title']); ?>
            </h1>
            
            <!-- Meta Information -->
            <div class="flex items-center gap-6 text-gray-600 mb-6 pb-6 border-b border-gray-200">
                <div class="flex items-center">
                    <i class="fas fa-user-circle mr-2 text-blue-600"></i>
                    <span><?php echo htmlspecialchars($post['author_email']); ?></span>
                </div>
                <div class="flex items-center">
                    <i class="fas fa-calendar-alt mr-2 text-blue-600"></i>
                    <span>
                        <?php 
                            $date = new DateTime($post['created_at']);
                            echo $date->format('d.m.Y H:i');
                        ?>
                    </span>
                </div>
            </div>
            
            <!-- Full Content -->
            <div class="prose max-w-none mb-8">
                <div class="text-gray-700 text-lg leading-relaxed whitespace-pre-wrap">
                    <?php echo htmlspecialchars($post['content']); ?>
                </div>
            </div>
            
            <!-- External Link Button -->
            <?php if (!empty($post['external_link'])): ?>
            <div class="mb-6">
                <a href="<?php echo htmlspecialchars($post['external_link']); ?>" 
                   target="_blank"
                   rel="noopener noreferrer"
                   class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-green-600 to-green-700 text-white rounded-lg font-semibold hover:from-green-700 hover:to-green-800 transition-all shadow-lg">
                    <i class="fas fa-external-link-alt mr-2"></i>
                    Mehr Informationen
                </a>
            </div>
            <?php endif; ?>
            
            <!-- Edit Button (Only for Author or Admin/Board) -->
            <?php if ($canEdit): ?>
            <div class="mb-6">
                <a href="edit.php?id=<?php echo (int)$post['id']; ?>" 
                   class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg font-semibold hover:from-blue-700 hover:to-blue-800 transition-all shadow-lg">
                    <i class="fas fa-edit mr-2"></i>
                    Beitrag bearbeiten
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Interaction Section -->
    <div class="card p-8 mb-8">
        <h2 class="text-2xl font-bold text-gray-800 mb-6">
            <i class="fas fa-heart mr-2 text-red-500"></i>
            Interaktion
        </h2>
        
        <!-- Like Button -->
        <form method="POST" class="mb-6">
            <input type="hidden" name="csrf_token" value="<?php echo CSRFHandler::getToken(); ?>">
            <input type="hidden" name="action" value="toggle_like">
            
            <button type="submit" 
                    class="inline-flex items-center px-6 py-3 rounded-lg font-semibold transition-all shadow-md hover:shadow-lg <?php echo $userHasLiked ? 'bg-red-600 text-white hover:bg-red-700' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                <i class="fas fa-heart mr-2"></i>
                <?php echo $userHasLiked ? 'Geliked' : 'Liken'; ?>
                <span class="ml-2 px-2 py-1 bg-white bg-opacity-20 rounded-full text-sm">
                    <?php echo (int)$post['like_count']; ?>
                </span>
            </button>
        </form>
    </div>

    <!-- Comments Section -->
    <div class="card p-8">
        <h2 class="text-2xl font-bold text-gray-800 mb-6">
            <i class="fas fa-comments mr-2 text-blue-500"></i>
            Kommentare (<?php echo count($post['comments']); ?>)
        </h2>
        
        <!-- Existing Comments -->
        <?php if (!empty($post['comments'])): ?>
            <div class="space-y-6 mb-8">
                <?php foreach ($post['comments'] as $comment): ?>
                    <div class="bg-gray-50 p-6 rounded-lg border border-gray-200">
                        <div class="flex items-center gap-4 mb-3">
                            <div class="flex items-center text-gray-700">
                                <i class="fas fa-user-circle mr-2 text-blue-600 text-xl"></i>
                                <span class="font-semibold"><?php echo htmlspecialchars($comment['commenter_email']); ?></span>
                            </div>
                            <div class="text-sm text-gray-500">
                                <i class="fas fa-clock mr-1"></i>
                                <?php 
                                    $commentDate = new DateTime($comment['created_at']);
                                    echo $commentDate->format('d.m.Y H:i');
                                ?>
                            </div>
                        </div>
                        <div class="text-gray-700 whitespace-pre-wrap">
                            <?php echo htmlspecialchars($comment['content']); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-gray-500 mb-8">Noch keine Kommentare. Seien Sie der Erste!</p>
        <?php endif; ?>
        
        <!-- Write Comment Form -->
        <div class="border-t border-gray-200 pt-6">
            <h3 class="text-xl font-semibold text-gray-800 mb-4">
                <i class="fas fa-pen mr-2"></i>
                Kommentar schreiben
            </h3>
            
            <form method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo CSRFHandler::getToken(); ?>">
                <input type="hidden" name="action" value="add_comment">
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Ihr Kommentar</label>
                    <textarea 
                        name="comment_content" 
                        required 
                        rows="4"
                        maxlength="2000"
                        placeholder="Schreibe Deinen Kommentar hier..."
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 font-sans"
                        style="resize: vertical; min-height: 100px;"
                    ></textarea>
                    <p class="text-sm text-gray-500 mt-2">
                        Maximum: 2000 Zeichen
                    </p>
                </div>
                
                <div>
                    <button type="submit" 
                            class="px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg font-semibold hover:from-blue-700 hover:to-blue-800 transition-all shadow-lg hover:shadow-xl">
                        <i class="fas fa-paper-plane mr-2"></i>
                        Kommentar absenden
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../../includes/templates/main_layout.php';
?>
