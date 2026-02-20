<?php
/**
 * Admin Index - Redirige vers le dashboard
 * Le fichier dashboard.php vérifiera automatiquement l'authentification via admin_check.php
 */

// Démarrer la session si nécessaire
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirection directe vers le dashboard
header("Location: dashboard.php");
exit();

