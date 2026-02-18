<?php
require_once '../../includes/services/MicrosoftGraphService.php';
require_once '../../src/Auth.php';
require_once '../../config/config.php';

session_start();

// Prüfen ob eingeloggt (oder zumindest Token da ist)
if (!isset($_SESSION['access_token'])) {
    die("Bitte erst ganz normal einloggen, dann diese Seite aufrufen!");
}

$graphService = new MicrosoftGraphService($_SESSION['access_token']);
try {
    // Rufe Gruppen ab (genau wie im AuthHandler)
    $groups = $graphService->getMemberGroups();
} catch (Exception $e) {
    die("Fehler beim Abruf: " . $e->getMessage());
}

echo "<h1>Microsoft Entra Diagnose</h1>";
echo "<h3>Deine Gruppen aus Azure:</h3>";
echo "<pre>" . print_r($groups, true) . "</pre>";

echo "<h3>Deine aktuelle Config (ROLE_MAPPING):</h3>";
echo "<pre>" . print_r(ROLE_MAPPING, true) . "</pre>";

echo "<h3>Vergleich:</h3>";
echo "<ul>";
foreach ($groups as $group) {
    $name = $group['displayName'];
    $id = $group['id'];
    $match = 'NEIN';
    
    foreach (ROLE_MAPPING as $roleKey => $mapping) {
        if ($mapping === $name || $mapping === $id) {
            $match = "JA -> Rolle: <strong>$roleKey</strong>";
            break;
        }
    }
    echo "<li>Gruppe: <strong>$name</strong> (ID: $id) - Treffer in Config? $match</li>";
}
echo "</ul>";
?>
