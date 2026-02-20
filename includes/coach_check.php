<?php
require_once __DIR__ . '/../config/database.php';

startSession();

if (!isLoggedIn()) {
    redirect('login.php');
}
if (!isCoach()) {
    $dest = isAdmin() ? '../admin/dashboard.php' : '../dashboard.php';
    header('Location: ' . $dest);
    exit;
}
?>
