<?php
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../includes/models/Alumni.php';
require_once __DIR__ . '/../../includes/helpers.php';

// Access Control: Allow all logged-in users (Admin, Board, Head, Member, Candidate, Alumni)
// No role restrictions - any authenticated user can view the alumni directory
if (!Auth::check()) {
    header('Location: ../auth/login.php');
    exit;
}

$user = Auth::user();

// Get search filters
$searchKeyword = $_GET['search'] ?? '';
$industryFilter = $_GET['industry'] ?? '';

// Build filters array
$filters = [];
if (!empty($searchKeyword)) {
    $filters['search'] = $searchKeyword;
}
if (!empty($industryFilter)) {
    $filters['industry'] = $industryFilter;
}

// Get alumni profiles
$profiles = Alumni::searchProfiles($filters);

// Get all industries for dropdown
$industries = Alumni::getAllIndustries();

$title = 'Alumni Directory - IBC Intranet';
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

    <!-- Header with Edit Button -->
    <div class="mb-8 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-4xl font-bold text-gray-800 mb-2">
                <i class="fas fa-user-graduate mr-3 text-purple-600"></i>
                Alumni Directory
            </h1>
            <p class="text-gray-600">Discover and connect with our alumni network</p>
        </div>
        
        <!-- Edit My Profile Button - Only for Alumni, Alumni-Vorstand, Alumni-Finanzprüfer, and Ehrenmitglied -->
        <?php if (in_array($user['role'], ['alumni', 'alumni_board', 'alumni_auditor', 'honorary_member'])): ?>
        <a href="../auth/profile.php" class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-purple-600 to-purple-700 text-white rounded-lg font-semibold hover:from-purple-700 hover:to-purple-800 transition-all shadow-lg hover:shadow-xl">
            <i class="fas fa-user-edit mr-2"></i>
            Profil bearbeiten
        </a>
        <?php endif; ?>
    </div>

    <!-- Search Bar and Filters -->
    <div class="card p-6 mb-8">
        <form method="GET" action="" class="space-y-4 sm:space-y-0 sm:flex sm:gap-4">
            <!-- Keyword Search -->
            <div class="flex-1">
                <label for="search" class="block text-sm font-medium text-gray-700 mb-2">
                    <i class="fas fa-search mr-1 text-purple-600"></i>
                    Search by Name, Position, Company or Industry
                </label>
                <input 
                    type="text" 
                    id="search" 
                    name="search" 
                    value="<?php echo htmlspecialchars($searchKeyword); ?>"
                    placeholder="Enter search term..."
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all"
                >
            </div>
            
            <!-- Industry Filter -->
            <div class="flex-1">
                <label for="industry" class="block text-sm font-medium text-gray-700 mb-2">
                    <i class="fas fa-industry mr-1 text-purple-600"></i>
                    Filter by Industry
                </label>
                <select 
                    id="industry" 
                    name="industry"
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all"
                >
                    <option value="">All Industries</option>
                    <?php foreach ($industries as $industry): ?>
                        <option value="<?php echo htmlspecialchars($industry); ?>" <?php echo $industryFilter === $industry ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($industry); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Search Button -->
            <div class="sm:flex sm:items-end">
                <button 
                    type="submit"
                    class="w-full sm:w-auto px-8 py-3 bg-gradient-to-r from-purple-600 to-purple-700 text-white rounded-lg font-semibold hover:from-purple-700 hover:to-purple-800 transition-all shadow-lg hover:shadow-xl"
                >
                    <i class="fas fa-search mr-2"></i>
                    Search
                </button>
            </div>
        </form>
        
        <!-- Clear Filters -->
        <?php if (!empty($searchKeyword) || !empty($industryFilter)): ?>
            <div class="mt-4">
                <a href="index.php" class="text-sm text-purple-600 hover:text-purple-800 transition-colors">
                    <i class="fas fa-times-circle mr-1"></i>
                    Clear all filters
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Results Count -->
    <div class="mb-6">
        <p class="text-gray-600">
            <strong><?php echo count($profiles); ?></strong> 
            <?php echo count($profiles) === 1 ? 'profile' : 'profiles'; ?> found
        </p>
    </div>

    <!-- Alumni Profiles Grid -->
    <?php if (empty($profiles)): ?>
        <div class="card p-12 text-center">
            <i class="fas fa-user-slash text-6xl text-gray-300 mb-4"></i>
            <p class="text-xl text-gray-600 mb-2">No profiles found</p>
            <p class="text-gray-500">Try adjusting your search filters</p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            <?php foreach ($profiles as $profile): ?>
                <div class="card p-6 hover:shadow-xl transition-shadow">
                    <!-- Profile Image -->
                    <div class="flex justify-center mb-4">
                        <?php 
                        // Generate initials for fallback
                        $initials = strtoupper(substr($profile['first_name'], 0, 1) . substr($profile['last_name'], 0, 1));
                        $imagePath = !empty($profile['image_path']) ? asset($profile['image_path']) : '';
                        ?>
                        <div class="w-24 h-24 rounded-full bg-gradient-to-br from-purple-400 to-purple-600 flex items-center justify-center text-white text-3xl font-bold overflow-hidden shadow-lg">
                            <?php if (!empty($imagePath)): ?>
                                <img 
                                    src="<?php echo $imagePath; ?>" 
                                    alt="<?php echo htmlspecialchars($profile['first_name'] . ' ' . $profile['last_name']); ?>"
                                    class="w-full h-full object-cover"
                                    onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                                >
                                <div style="display:none;" class="w-full h-full flex items-center justify-center text-3xl">
                                    <?php echo htmlspecialchars($initials); ?>
                                </div>
                            <?php else: ?>
                                <?php echo htmlspecialchars($initials); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Name -->
                    <h3 class="text-lg font-bold text-gray-800 text-center mb-2">
                        <?php echo htmlspecialchars($profile['first_name'] . ' ' . $profile['last_name']); ?>
                    </h3>
                    
                    <!-- Position & Company -->
                    <div class="text-center mb-4">
                        <p class="text-sm text-gray-600 mb-1">
                            <?php echo htmlspecialchars($profile['position']); ?>
                        </p>
                        <p class="text-sm text-gray-500">
                            <?php echo htmlspecialchars($profile['company']); ?>
                        </p>
                        <?php if (!empty($profile['industry'])): ?>
                            <p class="text-xs text-gray-400 mt-1">
                                <i class="fas fa-briefcase mr-1"></i>
                                <?php echo htmlspecialchars($profile['industry']); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Social Icons & Contact -->
                    <div class="flex justify-center items-center gap-3 mb-4">
                        <?php if (!empty($profile['linkedin_url'])): ?>
                            <a 
                                href="<?php echo htmlspecialchars($profile['linkedin_url']); ?>" 
                                target="_blank"
                                rel="noopener noreferrer"
                                class="w-10 h-10 flex items-center justify-center bg-blue-600 text-white rounded-full hover:bg-blue-700 transition-colors shadow-md"
                                title="LinkedIn Profile"
                            >
                                <i class="fab fa-linkedin-in"></i>
                            </a>
                        <?php endif; ?>
                        
                        <?php if (!empty($profile['xing_url'])): ?>
                            <a 
                                href="<?php echo htmlspecialchars($profile['xing_url']); ?>" 
                                target="_blank"
                                rel="noopener noreferrer"
                                class="w-10 h-10 flex items-center justify-center bg-green-700 text-white rounded-full hover:bg-green-800 transition-colors shadow-md"
                                title="Xing Profile"
                            >
                                <i class="fab fa-xing"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Contact Button -->
                    <a 
                        href="mailto:<?php echo htmlspecialchars($profile['email']); ?>"
                        class="block w-full text-center px-4 py-2 bg-gradient-to-r from-purple-600 to-purple-700 text-white rounded-lg font-semibold hover:from-purple-700 hover:to-purple-800 transition-all shadow-md"
                    >
                        <i class="fas fa-envelope mr-2"></i>
                        Contact
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
