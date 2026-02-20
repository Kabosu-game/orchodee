<?php
/**
 * Script d'export de la base de données Orchidee LLC
 * Exporte toutes les tables avec leurs données
 */

require_once 'config/database.php';

$conn = getDBConnection();

// Nom du fichier d'export - prêt pour import en ligne (à la racine du projet)
$filename = 'orchidee_export_ready_import.sql';

// Ouvrir le fichier en écriture
$file = fopen($filename, 'w');

if (!$file) {
    die("Erreur : Impossible de créer le fichier d'export.");
}

// En-tête du fichier SQL
fwrite($file, "-- ============================================\n");
fwrite($file, "-- Orchidee LLC - Database Export\n");
fwrite($file, "-- Export Date: " . date('Y-m-d H:i:s') . "\n");
fwrite($file, "-- Database: " . DB_NAME . "\n");
fwrite($file, "-- ============================================\n\n");

fwrite($file, "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n");
fwrite($file, "SET AUTOCOMMIT = 0;\n");
fwrite($file, "START TRANSACTION;\n");
fwrite($file, "SET time_zone = \"+00:00\";\n\n");

fwrite($file, "-- Disable foreign key checks during import\n");
fwrite($file, "SET FOREIGN_KEY_CHECKS = 0;\n\n");
fwrite($file, "-- IMPORTANT : Dans phpMyAdmin, sélectionnez d'abord votre base de données puis importez ce fichier.\n");
fwrite($file, "-- Sur hébergement mutualisé, ne pas exécuter CREATE DATABASE (droits refusés).\n\n");

// Récupérer toutes les tables
$tables = [];
$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_array()) {
    $tables[] = $row[0];
}

$totalTables = count($tables);
$exportedTables = 0;

echo "<!DOCTYPE html>
<html>
<head>
    <title>Database Export - Orchidee LLC</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 900px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #007bff; }
        h2 { color: #28a745; margin-top: 30px; }
        .success { color: green; padding: 5px 0; }
        .error { color: red; padding: 5px 0; }
        .info { color: #666; padding: 5px 0; }
        .btn { display: inline-block; padding: 12px 30px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px 0 0; }
        .btn:hover { background: #0056b3; }
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #218838; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>📦 Export de la Base de Données</h1>
        <h2>Tables trouvées : $totalTables</h2>";

// Exporter chaque table
foreach ($tables as $table) {
    echo "<h3>Export de la table : $table</h3>";
    
    // Structure de la table
    fwrite($file, "-- --------------------------------------------------------\n");
    fwrite($file, "-- Table structure for table `$table`\n");
    fwrite($file, "-- --------------------------------------------------------\n\n");
    
    fwrite($file, "DROP TABLE IF EXISTS `$table`;\n");
    
    $createTable = $conn->query("SHOW CREATE TABLE `$table`");
    $createTableRow = $createTable->fetch_assoc();
    fwrite($file, $createTableRow['Create Table'] . ";\n\n");
    
    // Données de la table
    fwrite($file, "-- --------------------------------------------------------\n");
    fwrite($file, "-- Dumping data for table `$table`\n");
    fwrite($file, "-- --------------------------------------------------------\n\n");
    
    $dataResult = $conn->query("SELECT * FROM `$table`");
    $rowCount = $dataResult->num_rows;
    
    if ($rowCount > 0) {
        // Récupérer les noms des colonnes
        $columns = [];
        $fields = $conn->query("SHOW COLUMNS FROM `$table`");
        while ($field = $fields->fetch_assoc()) {
            $columns[] = $field['Field'];
        }
        
        // Insérer les données
        fwrite($file, "INSERT INTO `$table` (`" . implode("`, `", $columns) . "`) VALUES\n");
        
        $rows = [];
        while ($row = $dataResult->fetch_assoc()) {
            $values = [];
            foreach ($columns as $column) {
                $value = $row[$column];
                if ($value === null) {
                    $values[] = "NULL";
                } else {
                    // Échapper les caractères spéciaux
                    $value = $conn->real_escape_string($value);
                    $values[] = "'$value'";
                }
            }
            $rows[] = "(" . implode(", ", $values) . ")";
        }
        
        // Écrire les lignes par lots de 100 pour éviter les fichiers trop longs
        $chunks = array_chunk($rows, 100);
        foreach ($chunks as $index => $chunk) {
            fwrite($file, implode(",\n", $chunk));
            if ($index < count($chunks) - 1) {
                fwrite($file, ",\n");
            } else {
                fwrite($file, ";\n\n");
            }
        }
        
        echo "<p class='success'>✓ Table '$table' exportée avec $rowCount lignes</p>";
    } else {
        fwrite($file, "-- No data in table `$table`\n\n");
        echo "<p class='info'>ℹ Table '$table' exportée (vide)</p>";
    }
    
    $exportedTables++;
}

// Réactiver les contraintes de clés étrangères
fwrite($file, "-- Re-enable foreign key checks\n");
fwrite($file, "SET FOREIGN_KEY_CHECKS = 1;\n\n");

fwrite($file, "COMMIT;\n");

fclose($file);

// Obtenir la taille du fichier
$fileSize = filesize($filename);
$fileSizeFormatted = $fileSize < 1024 ? $fileSize . ' bytes' : 
                    ($fileSize < 1048576 ? round($fileSize / 1024, 2) . ' KB' : 
                    round($fileSize / 1048576, 2) . ' MB');

echo "<hr>
        <h2 style='color: green;'>✅ Export terminé avec succès !</h2>
        <p><strong>Fichier créé :</strong> <code>$filename</code></p>
        <p><strong>Taille :</strong> $fileSizeFormatted</p>
        <p><strong>Tables exportées :</strong> $exportedTables / $totalTables</p>
        
        <h3>📥 Télécharger le fichier</h3>
        <p>
            <a href='$filename' class='btn btn-success' download>
                <i class='fa fa-download'></i> Télécharger l'export SQL
            </a>
            <a href='admin/dashboard.php' class='btn'>
                <i class='fa fa-arrow-left'></i> Retour au Dashboard
            </a>
        </p>
        
        <h3>📋 Informations</h3>
        <p>Le fichier d'export contient :</p>
        <ul>
            <li>La structure de toutes les tables</li>
            <li>Toutes les données de toutes les tables</li>
            <li>Les instructions SQL pour réimporter la base de données</li>
        </ul>
        
        <p><strong>Import en ligne :</strong> Créez une base, importez ce fichier dans phpMyAdmin. Puis créez <code>config/database_production.php</code> avec vos identifiants (copiez depuis <code>database_production.php.example</code>).</p>
    </div>
</body>
</html>";

$conn->close();
?>
