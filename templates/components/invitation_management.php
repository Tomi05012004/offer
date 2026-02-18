<?php
/**
 * Invitation Management Component
 * Modern UI for creating and managing user invitations
 * Required permissions: board or alumni_board
 */

// This component expects to be included in a page that has already done auth checks
// Also requires CSRFHandler to be loaded
require_once __DIR__ . '/../../includes/handlers/CSRFHandler.php';
?>

<div class="mb-8">
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center mb-3">
                <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-indigo-600 rounded-xl flex items-center justify-center mr-4 shadow-lg">
                    <i class="fas fa-envelope text-white text-xl"></i>
                </div>
                <div>
                    <h2 class="text-3xl font-bold text-gray-800 dark:text-gray-100">
                        Einladungs-Management
                    </h2>
                    <p class="text-gray-600 dark:text-gray-400">Erstellen Sie Einladungslinks für neue Mitglieder und Alumni</p>
                </div>
            </div>
        </div>
        <button 
            type="button" 
            id="openImportModalBtn" 
            class="px-6 py-3 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-xl hover:from-blue-700 hover:to-indigo-700 transition-all duration-200 flex items-center gap-2 font-semibold shadow-lg hover:shadow-xl transform hover:-translate-y-0.5"
            title="JSON Import"
        >
            <i class="fas fa-file-import"></i>
            JSON Import
        </button>
    </div>
</div>

<!-- Invitation Creation Card -->
<div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl overflow-hidden border border-gray-100 dark:border-gray-700 p-8 mb-6">
    <div class="flex items-center mb-6">
        <div class="w-10 h-10 bg-gradient-to-br from-green-500 to-emerald-600 rounded-lg flex items-center justify-center mr-3">
            <i class="fas fa-link text-white text-lg"></i>
        </div>
        <h3 class="text-2xl font-bold text-gray-800 dark:text-gray-100">
            Einladung erstellen
        </h3>
    </div>
    
    <form id="invitationForm" class="space-y-6 mb-4">
        <input type="hidden" name="csrf_token" value="<?php echo CSRFHandler::getToken(); ?>">
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">E-Mail-Adresse</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                        <i class="fas fa-envelope text-gray-400"></i>
                    </div>
                    <input 
                        type="email" 
                        id="invitationEmail" 
                        name="email" 
                        required 
                        class="w-full pl-11 pr-4 py-3 bg-white border-2 border-gray-200 text-gray-900 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white transition-all"
                        placeholder="benutzer@beispiel.de"
                    >
                </div>
            </div>
            
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Rolle</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                        <i class="fas fa-user-tag text-gray-400"></i>
                    </div>
                    <select 
                        id="invitationRole" 
                        name="role" 
                        class="w-full pl-11 pr-4 py-3 bg-white border-2 border-gray-200 text-gray-900 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white appearance-none cursor-pointer transition-all"
                    >
                        <option value="candidate">Anwärter</option>
                        <option value="member">Mitglied</option>
                        <option value="head">Ressortleiter</option>
                        <option value="alumni">Alumni</option>
                        <option value="honorary_member">Ehrenmitglied</option>
                        <option value="alumni_board">Alumni-Vorstand</option>
                        <option value="alumni_auditor">Alumni-Finanzprüfer</option>
                        <option value="board_finance">Vorstand Finanzen und Recht</option>
                        <option value="board_internal">Vorstand Intern</option>
                        <option value="board_external">Vorstand Extern</option>
                    </select>
                    <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none">
                        <i class="fas fa-chevron-down text-gray-400 text-sm"></i>
                    </div>
                </div>
            </div>
            
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Token Validity</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                        <i class="fas fa-clock text-gray-400"></i>
                    </div>
                    <select 
                        id="validityHours" 
                        name="validity_hours" 
                        class="w-full pl-11 pr-4 py-3 bg-white border-2 border-gray-200 text-gray-900 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white appearance-none cursor-pointer transition-all"
                    >
                        <option value="24">24 hours</option>
                        <option value="168" selected>7 days</option>
                        <option value="720">30 days</option>
                    </select>
                    <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none">
                        <i class="fas fa-chevron-down text-gray-400 text-sm"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Email Send Option -->
        <div class="flex items-center space-x-3 p-5 bg-gradient-to-r from-purple-50 to-indigo-50 dark:from-purple-900/20 dark:to-indigo-900/20 border-2 border-purple-200 dark:border-purple-800 rounded-xl">
            <div class="flex items-center">
                <input 
                    type="checkbox" 
                    id="sendMailCheckbox" 
                    name="send_mail" 
                    value="1"
                    checked
                    class="w-5 h-5 text-purple-600 bg-gray-100 border-gray-300 rounded focus:ring-purple-500 focus:ring-2 cursor-pointer"
                >
            </div>
            <label for="sendMailCheckbox" class="flex-1 cursor-pointer">
                <span class="text-sm font-bold text-gray-700 dark:text-gray-200">Einladung direkt per E-Mail senden</span>
                <p class="text-xs text-gray-600 dark:text-gray-400 mt-1 flex items-center">
                    <i class="fas fa-envelope text-purple-500 mr-1.5"></i>
                    Der Einladungslink wird automatisch per E-Mail an die angegebene Adresse versendet
                </p>
            </label>
        </div>
        
        <div>
            <button type="submit" class="w-full py-3.5 bg-gradient-to-r from-purple-600 to-indigo-600 text-white rounded-xl hover:from-purple-700 hover:to-indigo-700 transition-all duration-200 font-bold text-lg shadow-lg hover:shadow-xl transform hover:-translate-y-0.5">
                <i class="fas fa-magic mr-2"></i>Link erstellen
            </button>
        </div>
    </form>
    
    <!-- Generated Link Display -->
    <div id="generatedLinkContainer" class="hidden mt-6 p-5 bg-gradient-to-r from-green-50 to-emerald-50 dark:from-green-900/30 dark:to-emerald-900/30 border-2 border-green-300 dark:border-green-700 rounded-xl shadow-lg">
        <div class="flex items-start justify-between mb-3">
            <div class="flex-1">
                <div class="flex items-center mb-2">
                    <div class="w-8 h-8 bg-green-500 rounded-lg flex items-center justify-center mr-2">
                        <i class="fas fa-check-circle text-white"></i>
                    </div>
                    <p class="text-sm font-bold text-green-800 dark:text-green-200">
                        <span id="generatedSuccessMessage">Einladungslink erfolgreich erstellt!</span>
                    </p>
                </div>
                <p class="text-xs text-green-700 dark:text-green-300 ml-10 flex items-center gap-3">
                    <span class="flex items-center">
                        <i class="fas fa-envelope mr-1"></i>
                        <span id="generatedEmail" class="font-semibold"></span>
                    </span>
                    <span class="flex items-center">
                        <i class="fas fa-user-tag mr-1"></i>
                        <span id="generatedRole" class="font-semibold"></span>
                    </span>
                </p>
            </div>
        </div>
        <div id="generatedLinkSection" class="flex items-center gap-3">
            <input 
                type="text" 
                id="generatedLink" 
                readonly 
                class="flex-1 px-4 py-3 bg-white dark:bg-gray-700 border-2 border-green-300 dark:border-green-700 rounded-xl text-sm font-mono text-gray-700 dark:text-gray-300 focus:outline-none shadow-sm"
            >
            <button 
                type="button" 
                id="copyLinkBtn" 
                class="px-5 py-3 bg-gradient-to-r from-green-600 to-emerald-600 text-white rounded-xl hover:from-green-700 hover:to-emerald-700 transition-all duration-200 flex items-center gap-2 font-semibold shadow-md hover:shadow-lg transform hover:-translate-y-0.5"
                title="Link kopieren"
            >
                <i class="fas fa-copy"></i>
                Kopieren
            </button>
        </div>
    </div>
</div>

<!-- Open Invitations List -->
<div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl overflow-hidden border border-gray-100 dark:border-gray-700">
    <div class="p-6 bg-gradient-to-r from-gray-50 to-slate-50 dark:from-gray-800 dark:to-gray-750 border-b-2 border-gray-200 dark:border-gray-700">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-lg flex items-center justify-center mr-3">
                    <i class="fas fa-list text-white text-lg"></i>
                </div>
                <h3 class="text-2xl font-bold text-gray-800 dark:text-gray-100">
                    Offene Einladungen
                </h3>
            </div>
            <button 
                type="button" 
                id="refreshInvitationsBtn" 
                class="px-4 py-2 bg-gradient-to-r from-blue-100 to-indigo-100 dark:from-blue-900/50 dark:to-indigo-900/50 text-blue-700 dark:text-blue-300 rounded-lg hover:from-blue-200 hover:to-indigo-200 dark:hover:from-blue-900 dark:hover:to-indigo-900 transition-all font-semibold shadow-sm"
            >
                <i class="fas fa-sync-alt mr-2"></i>Aktualisieren
            </button>
        </div>
    </div>
    
    <div id="invitationsLoader" class="p-8 text-center">
        <div class="inline-block w-12 h-12 bg-gradient-to-br from-purple-500 to-indigo-600 rounded-full animate-pulse"></div>
        <p class="text-gray-600 dark:text-gray-400 mt-4 font-medium">Lade Einladungen...</p>
    </div>
    
    <div id="invitationsContainer" class="hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gradient-to-r from-gray-100 to-slate-100 dark:from-gray-700 dark:to-gray-600 border-b-2 border-purple-200 dark:border-purple-900">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 dark:text-gray-200 uppercase tracking-wider">E-Mail</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 dark:text-gray-200 uppercase tracking-wider">Rolle</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 dark:text-gray-200 uppercase tracking-wider">Erstellt am</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 dark:text-gray-200 uppercase tracking-wider">Läuft ab</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 dark:text-gray-200 uppercase tracking-wider">Erstellt von</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 dark:text-gray-200 uppercase tracking-wider">Link</th>
                        <th class="px-6 py-4 text-center text-xs font-bold text-gray-700 dark:text-gray-200 uppercase tracking-wider">Aktionen</th>
                    </tr>
                </thead>
                <tbody id="invitationsList" class="bg-white dark:bg-gray-800 divide-y divide-gray-100 dark:divide-gray-700">
                    <!-- Dynamically populated -->
                </tbody>
            </table>
        </div>
    </div>
    
    <div id="noInvitationsMessage" class="hidden p-12 text-center text-gray-500 dark:text-gray-400">
        <div class="inline-block w-16 h-16 bg-gray-100 dark:bg-gray-700 rounded-full flex items-center justify-center mb-4">
            <i class="fas fa-inbox text-4xl text-gray-400"></i>
        </div>
        <p class="text-lg font-medium">Keine offenen Einladungen vorhanden</p>
    </div>
</div>

<!-- JSON Import Modal -->
<div id="importModal" class="hidden fixed inset-0 bg-black bg-opacity-60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto shadow-2xl">
        <!-- Modal Header -->
        <div class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white p-6 rounded-t-2xl">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-white bg-opacity-20 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-file-import text-xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold">
                        JSON Massenimport
                    </h3>
                </div>
                <button type="button" id="closeImportModalBtn" class="w-10 h-10 bg-white bg-opacity-20 rounded-lg hover:bg-opacity-30 transition-all flex items-center justify-center">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
        </div>
        
        <!-- Modal Body -->
        <div class="p-6">
            <div class="mb-6">
                <p class="text-gray-700 dark:text-gray-300 mb-4">
                    Laden Sie eine JSON-Datei hoch, um mehrere Einladungen gleichzeitig zu erstellen.
                </p>
                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/30 dark:to-indigo-900/30 border-2 border-blue-200 dark:border-blue-800 rounded-xl p-5 mb-4">
                    <div class="flex items-center mb-3">
                        <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center mr-2">
                            <i class="fas fa-info-circle text-white"></i>
                        </div>
                        <p class="text-sm font-bold text-blue-800 dark:text-blue-200">
                            JSON-Format:
                        </p>
                    </div>
                    <pre class="text-xs bg-white dark:bg-gray-900 p-4 rounded-lg border-2 border-blue-300 dark:border-blue-700 overflow-x-auto font-mono"><code>[
  {
    "email": "user1@example.com",
    "role": "member"
  },
  {
    "email": "user2@example.com",
    "role": "alumni"
  }
]</code></pre>
                    <!-- Note: This role list matches Auth::VALID_ROLES. Keep in sync when roles change. -->
                    <p class="text-xs text-blue-700 dark:text-blue-300 mt-3 flex items-start">
                        <i class="fas fa-user-tag mr-2 mt-0.5"></i>
                        <span><strong>Verfügbare Rollen:</strong> candidate, member, head, alumni, honorary_member, alumni_board, alumni_auditor, board_finance, board_internal, board_external</span>
                    </p>
                </div>
            </div>
            
            <form id="importForm" class="space-y-5">
                <input type="hidden" name="csrf_token" value="<?php echo CSRFHandler::getToken(); ?>">
                
                <div>
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-3">
                        JSON-Datei auswählen
                    </label>
                    <input 
                        type="file" 
                        id="jsonFileInput" 
                        name="json_file" 
                        accept=".json"
                        required
                        class="w-full px-4 py-3 bg-white dark:bg-gray-700 border-2 border-gray-200 dark:border-gray-600 text-gray-900 dark:text-white rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all cursor-pointer"
                    >
                </div>
                
                <div class="flex gap-3">
                    <button 
                        type="submit" 
                        class="flex-1 px-6 py-3 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-xl hover:from-blue-700 hover:to-indigo-700 transition-all duration-200 flex items-center justify-center gap-2 font-bold shadow-lg hover:shadow-xl transform hover:-translate-y-0.5"
                    >
                        <i class="fas fa-upload"></i>
                        Importieren
                    </button>
                    <button 
                        type="button" 
                        id="cancelImportBtn"
                        class="px-6 py-3 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl hover:bg-gray-300 dark:hover:bg-gray-600 transition-all duration-200 font-bold"
                    >
                        Abbrechen
                    </button>
                </div>
            </form>
            
            <!-- Import Results -->
            <div id="importResults" class="hidden mt-6">
                <div class="border-t-2 border-gray-200 dark:border-gray-700 pt-5">
                    <h4 class="text-lg font-bold mb-4 flex items-center">
                        <i class="fas fa-chart-bar mr-2 text-purple-600"></i>
                        Import-Ergebnisse
                    </h4>
                    <div id="importResultsContent"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Invitation Management JavaScript
(function() {
    const form = document.getElementById('invitationForm');
    const generatedLinkContainer = document.getElementById('generatedLinkContainer');
    const generatedLink = document.getElementById('generatedLink');
    const generatedEmail = document.getElementById('generatedEmail');
    const generatedRole = document.getElementById('generatedRole');
    const generatedSuccessMessage = document.getElementById('generatedSuccessMessage');
    const generatedLinkSection = document.getElementById('generatedLinkSection');
    const copyLinkBtn = document.getElementById('copyLinkBtn');
    const refreshBtn = document.getElementById('refreshInvitationsBtn');
    const invitationsLoader = document.getElementById('invitationsLoader');
    const invitationsContainer = document.getElementById('invitationsContainer');
    const noInvitationsMessage = document.getElementById('noInvitationsMessage');
    const invitationsList = document.getElementById('invitationsList');
    
    // Import modal elements
    const openImportModalBtn = document.getElementById('openImportModalBtn');
    const closeImportModalBtn = document.getElementById('closeImportModalBtn');
    const cancelImportBtn = document.getElementById('cancelImportBtn');
    const importModal = document.getElementById('importModal');
    const importForm = document.getElementById('importForm');
    const importResults = document.getElementById('importResults');
    const importResultsContent = document.getElementById('importResultsContent');
    
    // Role name mapping
    const roleNames = {
        'candidate': 'Anwärter',
        'member': 'Mitglied',
        'head': 'Ressortleiter',
        'alumni': 'Alumni',
        'honorary_member': 'Ehrenmitglied',
        'alumni_board': 'Alumni-Vorstand',
        'alumni_auditor': 'Alumni-Finanzprüfer',
        'board_finance': 'Vorstand Finanzen und Recht',
        'board_internal': 'Vorstand Intern',
        'board_external': 'Vorstand Extern'
    };
    
    // Load invitations on page load
    loadInvitations();
    
    // Form submission handler
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalBtnText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Erstelle Link...';
        
        // Hide previous link
        generatedLinkContainer.classList.add('hidden');
        
        const formData = new FormData(form);
        
        try {
            const response = await fetch('/api/send_invitation.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Show generated link if available
                if (data.link) {
                    generatedLink.value = data.link;
                    generatedEmail.textContent = data.email;
                    generatedRole.textContent = roleNames[data.role] || data.role;
                    generatedLinkContainer.classList.remove('hidden');
                }
                
                // Reset form
                form.reset();
                // Restore default state of send_mail checkbox
                document.getElementById('sendMailCheckbox').checked = true;
                
                // Reset checkbox to checked (default state)
                document.getElementById('sendMailCheckbox').checked = true;
                
                // Reload invitations list
                loadInvitations();
                
                // Show appropriate success message
                showMessage(data.message || 'Einladungslink erfolgreich erstellt!', 'success');
            } else {
                showMessage(data.message || 'Fehler beim Erstellen des Einladungslinks', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showMessage('Netzwerkfehler beim Erstellen des Einladungslinks', 'error');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        }
    });
    
    // Copy link button handler
    copyLinkBtn.addEventListener('click', async function() {
        try {
            await navigator.clipboard.writeText(generatedLink.value);
            
            const originalText = copyLinkBtn.innerHTML;
            copyLinkBtn.innerHTML = '<i class="fas fa-check"></i> Kopiert!';
            copyLinkBtn.classList.add('bg-green-700');
            
            setTimeout(() => {
                copyLinkBtn.innerHTML = originalText;
                copyLinkBtn.classList.remove('bg-green-700');
            }, 2000);
        } catch (err) {
            // Fallback for older browsers
            generatedLink.select();
            document.execCommand('copy');
            
            const originalText = copyLinkBtn.innerHTML;
            copyLinkBtn.innerHTML = '<i class="fas fa-check"></i> Kopiert!';
            copyLinkBtn.classList.add('bg-green-700');
            
            setTimeout(() => {
                copyLinkBtn.innerHTML = originalText;
                copyLinkBtn.classList.remove('bg-green-700');
            }, 2000);
        }
    });
    
    // Refresh button handler
    refreshBtn.addEventListener('click', function() {
        loadInvitations();
    });
    
    // Import modal handlers
    openImportModalBtn.addEventListener('click', function() {
        importModal.classList.remove('hidden');
        importResults.classList.add('hidden');
        importForm.reset();
    });
    
    closeImportModalBtn.addEventListener('click', function() {
        importModal.classList.add('hidden');
    });
    
    cancelImportBtn.addEventListener('click', function() {
        importModal.classList.add('hidden');
    });
    
    // Close modal when clicking outside
    importModal.addEventListener('click', function(e) {
        if (e.target === importModal) {
            importModal.classList.add('hidden');
        }
    });
    
    // Import form submission handler
    importForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const submitBtn = importForm.querySelector('button[type="submit"]');
        const originalBtnText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Importiere...';
        
        // Hide previous results
        importResults.classList.add('hidden');
        
        const formData = new FormData(importForm);
        
        try {
            const response = await fetch('/api/import_invitations.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Show results
                displayImportResults(data);
                
                // Reload invitations list
                loadInvitations();
                
                // Show success message
                showMessage(data.message, 'success');
                
                // Reset form
                importForm.reset();
            } else {
                showMessage(data.message || 'Fehler beim Importieren der Einladungen', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showMessage('Netzwerkfehler beim Importieren der Einladungen', 'error');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        }
    });
    
    // Display import results
    function displayImportResults(data) {
        let html = '';
        
        // Summary
        html += '<div class="mb-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">';
        html += '<div class="flex items-center justify-between">';
        html += '<div>';
        html += '<p class="text-lg font-semibold text-blue-900">';
        html += '<i class="fas fa-info-circle mr-2"></i>Zusammenfassung';
        html += '</p>';
        html += '</div>';
        html += '</div>';
        html += '<div class="mt-3 grid grid-cols-3 gap-4">';
        html += '<div class="text-center">';
        html += '<p class="text-2xl font-bold text-gray-700">' + data.total + '</p>';
        html += '<p class="text-sm text-gray-600">Gesamt</p>';
        html += '</div>';
        html += '<div class="text-center">';
        html += '<p class="text-2xl font-bold text-green-600">' + data.success_count + '</p>';
        html += '<p class="text-sm text-gray-600">Erfolgreich</p>';
        html += '</div>';
        html += '<div class="text-center">';
        html += '<p class="text-2xl font-bold text-red-600">' + data.failed_count + '</p>';
        html += '<p class="text-sm text-gray-600">Fehlgeschlagen</p>';
        html += '</div>';
        html += '</div>';
        html += '</div>';
        
        // Errors
        if (data.errors && data.errors.length > 0) {
            html += '<div class="p-4 bg-red-50 border border-red-200 rounded-lg">';
            html += '<p class="font-semibold text-red-900 mb-2">';
            html += '<i class="fas fa-exclamation-triangle mr-2"></i>Fehler:';
            html += '</p>';
            html += '<ul class="list-disc list-inside text-sm text-red-800 space-y-1">';
            data.errors.forEach(error => {
                html += '<li>' + escapeHtml(error) + '</li>';
            });
            html += '</ul>';
            html += '</div>';
        }
        
        importResultsContent.innerHTML = html;
        importResults.classList.remove('hidden');
    }
    
    // Load invitations function
    async function loadInvitations() {
        invitationsLoader.classList.remove('hidden');
        invitationsContainer.classList.add('hidden');
        noInvitationsMessage.classList.add('hidden');
        
        try {
            const response = await fetch('/api/get_invitations.php');
            const data = await response.json();
            
            if (data.success) {
                if (data.invitations.length === 0) {
                    noInvitationsMessage.classList.remove('hidden');
                } else {
                    renderInvitations(data.invitations);
                    invitationsContainer.classList.remove('hidden');
                }
            } else {
                showMessage(data.message || 'Fehler beim Laden der Einladungen', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showMessage('Netzwerkfehler beim Laden der Einladungen', 'error');
        } finally {
            invitationsLoader.classList.add('hidden');
        }
    }
    
    // Render invitations table
    function renderInvitations(invitations) {
        invitationsList.innerHTML = '';
        
        invitations.forEach(invitation => {
            const row = document.createElement('tr');
            row.className = 'hover:bg-gradient-to-r hover:from-purple-50 hover:to-indigo-50 dark:hover:from-purple-900/20 dark:hover:to-indigo-900/20 transition-all duration-200';
            
            const createdDate = new Date(invitation.created_at);
            const expiresDate = new Date(invitation.expires_at);
            
            row.innerHTML = `
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-gradient-to-br from-purple-400 to-indigo-500 rounded-lg flex items-center justify-center mr-3 shadow-sm">
                            <i class="fas fa-envelope text-white text-sm"></i>
                        </div>
                        <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">${escapeHtml(invitation.email)}</span>
                    </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <span class="inline-flex items-center px-3 py-1.5 text-xs bg-gradient-to-r from-purple-100 to-indigo-100 dark:from-purple-900/50 dark:to-indigo-900/50 text-purple-800 dark:text-purple-200 rounded-lg font-semibold shadow-sm">
                        <i class="fas fa-user-tag mr-1.5"></i>
                        ${roleNames[invitation.role] || invitation.role}
                    </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300 font-medium">
                    <i class="fas fa-calendar-plus text-purple-500 mr-2"></i>
                    ${formatDate(createdDate)}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300 font-medium">
                    <i class="fas fa-clock text-orange-500 mr-2"></i>
                    ${formatDate(expiresDate)}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                    <i class="fas fa-user text-gray-400 mr-2"></i>
                    ${escapeHtml(invitation.created_by_email || 'Unbekannt')}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <button 
                        type="button" 
                        class="inline-flex items-center px-3 py-1.5 bg-gradient-to-r from-blue-100 to-indigo-100 dark:from-blue-900/50 dark:to-indigo-900/50 text-blue-700 dark:text-blue-300 rounded-lg hover:from-blue-200 hover:to-indigo-200 dark:hover:from-blue-900 dark:hover:to-indigo-900 transition-all font-semibold text-xs shadow-sm"
                        onclick="copyInvitationLink('${invitation.link}')"
                        title="Link kopieren"
                    >
                        <i class="fas fa-copy mr-1.5"></i>Kopieren
                    </button>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-center">
                    <button 
                        type="button" 
                        class="inline-flex items-center justify-center w-10 h-10 bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 rounded-lg hover:bg-red-200 dark:hover:bg-red-900/50 transition-all shadow-sm hover:shadow-md transform hover:scale-105"
                        onclick="deleteInvitation(${invitation.id})"
                        title="Einladung löschen"
                    >
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            `;
            
            invitationsList.appendChild(row);
        });
    }
    
    // Delete invitation function (exposed globally)
    window.deleteInvitation = async function(invitationId) {
        if (!confirm('Möchten Sie diese Einladung wirklich löschen?')) {
            return;
        }
        
        const csrfTokenElement = document.querySelector('input[name="csrf_token"]');
        if (!csrfTokenElement) {
            showMessage('CSRF token nicht gefunden', 'error');
            return;
        }
        
        const formData = new FormData();
        formData.append('invitation_id', invitationId);
        formData.append('csrf_token', csrfTokenElement.value);
        
        try {
            const response = await fetch('/api/delete_invitation.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                showMessage('Einladung erfolgreich gelöscht', 'success');
                loadInvitations();
            } else {
                showMessage(data.message || 'Fehler beim Löschen der Einladung', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showMessage('Netzwerkfehler beim Löschen der Einladung', 'error');
        }
    };
    
    // Copy invitation link function (exposed globally)
    window.copyInvitationLink = async function(link) {
        try {
            await navigator.clipboard.writeText(link);
            showMessage('Link in die Zwischenablage kopiert', 'success');
        } catch (err) {
            // Fallback for older browsers
            const tempInput = document.createElement('input');
            tempInput.value = link;
            document.body.appendChild(tempInput);
            tempInput.select();
            document.execCommand('copy');
            document.body.removeChild(tempInput);
            
            showMessage('Link in die Zwischenablage kopiert', 'success');
        }
    };
    
    // Helper functions
    function formatDate(date) {
        return date.toLocaleDateString('de-DE', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function showMessage(message, type) {
        // Remove existing messages
        const existingMessages = document.querySelectorAll('.ajax-message');
        existingMessages.forEach(msg => msg.remove());
        
        // Create new message element
        const messageDiv = document.createElement('div');
        messageDiv.className = 'ajax-message mb-6 p-4 rounded-lg ' + 
            (type === 'success' ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-red-100 border border-red-400 text-red-700');
        messageDiv.innerHTML = '<i class="fas fa-' + (type === 'success' ? 'check' : 'exclamation') + '-circle mr-2"></i>' + message;
        
        // Insert at the top of main content
        const mainContent = document.querySelector('main > div:first-child') || document.querySelector('main');
        if (mainContent) {
            mainContent.insertBefore(messageDiv, mainContent.firstChild);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                messageDiv.remove();
            }, 5000);
        }
    }
})();
</script>
