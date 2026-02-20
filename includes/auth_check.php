<?php
require_once __DIR__ . '/../config/database.php';

startSession();

// Check if user is logged in
if (!isLoggedIn()) {
    $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '/');
    $loginUrl = (strpos($scriptDir, '/admin') !== false || strpos($scriptDir, '\\admin') !== false || strpos($scriptDir, '/coach') !== false || strpos($scriptDir, '\\coach') !== false) ? '../login.php' : 'login.php';
    redirect($loginUrl);
}
?>

