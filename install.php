<?php
// Script d'installation de la base de données
error_reporting(E_ALL);
ini_set('display_errors', 1);

$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'orchidee_courses';

$success = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost = $_POST['db_host'] ?? 'localhost';
    $dbUser = $_POST['db_user'] ?? 'root';
    $dbPass = $_POST['db_pass'] ?? '';
    $dbName = $_POST['db_name'] ?? 'orchidee_courses';
    
    try {
        // Connexion sans base de données pour créer la base
        $conn = new mysqli($dbHost, $dbUser, $dbPass);
        
        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }
        
        // Lire le fichier SQL
        $sqlFile = __DIR__ . '/database/orchidee_courses.sql';
        if (!file_exists($sqlFile)) {
            throw new Exception("SQL file not found: " . $sqlFile);
        }
        
        $sql = file_get_contents($sqlFile);
        
        // Remplacer les valeurs dans le SQL
        $sql = str_replace('orchidee_courses', $dbName, $sql);
        
        // Exécuter les requêtes
        $queries = array_filter(array_map('trim', explode(';', $sql)));
        
        foreach ($queries as $query) {
            if (!empty($query) && !preg_match('/^--/', $query)) {
                if (!$conn->query($query)) {
                    // Ignorer les erreurs de "table already exists"
                    if (strpos($conn->error, 'already exists') === false) {
                        $errors[] = "Error: " . $conn->error . " in query: " . substr($query, 0, 50) . "...";
                    }
                }
            }
        }
        
        // Mettre à jour le fichier de configuration
        $configFile = __DIR__ . '/config/database.php';
        $configContent = file_get_contents($configFile);
        $configContent = preg_replace("/define\('DB_HOST',\s*'[^']*'\);/", "define('DB_HOST', '$dbHost');", $configContent);
        $configContent = preg_replace("/define\('DB_USER',\s*'[^']*'\);/", "define('DB_USER', '$dbUser');", $configContent);
        $configContent = preg_replace("/define\('DB_PASS',\s*'[^']*'\);/", "define('DB_PASS', '$dbPass');", $configContent);
        $configContent = preg_replace("/define\('DB_NAME',\s*'[^']*'\);/", "define('DB_NAME', '$dbName');", $configContent);
        file_put_contents($configFile, $configContent);
        
        $conn->close();
        $success = true;
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Installation - Orchidee Courses System</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #f8f9fa;
            padding: 50px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0"><i class="fa fa-database me-2"></i>Installation de la Base de Données</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <h4><i class="fa fa-check-circle me-2"></i>Installation réussie !</h4>
                                <p>La base de données a été créée avec succès.</p>
                                <p><strong>Compte Admin par défaut :</strong></p>
                                <ul>
                                    <li>Email: <code>admin@orchideellc.com</code></li>
                                    <li>Mot de passe: <code>admin123</code></li>
                                </ul>
                                <p class="text-danger"><strong>⚠️ IMPORTANT :</strong> Changez ce mot de passe après la première connexion !</p>
                                <hr>
                                <a href="login.php" class="btn btn-primary">
                                    <i class="fa fa-sign-in-alt me-2"></i>Aller à la page de connexion
                                </a>
                                <a href="index.html" class="btn btn-outline-secondary">
                                    <i class="fa fa-home me-2"></i>Retour à l'accueil
                                </a>
                            </div>
                        <?php else: ?>
                            <?php if (!empty($errors)): ?>
                                <div class="alert alert-danger">
                                    <h5>Erreurs :</h5>
                                    <ul>
                                        <?php foreach ($errors as $error): ?>
                                            <li><?php echo htmlspecialchars($error); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            
                            <form method="POST">
                                <div class="mb-3">
                                    <label for="db_host" class="form-label">Host</label>
                                    <input type="text" class="form-control" id="db_host" name="db_host" value="<?php echo htmlspecialchars($dbHost); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="db_user" class="form-label">Utilisateur MySQL</label>
                                    <input type="text" class="form-control" id="db_user" name="db_user" value="<?php echo htmlspecialchars($dbUser); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="db_pass" class="form-label">Mot de passe MySQL</label>
                                    <input type="password" class="form-control" id="db_pass" name="db_pass" value="<?php echo htmlspecialchars($dbPass); ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="db_name" class="form-label">Nom de la base de données</label>
                                    <input type="text" class="form-control" id="db_name" name="db_name" value="<?php echo htmlspecialchars($dbName); ?>" required>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fa fa-play me-2"></i>Installer la base de données
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>











