<?php
/**
 * Configuration base de données
 * - En local : utilise les valeurs par défaut ci-dessous
 * - En ligne : créez config/database_production.php avec vos identifiants (il sera chargé automatiquement)
 */
$isProduction = file_exists(__DIR__ . '/database_production.php');
if ($isProduction) {
    @ini_set('display_errors', '0');
    @ini_set('log_errors', '1');
    require_once __DIR__ . '/database_production.php';
} else {
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'orchidee');
}

// Database connection
function getDBConnection() {
    try {
        // Connect directly to the database (for production)
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            // Log error for debugging (don't expose to users)
            error_log("Database connection error: " . $conn->connect_error);
            throw new Exception("DATABASE_CONNECTION_ERROR");
        }
        
        $conn->set_charset("utf8mb4");
        return $conn;
    } catch (Exception $e) {
        // Log the error
        error_log("Database exception: " . $e->getMessage());
        throw $e;
    }
}

// Function to start session
function startSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

// Function to check if user is logged in
function isLoggedIn() {
    startSession();
    return isset($_SESSION['user_id']);
}

// Function to check if user is admin
function isAdmin() {
    startSession();
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

// Function to check if user is coach
function isCoach() {
    startSession();
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'coach';
}

// Function to get logged in user ID
function getUserId() {
    startSession();
    return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
}

// Function to redirect
function redirect($url) {
    header("Location: " . $url);
    exit();
}

// Base path for redirects (e.g. '' or '/orchidee/' when app is in subfolder)
function buildBasePath() {
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $dir = dirname($script);
    if ($dir === '/' || $dir === '\\') {
        return '';
    }
    return rtrim($dir, '/\\') . '/';
}

// Function to sanitize data
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}
?>
