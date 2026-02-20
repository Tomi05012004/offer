<?php
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../includes/models/Member.php';
require_once __DIR__ . '/../../includes/helpers.php';

// Access Control: Accessible by ALL active roles (admin, board, head, member, candidate)
// Use Auth::check() which is the standard authentication method in this codebase
if (!Auth::check()) {
    header('Location: ../auth/login.php');
    exit;
}

$user = Auth::user();

// Check if user has permission to access members page
// Allowed: board members, head, member, candidate
$hasMembersAccess = Auth::canAccessPage('members');
if (!$hasMembersAccess) {
    header('Location: ../dashboard/index.php');
    exit;
}

// Get search filters
$searchKeyword = $_GET['search'] ?? '';
$roleFilter = $_GET['role'] ?? '';

// Get members using Member model
$members = Member::getAllActive(
    !empty($searchKeyword) ? $searchKeyword : null,
    !empty($roleFilter) ? $roleFilter : null
);

$title = 'Mitgliederverzeichnis - IBC Intranet';
ob_start();
?>

<div class="max-w-7xl mx-auto">
    <!-- Success Message -->
    <?php if (isset($_SESSION['success_message'])): ?>
    <div class="mb-6 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
        <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($_SESSION['success_message']); ?>
    </div>
    <?php 
        unset($_SESSION['success_message']); 
    endif; 
    ?>

    <!-- Header -->
    <div class="mb-8 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-4xl font-bold text-gray-800 mb-2">
                <i class="fas fa-users mr-3 text-blue-600"></i>
                Mitgliederverzeichnis
            </h1>
            <p class="text-gray-600">Entdecken und vernetzen Sie sich mit unseren aktiven Mitgliedern</p>
        </div>
        
        <!-- Edit My Profile Button - Only for Vorstand (all types), Resortleiter, Mitglied, Anwärter -->
        <?php if (Auth::isBoard() || Auth::hasRole(['head', 'member', 'candidate'])): ?>
        <a href="../auth/profile.php" class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg font-semibold hover:from-blue-700 hover:to-blue-800 transition-all shadow-lg hover:shadow-xl">
            <i class="fas fa-user-edit mr-2"></i>
            Profil bearbeiten
        </a>
        <?php endif; ?>
    </div>

    <!-- Filter/Search Bar -->
    <div class="card p-6 mb-8">
        <form method="GET" action="" class="space-y-4 sm:space-y-0 sm:flex sm:gap-4">
            <!-- Search Input (Text) -->
            <div class="flex-1">
                <label for="search" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    <i class="fas fa-search mr-1 text-blue-600"></i>
                    Nach Name suchen
                </label>
                <input 
                    type="text" 
                    id="search" 
                    name="search" 
                    value="<?php echo htmlspecialchars($searchKeyword); ?>"
                    placeholder="Name eingeben..."
                    class="w-full px-4 py-3 bg-white border-gray-300 text-gray-900 rounded-lg focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500 transition-all"
                >
            </div>
            
            <!-- Role Filter (Dropdown) -->
            <div class="flex-1">
                <label for="role" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    <i class="fas fa-filter mr-1 text-blue-600"></i>
                    Nach Rolle filtern
                </label>
                <select 
                    id="role" 
                    name="role"
                    class="w-full px-4 py-3 bg-white border-gray-300 text-gray-900 rounded-lg focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-800 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500 transition-all"
                >
                    <option value="">Alle</option>
                    <option value="candidate" <?php echo $roleFilter === 'candidate' ? 'selected' : ''; ?>>Anwärter</option>
                    <option value="member" <?php echo $roleFilter === 'member' ? 'selected' : ''; ?>>Mitglieder</option>
                    <option value="honorary_member" <?php echo $roleFilter === 'honorary_member' ? 'selected' : ''; ?>>Ehrenmitglieder</option>
                    <option value="head" <?php echo $roleFilter === 'head' ? 'selected' : ''; ?>>Ressortleiter</option>
                    <option value="alumni" <?php echo $roleFilter === 'alumni' ? 'selected' : ''; ?>>Alumni</option>
                    <option value="alumni_board" <?php echo $roleFilter === 'alumni_board' ? 'selected' : ''; ?>>Alumni-Vorstand</option>
                    <option value="alumni_auditor" <?php echo $roleFilter === 'alumni_auditor' ? 'selected' : ''; ?>>Alumni-Finanzprüfer</option>
                    <option value="board_finance" <?php echo $roleFilter === 'board_finance' ? 'selected' : ''; ?>>Vorstand Finanzen und Recht</option>
                    <option value="board_internal" <?php echo $roleFilter === 'board_internal' ? 'selected' : ''; ?>>Vorstand Intern</option>
                    <option value="board_external" <?php echo $roleFilter === 'board_external' ? 'selected' : ''; ?>>Vorstand Extern</option>
                </select>
            </div>
            
            <!-- Search Button -->
            <div class="sm:flex sm:items-end">
                <button 
                    type="submit"
                    class="w-full sm:w-auto px-8 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg font-semibold hover:from-blue-700 hover:to-blue-800 transition-all shadow-lg hover:shadow-xl"
                >
                    <i class="fas fa-search mr-2"></i>
                    Suchen
                </button>
            </div>
        </form>
        
        <!-- Clear Filters -->
        <?php if (!empty($searchKeyword) || !empty($roleFilter)): ?>
            <div class="mt-4">
                <a href="index.php" class="text-sm text-blue-600 hover:text-blue-800 transition-colors">
                    <i class="fas fa-times-circle mr-1"></i>
                    Alle Filter zurücksetzen
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Results Count -->
    <div class="mb-6">
        <p class="text-gray-600">
            <strong><?php echo count($members); ?></strong> 
            <?php echo count($members) === 1 ? 'Mitglied' : 'Mitglieder'; ?> gefunden
        </p>
    </div>

    <!-- Results Grid: Responsive (1 col mobile, 3 cols desktop) -->
    <?php if (empty($members)): ?>
        <div class="card p-12 text-center">
            <i class="fas fa-user-slash text-6xl text-gray-300 mb-4"></i>
            <p class="text-xl text-gray-600 mb-2">Keine Mitglieder gefunden</p>
            <p class="text-gray-500">Bitte Suchfilter anpassen</p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 items-stretch">
            <?php foreach ($members as $member): ?>
                <?php
                // Determine role badge color
                $roleBadgeColors = [
                    'board_finance' => 'bg-purple-100 text-purple-800 border-purple-300',
                    'board_internal' => 'bg-purple-100 text-purple-800 border-purple-300',
                    'board_external' => 'bg-purple-100 text-purple-800 border-purple-300',
                    'head' => 'bg-blue-100 text-blue-800 border-blue-300',
                    'member' => 'bg-green-100 text-green-800 border-green-300',
                    'candidate' => 'bg-yellow-100 text-yellow-800 border-yellow-300',
                    'alumni' => 'bg-gray-100 text-gray-800 border-gray-300',
                    'alumni_board' => 'bg-indigo-100 text-indigo-800 border-indigo-300',
                    'alumni_auditor' => 'bg-indigo-100 text-indigo-800 border-indigo-300',
                    'honorary_member' => 'bg-amber-100 text-amber-800 border-amber-300',
                ];
                
                // Display role: prefer Entra-derived display_role, fall back to local role label
                $badgeClass = $roleBadgeColors[$member['role']] ?? 'bg-gray-100 text-gray-800 border-gray-300';
                $displayRole = htmlspecialchars($member['display_role'] ?? Auth::getRoleLabel($member['role']));
                
                // Generate initials for fallback
                $initials = strtoupper(substr($member['first_name'], 0, 1) . substr($member['last_name'], 0, 1));
                
                // Check if image exists and is accessible
                $imagePath = '';
                $showPlaceholder = true;
                if (!empty($member['image_path'])) {
                    // Build the full file path for checking existence
                    $fullImagePath = __DIR__ . '/../../' . ltrim($member['image_path'], '/');
                    $realPath = realpath($fullImagePath);
                    $basePath = realpath(__DIR__ . '/../../');
                    
                    // Security: Verify the resolved path is within the base directory
                    if ($realPath !== false && $basePath !== false && 
                        strpos($realPath, $basePath) === 0 && is_file($realPath)) {
                        $imagePath = asset($member['image_path']);
                        $showPlaceholder = false;
                    }
                }
                
                // Info snippet: Show position, or study_program + degree, or 'Mitglied'
                $infoSnippet = '';
                if (!empty($member['position'])) {
                    $infoSnippet = $member['position'];
                } else {
                    // If position is empty, try study_program and degree
                    $studyParts = [];
                    // Check both study_program and studiengang fields
                    $studyProgram = !empty($member['study_program']) ? $member['study_program'] : 
                                    (!empty($member['studiengang']) ? $member['studiengang'] : '');
                    // Check both degree and angestrebter_abschluss fields
                    $degree = !empty($member['degree']) ? $member['degree'] : 
                              (!empty($member['angestrebter_abschluss']) ? $member['angestrebter_abschluss'] : '');
                    
                    if (!empty($studyProgram)) {
                        $studyParts[] = $studyProgram;
                    }
                    if (!empty($degree)) {
                        $studyParts[] = $degree;
                    }
                    
                    if (!empty($studyParts)) {
                        $infoSnippet = implode(' - ', $studyParts);
                    } else {
                        $infoSnippet = 'Mitglied';
                    }
                }
                ?>
                <div class="card p-6 hover:shadow-xl transition-shadow flex flex-col h-full relative">
                    <!-- Role Badge: Different colors for each role - Top Right Corner -->
                    <div class="absolute top-4 right-4">
                        <span class="inline-block px-3 py-1 text-xs font-semibold rounded-full border <?php echo $badgeClass; ?>">
                            <?php echo $displayRole; ?>
                        </span>
                    </div>
                    
                    <!-- Profile Image (Circle, top center) -->
                    <div class="flex justify-center mb-4 mt-2">
                        <?php if ($showPlaceholder): ?>
                            <!-- Placeholder with initials - Colored background -->
                            <div class="w-24 h-24 rounded-full bg-purple-100 flex items-center justify-center text-purple-600 text-3xl font-bold shadow-lg">
                                <?php echo htmlspecialchars($initials); ?>
                            </div>
                        <?php else: ?>
                            <!-- Image with fallback to placeholder on error -->
                            <div class="w-24 h-24 rounded-full bg-purple-100 flex items-center justify-center text-purple-600 text-3xl font-bold overflow-hidden shadow-lg">
                                <img 
                                    src="<?php echo htmlspecialchars($imagePath); ?>" 
                                    alt="<?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>"
                                    class="w-full h-full object-cover"
                                    onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                                >
                                <div style="display:none;" class="w-full h-full flex items-center justify-center text-3xl">
                                    <?php echo htmlspecialchars($initials); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Name (Bold) -->
                    <h3 class="text-lg font-bold text-gray-800 text-center mb-2">
                        <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>
                    </h3>
                    
                    <!-- Info Snippet: 'Position' or 'Studium + Degree' or 'Mitglied' -->
                    <div class="text-center mb-4 flex-grow flex items-center justify-center" style="min-height: 3rem;">
                        <p class="text-sm <?php echo ($infoSnippet === 'Mitglied') ? 'text-gray-500' : 'text-gray-600'; ?>">
                            <i class="fas fa-briefcase mr-1 text-gray-400"></i>
                            <?php echo htmlspecialchars($infoSnippet); ?>
                        </p>
                    </div>
                    
                    <!-- Contact Icons: Small icons for Mail and LinkedIn (if set) -->
                    <div class="flex justify-center items-center gap-3 mb-4">
                        <!-- Mail Icon -->
                        <?php if (!empty($member['email'])): ?>
                            <a 
                                href="mailto:<?php echo htmlspecialchars($member['email']); ?>" 
                                class="w-10 h-10 flex items-center justify-center bg-gray-600 text-white rounded-full hover:bg-gray-700 transition-colors shadow-md"
                                title="E-Mail senden"
                            >
                                <i class="fas fa-envelope"></i>
                            </a>
                        <?php endif; ?>
                        
                        <!-- LinkedIn Icon (if set) -->
                        <?php if (!empty($member['linkedin_url'])): ?>
                            <?php
                            // Validate LinkedIn URL to prevent XSS attacks
                            $linkedinUrl = $member['linkedin_url'];
                            $isValidLinkedIn = (
                                strpos($linkedinUrl, 'https://linkedin.com') === 0 ||
                                strpos($linkedinUrl, 'https://www.linkedin.com') === 0 ||
                                strpos($linkedinUrl, 'http://linkedin.com') === 0 ||
                                strpos($linkedinUrl, 'http://www.linkedin.com') === 0
                            );
                            ?>
                            <?php if ($isValidLinkedIn): ?>
                            <a 
                                href="<?php echo htmlspecialchars($linkedinUrl); ?>" 
                                target="_blank"
                                rel="noopener noreferrer"
                                class="w-10 h-10 flex items-center justify-center bg-blue-600 text-white rounded-full hover:bg-blue-700 transition-colors shadow-md"
                                title="LinkedIn Profil"
                            >
                                <i class="fab fa-linkedin-in"></i>
                            </a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Action: 'Profil ansehen' Button -->
                    <a 
                        href="../alumni/view.php?id=<?php echo $member['profile_id']; ?>&return_to=members"
                        class="block w-full text-center px-4 py-2 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg font-semibold hover:from-blue-700 hover:to-blue-800 transition-all shadow-md"
                    >
                        <i class="fas fa-user mr-2"></i>
                        Profil ansehen
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../../includes/templates/main_layout.php';
?>
