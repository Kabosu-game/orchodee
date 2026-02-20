<?php
/**
 * Script de diagnostic — à exécuter en ligne pour identifier l'erreur 500.
 * Uploadez ce fichier, visitez https://orchideellc.com/diagnostic.php
 * SUPPRIMEZ CE FICHIER après utilisation (sécurité).
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

header('Content-Type: text/html; charset=utf-8');
echo "<h1>Diagnostic Orchidee</h1><pre>\n";

// 1. PHP version
echo "1. PHP version: " . PHP_VERSION . "\n\n";

// 2. Extensions
echo "2. mysqli: " . (extension_loaded('mysqli') ? 'OK' : 'MANQUANT') . "\n";
echo "   session: " . (extension_loaded('session') ? 'OK' : 'MANQUANT') . "\n\n";

// 3. Config
echo "3. Config database.php: ";
if (file_exists(__DIR__ . '/config/database.php')) {
    echo "OK\n";
} else {
    echo "MANQUANT\n";
    echo "</pre></body></html>";
    exit;
}

echo "   database_production.php: ";
if (file_exists(__DIR__ . '/config/database_production.php')) {
    echo "EXISTE (production)\n";
} else {
    echo "ABSENT (valeurs locales utilisées)\n";
}

// 4. Charger la config
echo "\n4. Chargement config...\n";
try {
    require_once __DIR__ . '/config/database.php';
    echo "   DB_HOST: " . (defined('DB_HOST') ? DB_HOST : 'non défini') . "\n";
    echo "   DB_USER: " . (defined('DB_USER') ? DB_USER : 'non défini') . "\n";
    echo "   DB_NAME: " . (defined('DB_NAME') ? DB_NAME : 'non défini') . "\n";
} catch (Throwable $e) {
    echo "   ERREUR: " . $e->getMessage() . "\n";
    echo "</pre></body></html>";
    exit;
}

// 5. Connexion DB
echo "\n5. Connexion base de données...\n";
try {
    $conn = getDBConnection();
    echo "   Connexion: OK\n";
} catch (Throwable $e) {
    echo "   ERREUR: " . $e->getMessage() . "\n";
    echo "   -> Vérifiez config/database_production.php (host, user, pass, base)\n";
    echo "</pre></body></html>";
    exit;
}

// 6. Table users
echo "\n6. Table 'users'...\n";
$r = $conn->query("SHOW TABLES LIKE 'users'");
if ($r && $r->num_rows > 0) {
    echo "   Table users: OK\n";
    $c = $conn->query("SELECT COUNT(*) as n FROM users");
    $row = $c ? $c->fetch_assoc() : null;
    echo "   Nombre d'utilisateurs: " . ($row['n'] ?? '?') . "\n";
} else {
    echo "   ERREUR: Table 'users' absente -> importez orchidee_export_ready_import.sql\n";
}

$conn->close();

echo "\n7. Sessions: ";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
echo "OK\n";

echo "\n--- Si tout est OK ci-dessus, l'erreur 500 vient d'ailleurs. ---\n";
echo "--- Consultez les logs d'erreur de votre hébergeur (cPanel > Errors). ---\n";
echo "</pre><p><strong>Supprimez ce fichier diagnostic.php après utilisation.</strong></p>";
