<?php
/**
 * Script de diagnostic pour la page d'accueil
 * Affiche les problèmes potentiels sans afficher la page complète
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Diagnostic - Index</title><style>body{font-family:Arial,sans-serif;padding:20px;background:#f5f5f5;} .ok{color:green;} .error{color:red;} .warning{color:orange;} h2{border-bottom:2px solid #333;padding-bottom:5px;} ul{list-style:none;padding-left:0;} li{padding:5px;margin:5px 0;background:white;border-left:4px solid #ccc;} .ok-li{border-left-color:green;} .error-li{border-left-color:red;} .warning-li{border-left-color:orange;}</style></head><body>";
echo "<h1>🔍 Diagnostic de la page d'accueil</h1>";

$errors = [];
$warnings = [];
$ok = [];

// 1. Vérifier les fichiers critiques
echo "<h2>1. Fichiers critiques</h2><ul>";
$files = [
    'index.php' => 'Page principale',
    'includes/services-section.php' => 'Section services',
    'includes/menu-dynamic.php' => 'Menu dynamique',
    'js/main.js' => 'Script principal',
    'config/database.php' => 'Configuration base de données',
    'css/bootstrap.min.css' => 'Bootstrap CSS',
    'css/style.css' => 'Style personnalisé'
];

foreach ($files as $file => $desc) {
    if (file_exists($file)) {
        echo "<li class='ok-li'>✅ <strong>$desc</strong> ($file) existe</li>";
        $ok[] = $file;
    } else {
        echo "<li class='error-li'>❌ <strong>$desc</strong> ($file) manquant</li>";
        $errors[] = "Fichier manquant: $file";
    }
}
echo "</ul>";

// 2. Vérifier les images
echo "<h2>2. Images et médias</h2><ul>";
$images = [
    'img/orchideelogo.png',
    'img/11.jpeg',
    'img/12.jpeg',
    'img/sss.jpeg',
    'img/about.jpeg',
    'img/cover.png',
    'img/presentation.mp4'
];

foreach ($images as $img) {
    if (file_exists($img)) {
        $size = filesize($img);
        echo "<li class='ok-li'>✅ $img existe (" . number_format($size/1024, 2) . " KB)</li>";
    } else {
        echo "<li class='warning-li'>⚠️ $img manquant</li>";
        $warnings[] = "Image manquante: $img";
    }
}
echo "</ul>";

// 3. Vérifier la connexion à la base de données
echo "<h2>3. Base de données</h2><ul>";
try {
    if (file_exists('config/database.php')) {
        require_once 'config/database.php';
        $conn = @getDBConnection();
        if ($conn && !$conn->connect_error) {
            echo "<li class='ok-li'>✅ Connexion à la base de données réussie</li>";
            $ok[] = "DB Connection";
            
            // Vérifier la table services
            $result = $conn->query("SHOW TABLES LIKE 'services'");
            if ($result && $result->num_rows > 0) {
                $count = $conn->query("SELECT COUNT(*) as cnt FROM services WHERE status = 'active'");
                $row = $count->fetch_assoc();
                echo "<li class='ok-li'>✅ Table 'services' existe (" . $row['cnt'] . " services actifs)</li>";
                $ok[] = "Services table";
            } else {
                echo "<li class='warning-li'>⚠️ Table 'services' n'existe pas (sera créée automatiquement)</li>";
                $warnings[] = "Services table";
            }
            $conn->close();
        } else {
            echo "<li class='error-li'>❌ Échec de connexion à la base de données</li>";
            $errors[] = "Database connection failed";
        }
    } else {
        echo "<li class='error-li'>❌ Fichier config/database.php manquant</li>";
        $errors[] = "Database config missing";
    }
} catch (Exception $e) {
    echo "<li class='error-li'>❌ Erreur base de données: " . htmlspecialchars($e->getMessage()) . "</li>";
    $errors[] = "Database error: " . $e->getMessage();
}
echo "</ul>";

// 4. Vérifier les bibliothèques JavaScript
echo "<h2>4. Bibliothèques JavaScript (CDN)</h2><ul>";
$jsLibs = [
    'jQuery' => 'https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js',
    'Bootstrap' => 'https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js'
];

foreach ($jsLibs as $name => $url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($code == 200) {
        echo "<li class='ok-li'>✅ $name CDN accessible</li>";
    } else {
        echo "<li class='warning-li'>⚠️ $name CDN peut être inaccessible (code: $code)</li>";
        $warnings[] = "$name CDN";
    }
}
echo "</ul>";

// 5. Résumé
echo "<h2>📊 Résumé</h2>";
echo "<p><strong class='ok'>✅ Succès:</strong> " . count($ok) . "</p>";
echo "<p><strong class='warning'>⚠️ Avertissements:</strong> " . count($warnings) . "</p>";
echo "<p><strong class='error'>❌ Erreurs:</strong> " . count($errors) . "</p>";

if (count($errors) > 0) {
    echo "<h3>🔴 Erreurs critiques:</h3><ul>";
    foreach ($errors as $error) {
        echo "<li class='error-li'>$error</li>";
    }
    echo "</ul>";
}

if (count($warnings) > 0) {
    echo "<h3>🟡 Avertissements:</h3><ul>";
    foreach ($warnings as $warning) {
        echo "<li class='warning-li'>$warning</li>";
    }
    echo "</ul>";
}

echo "<hr><p><a href='index.php'>← Retour à la page d'accueil</a></p>";
echo "</body></html>";
?>

