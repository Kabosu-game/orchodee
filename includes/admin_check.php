<?php
require_once __DIR__ . '/../config/database.php';

startSession();

// Check if user is logged in
if (!isLoggedIn()) {
    // Déterminer le bon chemin vers login.php selon l'emplacement
    $redirectPath = (strpos($_SERVER['REQUEST_URI'], '/admin/') !== false) ? '../login.php' : 'login.php';
    redirect($redirectPath);
}

// Check if user is admin
if (!isAdmin()) {
    // Rediriger vers le dashboard utilisateur si ce n'est pas un admin
    $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '/');
    $dashboardUrl = (strpos($scriptDir, '/admin') !== false || strpos($scriptDir, '\\admin') !== false) ? '../dashboard.php' : 'dashboard.php';
    redirect($dashboardUrl);
}
?>
